<?php

namespace tests\FileQueue;

require_once __DIR__ . '/../autoloader.php';

class FileQueueTest extends \PHPUnit_Framework_TestCase
{

    /**
     * set up
     */
    public function setup()
    {
        class_exists(\FileQueue::class);
    }

    /**
     * @test
     */
    public function push()
    {
        $queueDir = __DIR__ . '/run';
        $queueFileName = 'test-push';
        $queueFile = $queueDir . '/' . $queueFileName . '.mq';
        if (file_exists($queueFile)) {
            unlink($queueFile);
        }
        $GeneratorFileQueue = new \FileQueue(array(
            'queueDir' => $queueDir,
            'queueFileName' => $queueFileName
        ));
        $pushData = 'push data';
        $GeneratorFileQueue->push($pushData);
        $fp_queueFile = fopen($queueFile, 'r');
        $pushLineData = fgets($fp_queueFile);
        $pushLineData = rtrim($pushLineData, "\r\n");
        if (file_exists($queueFile)) {
            unlink($queueFile);
        }
        $this->assertEquals($pushData, $pushLineData);
    }

    /**
     * @test
     */
    public function pop()
    {
        $queueDir = __DIR__ . '/run';
        $queueFileName = 'test-pop';
        $cursorFile = $queueDir . '/' . $queueFileName . '.nsp.cursor';
        $configsFile = $queueDir . '/' . $queueFileName . '.configs';
        if (file_exists($cursorFile)) {
            unlink($cursorFile);
        }
        if (file_exists($configsFile)) {
            unlink($configsFile);
        }
        $CustomerFileQueue = new \FileQueue(array(
            'role' => 'customer',
            'queueDir' => $queueDir,
            'queueFileName' => $queueFileName
        ));
        $currentPosition = array();
        $popData = $CustomerFileQueue->pop(1, $currentPosition);
        if (file_exists($cursorFile)) {
            unlink($cursorFile);
        }
        if (file_exists($configsFile)) {
            unlink($configsFile);
        }
        $this->assertEquals('pop data', $popData[0]);
        $this->assertEquals(array('pos' => 8, 'line' => 2), $currentPosition);
    }

    /**
     * @test
     */
    public function position()
    {
        $queueDir = __DIR__ . '/run';
        $queueFileName = 'test-position';
        $configsFile = $queueDir . '/' . $queueFileName . '.configs';
        $CustomerFileQueue = new \FileQueue(array(
            'role' => 'customer',
            'queueDir' => $queueDir,
            'queueFileName' => $queueFileName
        ));
        $position = $CustomerFileQueue->position();
        if (file_exists($configsFile)) {
            unlink($configsFile);
        }
        $this->assertEquals(array(8, 2), $position);
    }

    /**
     * @test
     */
    public function rewind()
    {
        $queueDir = __DIR__ . '/run';
        $queueFileName = 'test-rewind';
        $configsFile = $queueDir . '/' . $queueFileName . '.configs';
        $cursorFile = $queueDir . '/' . $queueFileName . '.nsp.cursor';
        $CustomerFileQueue = new \FileQueue(array(
            'role' => 'customer',
            'queueDir' => $queueDir,
            'queueFileName' => $queueFileName
        ));
        $CustomerFileQueue->rewind();
        $rewindCursorData = file_get_contents($cursorFile);
        if (file_exists($configsFile)) {
            unlink($configsFile);
        }
        $this->assertEquals('0,1', $rewindCursorData);
        file_put_contents($cursorFile, '8,2');
    }

    /**
     * @test
     */
    public function end()
    {
        $queueDir = __DIR__ . '/run';
        $queueFileName = 'test-end';
        $configsFile = $queueDir . '/' . $queueFileName . '.configs';
        $cursorFile = $queueDir . '/' . $queueFileName . '.nsp.cursor';
        $CustomerFileQueue = new \FileQueue(array(
            'role' => 'customer',
            'queueDir' => $queueDir,
            'queueFileName' => $queueFileName
        ));
        $CustomerFileQueue->end();
        $endCursorData = file_get_contents($cursorFile);
        if (file_exists($configsFile)) {
            unlink($configsFile);
        }
        $this->assertEquals('14,7', $endCursorData);
        file_put_contents($cursorFile, '0,1');
    }

    /**
     * @test
     */
    public function track()
    {
        $queueDir = __DIR__ . '/run';
        $queueFileName = 'test-track';
        $configsFile = $queueDir . '/' . $queueFileName . '.configs';
        $trackFile = $queueDir . '/' . $queueFileName . '.nsp.track';
        $CustomerFileQueue = new \FileQueue(array(
            'role' => 'customer',
            'queueDir' => $queueDir,
            'queueFileName' => $queueFileName
        ));
        $CustomerFileQueue->track();
        $trackCursorData = file_get_contents($trackFile);
        if (file_exists($configsFile)) {
            unlink($configsFile);
        }
        if (file_exists($trackFile)) {
            unlink($trackFile);
        }
        $this->assertEquals('12,6', $trackCursorData);
    }

    /**
     * @test
     */
    public function recover()
    {
        $queueDir = __DIR__ . '/run';
        $queueFileName = 'test-recover';
        $queueFile = $queueDir . '/' . $queueFileName . '.mq';
        $configsFile = $queueDir . '/' . $queueFileName . '.configs';
        $cursorFile = $queueDir . '/' . $queueFileName . '.nsp.cursor';
        $CustomerFileQueue = new \FileQueue(array(
            'role' => 'customer',
            'queueDir' => $queueDir,
            'queueFileName' => $queueFileName
        ));
        $CustomerFileQueue->recover();
        $recoverCursorData = file_get_contents($cursorFile);
        if (file_exists($queueFile)) {
            unlink($queueFile);
        }
        if (file_exists($configsFile)) {
            unlink($configsFile);
        }
        if (file_exists($cursorFile)) {
            unlink($cursorFile);
        }
        $this->assertEquals('12,6', $recoverCursorData);
    }

    /**
     * @test
     */
    public function length()
    {
        $queueDir = __DIR__ . '/run';
        $queueFileName = 'test-length';
        $configsFile = $queueDir . '/' . $queueFileName . '.configs';
        $cursorFile = $queueDir . '/' . $queueFileName . '.nsp.cursor';
        $CustomerFileQueue = new \FileQueue(array(
            'role' => 'customer',
            'queueDir' => $queueDir,
            'queueFileName' => $queueFileName
        ));
        $length = $CustomerFileQueue->length();
        if (file_exists($configsFile)) {
            unlink($configsFile);
        }
        if (file_exists($cursorFile)) {
            unlink($cursorFile);
        }
        $this->assertEquals(3, $length);
    }

    /**
     * @test
     */
    public function eof()
    {
        $queueDir = __DIR__ . '/run';
        $queueFileName = 'test-eof';
        $configsFile = $queueDir . '/' . $queueFileName . '.configs';
        $cursorFile = $queueDir . '/' . $queueFileName . '.nsp.cursor';
        $CustomerFileQueue = new \FileQueue(array(
            'role' => 'customer',
            'queueDir' => $queueDir,
            'queueFileName' => $queueFileName,
            'initialReadLineNumber' => 'end'
        ));
        $eof = $CustomerFileQueue->eof();
        if (file_exists($configsFile)) {
            unlink($configsFile);
        }
        if (file_exists($cursorFile)) {
            unlink($cursorFile);
        }
        $this->assertEquals(true, $eof);
    }

}