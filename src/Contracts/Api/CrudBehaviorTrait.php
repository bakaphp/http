<?php

namespace Baka\Http\Contracts\Api;

use Phalcon\Http\Response;
use Phalcon\Http\RequestInterface;
use Baka\Http\Converter\RequestUriToSql;
use Phalcon\Mvc\ModelInterface;
use ArgumentCountError;
use Phalcon\Mvc\Model\Resultset\Simple as SimpleRecords;
use PDO;
use Exception;

trait CrudBehaviorTrait
{
    /**
     * We need to find the response if you plan to use this trait.
     *
     * @param mixed $content
     * @param integer $statusCode
     * @param string $statusMessage
     * @return Response
     */
    abstract protected function response($content, int $statusCode = 200, string $statusMessage = 'OK'): Response;

    /**
    * Given a request it will give you the SQL to process.
    *
    * @param RequestInterface $request
    * @return string
    */
    protected function processRequest(RequestInterface $request): array
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
     * @param RequestInterface $request
     * @param array|object $results
     * @return array
     */
    protected function appendRelationshipsToResult(RequestInterface $request, $results)
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

    // TODO: Move it to its own class.

    /**
     * Given a process request return the records.
     *
     * @return void
     */
    protected function getRecords(array $processedRequest): array
    {
        // TODO: Create a const with these values
        $required = ['sql', 'countSql', 'bind'];

        if ($diff = array_diff($required, array_keys($processedRequest))) {
            throw new ArgumentCountError(
                sprintf(
                    'Request no processed. Missing following params : %s.',
                    implode(', ', $diff)
                    )
                );
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
     * Get the record by its primary key.
     *
     * @param mixed $id
     *
     * @throws Exception
     * @return Response
     */
    public function getById($id): Response
    {
        //find the info
        $record = $this->model::findFirstOrFail([
            'conditions' => $this->model->getPrimaryKey() . '= ?0',
            'bind' => [$id]
        ]);

        //get the results and append its relationships
        $result = $this->appendRelationshipsToResult($this->request, $record);

        return $this->response($this->processOutput($result));
    }

    /**
     * Create new record.
     *
     * @return Response
     */
    public function create(): Response
    {
        //process the input
        $result = $this->processCreate($this->request);

        return $this->response($this->processOutput($result));
    }

    /**
     * Process the create request and trecurd the boject.
     *
     * @return ModelInterface
     * @throws Exception
     */
    protected function processCreate(RequestInterface $request): ModelInterface
    {
        //process the input
        $request = $this->processInput($request->getPostData());

        $this->model->saveOrFail($request, $this->createFields);

        return $this->model;
    }

    /**
     * Update a record.
     *
     * @param mixed $id
     * @return Response
     */
    public function edit($id): Response
    {
        $record = $this->model::findFirstOrFail([
            'conditions' => $this->model->getPrimaryKey() . '= ?0',
            'bind' => [$id]
        ]);

        //process the input
        $result = $this->processEdit($this->request, $record);

        return $this->response($this->processOutput($result));
    }

    /**
     * Process the update request and return the object.
     *
     * @param RequestInterface $request
     * @param ModelInterface $record
     * @throws Exception
     * @return ModelInterface
     */
    protected function processEdit(RequestInterface $request, ModelInterface $record): ModelInterface
    {
        //process the input
        $request = $this->processInput($request->getPutData());

        $record->updateOrFail($request, $this->updateFields);

        return $record;
    }

    /**
     * Delete a Record.
     *
     * @throws Exception
     * @return Response
     */
    public function delete($id): Response
    {
        $record = $this->model::findFirstOrFail([
            'conditions' => $this->model->getPrimaryKey() . '= ?0',
            'bind' => [$id]
        ]);

        if ($this->softDelete == 1) {
            $record->softDelete();
        } else {
            $record->delete();
        }

        return $this->response(['Delete Successfully']);
    }
}
