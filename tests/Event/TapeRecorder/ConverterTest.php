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
use Ivory\HttpAdapter\Message\RequestInterface;
use Ivory\HttpAdapter\Message\ResponseInterface;

class ConverterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Converter
     */
    private $converter;

    /**
     * @var MessageFactory
     */
    private $messageFactory;

    protected function setUp()
    {
        $this->converter = new Converter();
        $this->messageFactory = new MessageFactory();
    }

    public function testDefaultState()
    {
        $this->assertAttributeInstanceOf(
            '\Ivory\HttpAdapter\Message\MessageFactoryInterface', 'messageFactory', $this->converter
        );
    }

    public function testInitialState()
    {
        $converter = new Converter($factory = $this->getMock('\Ivory\HttpAdapter\Message\MessageFactoryInterface'));
        $this->assertAttributeSame($factory, 'messageFactory', $converter);
    }

    public function testTrackToArrayWithRequestOnly()
    {
        $array = $this->converter->trackToArray(
            $track = $this->createTrack(
                $this->createRequest()
            )
        );

        $this->assertArrayHasKey('request', $array);
        $this->assertArrayNotHasKey('response', $array);
        $this->assertArrayNotHasKey('exception', $array);

        $check = $this->converter->arrayToTrack($array);

        $this->assertFalse($check->hasResponse());
        $this->assertFalse($check->hasException());
    }

    public function testTrackToArrayWithRequestAndResponse()
    {
        $array = $this->converter->trackToArray(
            $track = $this->createTrack(
                $this->createRequest(),
                $this->createResponse()
            )
        );

        $this->assertArrayHasKey('request', $array);
        $this->assertArrayHasKey('response', $array);
        $this->assertArrayNotHasKey('exception', $array);

        $check = $this->converter->arrayToTrack($array);

        $this->assertTrue($check->hasResponse());
        $this->assertFalse($check->hasException());
    }

    public function testTrackToArrayWithRequestAndResponseAndException()
    {
        $array = $this->converter->trackToArray(
            $track = $this->createTrack(
                $this->createRequest(),
                $this->createResponse(),
                $this->createException()
            )
        );

        $this->assertArrayHasKey('request', $array);
        $this->assertArrayHasKey('response', $array);
        $this->assertArrayHasKey('exception', $array);

        $check = $this->converter->arrayToTrack($array);

        $this->assertTrue($check->hasResponse());
        $this->assertTrue($check->hasException());
    }

    /**
     * Creates a track.
     *
     * @param RequestInterface     $request
     * @param ResponseInterface    $response
     * @param HttpAdapterException $exception
     *
     * @return Track
     */
    protected function createTrack(RequestInterface $request = null, ResponseInterface $response = null, HttpAdapterException $exception = null)
    {
        $request = $request ?: $this->createRequest();

        $track = new Track($request);

        if ($response) {
            $track->setResponse($response);
        }

        if ($exception) {
            $track->setException($exception);
        }

        return $track;
    }

    /**
     * Creates a request.
     *
     * @param string|null $uri
     *
     * @return RequestInterface
     */
    protected function createRequest($uri = null)
    {
        /** @var RequestInterface $request */
        $request = $this->messageFactory->createRequest($uri ?: 'http://httpstat.us/200');

        return $request;
    }

    /**
     * Creates an internal request.
     *
     * @param string|null $uri
     *
     * @return InternalRequestInterface
     */
    protected function createInternalRequest($uri = null)
    {
        $request = $this->messageFactory->createInternalRequest($uri ?: 'http://httpstat.us/200');

        return $request;
    }

    /**
     * Creates a response.
     *
     * @return ResponseInterface
     */
    protected function createResponse()
    {
        return $this->messageFactory->createResponse();
    }

    /**
     * @param InternalRequestInterface $request
     * @param ResponseInterface        $response
     *
     * @return HttpAdapterException
     */
    protected function createException(InternalRequestInterface $request = null, ResponseInterface $response = null)
    {
        $request = $request ?: $this->createInternalRequest();
        $response = $response ?: $this->createResponse();

        $e = new HttpAdapterException();
        $e->setRequest($request);
        $e->setResponse($response);

        return $e;
    }
}
