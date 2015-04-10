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

/**
 * Tape.
 *
 * @author Jérôme Gamez <jerome@gamez.name>
 */
interface TapeInterface
{
    /**
     * Gets the name of the tape.
     *
     * @return string
     */
    public function getName();

    /**
     * Starts recording.
     *
     * @param RequestInterface $request The request.
     */
    public function startRecording(RequestInterface $request);

    /**
     * Replays a track, if it has either a response or an exception.
     *
     * @param TrackInterface $track The track to replay.
     *
     * @throws HttpAdapterException  When an exception is replayed.
     * @throws TapeRecorderException When a response is replayed.
     */
    public function play(TrackInterface $track);

    /**
     * Writes the track to the tape.
     *
     * @param TrackInterface            $track     The track to write.
     * @param ResponseInterface|null    $response  The response to write into the track.
     * @param HttpAdapterException|null $exception The exception to write into the track.
     */
    public function finishRecording(
        TrackInterface $track,
        ResponseInterface $response = null,
        HttpAdapterException $exception = null
    );

    /**
     * (Over)Writes a track to the current tape.
     *
     * @param TrackInterface $track
     */
    public function writeTrack(TrackInterface $track);

    /**
     * Checks whether a track exists for the given request.
     *
     * @param RequestInterface $request The request.
     *
     * @return bool TRUE if a track exists, FALSE if not.
     */
    public function hasTrackForRequest(RequestInterface $request);

    /**
     * Returns a track for the given request. If a track does not already exist, creates a new one.
     *
     * @param RequestInterface $request The request.
     *
     * @return TrackInterface The (new) track.
     */
    public function getTrackForRequest(RequestInterface $request);

    /**
     * Returns the tracks.
     *
     * @return TrackInterface[] The tracks.
     */
    public function getTracks();

    /**
     * Loads already existing tracks into the tape.
     */
    public function load();

    /**
     * Stores the tape.
     */
    public function store();
}
