<?php

/*
 * This file is part of the tape-recorder-subscriber package.
 *
 * (c) Jérôme Gamez <jerome@kreait.com>
 * (c) kreait GmbH <info@kreait.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Kreait\Ivory\HttpAdapter\Event\Subscriber;

use Ivory\HttpAdapter\Event\Events;
use Ivory\HttpAdapter\Event\RequestCreatedEvent;
use Ivory\HttpAdapter\Event\RequestErroredEvent;
use Ivory\HttpAdapter\Event\RequestSentEvent;
use Ivory\HttpAdapter\HttpAdapterException;
use Kreait\Ivory\HttpAdapter\Event\TapeRecorder\Tape;
use Kreait\Ivory\HttpAdapter\Event\TapeRecorder\TapeRecorderException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Tape subscriber.
 *
 * @author Jérôme Gamez <jerome@gamez.name>
 */
class TapeRecorderSubscriber implements EventSubscriberInterface
{
    const RECORDING_MODE_ONCE = 1;      // Performs a real request and stores it to a fixture, unless the fixture
    // already exists.
    const RECORDING_MODE_OVERWRITE = 2; // Always performs a real request and overwrites the fixture.
    const RECORDING_MODE_NEVER = 3;     // Always performs a real request and does not write a fixture.

    public static $recordingModes = [
        self::RECORDING_MODE_ONCE => 'once',
        self::RECORDING_MODE_OVERWRITE => 'overwrite',
        self::RECORDING_MODE_NEVER => 'never',
    ];

    /**
     * @var bool
     */
    private $isRecording;

    /**
     * @var string
     */
    private $path;

    /**
     * The current tape.
     *
     * @var Tape
     */
    private $currentTape;

    /**
     * The current recording mode.
     *
     * @var int
     */
    private $recordingMode;

    /**
     * Initializes the subscriber.
     *
     * @param string $path
     */
    public function __construct($path)
    {
        // @codeCoverageIgnoreStart
        if (!class_exists('Symfony\Component\Yaml\Yaml')) {
            throw new \RuntimeException('You need the symfony/yaml library to use the Tape Recorder subscriber');
        }
        // @codeCoverageIgnoreEnd

        $this->path = $path;
        $this->isRecording = false;
        $this->recordingMode = self::RECORDING_MODE_ONCE;
    }

    public static function getSubscribedEvents()
    {
        return [
            Events::REQUEST_CREATED => ['onPreSend', 400],
            Events::REQUEST_SENT => ['onPostSend', 400],
            Events::REQUEST_ERRORED => ['onException', 400],
        ];
    }

    /**
     * Sets the recording mode.
     *
     * @param int $recordingMode The recording mode.
     */
    public function setRecordingMode($recordingMode)
    {
        if (!array_key_exists($recordingMode, self::$recordingModes)) {
            throw new \InvalidArgumentException(sprintf('Undefined recording mode %s.', $recordingMode));
        }

        $this->recordingMode = $recordingMode;
    }

    /**
     * Inserts the tape with the given name.
     *
     * @param $name string The name.
     */
    public function insertTape($name)
    {
        if (isset($this->currentTape)) {
            throw new \OutOfBoundsException('Another tape is already inserted.');
        }

        $this->currentTape = new Tape($name, $this->path);
    }

    /**
     * Ejects the currently inserted tape.
     *
     *
     * @codeCoverageIgnore
     */
    public function eject()
    {
        if (!$this->currentTape) {
            // Not throwing an exception because no harm is done.
            return;
        }

        $this->stopRecording();
        $this->currentTape->store();
        unset($this->currentTape);
    }

    /**
     * Starts recording.
     */
    public function startRecording()
    {
        if (!$this->currentTape) {
            throw new \OutOfBoundsException('No tape has been inserted.');
        }

        if ($this->recordingMode !== self::RECORDING_MODE_NEVER) {
            $this->isRecording = true;
        }
    }

    /**
     * Stops recording.
     *
     *
     * @codeCoverageIgnore
     */
    public function stopRecording()
    {
        $this->isRecording = false;
    }

    /**
     * On pre send event.
     *
     * @param RequestCreatedEvent $event The pre send event.
     *
     * @throws TapeRecorderException|HttpAdapterException
     */
    public function onPreSend(RequestCreatedEvent $event)
    {
        if (!$this->isRecording) {
            return;
        }

        $request = $event->getRequest();

        if ($this->currentTape->hasTrackForRequest($request)
            && $this->recordingMode !== self::RECORDING_MODE_OVERWRITE
        ) {
            $track = $this->currentTape->getTrackForRequest($request);
            $this->currentTape->play($track);
        }

        $this->currentTape->startRecording($request);
    }

    /**
     * On post send event.
     *
     * We reach this event when the request has not been intercepted.
     *
     * @param RequestSentEvent $event The post send event.
     */
    public function onPostSend(RequestSentEvent $event)
    {
        if (!$this->isRecording) {
            return;
        }

        $request = $event->getRequest();

        if (!$this->currentTape->hasTrackForRequest($request)) {
            return;
        }

        $track = $this->currentTape->getTrackForRequest($request);

        $this->currentTape->finishRecording(
            $track,
            $event->getResponse(),
            $event->hasException() ? $event->getException() : null
        );
    }

    /**
     * We arrive here when the request has successfully been intercepted.
     *
     * @param RequestErroredEvent $event The exception event.
     */
    public function onException(RequestErroredEvent $event)
    {
        if (!$this->isRecording) {
            return;
        }

        $exception = $event->getException();
        $request = $exception->getRequest();

        if (!$this->currentTape->hasTrackForRequest($request)) {
            return;
        }

        $track = $this->currentTape->getTrackForRequest($request);

        if (!($exception instanceof TapeRecorderException)) {
            // Normal exception, let's store it in the track for the next time
            $this->currentTape->finishRecording(
                $track,
                $event->getResponse(),
                $event->getException()
            );

            return;
        }

        // We are in replay mode
        if ($track->hasResponse()) {
            $event->setResponse($track->getResponse());
        }

        if ($track->hasException()) {
            $event->setException($track->getException());
        }
    }
}
