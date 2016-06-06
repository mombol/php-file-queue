<?php

require '../src/FileQueue.php';

try {
    $FileQueue = new FileQueue(array(
        'queueNamespace' => 'demo',
        'queueDir' => '/tmp/php-file-queue',
        'consumeSpan' => 5
    ));

    while (true) {
        $data = $FileQueue->pop();
        file_put_contents('/tmp/php-file-queue/default.demo.copy', $data, FILE_APPEND);
        usleep(200000);
    }
} catch (Exception $e) {
    echo $e->getMessage();
}