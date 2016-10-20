<?php

if (defined('VENDOR_DIRECTORY')) {
    return;
} elseif (file_exists(__DIR__ . '/../vendor/')) {
    define('VENDOR_DIRECTORY', __DIR__ . '/../vendor/');
} elseif (file_exists(__DIR__ . '/../../../../vendor/')) {
    define('VENDOR_DIRECTORY', __DIR__ . '/../../../../vendor/');
} else {
    die('vendor directory not found');
}
require_once(VENDOR_DIRECTORY . 'autoload.php');
$loader = new \Composer\Autoload\ClassLoader();

$loader->addPsr4('tests\\', __DIR__);
$loader->addPsr4('Mombol\\FileQueue\\', __DIR__ . '/../src');
$loader->addClassMap(array());
$loader->register();
