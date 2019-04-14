<?php

use \Phalcon\Di;
use \Phalcon\Test\UnitTestCase as PhalconTestCase;

abstract class PhalconUnitTestCase extends PhalconTestCase
{
    /**
     * @var \Voice\Cache
     */
    protected $_cache;

    /**
     * @var \Phalcon\Config
     */
    protected $_config;

    /**
     * @var bool
     */
    private $_loaded = false;

    /**
     * Setup phalconPHP DI to use for testing components.
     *
     * @return Phalcon\DI
     */
    protected function _getDI()
    {
        Phalcon\DI::reset();

        $di = new Phalcon\DI();

        /**
         * DB Config.
         * @var array
         */
        $this->_config = new \Phalcon\Config([
            'database' => [
                'adapter' => 'Mysql',
                'host' => getenv('DATA_API_MYSQL_HOST'),
                'username' => getenv('DATA_API_MYSQL_USER'),
                'password' => getenv('DATA_API_MYSQL_PASS'),
                'dbname' => getenv('DATA_API_MYSQL_NAME'),
            ],
            'memcache' => [
                'host' => getenv('MEMCACHE_HOST'),
                'port' => getenv('MEMCACHE_PORT'),
            ],
            'namespace' => [
                'models' => 'Test\Models',
                'elasticIndex' => 'Test\Indices',
            ],
            'elasticSearch' => [
                'hosts' => [getenv('ELASTIC_HOST')], //change to pass array
            ],
        ]);

        $config = $this->_config;

        $di->set('config', function () use ($config) {
            return $config;
        }, true);

        /**
         * Everything needed initialize phalconphp db.
         */
        $di->set('modelsManager', function () {
            return new Phalcon\Mvc\Model\Manager();
        }, true);

        $di->set('modelsMetadata', function () {
            return new Phalcon\Mvc\Model\Metadata\Memory();
        }, true);

        $di->set('db', function () use ($config, $di) {
            //db connection
            $connection = new Phalcon\Db\Adapter\Pdo\Mysql([
                'host' => $config->database->host,
                'username' => $config->database->username,
                'password' => $config->database->password,
                'dbname' => $config->database->dbname,
                'charset' => 'utf8',
            ]);

            return $connection;
        });

        return $di;
    }

    /**
    * this runs before everyone.
    */
    protected function setUp()
    {
        $this->_getDI();
    }
}
