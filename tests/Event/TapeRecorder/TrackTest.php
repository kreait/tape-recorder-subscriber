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
use Ivory\HttpAdapter\Message\ResponseInterface;

/**
 * Track test.
 *
 * @group TapeRecorderSubscriber
 *
 * @author Jérôme Gamez <jerome@gamez.name>
 */
class TrackTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Track
     */
    private $track;

    /**
     * @var InternalRequestInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $request;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->track = new Track(
            $this->request = $this->createRequestMock()
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        unset($this->track);
        unset($this->request);
        unset($this->response);
    }

    public function testDefaultState()
    {
        $this->assertSame($this->request, $this->track->getRequest());

        $this->assertFalse($this->track->hasResponse());
        $this->assertNull($this->track->getResponse());

        $this->assertFalse($this->track->hasException());
        $this->assertNull($this->track->getException());
    }

    public function testSetResponse()
    {
        $this->track->setResponse($response = $this->createResponseMock());

        $this->assertTrue($this->track->hasResponse());
        $this->assertSame($response, $this->track->getResponse());
    }

    public function testSetException()
    {
        $this->track->setException($exception = $this->createExceptionMock());

        $this->assertTrue($this->track->hasException());
        $this->assertSame($exception, $this->track->getException());
    }

    /**
     * Creates a request mock.
     *
     * @return InternalRequestInterface|\PHPUnit_Framework_MockObject_MockObject The request mock.
     */
    private function createRequestMock()
    {
        return $this->getMock('Ivory\HttpAdapter\Message\InternalRequestInterface');
    }

    /**
     * Creates a response mock.
     *
     * @return ResponseInterface|\PHPUnit_Framework_MockObject_MockObject The response mock.
     */
    private function createResponseMock()
    {
        return $this->getMock('Ivory\HttpAdapter\Message\ResponseInterface');
    }

    /**
     * Creates an exception mock.
     *
     * @return HttpAdapterException|\PHPUnit_Framework_MockObject_MockObject The response mock.
     */
    private function createExceptionMock()
    {
        return $this->getMock('Ivory\HttpAdapter\HttpAdapterException');
    }
}
