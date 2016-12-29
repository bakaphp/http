<?php

namespace Baka\Http\Rest;

use Baka\Http\QueryParser;
use Exception;

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
     * @url /v1/business
     * @return response
     */
    public function index($id = null)
    {
        if ($id != null) {
            return $this->getById($id);
        }

        //parse the rquest
        $parse = new QueryParser($this->request->getQuery());
        $params = $parse->request();
        $newRecordList = [];

        // If a filter is sent we take it and built a query
        $recordList = $this->model->find($params);

        //navigate los records
        foreach ($recordList as $key => $record) {

            //turn record into array
            $newRecordList[$key] = $record->toFullArray();

            //get the custom fields for this type of record
            //$newRecordList[$key]['custom_fields'] = $record->getAllCustomFields();

        }

        return $this->response($newRecordList);
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
     * @url /v1/business
     *
     * @return Phalcon\Http\Response
     */
    public function create()
    {
        $data = $this->request->getPost();

        //we need even if empty the custome fields
        if (empty($this->request->getPost())) {
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
     * @url /v1/business/{id}
     *
     * @return Phalcon\Http\Response
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
     * @url /v1/business/{id}
     *
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
