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

namespace Kreait\Ivory\HttpAdapter\Event\TapeRecorder;

use Ivory\HttpAdapter\HttpAdapterException;
use Ivory\HttpAdapter\Message\RequestInterface;
use Ivory\HttpAdapter\Message\ResponseInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Tape.
 *
 * @author Jérôme Gamez <jerome@gamez.name>
 */
class Tape implements TapeInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var TrackMatcher
     */
    private $trackMatcher;

    /**
     * @var TrackInterface[]
     */
    private $tracks;

    /**
     * @var string
     */
    private $storagePath;

    /**
     * @var Converter
     */
    private $converter;

    /**
     * Initializes a tape with the given name and loads already existing tracks from the file storage.
     *
     * @param string $name        The name of the tap.
     * @param string $storagePath The path where the tape is stored.
     */
    public function __construct($name, $storagePath)
    {
        $this->name = $name;
        $this->storagePath = $storagePath;

        $this->trackMatcher = new TrackMatcher();
        $this->converter = new Converter();
        $this->load();
    }

    public function getName()
    {
        return $this->name;
    }

    public function getStoragePath()
    {
        if (!file_exists($this->storagePath)) {
            mkdir($this->storagePath, 0777, true);
        }

        return $this->storagePath;
    }

    public function startRecording(RequestInterface $request)
    {
        $track = new Track($request);
        $this->writeTrack($track);
    }

    public function play(TrackInterface $track)
    {
        if ($track->hasException()) {
            $this->replayException($track);
        }

        if ($track->hasResponse()) {
            $this->replayResponse($track);
        }
    }

    public function finishRecording(
        TrackInterface $track,
        ResponseInterface $response = null,
        HttpAdapterException $exception = null
    ) {
        $track->setResponse($response);
        $track->setException($exception);

        $this->writeTrack($track);
    }

    /**
     * Replays the exception of the given track.
     *
     * @param TrackInterface $track The track.
     *
     * @throws HttpAdapterException The exception to be replayed
     */
    private function replayException(TrackInterface $track)
    {
        throw $track->getException();
    }

    /**
     * Replays the response of the given track.
     *
     * This is done by throwing a TapeRecorderException, which will trigger the exception event of the
     * TapeRecorderSubscriber.
     *
     * @param TrackInterface $track The track.
     *
     * @throws TapeRecorderException The Tape Recorder exception.
     */
    private function replayResponse(TrackInterface $track)
    {
        $e = TapeRecorderException::interceptingRequest();
        $e->setResponse($track->getResponse());
        throw $e;
    }

    public function writeTrack(TrackInterface $track)
    {
        $newTracks = [];

        foreach ($this->tracks as $key => $existing) {
            if (!$this->trackMatcher->matchByRequest($existing, $track->getRequest())) {
                $newTracks[] = $existing;
            }
        }

        $newTracks[] = $track;

        $this->tracks = $newTracks;
    }

    public function getTracks()
    {
        return $this->tracks;
    }

    public function load()
    {
        $this->tracks = [];

        $filePath = $this->getFilePath();

        if (is_file($filePath) && is_readable($filePath)) {
            $data = Yaml::parse(file_get_contents($filePath));
            foreach ($data as $item) {
                $this->writeTrack($this->converter->arrayToTrack($item));
            }
        }
    }

    public function store()
    {
        $filePath = $this->getFilePath();
        $data = [];
        foreach ($this->tracks as $track) {
            $data[] = $this->converter->trackToArray($track);
        }

        file_put_contents($filePath, Yaml::dump($data, 4));
    }

    public function hasTrackForRequest(RequestInterface $request)
    {
        foreach ($this->tracks as $track) {
            if ($this->trackMatcher->matchByRequest($track, $request)) {
                return true;
            }
        }

        return false;
    }

    public function getTrackForRequest(RequestInterface $request)
    {
        foreach ($this->tracks as $track) {
            if ($this->trackMatcher->matchByRequest($track, $request)) {
                return $track;
            }
        }

        return new Track($request);
    }

    /**
     * Returns a file path for the current tape.
     *
     * @return string
     */
    private function getFilePath()
    {
        return $this->getStoragePath().DIRECTORY_SEPARATOR.$this->getName().'.yml';
    }
}
