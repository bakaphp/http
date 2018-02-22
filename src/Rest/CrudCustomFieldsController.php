<?php

namespace Baka\Http\Rest;

use Baka\Http\QueryParserCustomFields;
use Exception;
use Phalcon\Http\Response;
use Phalcon\Mvc\Model\Resultset\Simple as SimpleRecords;

/**
 * Default REST API Base Controller
 */
class CrudCustomFieldsController extends CrudExtendedController
{
    /**
     * List items.
     *
     * @method GET
     * url /v1/controller
     *
     * @param mixed $id
     *
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

        $recordList = (new SimpleRecords(null, $this->model, $this->model->getReadConnection()->query($params['sql'], $params['bind'])));
        $count = $this->model->getReadConnection()->query($params['countSql'], $params['bind'])->fetch(\PDO::FETCH_OBJ)->total;

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
            $limit = (int) $this->request->getQuery('limit', 'int', 25);

            $newResult = [
                'data' => $newResult,
                'limit' => $limit,
                'page' => $this->request->getQuery('page', 'int', 1),
                'total_pages' => ceil($count / $limit)
            ];
        }

        return $this->response($newResult);
    }

    /**
     * Get item.
     *
     * @method GET
     * url /v1/controller/{id}
     *
     * @param mixed $id
     *
     * @return \Phalcon\Http\Response
     * @throws \Exception
     */
    public function getById($id): Response
    {
        //find the info
        if ($objectInfo = $this->model->findFirst($id)) {
            $newRecordList = $objectInfo->toFullArray();

            return $this->response($newRecordList);
        } else {
            throw new Exception(_('Record not found'));
        }
    }

    /**
     * Add a new item.
     *
     * @method POST
     * url /v1/controller
     *
     * @return \Phalcon\Http\Response
     * @throws \Exception
     */
    public function create(): Response
    {
        $data = $this->request->getPost();

        //we need even if empty the custome fields
        if (empty($data)) {
            throw new Exception('No valie info sent');
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
     * Update an item.
     *
     * @method PUT
     * url /v1/controller/{id}
     *
     * @param mixed $id
     *
     * @return \Phalcon\Http\Response
     * @throws \Exception
     */
    public function edit($id): Response
    {
        if ($objectInfo = $this->model->findFirst($id)) {
            $data = $this->request->getPut();

            if (empty($data)) {
                throw new Exception('No valid data sent.');
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
            throw new Exception(_('Record not found'));
        }
    }

    /**
     * Delete an item.
     *
     * @method DELETE
     * url /v1/controller/{id}
     *
     * @param mixed $id
     *
     * @return \Phalcon\Http\Response
     * @throws \Exception
     */
    public function delete($id): Response
    {
        if ($objectInfo = $this->model->findFirst($id)) {
            if ($objectInfo->delete() === false) {
                foreach ($objectInfo->getMessages() as $message) {
                    throw new Exception($message);
                }
            }

            return $this->response(['Delete Successfully']);
        } else {
            throw new Exception(_('Record not found'));
        }
    }
}
