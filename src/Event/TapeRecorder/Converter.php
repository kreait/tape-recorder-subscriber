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
use Ivory\HttpAdapter\Message\InternalRequestInterface;
use Ivory\HttpAdapter\Message\MessageFactory;
use Ivory\HttpAdapter\Message\MessageFactoryInterface;
use Ivory\HttpAdapter\Message\RequestInterface;
use Ivory\HttpAdapter\Message\ResponseInterface;

class Converter
{
    /**
     * @var MessageFactoryInterface
     */
    private $messageFactory;

    public function __construct(MessageFactoryInterface $messageFactory = null)
    {
        $this->messageFactory = $messageFactory ?: new MessageFactory();
    }

    /**
     * @param TrackInterface $track
     *
     * @return array
     */
    public function trackToArray(TrackInterface $track)
    {
        $t = clone $track;

        $data = [
            'request' => $this->requestToArray($t->getRequest()),
        ];

        if ($t->hasResponse()) {
            $data['response'] = $this->responseToArray($t->getResponse());
        }

        if ($t->hasException()) {
            $data['exception'] = $this->exceptionToArray($t->getException());
        }

        return $data;
    }

    /**
     * @param array $data
     *
     * @return TrackInterface
     */
    public function arrayToTrack(array $data)
    {
        $track = new Track($this->arrayToRequest($data['request']));

        if (isset($data['response'])) {
            $track->setResponse($this->arrayToResponse($data['response']));
        }

        if (isset($data['exception'])) {
            $track->setException($this->arrayToException($data['exception']));
        }

        return $track;
    }

    /**
     * @param InternalRequestInterface $request
     *
     * @return array
     */
    public function internalRequestToArray(InternalRequestInterface $request)
    {
        /** @var InternalRequestInterface $r */
        $r = $request->withoutParameter('track');

        return [
            'uri' => (string) $r->getUri(),
            'method' => $r->getMethod(),
            'protocol_version' => $r->getProtocolVersion(),
            'headers' => $r->getHeaders(),
            'datas' => $r->getDatas(),
            'files' => $r->getFiles(),
            'parameters' => $r->getParameters(),
        ];
    }

    /**
     * @param array $data
     *
     * @return InternalRequestInterface
     */
    public function arrayToInternalRequest(array $data)
    {
        return $this->messageFactory->createInternalRequest(
            $data['uri'],
            $data['method'],
            $data['protocol_version'],
            $data['headers'],
            $data['datas'],
            $data['files'],
            $data['parameters']
        );
    }

    /**
     * @param RequestInterface|InternalRequestInterface $request
     *
     * @return array
     */
    public function requestToArray(RequestInterface $request)
    {
        /** @var RequestInterface $r */
        $r = $request->withoutParameter('track');

        return [
            'uri' => (string) $r->getUri(),
            'method' => $r->getMethod(),
            'protocol_version' => $r->getProtocolVersion(),
            'headers' => $r->getHeaders(),
            'body' => (string) $request->getBody(),
            'parameters' => $r->getParameters(),
        ];
    }

    /**
     * @param array $data
     *
     * @return RequestInterface
     */
    public function arrayToRequest(array $data)
    {
        return $this->messageFactory->createRequest(
            $data['uri'],
            $data['method'],
            $data['protocol_version'],
            $data['headers'],
            isset($data['body']) ? $data['body'] : null,
            $data['parameters']
        );
    }

    /**
     * Gets an array from a Response.
     *
     * @param ResponseInterface $response
     *
     * @return array
     */
    public function responseToArray(ResponseInterface $response)
    {
        return [
            'status_code' => $response->getStatusCode(),
            'protocol_version' => $response->getProtocolVersion(),
            'headers' => $response->getHeaders(),
            'body' => (string) $response->getBody(),
            'parameters' => $response->getParameters(),
        ];
    }

    /**
     * Gets a response from an array.
     *
     * @param array $data
     *
     * @return ResponseInterface
     */
    public function arrayToResponse(array $data)
    {
        return $this->messageFactory->createResponse(
            $data['status_code'],
            $data['protocol_version'],
            $data['headers'],
            $data['body'],
            $data['parameters']
        );
    }

    /**
     * Gets an array from an HttpAdapterException.
     *
     * @param HttpAdapterException $exception
     *
     * @return array
     */
    public function exceptionToArray(HttpAdapterException $exception)
    {
        $array = [
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
        ];

        if ($exception->hasRequest()) {
            $array['request'] = $this->internalRequestToArray($exception->getRequest());
        }

        if ($exception->hasResponse()) {
            $array['response'] = $this->responseToArray($exception->getResponse());
        }

        return $array;
    }

    /**
     * Gets an HttpAdapterException from an array.
     *
     * @param array $data
     *
     * @return HttpAdapterException
     */
    public function arrayToException(array $data)
    {
        $exception = new HttpAdapterException($data['message'], $data['code']);

        if (isset($data['request'])) {
            $exception->setRequest($this->arrayToInternalRequest($data['request']));
        }

        if (isset($data['response'])) {
            $exception->setResponse($this->arrayToResponse($data['response']));
        }

        return $exception;
    }
}
