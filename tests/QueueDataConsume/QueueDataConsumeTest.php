<?php

namespace tests\QueueDataConsume;

use Mombol\FileQueue\FileQueue;
use Mombol\FileQueue\QueueDataConsume;

require_once __DIR__ . '/../autoloader.php';

class QueueDataConsumeTest extends \PHPUnit_Framework_TestCase
{

    /**
     * set up
     */
    public function setup()
    {
        class_exists('\\QueueDataConsume');
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
        $configsFile = $queueDir . '/' . $queueFileName . '.configs';

        // generate mg.config file
        new FileQueue(array(
            'role' => 'customer',
            'queueDir' => $queueDir,
            'queueFileName' => $queueFileName
        ));

        $QueueDataConsume = new QueueDataConsume(array(
            'queueDir' => $queueDir,
            'queueFileName' => $queueFileName,
            'consumeSpan' => 2,
            'doConsumeBackup' => true
        ));
        $QueueDataConsume->consume();
        $silentFileQueue = new FileQueue(array(
            'silent' => true,
            'queueDir' => $queueDir,
            'queueFileName' => $queueFileName
        ));
        $backupFile = $silentFileQueue->getDoConsumeBackupFile();
        $queueData = file_get_contents($queueFile);
        $backupData = @file_get_contents($backupFile);
        file_put_contents($queueFile, "1\r\n2\r\n3\r\n4\r\n");
        file_put_contents($cursorFile, "9,3");
        if (file_exists($backupFile)) {
            unlink($backupFile);
        }
        if (file_exists($configsFile)) {
            unlink($configsFile);
        }
        $this->assertEquals("3\r\n4\r\n", $queueData);
        $this->assertEquals("1\r\n2\r\n", $backupData);
    }

    /**
     * @test
     */
    public function clean()
    {
        $queueDir = __DIR__ . '/run';
        $queueFileName = 'test-clean';
        $queueFile = $queueDir . '/' . $queueFileName . '.mq';
        $configsFile = $queueDir . '/' . $queueFileName . '.configs';
        $cursorFile = $queueDir . '/' . $queueFileName . '.nsp.cursor';
        $cursorFile1 = $queueDir . '/' . $queueFileName . '.nsp1.cursor';

        // generate mg.config file
        new FileQueue(array(
            'role' => 'customer',
            'queueDir' => $queueDir,
            'queueFileName' => $queueFileName
        ));
        new FileQueue(array(
            'role' => 'customer',
            'queueNamespace' => 'nsp1',
            'queueDir' => $queueDir,
            'queueFileName' => $queueFileName
        ));

        $QueueDataConsume = new QueueDataConsume(array(
            'queueDir' => $queueDir,
            'queueFileName' => $queueFileName,
            'consumeSpan' => 2,
            'doConsumeBackup' => true
        ));
        $QueueDataConsume->clean();
        $queueData = file_get_contents($queueFile);
        $cursorData = file_get_contents($cursorFile);
        $cursorData1 = file_get_contents($cursorFile1);
        file_put_contents($queueFile, "1\r\n2\r\n3\r\n4\r\n");
        file_put_contents($cursorFile, '6,2');
        file_put_contents($cursorFile1, '9,3');
        if (file_exists($configsFile)) {
            unlink($configsFile);
        }
        $this->assertEquals(null, $queueData);
        $this->assertEquals('0,1', $cursorData);
        $this->assertEquals('0,1', $cursorData1);
    }

}