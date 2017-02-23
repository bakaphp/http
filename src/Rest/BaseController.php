<?php

namespace Baka\Http\Rest;

use \Phalcon\Http\Response;
use \Phalcon\Mvc\Controller;

/**
 * Default REST API Base Controller
 */
class BaseController extends Controller
{

    /**
     * Set JSON response for AJAX, API request
     *
     * @param mixed $content
     * @param integer $statusCode
     * @param string $statusMessage
     *
     * @return \Phalcon\Http\Response
     */
    public function response($content, int $statusCode = 200, string $statusMessage = 'OK'): Response
    {
<<<<<<< HEAD
        $di = \Phalcon\DI::getDefault();
=======

>>>>>>> b164f1ddff6a3b9696fc7c3944edc9eee86b4033
        $response = [
            'statusCode' => $statusCode,
            'statusMessage' => $statusMessage,
            'content' => $content,
        ];

        if ($this->config->application->debug->logRequest) {
            $this->log->addInfo('RESPONSE', $response);
        }

        // Create a response since it's an ajax
        $response = new Response();
        $response->setStatusCode($statusCode, $statusMessage);
        $response->setJsonContent($content);

        return $response;
    }
}
