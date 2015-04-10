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
 * Track.
 *
 * @author Jérôme Gamez <jerome@gamez.name>
 */
class Track implements TrackInterface
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var ResponseInterface
     */
    private $response;

    /**
     * @var HttpAdapterException
     */
    private $exception;

    public function __construct(RequestInterface $request)
    {
        $this->request = $request;
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function hasResponse()
    {
        return $this->response !== null;
    }

    public function getResponse()
    {
        if (!$this->hasResponse()) {
            return;
        }
        if ($body = $this->response->getBody()) {
            $body->seek(0, SEEK_SET);
        }

        return $this->response;
    }

    public function setResponse(ResponseInterface $response = null)
    {
        $this->response = $response;
    }

    public function hasException()
    {
        return $this->exception !== null;
    }

    public function getException()
    {
        if (!$this->hasException()) {
            return;
        }

        if ($this->exception->hasResponse() && $this->exception->getResponse()->getBody()->getSize() !== null) {
            $this->exception->getResponse()->getBody()->seek(0, SEEK_SET);
        }

        return $this->exception;
    }

    public function setException(HttpAdapterException $exception = null)
    {
        $this->exception = $exception;
    }
}
