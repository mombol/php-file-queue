<?php

/**
 * php file queue data consumption (base on FileQueue)
 *
 * @author mombol
 * @contact mombol@163.com
 * @version v1.0.0
 */
class QueueDataConsume extends FileQueueBase
{
    /**
     * @var int $_consumeSpan : queue data consumption span
     */
    protected $_consumeSpan = 1000000;

    /**
     * @var bool $_doConsumeBackup : whether or not to do queue data consumption backup
     */
    protected $_doConsumeBackup = true;

    /**
     * @var string $_queueDir : queue directory
     */
    protected $_queueDir = '';

    /**
     * @var string $_queueFileName : queue file name
     */
    protected $_queueFileName = 'default';

    /**
     * @var string $_configsFile : queue config file
     */
    private $_configsFile = '';

    /**
     * @var string $_queueFile : the path of queue data file
     */
    private $_queueFile = '';

    /**
     * @var object $_silentFileQueue : the instance of silence file queue object
     */
    private $_silentFileQueue;

    /**
     * construct a queue data consumption object
     *
     * @param $config
     */
    public function __construct($config)
    {
        if (!isset($config['queueDir'])) {
            throw new InvalidArgumentException('config option`queueFile` is not setted !');
        } else {
            $this->setConfig($config);
            $this->_silentFileQueue = new FileQueue(array(
                'silent' => true,
                'queueDir' => $this->_queueDir,
                'queueFileName' => $this->_queueFileName
            ));
            $this->_configsFile = $this->_silentFileQueue->getConfigsFile();
            $this->_queueFile = $this->_silentFileQueue->getQueueFile();
        }
    }

    /**
     * consume queue data
     */
    public function consume()
    {
        if (file_exists($this->_configsFile)) {
            $configsFile = $this->_configsFile;
            $queueFile = $this->_queueFile;
            $fp_configs = fopen($configsFile, 'r');
            flock($fp_configs, LOCK_EX);
            $fileSize = filesize($configsFile);
            if ($fileSize) {
                $configs = fread($fp_configs, $fileSize);
                $configs = unserialize($configs);
                if (!is_array($configs)) {
                    $configs = array();
                }
                if ($configs) {
                    $canConsume = true;
                    $cursorFiles = array();
                    $consumeSpan = $this->_consumeSpan;
                    foreach ($configs as $config) {
                        $config['silent'] = true;
                        $FileQueue = new FileQueue($config);
                        $cursorFiles[] = $cursorFile = $FileQueue->getCursorFile();
                        $fp_cursor = fopen($cursorFile, 'r');
                        list(, $line) = $FileQueue->parseCursor($fp_cursor);
                        if ($line < $consumeSpan) {
                            $canConsume = false;
                            fclose($fp_cursor);
                            break;
                        } else {
                            fclose($fp_cursor);
                        }
                    }

                    if ($canConsume) {
                        $fp_cursors = array();
                        foreach ($cursorFiles as $key => $cursorFile) {
                            $fp_cursors[] = fopen($cursorFile, 'r+');
                        }

                        foreach ($fp_cursors as $fp_cursor) {
                            flock($fp_cursor, LOCK_EX);
                        }

                        $fp_queueFile = fopen($queueFile, 'r+b');
                        $consumePos = $this->_silentFileQueue->getSpecifyLinePosition($consumeSpan + 1, $fp_queueFile);
                        flock($fp_queueFile, LOCK_EX);
                        $content = null;
                        $last_position = ftell($fp_queueFile);
                        try {
                            fseek($fp_queueFile, $consumePos);
                            ob_start();
                            fpassthru($fp_queueFile);
                            $content = ob_get_contents();
                            ob_end_clean();
                            if (!empty($content)) {
                                if ($this->_doConsumeBackup) {
                                    $queuePos = $consumePos;
                                    $readOffset = 10 * 1024 * 1024;
                                    $backupFile = $this->_silentFileQueue->getDoConsumeBackupFile();
                                    $fp_backup = fopen($backupFile, 'a+b');
                                    flock($fp_backup, LOCK_EX);
                                    rewind($fp_queueFile);
                                    $curQueuePos = ftell($fp_queueFile);
                                    while ($curQueuePos < $consumePos) {
                                        $targetPos = $curQueuePos + $readOffset;
                                        $readOffset = $targetPos < $queuePos ? $readOffset : ($queuePos - $curQueuePos);
                                        fwrite($fp_backup, fread($fp_queueFile, $readOffset));
                                        $curQueuePos = ftell($fp_queueFile);
                                    }
                                    flock($fp_backup, LOCK_UN);
                                    fclose($fp_backup);
                                }

                                rewind($fp_queueFile);
                                ftruncate($fp_queueFile, 0);
                                fwrite($fp_queueFile, $content);

                                foreach ($fp_cursors as $fp_cursor) {
                                    $config['silent'] = true;
                                    $FileQueue = new FileQueue($config);
                                    list($pos, $line) = $FileQueue->parseCursor($fp_cursor);
                                    $pos -= $consumePos;
                                    $line -= $this->_consumeSpan;
                                    $FileQueue->recordCursor($pos, $line, $fp_cursor);
                                }
                            }
                        } catch (Exception $e) {
                            fseek($fp_queueFile, $last_position);
                        }

                        flock($fp_queueFile, LOCK_UN);

                        $reverse_fp_cursors = array_reverse($fp_cursors);
                        foreach ($reverse_fp_cursors as $reverse_fp_cursor) {
                            flock($reverse_fp_cursor, LOCK_UN);
                        }

                        foreach ($fp_cursors as $fp_cursor) {
                            fclose($fp_cursor);
                        }
                    }
                }
            }
            flock($fp_configs, LOCK_UN);
            fclose($fp_configs);
        }
    }

    /**
     * clean the queue data
     */
    public function clean()
    {
        if (file_exists($this->_configsFile)) {
            $configsFile = $this->_configsFile;
            $queueFile = $this->_queueFile;
            $fp_configs = fopen($configsFile, 'r');
            flock($fp_configs, LOCK_EX);
            $fileSize = filesize($configsFile);
            if ($fileSize) {
                $configs = fread($fp_configs, $fileSize);
                $configs = unserialize($configs);
                if (!is_array($configs)) {
                    $configs = array();
                }
                if ($configs) {
                    $cursorFiles = array();
                    foreach ($configs as $config) {
                        $config['silent'] = true;
                        $FileQueue = new FileQueue($config);
                        $cursorFiles[] = $cursorFile = $FileQueue->getCursorFile();
                    }

                    $fp_cursors = array();
                    foreach ($cursorFiles as $key => $cursorFile) {
                        $fp_cursors[] = fopen($cursorFile, 'r+');
                    }

                    foreach ($fp_cursors as $fp_cursor) {
                        flock($fp_cursor, LOCK_EX);
                    }

                    $fp_queueFile = fopen($queueFile, 'r+b');
                    flock($fp_queueFile, LOCK_EX);

                    rewind($fp_queueFile);
                    ftruncate($fp_queueFile, 0);
                    rewind($fp_queueFile);

                    foreach ($fp_cursors as $fp_cursor) {
                        rewind($fp_cursor);
                        ftruncate($fp_cursor, 0);
                        fwrite($fp_cursor, "0,1");
                    }

                    flock($fp_queueFile, LOCK_UN);

                    $reverse_fp_cursors = array_reverse($fp_cursors);
                    foreach ($reverse_fp_cursors as $reverse_fp_cursor) {
                        flock($reverse_fp_cursor, LOCK_UN);
                    }

                    foreach ($fp_cursors as $fp_cursor) {
                        fclose($fp_cursor);
                    }
                }
            }
            flock($fp_configs, LOCK_UN);
            fclose($fp_configs);
        }
    }

}