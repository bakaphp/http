<?php

namespace Baka\Http\Rest;

use Exception;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use \Baka\Http\QueryParser;
use \Phalcon\Mvc\Controller;

/**
 * Default REST API Base Controller
 */
class CrudController extends BaseController
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
     * List of business
     *
     * @method GET
     * @url /v1/business
     *
     * @todo  add security to query strings with bidn params
     * @return Phalcon\Http\Response
     */
    public function index($id = null)
    {
        // Support for resource/identifier/resource
        if ($id != null) {
            return $this->getById($id);
        }

        //parse the rquest
        $parse = new QueryParser($this->request->getQuery());
        $params = $parse->request();

        $results = $this->model->find($params);

        //this means the want the response in a vuejs format
        if ($this->request->hasQuery('format')) {

            $paginator = new PaginatorModel([
                "data" => $results,
                "limit" => $this->request->getQuery('limit', 'int'),
                "page" => $this->request->getQuery('page', 'int'),
            ]);

            // Get the paginated results
            $results = (array) $paginator->getPaginate();
        }

        return $this->response($results);
    }

    /**
     * Add a new item
     *
     * @method POST
     * @url /v1/business
     *
     * @return Phalcon\Http\Response
     */
    public function create($id = null)
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
     * @method GET
     * @url /v1/business/{id}
     *
     * @return Phalcon\Http\Response
     */
    public function getById(int $id)
    {
        //find the info
        $objectInfo = $this->model->findFirst([
            'id = ?0 AND is_deleted = 0',
            'bind' => [$id],
        ]);

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
    public function edit(int $id)
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
    public function delete(int $id)
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
