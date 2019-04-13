<?php
// This is global bootstrap for autoloading

/**
 * Setup autoloading.
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/unit/PhalconUnitTestCase.php';

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__) . '/');
}

//load classes
$loader = new \Phalcon\Loader();
$loader->registerNamespaces(
    [
        'Baka\Http' => ROOT_DIR . 'src',
        'Test\Model' => ROOT_DIR . 'tests\_support\Model',
        'Baka\Elasticsearch' => ROOT_DIR . '..\phalcon-elasticsearch\src\\'
    ]
);

$loader->register();

$dotenv = new Dotenv\Dotenv(__DIR__ . '/../');
$dotenv->load();
