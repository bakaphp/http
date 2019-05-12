<?php

namespace Baka\Http\Api;

use Phalcon\Http\Response;
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Baka\Http\Converter\RequestUriToSql;
use Phalcon\Mvc\ModelInterface;
use ArgumentCountError;
use Phalcon\Mvc\Model\Resultset\Simple as SimpleRecords;
use PDO;

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
     * Given a request it will give you the SQL to process.
     *
     * @param Request $request
     * @return string
     */
    protected function processRequest(Request $request): array
    {
        //parse the rquest
        $parse = new RequestUriToSql($request->getQuery(), $this->model);
        $parse->setCustomColumns($this->customColumns);
        $parse->setCustomTableJoins($this->customTableJoins);
        $parse->setCustomConditions($this->customConditions);
        $parse->appendParams($this->additionalSearchFields);
        $parse->appendCustomParams($this->additionalCustomSearchFields);
        $parse->appendRelationParams($this->additionalRelationSearchFields);

        //conver to SQL
        return $parse->convert();
    }

    /**
     * Given the results we append the relationships.
     *
     * @param Request $request
     * @param array|object $results
     * @return array
     */
    protected function appendRelationshipsToResult(Request $request, $results): array
    {
        // Relationships, but we have to change it to sparo full implementation
        if ($request->hasQuery('relationships')) {
            $relationships = $request->getQuery('relationships', 'string');

            $results = RequestUriToSql::parseRelationShips($relationships, $results);
        }

        return $results;
    }

    /**
     * Given the results we will proess the output
     * we will check if a DTO transformer exist and if so we will send it over to change it.
     *
     * @param object|array $results
     * @return void
     */
    protected function processOutput($results)
    {
        return $results;
    }

    /**
     * Given a array request from a method DTO transformet to whats is needed to
     * process it.
     *
     * @param array $request
     * @return array
     */
    protected function processInput(array $request): array
    {
        return $request;
    }

    /**
     * Given a process request return the records.
     *
     * @return void
     */
    protected function getRecords(array $processedRequest): array
    {
        $required = ['sql', 'countSql', 'bind'];

        if (count(array_intersect_key(array_flip($required), $processedRequest)) != count($required)) {
            throw new ArgumentCountError('Not a processed request missing any of the following params : SQL, CountSQL, Bind');
        }

        $results = new SimpleRecords(
            null,
            $this->model,
            $this->model->getReadConnection()->query($processedRequest['sql'], $processedRequest['bind'])
        );

        $count = $this->model->getReadConnection()->query(
            $processedRequest['countSql'],
            $processedRequest['bind']
        )->fetch(PDO::FETCH_OBJ)->total;

        return [
            'results' => $results,
            'total' => $count
        ];
    }

    /**
     * Given the model list the records based on the  filter.
     *
     * @return Response
     */
    public function index(): Response
    {
        $results = $this->processIndex();
        //return the response + transform it if needed
        return $this->response($results);
    }

    /**
     * body of the index function to simply extending methods.
     *
     * @return void
     */
    protected function processIndex()
    {
        //conver the request to sql
        $processedRequest = $this->processRequest($this->request);
        $records = $this->getRecords($processedRequest);

        //get the results and append its relationships
        $results = $this->appendRelationshipsToResult($this->request, $records['results']);

        //this means the want the response in a vuejs format
        if ($this->request->hasQuery('format')) {
            $limit = (int) $this->request->getQuery('limit', 'int', 25);

            $results = [
                'data' => $results,
                'limit' => $limit,
                'page' => $this->request->getQuery('page', 'int', 1),
                'total_pages' => ceil($records['total'] / $limit),
            ];
        }

        return $this->processOutput($results);
    }

    /**
     * Send a response when needed.
     *
     * @param mixed $content
     * @param integer $statusCode
     * @param string $statusMessage
     *
     * @return \Phalcon\Http\Response
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
