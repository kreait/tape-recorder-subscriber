# Tape Recorder Subscriber for the Ivory HTTP Adapter
 
The Tape Recorder Subscriber for the [Ivory HTTP Adapter](https://github.com/egeloen/ivory-http-adapter/) works 
similarly to the [php-vcr](http://php-vcr.github.io) and also uses similar wordings :). 

With it, it is possible to record the HTTP interactions, e.g. in Unit Tests, store them as Fixture files and replay 
them in future runs.

An example fixture (actually used for such a test) can be found here: 
[Example fixture](tests/Event/TapeRecorder/fixtures/testLoadExistingTape.yml).

[A new section has been added](https://github.com/jeromegamez/ivory-http-adapter/blob/feature/tape-recorder-subscriber/doc/events.md#tape-recorder) to the documentation explaining how to use the subscriber.

#### Usage

```php
use Ivory\HttpAdapter\Event\Subscriber\FixtureSubscriber;

$recorder = new TapeRecorderSubscriber(__DIR__.'/fixtures');
$httpAdapter->getConfiguration()->getEventDispatcher()
    ->addSubscriber($recorder);

$recorder->insertTape('my_tape');
$recorder->startRecording();
$httpAdapter->get(...); // This interaction will be stored as a track.
$recorder->stopRecording;
$httpAdapter->get(...); // This interaction will not be stored.
$recorder->eject(); // Stores the tape to the file system
```

##### Recording modes

```php
use Ivory\HttpAdapter\Event\Subscriber\FixtureSubscriber;

$recorder = new TapeRecorderSubscriber(__DIR__.'/fixtures');
$httpAdapter->getConfiguration()->getEventDispatcher()
    ->addSubscriber($recorder);

$recorder->setRecordingMode(TapeRecorderSubscriber::RECORDING_MODE_OVERWRITE);
$recorder->setRecordingMode(TapeRecorderSubscriber::RECORDING_MODE_NEVER);
// Default
$recorder->setRecordingMode(TapeRecorderSubscriber::RECORDING_MODE_ONCE);
```

The following recording modes can be set when using the Tape Recorder:

| Mode                     | Description |
| ------------------------ | ----------- |
| RECORDING_MODE_ONCE      | (default) Performs a real request and stores it to a fixture, unless a fixture already exists. |
| RECORDING_MODE_OVERWRITE | Always performs a real request and overwrites the fixture. | 
| RECORDING_MODE_NEVER     | Always performs a real request and does not write a fixture. |

##### Usage example in Unit Tests

```php

namespace My\Application\Tests;

use Ivory\HttpAdapter\Event\Subscriber\TapeRecorderSubscriber;

class MyTest extends extends \PHPUnit_Framework_TestCase
{
    protected $http;
    protected $recorder;

    protected function setUp()
    {
        $this->http = HttpAdapterFactory::guess();
        $this->recorder = new TapeRecorderSubscriber(__DIR__ . '/fixtures');
        $this->http->getConfiguration()->getEventDispatcher()
            ->addSubscriber($this->recorder);
    }

    protected function tearDown()
    {
        $this->recorder->eject();
    }

    protected function testRequest()
    {
        // This will result in the file 'fixtures/testRequest.yml'
        $this->recorder->insertTape(__FUNCTION__);
        $this->recorder->startRecording();
        $this->http->get(...);
    }
}
```

