<?php

/**
 * php file queue
 *
 * @author mombol
 * @contact mombol@163.com
 * @version v0.0.1
 */
class FileQueue
{

    /**
     * @var string $_namespace : queue namespace
     */
    private $_queueNamespace = 'nsp';

    /**
     * @var string $_queueDir : queue directory
     */
    private $_queueDir = '/var/run/php-file-queue';

    /**
     * @var string $_queueFileName : queue file name
     */
    private $_queueFileName = 'default';

    /**
     * @var string $_queueFileSuffix : queue file suffix
     */
    private $_queueFileSuffix = 'mq';

    /**
     * @var string $_cursorFileSuffix : queue cursor file suffix
     */
    private $_cursorFileSuffix = 'cursor';

    /**
     * @var string $_trackFileSuffix : queue cursor track file suffix, used to recover the cursor
     */
    private $_trackFileSuffix = 'track';

    /**
     * @var bool $_doConsume : do or not consume queue file
     */
    private $_doConsume = true;

    /**
     * @var int $_consumeSpan : queue file consume frequency line
     */
    private $_consumeSpan = 1000000;

    /**
     * @var resource $_fp_queueFile : queue file open handler
     */
    private $_fp_queueFile;

    /**
     * @var resource $_fp_cursorFile : queue cursor file open handler
     */
    private $_fp_cursorFile;

    /**
     * @var resource $_fp_trackFile : queue cursor track file open handler
     */
    private $_fp_trackFile;

    /**
     * construct a queue object
     *
     * @param array $configs
     */
    public function __construct($configs)
    {
        $this->setConfig($configs);
        $this->mount();
    }

    /**
     * set queue property
     *
     * @param $configs
     */
    private function setConfig($configs)
    {
        if (!is_array($configs)) {
            $configs = array();
        }

        foreach ($configs as $config => $value) {
            $_config = '_' . $config;
            if (isset($this->$_config)) {
                $setConfig = 'set' . ucfirst($config);
                $this->$setConfig($value);
            }
        }
    }

    /**
     * mount queue files
     */
    private function mount()
    {
        $queueFile = $this->getQueueFile();
        $cursorFile = $this->getCursorFile();
        $trackFile = $this->getTrackFile();

        if (!file_exists($queueFile)) {
            $this->mkDirs($this->_queueDir);
            $fp = fopen($queueFile, 'w+');
            fclose($fp);

            if (file_exists($cursorFile)) {
                unlink($cursorFile);
            }
        }

        if (!file_exists($cursorFile)) {
            $fp = fopen($cursorFile, 'w+');
            fclose($fp);
        }

        if (!file_exists($trackFile)) {
            $fp = fopen($trackFile, 'w+');
            fclose($fp);
        }

        $this->_fp_queueFile = fopen($queueFile, 'a+b');
        $this->_fp_cursorFile = fopen($cursorFile, 'r+b');
        $this->_fp_trackFile = fopen($trackFile, 'r+b');
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
        $number = max(1, $number);

        flock($this->_fp_cursorFile, LOCK_EX);
        list($pos, $line) = $this->parseCursor();

        if ($this->_doConsume && $line > $this->_consumeSpan) {
            flock($this->_fp_queueFile, LOCK_EX);
            $content = null;
            try {
                ob_start();
                fpassthru($this->_fp_queueFile);
                $content = ob_get_flush();
            } catch (Exception $e) {
            }
            if (!empty($content)) {
                ftruncate($this->_fp_queueFile, 0);
                fwrite($this->_fp_queueFile, $content);
                rewind($this->_fp_queueFile);
                $pos = 0;
                $line = 1;
                $this->recordCursor($pos, $line);
            }
            flock($this->_fp_queueFile, LOCK_UN);
        }

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
        flock($this->_fp_cursorFile, LOCK_EX);
        flock($this->_fp_trackFile, LOCK_EX);
        rewind($this->_fp_cursorFile);
        $cursorData = fgets($this->_fp_cursorFile);
        fwrite($this->_fp_trackFile, $cursorData);
        flock($this->_fp_trackFile, LOCK_UN);
        flock($this->_fp_cursorFile, LOCK_UN);
    }

    /**
     * recover the last cursor
     */
    public function recover()
    {
        flock($this->_fp_trackFile, LOCK_EX);
        flock($this->_fp_cursorFile, LOCK_EX);
        rewind($this->_fp_trackFile);
        $trackData = fgets($this->_fp_trackFile);
        if ($trackData !== false) {
            $pos = intval(trim($trackData[0]));
            $line = isset($trackData[1]) ? intval(trim($trackData[1])) : 0;
            $this->recordCursor($pos, $line);
        }
        flock($this->_fp_cursorFile, LOCK_UN);
        flock($this->_fp_trackFile, LOCK_UN);
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
     * get the path of queue file
     *
     * @return string
     */
    public function getQueueFile()
    {
        return $this->getQueueFileWithoutSuffix() . '.' . $this->getQueueFileSuffix();
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
        return $this->getQueueFileWithoutSuffix() . '.' . $this->getTrackFileSuffix();
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

    /**
     * set queue namespace
     *
     * @param $queueNamespace
     */
    private function setQueueNamespace($queueNamespace)
    {
        $this->_queueNamespace = $queueNamespace;
    }

    /**
     * get queue namespace
     *
     * @return string
     */
    public function getQueueNamespace()
    {
        return $this->_queueNamespace;
    }


    /**
     * set queue directory
     *
     * @param $queueDir
     */
    private function setQueueDir($queueDir)
    {
        $this->_queueDir = $queueDir;
    }

    /**
     * get queue directory
     *
     * @return string
     */
    public function getQueueDir()
    {
        return $this->_queueDir;
    }

    /**
     * set queue file name
     *
     * @param $queueFileName
     */
    private function setQueueFileName($queueFileName)
    {
        $this->_queueFileName = $queueFileName;
    }

    /**
     * get queue file name
     *
     * @return string
     */
    public function getQueueFileName()
    {
        return $this->_queueFileName;
    }

    /**
     * set queue file suffix
     *
     * @param $queueFileSuffix
     */
    private function setQueueFileSuffix($queueFileSuffix)
    {
        $this->_queueFileSuffix = $queueFileSuffix;
    }

    /**
     * get queue file suffix
     *
     * @return string
     */
    public function getQueueFileSuffix()
    {
        return $this->_queueFileSuffix;
    }

    /**
     * set queue cursor file suffix
     *
     * @param $cursorFileSuffix
     */
    private function setCursorFileSuffix($cursorFileSuffix)
    {
        $this->_cursorFileSuffix = $cursorFileSuffix;
    }

    /**
     * get queue cursor file suffix
     *
     * @return string
     */
    public function getCursorFileSuffix()
    {
        return $this->_cursorFileSuffix;
    }

    /**
     * set queue track file suffix
     *
     * @param $trackFileSuffix
     */
    private function setTrackFileSuffix($trackFileSuffix)
    {
        $this->_trackFileSuffix = $trackFileSuffix;
    }

    /**
     * get queue track file suffix
     *
     * @return string
     */
    public function getTrackFileSuffix()
    {
        return $this->_trackFileSuffix;
    }


    /**
     * set do or not consume
     *
     * @param $doConsume
     */
    private function setDoConsume($doConsume)
    {
        $this->_doConsume = (bool)$doConsume;
    }

    /**
     * get the switch of do or not consume
     *
     * @return bool
     */
    public function getDoConsume()
    {
        return $this->_doConsume;
    }

    /**
     * set consume frequency line
     *
     * @param $consumeSpan
     */
    private function setConsumeSpan($consumeSpan)
    {
        $this->_consumeSpan = $consumeSpan;
    }

    /**
     * get consume frequency line
     *
     * @return int
     */
    public function getConsumeSpan()
    {
        return $this->_consumeSpan;
    }

    /**
     * parse cursor to get position and line number
     *
     * @return array
     */
    private function parseCursor()
    {
        rewind($this->_fp_cursorFile);
        $cursorData = fgets($this->_fp_cursorFile);
        $cursorData = explode(',', $cursorData);
        $pos = intval(trim($cursorData[0]));
        $line = isset($cursorData[1]) ? max(1, intval(trim($cursorData[1]))) : 1;
        return array($pos, $line);
    }

    /**
     * record cursor
     *
     * @param $pos
     * @param $line
     */
    private function recordCursor($pos, $line)
    {
        rewind($this->_fp_cursorFile);
        ftruncate($this->_fp_cursorFile, 0);
        fwrite($this->_fp_cursorFile, "{$pos},{$line}");
    }

    /**
     * make multi-level directory
     *
     * @param $dir
     * @param int $mode
     * @return bool
     */
    private function mkDirs($dir, $mode = 0755)
    {
        if (!is_dir($dir)) {
            $this->mkDirs(dirname($dir), $mode);
            return @mkdir($dir, $mode);
        }
        return true;
    }

    /**
     * magic function called when call method which does't exist
     *
     * @param $name
     * @param $parameters
     * @throws Exception
     */
    public function __call($name, $parameters)
    {
        if (preg_match('/^set[A-Z]/', $name)) {
            throw new Exception('Can not set config ' . preg_replace('/^set/', '', $name));
        }
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

        if (!is_null($this->_fp_trackFile)) {
            fclose($this->_fp_trackFile);
        }
    }

}