<?php

namespace Baka\Http\Rest;

use Baka\Http\QueryParserCustomFields;
use Baka\Http\QueryParser;
use Phalcon\Http\Response;
use Phalcon\Mvc\Model\Resultset\Simple as SimpleRecords;

/**
 * Default REST API Base Controller
 */
class CrudExtendedController extends BaseController
{
    /**
     * Soft delete option, default 1
     *
     * @var int
     */
    public $softDelete = 0;

    /**
     * fields we accept to create
     *
     * @var array
     */
    protected $createFields = [];

    /**
     * fields we accept to update
     *
     * @var array
     */
    protected $updateFields = [];

    /**
     * the model that interacts witht his controler
     *
     * @var array
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
     * List of business
     *
     * @method GET
     * url /v1/business
     *
     * @param int $id
     * @return \Phalcon\Http\Response
     */
    public function index($id = null): Response
    {
        if ($id != null) {
            return $this->getById($id);
        }

        //parse the rquest
        $parse = new QueryParserCustomFields($this->request->getQuery(), $this->model);
        $parse->appendParams($this->additionalSearchFields);
        $parse->appendCustomParams($this->additionalCustomSearchFields);
        $parse->appendRelationParams($this->additionalRelationSearchFields);
        $params = $parse->request();

        $results = (new SimpleRecords(null, $this->model, $this->model->getReadConnection()->query($params['sql'], $params['bind'])));
        $count = $this->model->getReadConnection()->query($params['countSql'], $params['bind'])->fetch(\PDO::FETCH_OBJ)->total;

        // Relationships, but we have to change it to sparo full implementation
        if ($this->request->hasQuery('relationships')) {
            $relationships = $this->request->getQuery('relationships', 'string');

            $results = QueryParser::parseRelationShips($relationships, $results);
        }

        $newResult = $results;

        //this means the want the response in a vuejs format
        if ($this->request->hasQuery('format')) {
            $limit = (int) $this->request->getQuery('limit', 'int', 25);

            $newResult =[
                'data' => $results,
                'limit' => $limit,
                'page' => $this->request->getQuery('page', 'int', 1),
                'total_pages' => ceil($count / $limit),
            ];
        }

        return $this->response($newResult);
    }

    /**
     * Add a new item
     *
     * @method POST
     * @url /v1/business
     *
     * @return Phalcon\Http\Response
     */
    public function create(): Response
    {
        //try to save all the fields we allow
        if ($this->model->save($this->request->getPost(), $this->createFields)) {
            return $this->response($this->model->toArray());
        } else {
            //if not thorw exception
            throw new Exception($this->model->getMessages()[0]);
        }
    }

    /**
     * get item
     *
     * @param mixed $id
     *
     * @method GET
     * @url /v1/business/{id}
     *
     * @return Phalcon\Http\Response
     */
    public function getById($id): Response
    {
        //find the info
        $objectInfo = $this->model->findFirst([
            'id = ?0 AND is_deleted = 0',
            'bind' => [$id],
        ]);

        //get relationship
        if ($this->request->hasQuery('relationships')) {
            $relationships = $this->request->getQuery('relationships', 'string');

            $objectInfo = QueryParser::parseRelationShips($relationships, $objectInfo);
        }

        if ($objectInfo) {
            return $this->response($objectInfo);
        } else {
            throw new Exception('Record not found');
        }
    }

    /**
     * Update a new Entry
     *
     * @method PUT
     * @url /v1/business/{id}
     *
     * @return Phalcon\Http\Response
     */
    public function edit($id): Response
    {
        if ($objectInfo = $this->model->findFirst($id)) {
            //update
            if ($objectInfo->update($this->request->getPut(), $this->updateFields)) {
                return $this->response($objectInfo->toArray());
            } else {
                //didnt work
                throw new Exception($objectInfo->getMessages()[0]);
            }
        } else {
            throw new Exception("Record not found");
        }
    }

    /**
     * delete a new Entry
     *
     * @method DELETE
     * @url /v1/business/{id}
     *
     * @return Phalcon\Http\Response
     */
    public function delete($id): Response
    {
        if ($objectInfo = $this->model->findFirst($id)) {
            if ($this->softDelete == 1) {
                $objectInfo->softDelete();
            } else {
                $objectInfo->delete();
            }

            return $this->response(['Delete Successfully']);
        } else {
            throw new Exception('Record not found');
        }
    }
}
