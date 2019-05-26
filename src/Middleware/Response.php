<?php

declare(strict_types=1);

namespace Baka\Http\Middleware;

use Phalcon\Mvc\Micro;
use Phalcon\Mvc\Micro\MiddlewareInterface;

/**
 * Class ResponseMiddleware
 *
 * @package Niden\Middleware
 *
 * @property Response $response
 */
class Response implements MiddlewareInterface
{

    /**
     * Call me
     *
     * @param Micro $api
     *
     * @return bool
     */
    public function call(Micro $api)
    {
        /** @var Response $response */
        $response = $api->getService('response');
        $response->send();

        return true;
    }
}