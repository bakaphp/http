<?php

namespace Baka\Http\Rest;

use Baka\Http\QueryParserCustomFields;
use Exception;
use Phalcon\Mvc\Model\Resultset\Simple as SimpleRecords;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;

/**
 * Default REST API Base Controller
 */
class CrudCustomFieldsController extends CrudController
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
     * @var string
     */
    public $model;

    /**
     * the custom model where the info of the custom fields is saved
     *
     * @var string
     */
    public $customModel;

    /**
     * List of business
     *
     * @method GET
     * url /v1/business
     *
     * @param int $id
     * @return \Phalcon\Http\Response
     */
    public function index($id = null)
    {
        if ($id != null) {
            return $this->getById($id);
        }

        //parse the rquest
        $parse = new QueryParserCustomFields($this->request->getQuery(), $this->model);
        $params = $parse->request();

        $recordList = (new SimpleRecords(null, $this->model, $this->model->getReadConnection()->query($params['sql'], $params['bind'])));

        //navigate los records
        $newResult = [];
        foreach ($recordList as $key => $record) {

            //field the object
            foreach ($record->getAllCustomFields() as $key => $value) {
                $record->{$key} = $value;
            }
            $newResult[] = $record->toFullArray();
        }

        unset($recordList);

        //this means the want the response in a vuejs format
        if ($this->request->hasQuery('format')) {
            $paginator = new PaginatorModel([
                "data" => $newResult,
                "limit" => $this->request->getQuery('limit', 'int'),
                "page" => $this->request->getQuery('page', 'int'),
            ]);

            // Get the paginated results
            $newResult = (array) $paginator->getPaginate();
        }

        return $this->response($newResult);
    }

    /**
     * get item
     *
     * @method GET
     * url /v1/business/{id}
     *
     * @param int $id
     * @return \Phalcon\Http\Response
     */
    public function getById(int $id)
    {

        //find the info
        if ($objectInfo = $this->model->findFirst($id)) {
            $newRecordList = $objectInfo->toFullArray();

            return $this->response($newRecordList);
        } else {
            throw new Exception(_("Record not found"));
        }
    }

    /**
     * Add a new item
     *
     * @method POST
     * url /v1/business
     *
     * @return \Phalcon\Http\Response
     */
    public function create()
    {
        $data = $this->request->getPost();

        //we need even if empty the custome fields
        if (empty($data)) {
            throw new Exception("No valie info sent");
        }

        //set the custom fields to update
        $this->model->setCustomFields($this->request->getPost());

        //try to save all the fields we allow
        if ($this->model->save($data, $this->createFields)) {
            return $this->getById($this->model->id);
        } else {

            //if not thorw exception
            throw new Exception($this->model->getMessages()[0]);
        }
    }

    /**
     * Update a new Entry
     *
     * @method PUT
     * url /v1/business/{id}
     *
     * @param int $id
     * @return \Phalcon\Http\Response
     */
    public function edit(int $id)
    {
        if ($objectInfo = $this->model->findFirst($id)) {
            $data = $this->request->getPut();

            if (empty($data)) {
                throw new Exception("No valie info sent");
            }

            //set the custom fields to update
            $objectInfo->setCustomFields($data);

            //update
            if ($objectInfo->update($data, $this->updateFields)) {
                return $this->getById($id);
            } else {
                //didnt work
                throw new Exception($objectInfo->getMessages()[0]);
            }
        } else {
            throw new Exception(_("Record not found"));
        }
    }

    /**
     * delete a new Entry
     *
     * @method DELETE
     * url /v1/business/{id}
     *
     * @param int $id
     * @return \Phalcon\Http\Response
     * @throws \Exception
     */
    public function delete(int $id)
    {
        if ($objectInfo = $this->model->findFirst($id)) {
            if ($objectInfo->delete() === false) {
                foreach ($objectInfo->getMessages() as $message) {
                    throw new Exception($message);
                }
            }

            return $this->response(['Delete Successfully']);
        } else {
            throw new Exception(_("Record not found"));
        }
    }
}
