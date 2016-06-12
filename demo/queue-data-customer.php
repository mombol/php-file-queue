<?php

date_default_timezone_set('PRC');

require '../src/FileQueueBase.php';
require '../src/FileQueue.php';
require '../src/QueueDataConsume.php';

$QueueDataConsume = new QueueDataConsume(array(
    'queueDir' => '/tmp/php-file-queue',
    'consumeSpan' => 5, // queue consumption span
    'doConsumeBackup' => true // whether or not to backup the queue consumption data
));

while (true) {
    $QueueDataConsume->consume();
    usleep(200000);
}
