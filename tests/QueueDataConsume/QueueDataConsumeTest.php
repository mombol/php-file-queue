<?php

namespace tests\QueueDataConsume;

require_once __DIR__ . '/../autoloader.php';

class QueueDataConsumeTest extends \PHPUnit_Framework_TestCase
{

    /**
     * set up
     */
    public function setup()
    {
        class_exists(\QueueDataConsume::class);
    }

    /**
     * @test
     */
    public function consume()
    {
        date_default_timezone_set('PRC');
        $queueDir = __DIR__ . '/run';
        $queueFileName = 'test-consume';
        $queueFile = $queueDir . '/' . $queueFileName . '.mq';
        $cursorFile = $queueDir . '/' . $queueFileName . '.nsp.cursor';
        $QueueDataConsume = new \QueueDataConsume(array(
            'queueDir' => $queueDir,
            'queueFileName' => $queueFileName,
            'consumeSpan' => 2,
            'doConsumeBackup' => true
        ));
        $QueueDataConsume->consume();
        $silentFileQueue = new \FileQueue(array(
            'silent' => true,
            'queueDir' => $queueDir,
            'queueFileName' => $queueFileName
        ));
        $backupFile = $silentFileQueue->getDoConsumeBackupFile();
        $queueData = file_get_contents($queueFile);
        $backupData = file_get_contents($backupFile);
        file_put_contents($queueFile, "1\n2\n3\n4\n");
        file_put_contents($cursorFile, "6,3");
        if (file_exists($backupFile)) {
            unlink($backupFile);
        }
        $this->assertEquals("3\n4\n", $queueData);
        $this->assertEquals("1\n2\n", $backupData);
    }

    /**
     * @test
     */
    public function clean()
    {
        $queueDir = __DIR__ . '/run';
        $queueFileName = 'test-clean';
        $queueFile = $queueDir . '/' . $queueFileName . '.mq';
        $cursorFile = $queueDir . '/' . $queueFileName . '.nsp.cursor';
        $cursorFile1 = $queueDir . '/' . $queueFileName . '.nsp1.cursor';
        $QueueDataConsume = new \QueueDataConsume(array(
            'queueDir' => $queueDir,
            'queueFileName' => $queueFileName,
            'consumeSpan' => 2,
            'doConsumeBackup' => true
        ));
        $QueueDataConsume->clean();
        $queueData = file_get_contents($queueFile);
        $cursorData = file_get_contents($cursorFile);
        $cursorData1 = file_get_contents($cursorFile1);
        file_put_contents($queueFile, "1\n2\n3\n4\n");
        file_put_contents($cursorFile, '4,2');
        file_put_contents($cursorFile1, '6,3');
        $this->assertEquals(null, $queueData);
        $this->assertEquals('0,1', $cursorData);
        $this->assertEquals('0,1', $cursorData1);
    }

}