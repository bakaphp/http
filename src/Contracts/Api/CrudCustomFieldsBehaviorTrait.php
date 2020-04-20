<?php

namespace Baka\Http\Contracts\Api;

use Phalcon\Http\RequestInterface;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\Resultset\Simple as SimpleRecords;
use PDO;
use Exception;
use Baka\Http\Converter\RequestUriToElasticSearch;

trait CrudCustomFieldsBehaviorTrait
{
    use CrudBehaviorTrait {
        CrudBehaviorTrait::processCreate as processCreateParent;
        CrudBehaviorTrait::processEdit as processEditParent;
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
    
            $results = is_object($results) ? RequestUriToElasticSearch::parseRelationShips($relationships, $results) : $results;
        }

        return $results;
    }

    /**
     * Process output
     *
     * @param mixed $results
     * @return mixed
     */
    protected function processOutput($results)
    {
        return is_object($results) ? $results->toFullArray() : $results;
    }

    /**
     * Process the create request and trecurd the boject.
     *
     * @return ModelInterface
     * @throws Exception
     */
    protected function processCreate(RequestInterface $request): ModelInterface
    {
        //set the custom fields to create
        $this->model->setCustomFields($request->getPostData());

        $this->processCreateParent($request);

        return $this->model;
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
        //set the custom fields to update
        $record->setCustomFields($request->getPutData());

        $record = $this->processEditParent($request, $record);

        return $record;
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

        //navigate los records
        $newResult = [];
        $relationships = $this->request->getQuery('relationships', 'string');

        foreach ($results as $key => $record) {
            //field the object
            foreach ($record->getAllCustomFields() as $key => $value) {
                $record->{$key} = $value;
            }

            /**
             * @todo clean this up later on regarding custom fields SQL
             */
            $newResult[] = !$relationships ? $record->toFullArray() : RequestUriToElasticSearch::parseRelationShips($relationships, $record);
        }

        unset($results);

        return [
            'results' => $newResult,
            'total' => $count
        ];
    }
}
