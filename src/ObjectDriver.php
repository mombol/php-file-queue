<?php

namespace Mombol\FileQueue;

/**
 * php file queue base abstract class
 *
 * @author mombol
 * @contact mombol@163.com
 * @version v1.2.1
 */
abstract class ObjectDriver
{

    /**
     * set queue property
     *
     * @param $config
     */
    protected function setConfig($config)
    {
        if (!is_array($config)) {
            $config = array();
        }

        foreach ($config as $property => $value) {
            $_property = '_' . $property;
            if (isset($this->$_property)) {
                $setProperty = 'set' . ucfirst($property);
                $this->$setProperty($value);
            }
        }
    }

    /**
     * make multi-level directory
     *
     * @param $dir
     * @param int $mode
     * @return bool
     */
    protected function mkDirs($dir, $mode = 0755)
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
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function __call($name, $parameters)
    {
        if (preg_match('/^[sg]et[A-Z]/', $name)) {
            $property = '_' . lcfirst(preg_replace('/^[sg]et/', '', $name));
            if (isset($this->$property)) {
                if ($name[0] == 's') {
                    $this->$property = $parameters[0];
                } else {
                    return $this->$property;
                }
            }
        } else {
            throw new \BadMethodCallException('Can not call your method ' . $name);
        }
    }

}