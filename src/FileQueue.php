<?php

/**
 * php file queue
 *
 * @author mombol
 * @contact mombol@163.com
 * @version v0.1.0
 */
class FileQueue extends FileQueueBase
{
    /**
     * @var string $_role : queue role
     */
    protected $_role = 'generator';

    /**
     * @var string $_queueDir : queue directory
     */
    protected $_queueDir = '/var/run/php-file-queue';

    /**
     * @var string $_queueFileName : queue file name
     */
    protected $_queueFileName = 'default';

    /**
     * @var string $_queueFileSuffix : queue file suffix
     */
    protected $_queueFileSuffix = 'mq';

    /**
     * @var string $_cursorFileSuffix : queue cursor file suffix
     */
    protected $_cursorFileSuffix = 'cursor';

    /**
     * @var string $_namespace : queue namespace
     */
    protected $_queueNamespace = 'nsp';

    /**
     * @var int $_initialReadLineNumber : the number of initial read rows
     */
    protected $_initialReadLineNumber = 0;

    /**
     * @var resource $_fp_queueFile : queue file open handler
     */
    private $_fp_queueFile;

    /**
     * @var resource $_fp_cursorFile : queue cursor file open handler
     */
    private $_fp_cursorFile;

    /**
     * construct a queue object
     *
     * @param array $config
     */
    public function __construct($config)
    {
        $silent = false;
        if (isset($config['silent'])) {
            $silent = $config['silent'];
            unset($config['silent']);
        }
        $this->setConfig($config);
        if (!$silent) {
            $this->beforeMount($config);
            $this->mount();
            $this->afterMount();
        }
    }

    /**
     * before mount
     *
     * @param $config
     */
    private function beforeMount($config)
    {
        $queueFile = $this->getQueueFile();
        $cursorFile = $this->getCursorFile();
        $configsFile = $this->getConfigsFile();

        $this->mkDirs($this->_queueDir);

        if (!file_exists($queueFile)) {
            $fp = fopen($queueFile, 'w+');
            fclose($fp);

            if (file_exists($cursorFile)) {
                unlink($cursorFile);
            }

            if (file_exists($configsFile)) {
                unlink($configsFile);
            }
        }

        if (!file_exists($cursorFile) && !$this->isGenerator()) {
            $fp = fopen($cursorFile, 'w+');
            fclose($fp);
        }

        if (!$this->isGenerator()) {
            if (!file_exists($configsFile)) {
                $fp = fopen($configsFile, 'w+');
                fclose($fp);
            }
            $fp_configs = fopen($configsFile, 'r+');
            flock($fp_configs, LOCK_EX);
            $fileConsumes = $this->parseSerializeFile($fp_configs, $configsFile);
            $fileConsumes[$this->_queueNamespace] = $config;
            rewind($fp_configs);
            ftruncate($fp_configs, 0);
            fwrite($fp_configs, serialize($fileConsumes));
            flock($fp_configs, LOCK_UN);
            fclose($fp_configs);
        }
    }

    /**
     * mount queue files
     */
    private function mount()
    {
        $this->_fp_queueFile = fopen($this->getQueueFile(), 'a+b');
        if (!$this->isGenerator()) {
            $this->_fp_cursorFile = fopen($this->getCursorFile(), 'r+b');
        }
    }

    /**
     * after mount
     */
    private function afterMount()
    {
        $initialReadLineNumber = $this->_initialReadLineNumber;
        // if the number of initial read line number was specified
        if ($initialReadLineNumber) {
            flock($this->_fp_cursorFile, LOCK_EX);
            if ($initialReadLineNumber == 'end') {
                $pos = filesize($this->getQueueFile());
                $line = $this->length();
            } else {
                $pos = $this->getSpecifyLinePosition($initialReadLineNumber);
                $line = $initialReadLineNumber;
            }
            $this->recordCursor($pos, $line);
            flock($this->_fp_cursorFile, LOCK_UN);
        }
    }

    /**
     * push data to queue file
     *
     * @param string|array|object $data
     * @return int
     */
    public function push($data)
    {
        if (is_string($data) || is_numeric($data)) {
            $data = array($data);
        } else if (is_object($data)) {
            $data = array(serialize($data));
        }

        $count = 0;

        if (is_array($data)) {
            flock($this->_fp_queueFile, LOCK_EX);
            foreach ($data as $line) {
                fwrite($this->_fp_queueFile, $line . "\r\n");
                $count++;
            }
            flock($this->_fp_queueFile, LOCK_UN);
        }

        return $count;
    }

    /**
     * pop data from queue file
     *
     * @param $number
     * @return array
     */
    public function pop($number = 1)
    {
        if ($this->isGenerator()) {
            throw new LogicException('Generator can not pop data from queue!');
        }
        $number = max(1, $number);

        flock($this->_fp_cursorFile, LOCK_EX);
        list($pos, $line) = $this->parseCursor();

        $result = array();
        for ($i = 0; $i < $number; $i++) {
            fseek($this->_fp_queueFile, $pos);
            $data = fgets($this->_fp_queueFile);

            if ($data !== false) {
                $pos = ftell($this->_fp_queueFile);
                $line++;
                $this->recordCursor($pos, $line);
                $result[] = $data;
            } else {
                break;
            }
        }

        flock($this->_fp_cursorFile, LOCK_UN);

        return $result;
    }

    /**
     * get the queue cursor position
     *
     * @return array
     */
    public function position()
    {
        flock($this->_fp_cursorFile, LOCK_EX);
        list($pos, $line) = $this->parseCursor();
        flock($this->_fp_cursorFile, LOCK_UN);
        return array($pos, $line);
    }

    /**
     * rewind the queue cursor position to the header
     */
    public function rewind()
    {
        flock($this->_fp_cursorFile, LOCK_EX);
        $this->recordCursor(0, 1);
        flock($this->_fp_cursorFile, LOCK_UN);
    }

    /**
     * point the queue cursor to the end
     */
    public function end()
    {
        flock($this->_fp_cursorFile, LOCK_EX);
        $pos = filesize($this->_fp_queueFile) - 1;
        $line = $this->length(false);
        $this->recordCursor($pos, $line);
        flock($this->_fp_cursorFile, LOCK_UN);
        flock($this->_fp_queueFile, LOCK_UN);
    }

    /**
     * track the queue current cursor which used to recover last cursor
     */
    public function track()
    {
        $trackFile = $this->getTrackFile();
        if (!file_exists($trackFile)) {
            $fp = fopen($trackFile, 'w+');
            fclose($fp);
        }
        $fp_track = fopen($trackFile, 'r+b');
        flock($this->_fp_cursorFile, LOCK_EX);
        flock($fp_track, LOCK_EX);
        rewind($this->_fp_cursorFile);
        $cursorData = fgets($this->_fp_cursorFile);
        ftruncate($fp_track, 0);
        fwrite($fp_track, $cursorData);
        flock($fp_track, LOCK_UN);
        flock($this->_fp_cursorFile, LOCK_UN);
        fclose($fp_track);
    }

    /**
     * recover the last cursor
     */
    public function recover()
    {
        $trackFile = $this->getTrackFile();
        if (!file_exists($trackFile)) {
            $fp = fopen($trackFile, 'w+');
            fclose($fp);
        }
        $fp_track = fopen($trackFile, 'r+b');
        flock($fp_track, LOCK_EX);
        flock($this->_fp_cursorFile, LOCK_EX);
        rewind($fp_track);
        $trackData = fgets($fp_track);
        if ($trackData !== false) {
            $pos = intval(trim($trackData[0]));
            $line = isset($trackData[1]) ? max(1, intval(trim($trackData[1]))) : 1;
            $this->recordCursor($pos, $line);
        }
        flock($this->_fp_cursorFile, LOCK_UN);
        flock($fp_track, LOCK_UN);
        fclose($fp_track);
    }

    /**
     * get the queue length
     *
     * @param $unlock
     * @return int
     */
    public function length($unlock = true)
    {
        flock($this->_fp_queueFile, LOCK_EX);
        $last_pos = ftell($this->_fp_queueFile);
        rewind($this->_fp_queueFile);
        $line = 0;
        while (fgets($this->_fp_queueFile) !== false) {
            $line++;
        }
        fseek($this->_fp_queueFile, $last_pos);
        if ($unlock) {
            flock($this->_fp_queueFile, LOCK_UN);
        }
        return $line;
    }

    /**
     * get specify line position, may return last line position when line number greater then queue length
     *
     * @param $line
     * @param null $fp_queueFile
     * @return int
     */
    public function getSpecifyLinePosition($line, $fp_queueFile = null)
    {
        if (is_null($fp_queueFile)) {
            $fp_queueFile = $this->_fp_queueFile;
        }
        flock($fp_queueFile, LOCK_EX);
        $last_position = ftell($fp_queueFile);
        rewind($fp_queueFile);
        $lineCount = 1;
        while (fgets($fp_queueFile) !== false) {
            $lineCount++;
            if ($lineCount == $line) {
                break;
            }
        }
        $pos = ftell($fp_queueFile);
        fseek($fp_queueFile, $last_position);
        flock($fp_queueFile, LOCK_UN);
        return $pos;
    }

    /**
     * tests for the end of queue
     *
     * @return bool
     */
    public function eof()
    {
        $eof = true;
        if ($this->_fp_queueFile) {
            flock($this->_fp_queueFile, LOCK_EX);
            if (!feof($this->_fp_queueFile)) {
                $eof = false;
            }
            flock($this->_fp_queueFile, LOCK_UN);
        }
        return $eof;
    }

    /**
     * get the path of queue file
     *
     * @return string
     */
    public function getQueueFile()
    {
        return $this->getNormalizeQueueDir() . $this->getQueueFileName() . '.' . $this->getQueueFileSuffix();
    }

    /**
     * get the path of cursor file
     *
     * @return string
     */
    public function getCursorFile()
    {
        return $this->getQueueFileWithoutSuffix() . '.' . $this->getCursorFileSuffix();
    }

    /**
     * get the path of track file
     *
     * @return string
     */
    public function getTrackFile()
    {
        return $this->getQueueFileWithoutSuffix() . '.track';
    }

    /**
     * get the path of configs file
     *
     * @return string
     */
    public function getConfigsFile()
    {
        return $this->getNormalizeQueueDir() . $this->getQueueFileName() . '.configs';
    }

    /**
     * get the path of consumption backup file
     *
     * @return string
     */
    public function getDoConsumeBackupFile()
    {
        return $this->getQueueFile() . '.' . date('Y-m-d') . '.backup';
    }

    /**
     * get queue file path without suffix
     *
     * @return string
     */
    public function getQueueFileWithoutSuffix()
    {
        return $this->getNormalizeQueueDir() . $this->getQueueFileName() . '.' . $this->getQueueNamespace();
    }

    /**
     * get normalize queue directory
     *
     * @return string
     */
    public function getNormalizeQueueDir()
    {
        return rtrim($this->getQueueDir(), '/') . '/';
    }

//    /**
//     * set queue role
//     *
//     * @param $role
//     */
//    private function setRole($role)
//    {
//        $this->_role = $role;
//    }
//
//    /**
//     * get queue role
//     * @return string
//     */
//    public function getRole()
//    {
//        return $this->_role;
//    }
//
//    /**
//     * set queue namespace
//     *
//     * @param $queueNamespace
//     */
//    private function setQueueNamespace($queueNamespace)
//    {
//        $this->_queueNamespace = $queueNamespace;
//    }
//
//    /**
//     * get queue namespace
//     *
//     * @return string
//     */
//    public function getQueueNamespace()
//    {
//        return $this->_queueNamespace;
//    }
//
//    /**
//     * set queue directory
//     *
//     * @param $queueDir
//     */
//    private function setQueueDir($queueDir)
//    {
//        $this->_queueDir = $queueDir;
//    }
//
//    /**
//     * get queue directory
//     *
//     * @return string
//     */
//    public function getQueueDir()
//    {
//        return $this->_queueDir;
//    }
//
//    /**
//     * set queue file name
//     *
//     * @param $queueFileName
//     */
//    private function setQueueFileName($queueFileName)
//    {
//        $this->_queueFileName = $queueFileName;
//    }
//
//    /**
//     * get queue file name
//     *
//     * @return string
//     */
//    public function getQueueFileName()
//    {
//        return $this->_queueFileName;
//    }
//
//    /**
//     * set queue file suffix
//     *
//     * @param $queueFileSuffix
//     */
//    private function setQueueFileSuffix($queueFileSuffix)
//    {
//        $this->_queueFileSuffix = $queueFileSuffix;
//    }
//
//    /**
//     * get queue file suffix
//     *
//     * @return string
//     */
//    public function getQueueFileSuffix()
//    {
//        return $this->_queueFileSuffix;
//    }
//
//    /**
//     * set queue cursor file suffix
//     *
//     * @param $cursorFileSuffix
//     */
//    private function setCursorFileSuffix($cursorFileSuffix)
//    {
//        $this->_cursorFileSuffix = $cursorFileSuffix;
//    }
//
//    /**
//     * get queue cursor file suffix
//     *
//     * @return string
//     */
//    public function getCursorFileSuffix()
//    {
//        return $this->_cursorFileSuffix;
//    }
//
//    /**
//     * set the number of initial read rows
//     *
//     * @param $initialReadLineNumber
//     */
//    private function setInitialReadLineNumber($initialReadLineNumber)
//    {
//        $this->_initialReadLineNumber = $initialReadLineNumber;
//    }
//
//    /**
//     * get the number of initial read rows
//     *
//     * @return int
//     */
//    public function getInitialReadLineNumber()
//    {
//        return $this->_initialReadLineNumber;
//    }

    /**
     * parse cursor to get position and line number
     *
     * @param null $fp_cursorFile
     * @return array
     */
    public function parseCursor($fp_cursorFile = null)
    {
        if (is_null($fp_cursorFile)) {
            $fp_cursorFile = $this->_fp_cursorFile;
        }
        rewind($fp_cursorFile);
        $cursorData = fgets($fp_cursorFile);
        $cursorData = explode(',', $cursorData);
        $pos = max(0, intval(trim($cursorData[0])));
        $line = isset($cursorData[1]) ? max(1, intval(trim($cursorData[1]))) : 1;
        return array($pos, $line);
    }

    /**
     * parse file of store serialize data
     *
     * @param $fp
     * @param $file
     * @return array|mixed|string
     */
    public function parseSerializeFile($fp, $file)
    {
        $fileSize = filesize($file);
        if (!$fileSize) {
            return array();
        }
        rewind($fp);
        $data = fread($fp, $fileSize);
        $data = unserialize($data);
        if (!is_array($data)) {
            $data = array();
        }
        return $data;
    }

    /**
     * record cursor
     *
     * @param $pos
     * @param $line
     * @param null $fp_cursorFile
     */
    public function recordCursor($pos, $line, $fp_cursorFile = null)
    {
        if (empty($fp_cursorFile)) {
            $fp_cursorFile = $this->_fp_cursorFile;
        }
        rewind($fp_cursorFile);
        ftruncate($fp_cursorFile, 0);
        fwrite($fp_cursorFile, "{$pos},{$line}");
    }

    /**
     * check whether the queue role is generator
     */
    public function isGenerator()
    {
        return $this->_role == 'generator';
    }

    /**
     * destruct queue object
     */
    public function __destruct()
    {
        if (!is_null($this->_fp_queueFile)) {
            fclose($this->_fp_queueFile);
        }

        if (!is_null($this->_fp_cursorFile)) {
            fclose($this->_fp_cursorFile);
        }
    }

}