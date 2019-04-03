<?php

namespace Baka\Http\Api;

use Phalcon\Http\Response;
use Phalcon\Mvc\Controller;

/**
 * Default REST API Base Controller.
 */
class BaseController extends Controller
{
    /**
     * Set JSON response for AJAX, API request.
     *
     * @param mixed $content
     * @param integer $statusCode
     * @param string $statusMessage
     *
     * @return \Phalcon\Http\Response
     */
    public function response($content, int $statusCode = 200, string $statusMessage = 'OK'): Response
    {
        $response = [
            'statusCode' => $statusCode,
            'statusMessage' => $statusMessage,
            'content' => $content,
        ];

        if ($this->config->application->debug->logRequest) {
            $this->log->addInfo('RESPONSE', $response);
        }

        // Create a response since it's an ajax
        //in order to use the current response instead of having to create a new object , this is needed for swoole servers
        $response = $this->response ?? new Response();
        $response->setStatusCode($statusCode, $statusMessage);
        $response->setContentType('application/vnd.api+json', 'UTF-8');
        $response->setJsonContent($content);

        return $response;
    }

    /**
     * Get the unique identifier.
     *
     * @return string IP + session_id
     */
    public function getIdentifier()
    {
        return hash_hmac('crc32', str_replace('.', '', $this->request->getClientAddress() . '-' . $this->session->getId()), 'secret');
    }
}
