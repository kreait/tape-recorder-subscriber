# Tape Recorder Subscriber for the Ivory HTTP Adapter
 
The Tape Recorder Subscriber for the [Ivory HTTP Adapter](https://github.com/egeloen/ivory-http-adapter/) works 
similarly to the [php-vcr](http://php-vcr.github.io) and also uses similar wordings :). 

With it, it is possible to record the HTTP interactions, e.g. in Unit Tests, store them as Fixture files and replay 
them in future runs.

An example fixture (actually used for such a test) can be found here: 
[Example fixture](tests/Event/TapeRecorder/fixtures/testLoadExistingTape.yml).

#### Usage

```php
use Ivory\HttpAdapter\EventDispatcherHttpAdapter;
use Ivory\HttpAdapter\HttpAdapterFactory;
use Kreait\Ivory\HttpAdapter\Event\Subscriber\TapeRecorderSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcher;

$recorder = new TapeRecorderSubscriber(__DIR__.'/fixtures');

$eventDispatcher = new EventDispatcher();
$eventDispatcher->addSubscriber($this->recorder);

$http = new EventDispatcherHttpAdapter(
    HttpAdapterFactory::guess(),
    $eventDispatcher
);       

$recorder->insertTape('my_tape');
$recorder->startRecording();
$httpAdapter->get(...); // This interaction will be stored as a track.
$recorder->stopRecording;
$httpAdapter->get(...); // This interaction will not be stored.
$recorder->eject(); // Stores the tape to the file system
```

##### Recording modes

```php
$recorder->setRecordingMode(...);
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

use Ivory\HttpAdapter\EventDispatcherHttpAdapter;
use Ivory\HttpAdapter\HttpAdapterFactory;
use Kreait\Ivory\HttpAdapter\Event\Subscriber\TapeRecorderSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcher;

class MyTest extends extends \PHPUnit_Framework_TestCase
{
    /** @var \Ivory\HttpAdapter\HttpAdapterInterface **/
    protected $http;
    
    /** @var TapeRecorderSubscriber */
    protected $recorder;

    protected function setUp()
    {
        $this->recorder = new TapeRecorderSubscriber(__DIR__.'/fixtures');

        $http = HttpAdapterFactory::guess();

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber($this->recorder);

        $this->http = new EventDispatcherHttpAdapter(
            $http, $eventDispatcher
        );
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

