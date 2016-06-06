# PHP file queue

A php file queue support:

* multi queue task shared one queue data by `queueNamespace`
* consume queue data by set `consumeSpan`(in order to avoid the queue data file too large)
* track the queue cursor position to recover last cursor position(have to call `track` method manual)

## Usage:

### Instance

```
** NOTE **
All construct parameters are optional for your custom setting.
```

```
$FileQueue = new FileQueue(array(
    'queueNamespace' => 'nsp',
    'queueDir' => '/var/run/php-file-queue',
    'queueFileName' => 'default',
    'queueFileSuffix' => 'mq',
    'cursorFileSuffix' => 'cursor',
    'trackFileSuffix' => 'track',
    'doConsume' => true,
    'consumeSpan' => 1000000
));
```

### Push data to queue

```
$data = 'test'; // anything you want to push
$FileQueue->push($data);
```

### Pop data from queue

```
$num = 1;
$data = $FileQueue->pop($num); // $num: pop number
```

### Get current position

```
$pos = $FileQueue->position();
```

### Rewind position to queue header

```
$FileQueue->rewind();
```

### Point queue cursor to the end

```
$FileQueue->end();
```

### Track queue cursor and recover last cursor

```
$FileQueue->track();
$FileQueue->rewind();
// do anything ...
$FileQueue->recover();
```

### Get queue length

```
$length = $FileQueue->length();
```
