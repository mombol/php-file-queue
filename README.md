# PHP file queue

A php file queue support:

* multi queue task shared one queue data by `queueNamespace`.
* consume queue data by set `consumeSpan`(in order to avoid the queue data file too large).
* track the queue cursor position to recover last cursor position(have to call `track` method manual).

## Usage:

### Instance

```
**NOTE**
* All construct parameters are optional for your custom settings.
* set config `role` to `customer` or orthers when process as a customer to consume queue data which shared by multi queue.
```

File queue instance
```
$FileQueue = new FileQueue(array(
    'silent' => false, // if it is set and is equal to true, just return only one object handler without mount any files
    'role' => 'generator', // queue role, default generator, it must be setted not generator when process as a customer
    'queueNamespace' => 'nsp', // queue namespace to support one queue data shared by multi queue
    'queueDir' => '/var/run/php-file-queue',
    'queueFileName' => 'default',
    'queueFileSuffix' => 'mq',
    'cursorFileSuffix' => 'cursor',
    'initialReadLineNumber' => 0, //  the number of initial read line number, default value set 0 mean that will read from the queue header, the orthers you can set to 'end' which make it read from the queue tail
));
```

File queue data consumption instance

```
$QueueDataConsume = new QueueDataConsume(array(
    'consumeSpan' => 5, // queue consumption span
    'doConsumeBackup' => true // whether or not to backup the queue consumption data
    'queueDir' => '/var/run/php-file-queue',
    'queueFileName' => 'default'
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

### Tests for the end of file queue

```
while (!$FileQueue->eof()) {
    // do anything ...
}
```

### Consume queue data

```
$QueueDataConsume->consume();
```
