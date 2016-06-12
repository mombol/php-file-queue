<?php

require '../src/FileQueueBase.php';
require '../src/FileQueue.php';

try {
    $FileQueue = new FileQueue(array(
        'role' => 'customer',
        'queueNamespace' => 'demo',
        'queueDir' => '/tmp/php-file-queue',
    ));

    while (true) {
        $data = $FileQueue->pop();
        file_put_contents('/tmp/php-file-queue/default.demo.copy', $data, FILE_APPEND);
        usleep(500000);
    }
} catch (Exception $e) {
    echo $e->getMessage();
}