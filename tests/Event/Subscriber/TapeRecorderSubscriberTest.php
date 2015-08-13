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
use Ivory\HttpAdapter\HttpAdapterInterface;
use Ivory\HttpAdapter\Message\InternalRequestInterface;
use Ivory\HttpAdapter\Message\ResponseInterface;
use Kreait\Ivory\HttpAdapter\Event\TapeRecorder\Tape;
use Kreait\Ivory\HttpAdapter\Event\TapeRecorder\TapeRecorderException;
use Kreait\Ivory\HttpAdapter\Event\TapeRecorder\TrackInterface;

/**
 * Tape Recorder subscriber test.
 *
 * @group TapeRecorderSubscriber
 *
 * @author Jérôme Gamez <jerome@gamez.name>
 */
class TapeRecorderSubscriberTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var TapeRecorderSubscriber
     */
    private $subscriber;

    /**
     * @var string
     */
    private $path;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->subscriber = new TapeRecorderSubscriber(
            $this->path = sys_get_temp_dir().'/TapeRecorderSubscriber'
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        unset($this->subscriber);
    }

    public function testSubscribedEvents()
    {
        $events = TapeRecorderSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(Events::REQUEST_CREATED, $events);
        $this->assertSame(['onPreSend', 400], $events[Events::REQUEST_CREATED]);

        $this->assertArrayHasKey(Events::REQUEST_SENT, $events);
        $this->assertSame(['onPostSend', 400], $events[Events::REQUEST_SENT]);

        $this->assertArrayHasKey(Events::REQUEST_ERRORED, $events);
        $this->assertSame(['onException', 400], $events[Events::REQUEST_ERRORED]);
    }

    public function testInitialState()
    {
        $this->assertAttributeSame($this->path, 'path', $this->subscriber);
        $this->assertAttributeEquals(false, 'isRecording', $this->subscriber);
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testInsertingTwoTapesShouldThrowAnException()
    {
        $this->subscriber->insertTape('foo');
        $this->subscriber->insertTape('foo');
    }

    public function testEject()
    {
        $this->subscriber->eject();
        $this->assertAttributeEquals(false, 'isRecording', $this->subscriber);
        $this->assertAttributeEquals(null, 'currentTape', $this->subscriber);
    }

    public function testStartRecording()
    {
        $this->injectTapeMock();
        $this->subscriber->startRecording();
        $this->assertAttributeEquals(true, 'isRecording', $this->subscriber);
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testStartRecordingWithoutATapeShouldThrowAnException()
    {
        $this->subscriber->startRecording();
    }

    public function testStopRecording()
    {
        $this->subscriber->stopRecording();
        $this->assertAttributeEquals(false, 'isRecording', $this->subscriber);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Undefined recording mode -1.
     */
    public function testSettingAnInvalidRecordingModeShouldThrowException()
    {
        $this->subscriber->setRecordingMode(-1);
    }

    public function testSettingRecordingModeToNeverShouldNeverStartRecording()
    {
        $preSendEvent = $this->createRequestCreatedEventMock();
        $preSendEvent
            ->expects($this->never())
            ->method('getRequest');

        $postSendEvent = $this->createRequestSentEventMock();
        $postSendEvent
            ->expects($this->never())
            ->method('getRequest');

        $exceptionEvent = $this->createRequestErroredEventMock();
        $exceptionEvent
            ->expects($this->never())
            ->method('getException');

        $this->injectTapeMock();
        $this->subscriber->setRecordingMode(TapeRecorderSubscriber::RECORDING_MODE_NEVER);

        $this->subscriber->startRecording();
        $this->assertAttributeEquals(false, 'isRecording', $this->subscriber);

        $this->subscriber->onPreSend($preSendEvent);
        $this->subscriber->onPostSend($postSendEvent);
        $this->subscriber->onException($exceptionEvent);
    }

    public function testSettingRecordingModeToOverwriteShouldNeverReplay()
    {
        $tape = $this->createTapeMock();

        $tape
            ->expects($this->once())
            ->method('hasTrackForRequest')
            ->with($request = $this->createRequestMock())
            ->willReturn(true);

        $tape
            ->expects($this->never())
            ->method('play');

        $this->injectTapeMock($tape);
        $this->subscriber->setRecordingMode(TapeRecorderSubscriber::RECORDING_MODE_OVERWRITE);
        $this->subscriber->startRecording();
        $this->subscriber->onPreSend($this->createRequestCreatedEvent(null, $request));
    }

    public function testPreSendEventWithEmptyTapeShouldStartRecording()
    {
        $tape = $this->createTapeMock();

        $tape
            ->expects($this->once())
            ->method('hasTrackForRequest')
            ->with($request = $this->createRequestMock())
            ->willReturn(false);

        $tape
            ->expects($this->never())
            ->method('getTrackForRequest');

        $tape
            ->expects($this->once())
            ->method('startRecording')
            ->with($request);

        $this->injectTapeMock($tape);

        $this->subscriber->startRecording();
        $this->subscriber->onPreSend($this->createRequestCreatedEvent(null, $request));
    }

    public function testPreSendEventWithExistingTrackShouldReplay()
    {
        $tape = $this->createTapeMock();

        $tape
            ->expects($this->once())
            ->method('hasTrackForRequest')
            ->with($request = $this->createRequestMock())
            ->willReturn(true);

        $tape
            ->expects($this->once())
            ->method('getTrackForRequest')
            ->with($request)
            ->willReturn($track = $this->createTrackMock($request));

        $tape
            ->expects($this->once())
            ->method('play')
            ->with($track);

        $this->injectTapeMock($tape);

        $this->subscriber->startRecording();
        $this->subscriber->onPreSend($this->createRequestCreatedEvent(null, $request));
    }

    public function testPreSendEventWithoutStartedRecordingShouldDoNothing()
    {
        $tape = $this->createTapeMock();
        $tape
            ->expects($this->never())
            ->method('startRecording');

        $this->subscriber->onPreSend($this->createRequestCreatedEvent(null, $this->createRequestMock()));
    }

    public function testPostSendEventWithoutStartedRecordingShouldDoNothing()
    {
        $tape = $this->createTapeMock();
        $tape
            ->expects($this->never())
            ->method('startRecording');

        $this->subscriber->onPostSend($this->createReuestSentEvent(null, $this->createRequestMock()));
    }

    public function testPostSend()
    {
        $request = $this->createRequestMock();

        $this->injectTapeMock($tape = $this->createTapeMock());

        $tape
            ->expects($this->once())
            ->method('finishRecording');

        $tape
            ->expects($this->once())
            ->method('hasTrackForRequest')
            ->with($request)
            ->willReturn(true);

        $tape->expects($this->once())
            ->method('getTrackForRequest')
            ->with($request)
            ->willReturn($this->createTrackMock($request));

        $this->subscriber->startRecording();
        $this->subscriber->onPostSend($this->createReuestSentEvent(null, $request));
    }

    public function testPostSendEventWithARequestThatHasNoAttachedTrackShouldDoNothing()
    {
        $this->markTestSkipped();
        $this->injectTapeMock($tape = $this->createTapeMock());
        $tape
            ->expects($this->never())
            ->method('finishRecording');

        $request = $this->createRequestMock();
        $request
            ->expects($this->once())
            ->method('hasParameter')
            ->with('track')
            ->willReturn(false);

        $this->subscriber->startRecording();
        $this->subscriber->onPostSend($this->createReuestSentEvent(null, $request));
    }

    public function testExceptionEventWithoutStartedRecordingShouldDoNothing()
    {
        $event = $this->createRequestErroredEvent(null, $exception = $this->createExceptionMock());
        $exception
            ->expects($this->never())
            ->method('hasRequest');

        $this->subscriber->onException($event);
    }

    public function testExceptionEventWithARequestThatHasNoAttachedTrackShouldDoNothing()
    {
        $this->markTestSkipped();
        $exception = $this->createExceptionMock($request = $this->createRequestMock());
        $request
            ->expects($this->once())
            ->method('hasParameter')
            ->with('track')
            ->willReturn(false);

        $request
            ->expects($this->never())
            ->method('getParameter');

        $this->injectTapeMock();
        $this->subscriber->startRecording();
        $this->subscriber->onException($this->createRequestErroredEvent(null, $exception));
    }

    public function testExceptionEventWithNormalHttpAdapterExceptionShouldFinishRecording()
    {
        $exception = $this->createExceptionMock(
            $request = $this->createRequestMock()
        );

        $this->injectTapeMock($tape = $this->createTapeMock());

        $tape
            ->expects($this->once())
            ->method('hasTrackForRequest')
            ->with($request)
            ->willReturn(true);

        $tape->expects($this->once())
            ->method('getTrackForRequest')
            ->with($request)
            ->willReturn($track = $this->createTrackMock($request));

        $tape
            ->expects($this->once())
            ->method('finishRecording')
            ->with($track);

        $this->subscriber->startRecording();
        $this->subscriber->onException($this->createRequestErroredEvent(null, $exception));
    }

    public function testExceptionEventWithFullTrackWillReplayTheResponseAndException()
    {
        $response = $this->createResponseMock();
        $exception = $this->createExceptionMock();

        $exception = $this->createTapeRecorderExceptionMock(
            $request = $this->createRequestMock(), $response, $exception
        );

        $this->injectTapeMock($tape = $this->createTapeMock());

        $tape
            ->expects($this->never())
            ->method('finishRecording');

        $event = $this->createRequestErroredEvent(null, $exception);

        $this->subscriber->startRecording();
        $this->subscriber->onException($event);
    }

    private function injectTapeMock($tape = null)
    {
        $tape = $tape ?: $this->createTapeMock();

        $r = new \ReflectionClass($this->subscriber);
        $p = $r->getProperty('currentTape');
        $p->setAccessible(true);
        $p->setValue($this->subscriber, $tape);
    }

    /**
     * @return Tape|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function createTapeMock()
    {
        return $this
            ->getMockBuilder('Kreait\Ivory\HttpAdapter\Event\TapeRecorder\TapeInterface')
            ->getMock();
    }

    /**
     * @param InternalRequestInterface $request
     * @param ResponseInterface        $response
     * @param HttpAdapterException     $exception
     *
     * @return TrackInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function createTrackMock(
        InternalRequestInterface $request = null,
        ResponseInterface $response = null,
        HttpAdapterException $exception = null
    ) {
        $request = $request ?: $this->createRequestMock();

        $track = $this
            ->getMockBuilder('Kreait\Ivory\HttpAdapter\Event\TapeRecorder\TrackInterface')
            ->getMock();

        $track->expects($this->any())
            ->method('hasRequest')
            ->willReturn(true);

        $track->expects($this->any())
            ->method('getRequest')
            ->willReturn($request);

        $track->expects($this->any())
            ->method('getResponse')
            ->willReturn($response);

        $track->expects($this->any())
            ->method('hasResponse')
            ->willReturn($response ? true : false);

        $track->expects($this->any())
            ->method('getException')
            ->willReturn($exception);

        $track->expects($this->any())
            ->method('hasException')
            ->willReturn($exception ? true : false);

        return $track;
    }

    /**
     * @param InternalRequestInterface $internalRequest
     * @param ResponseInterface        $response
     * @param HttpAdapterException     $httpAdapterException
     *
     * @return TapeRecorderException|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function createTapeRecorderExceptionMock(
        InternalRequestInterface $internalRequest = null,
        ResponseInterface $response = null,
        HttpAdapterException $httpAdapterException = null
    ) {
        $exception = $this->getMock('Kreait\Ivory\HttpAdapter\Event\TapeRecorder\TapeRecorderException');

        if ($internalRequest === null) {
            $internalRequest = $this->createRequestMock();
        }

        $exception
            ->expects($this->any())
            ->method('hasRequest')
            ->will($this->returnValue(true));

        $exception
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($internalRequest));

        if ($response) {
            $exception
                ->expects($this->any())
                ->method('hasResponse')
                ->will($this->returnValue(true));

            $exception
                ->expects($this->any())
                ->method('getResponse')
                ->will($this->returnValue($response));
        } else {
            $exception
                ->expects($this->any())
                ->method('hasResponse')
                ->will($this->returnValue(false));

            $exception
                ->expects($this->never())
                ->method('getResponse');
        }

        if ($httpAdapterException) {
            $exception
                ->expects($this->any())
                ->method('hasException')
                ->will($this->returnValue(true));

            $exception
                ->expects($this->any())
                ->method('getException')
                ->will($this->returnValue($httpAdapterException));
        } else {
            $exception
                ->expects($this->any())
                ->method('hasException')
                ->will($this->returnValue(false));

            $exception
                ->expects($this->never())
                ->method('getException');
        }

        return $exception;
    }

    /**
     * @return RequestCreatedEvent|\PHPUnit_Framework_MockObject_MockObject
     */
    private function createRequestCreatedEventMock()
    {
        $event = $this->getMockBuilder('Ivory\HttpAdapter\Event\RequestCreatedEvent')
            ->disableOriginalConstructor()
            ->getMock();

        return $event;
    }

    /**
     * @return RequestSentEvent|\PHPUnit_Framework_MockObject_MockObject
     */
    private function createRequestSentEventMock()
    {
        $event = $this->getMockBuilder('Ivory\HttpAdapter\Event\RequestSentEvent')
            ->disableOriginalConstructor()
            ->getMock();

        return $event;
    }

    /**
     * @return RequestErroredEvent|\PHPUnit_Framework_MockObject_MockObject
     */
    private function createRequestErroredEventMock()
    {
        $event = $this->getMockBuilder('Ivory\HttpAdapter\Event\RequestErroredEvent')
            ->disableOriginalConstructor()
            ->getMock();

        return $event;
    }

    /**
     * Creates a request mock.
     *
     * @return InternalRequestInterface|\PHPUnit_Framework_MockObject_MockObject The request mock.
     */
    protected function createRequestMock()
    {
        return $this->getMock('Ivory\HttpAdapter\Message\InternalRequestInterface');
    }

    /**
     * Creates a response mock.
     *
     * @return ResponseInterface|\PHPUnit_Framework_MockObject_MockObject The response mock.
     */
    protected function createResponseMock()
    {
        return $this->getMock('Ivory\HttpAdapter\Message\ResponseInterface');
    }

    /**
     * Creates a pre send event.
     *
     * @param HttpAdapterInterface|null     $httpAdapter The http adapter.
     * @param InternalRequestInterface|null $request     The request.
     *
     * @return RequestCreatedEvent The pre send event.
     */
    protected function createRequestCreatedEvent(
        HttpAdapterInterface $httpAdapter = null,
        InternalRequestInterface $request = null
    ) {
        return new RequestCreatedEvent(
            $httpAdapter ?: $this->createHttpAdapterMock(),
            $request ?: $this->createRequestMock()
        );
    }

    /**
     * Creates a post send event.
     *
     * @param HttpAdapterInterface|null     $httpAdapter The http adapter.
     * @param InternalRequestInterface|null $request     The request.
     * @param ResponseInterface|null        $response    The response.
     *
     * @return RequestSentEvent The post send event.
     */
    protected function createReuestSentEvent(
        HttpAdapterInterface $httpAdapter = null,
        InternalRequestInterface $request = null,
        ResponseInterface $response = null
    ) {
        return new RequestSentEvent(
            $httpAdapter ?: $this->createHttpAdapterMock(),
            $request ?: $this->createRequestMock(),
            $response ?: $this->createResponseMock()
        );
    }

    /**
     * Creates an exception event.
     *
     * @param HttpAdapterInterface|null                    $httpAdapter The http adapter.
     * @param \Ivory\HttpAdapter\HttpAdapterException|null $exception   The exception.
     *
     * @return RequestErroredEvent The exception event.
     */
    protected function createRequestErroredEvent(
        HttpAdapterInterface $httpAdapter = null,
        HttpAdapterException $exception = null
    ) {
        return new RequestErroredEvent(
            $httpAdapter ?: $this->createHttpAdapterMock(),
            $exception ?: $this->createExceptionMock()
        );
    }

    /**
     * Creates an http adapter mock.
     *
     * @return HttpAdapterInterface|\PHPUnit_Framework_MockObject_MockObject The http adapter mock.
     */
    protected function createHttpAdapterMock()
    {
        $httpAdapter = $this->getMock('Ivory\HttpAdapter\HttpAdapterInterface');
        $httpAdapter
            ->expects($this->any())
            ->method('getName')
            ->will($this->returnValue('http_adapter'));

        $httpAdapter
            ->expects($this->any())
            ->method('getConfiguration')
            ->will($this->returnValue($this->createConfigurationMock()));

        return $httpAdapter;
    }

    /**
     * Creates a configuration mock.
     *
     * @return \Ivory\HttpAdapter\ConfigurationInterface|\PHPUnit_Framework_MockObject_MockObject The configuration mock.
     */
    protected function createConfigurationMock()
    {
        return $this->getMock('Ivory\HttpAdapter\ConfigurationInterface');
    }

    /**
     * Creates an exception mock.
     *
     * @param InternalRequestInterface|null $internalRequest The internal request.
     * @param ResponseInterface|null        $response        The response.
     *
     * @return \Ivory\HttpAdapter\HttpAdapterException|\PHPUnit_Framework_MockObject_MockObject The exception mock.
     */
    protected function createExceptionMock(
        InternalRequestInterface $internalRequest = null,
        ResponseInterface $response = null
    ) {
        $exception = $this->getMock('Ivory\HttpAdapter\HttpAdapterException');

        if ($internalRequest === null) {
            $internalRequest = $this->createRequestMock();
        }

        $exception
            ->expects($this->any())
            ->method('hasRequest')
            ->will($this->returnValue(true));

        $exception
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($internalRequest));

        if ($response !== null) {
            $exception
                ->expects($this->any())
                ->method('hasResponse')
                ->will($this->returnValue(true));

            $exception
                ->expects($this->any())
                ->method('getResponse')
                ->will($this->returnValue($response));
        }

        return $exception;
    }
}
