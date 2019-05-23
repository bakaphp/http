<?php

namespace Baka\Http\Api;

use Phalcon\Http\Response;
use Phalcon\Mvc\Controller;
use Baka\Http\Contracts\Api\CrudBehaviorTrait;

/**
 * Default REST API Base Controller.
 */
class BaseController extends Controller
{
    /**
     * Soft delete option, default 1.
     *
     * @var int
     */
    public $softDelete = 0;

    /**
     * fields we accept to create.
     *
     * @var array
     */
    protected $createFields = [];

    /**
     * fields we accept to update.
     *
     * @var array
     */
    protected $updateFields = [];

    /**
     * PhalconPHP Model.
     *
     * @var object
     */
    public $model;

    /**
     * @param array $normalSearchFields
     */
    protected $additionalSearchFields = [];

    /**
     * @param array $customSearchFields
     */
    protected $additionalCustomSearchFields = [];

    /**
     * @param array $relationSearchFields
     */
    protected $additionalRelationSearchFields = [];

    /**
     * Specify any customf columns.
     *
     * @var string
     */
    protected $customColumns = null;

    /**
     * Specify any custom join tables.
     *
     * @var string
     */
    protected $customTableJoins = null;

    /**
     * Specify any custom conditionals we need.
     *
     * @var string
     */
    protected $customConditions = null;

    /**
     * Send a response when needed.
     *
     * @param mixed $content
     * @param integer $statusCode
     * @param string $statusMessage
     *
     * @return Response
     *
     */
    protected function response($content, int $statusCode = 200, string $statusMessage = 'OK'): Response
    {
        $response = [
            'statusCode' => $statusCode,
            'statusMessage' => $statusMessage,
            'content' => $content,
        ];

        if ($this->config->application->debug->logRequest) {
            $this->log->addInfo('RESPONSE', $response);
        }

        //in order to use the current response instead of having to create a new object , this is needed for swoole servers
        //$response = $this->response ?? new Response();
        $this->response->setStatusCode($statusCode, $statusMessage);
        $this->response->setContentType('application/vnd.api+json', 'UTF-8');
        $this->response->setJsonContent($content);

        return $this->response;
    }
}
