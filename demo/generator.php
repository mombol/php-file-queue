<?php

require '../src/ObjectDriver.php';
require '../src/FileQueue.php';

try {
    $FileQueue = new FileQueue(array(
        'queueNamespace' => 'demo',
        'queueDir' => '/tmp/php-file-queue',
        'consumeSpan' => 5
    ));

    $i = 1;
    while (true) {
        $FileQueue->push($i);
        $i++;
        sleep(1);
    }

} catch (Exception $e) {
    echo $e->getMessage();
}