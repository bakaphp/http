<?php

namespace Baka\Http;

use \Exception;
use \Phalcon\Mvc\Micro;
use \Phalcon\Mvc\Micro\Collection as MicroCollection;

/**
 * Router collection for Micro Phalcon API, insted of having to do
 *
 * Phalcon Way
 * $index = new MicroCollection();
 * $index->setHandler("MyDealer\Controllers\IndexController", true);
 * $index->setPrefix("/");
 * $index->get("", "index");
 * $application->mount($index);
 *
 * We provide a clean API to emulate Phalcon MVC Routers
 *
 * $router = new RouterCollection($application);
 * $router->setPrefix('/v2');
 * $router->get('/', [
 *     'MyDealer\Controllers\IndexController',
 *     'index',
 *  ]);
 *
 * $router->post('/add', [
 *     'MyDealer\Controllers\IndexController',
 *      'index',
 *  ]);
 *
 *  $router->mount();
 */
class RouterCollection
{
    private $application;
    private $prefix = null;
    private $collections = [];
    private static $jwt = [];
    private static $hasJwtOptionsSetup = false;
    private static $middleware = [];

    /**
     * Constructor , we pass the micro app
     *
     * @param Micro $application
     */
    public function __construct(Micro $application)
    {
        $this->application = $application;
    }

    /**
     * If the router is user a prefix
     *
     * @param string $prefix
     */
    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    /**
     * Mount the collection to the micro app router
     *
     * @return void
     */
    public function mount(): void
    {
        if (count($this->collections) > 0) {
            foreach ($this->collections as $collection) {
                $micro = new MicroCollection();
                // Set the main handler. ie. a controller instance
                $micro->setHandler($collection['className'], true);
                // Set a common prefix for all routes

                if ($this->prefix) {
                    $micro->setPrefix($this->prefix);
                }

                // Use the method 'index' in PostsController
                $micro->{$collection['method']}($collection['pattern'], $collection['function']);

                $this->application->mount($micro);
            }
        }

        return;
    }

    /**
     * Add the call function to the collection array
     *
     * @param  string $method
     * @param  string $pattern
     * @param  string $className
     * @param  string $function
     * @return void
     */
    private function call(string $method, string $pattern, string $className, string $function, array $options = []): void
    {
        if (empty($className) || empty($function)) {
            throw new Exception('Missing params, we need 2 parameters');
        }

        $route = [
            'method' => $method,
            'pattern' => $pattern,
            'className' => $className,
            'function' => $function,
        ];
        $this->collections[] = $route;

        if (array_key_exists('options', $options)) {
            $this->setOptions($route, $options);
        }

        return;
    }

    /**
     * Set routers options JWT
     *
     * @todo add Middleware that the router will call
     * @param array $route
     * @param array $options
     * @return void
     */
    private function setOptions(array $route, array $options): void
    {
        if (array_key_exists('jwt', $options['options'])) {
            //only add if we want to ignore this url
            if (!$options['options']['jwt']) {
                self::$hasJwtOptionsSetup = true;
                self::$jwt[] = $this->prefix . $route['pattern'] . ':' . strtoupper($route['method']);
            }
        }
    }

    /**
     * Get the ignore JWT url
     *
     * @return array
     */
    public static function getJwtIgnoreRoutes(): array
    {
        $ignoreUrl = [];
        if (self::$hasJwtOptionsSetup) {
            $ignoreUrl = self::$jwt;
        }

        return $ignoreUrl;
    }

    /**
     * Insted of using magic we define each method function
     *
     * @param  string $pattern
     * @param  array  $param
     * @return void
     */
    public function get(string $pattern, array $param): void
    {
        $this->call('get', $pattern, $param[0], $param[1], $param);
    }

    /**
     * Insted of using magic we define each method function
     *
     * @param  string $pattern
     * @param  array  $param
     * @return void
     */
    public function put(string $pattern, array $param) : void
    {
        $this->call('put', $pattern, $param[0], $param[1], $param);
    }

    /**
     * Insted of using magic we define each method function
     *
     * @param  string $pattern
     * @param  array  $param
     * @return void
     */
    public function post(string $pattern, array $param) : void
    {
        $this->call('post', $pattern, $param[0], $param[1], $param);
    }

    /**
     * Insted of using magic we define each method function
     *
     * @param  string $pattern
     * @param  array  $param
     * @return void
     */
    public function delete(string $pattern, array $param) : void
    {
        $this->call('delete', $pattern, $param[0], $param[1], $param);
    }

    /**
     * Insted of using magic we define each method function
     *
     * @param  string $pattern
     * @param  array  $param
     * @return void
     */
    public function patch(string $pattern, array $param) : void
    {
        $this->call('patch', $pattern, $param[0], $param[1], $param);
    }

    /**
     * Insted of using magic we define each method function
     *
     * @param  string $pattern
     * @param  array  $param
     * @return void
     */
    public function options(string $pattern, array $param) : void
    {
        $this->call('options', $pattern, $param[0], $param[1], $param);
    }
    
     /**
     * Instead of using magic we define each method function
     *
     * @param  string $pattern
     * @param  array  $param
     */
    public function head(string $pattern, array $param)
    {
        return $this->call('head', $pattern, $param[0], $param[1]);
    }
}
