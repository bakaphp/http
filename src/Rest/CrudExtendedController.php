<?php

namespace Baka\Http\Rest;

use Baka\Http\QueryParserCustomFields;
use Baka\Http\QueryParser;
use Exception;
use Phalcon\Mvc\Model\Resultset\Simple as SimpleRecords;
use Phalcon\Paginator\Adapter\NativeArray as PaginatorModel;

/**
 * Default REST API Base Controller
 */
class CrudExtendedController extends CrudController
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

        $results = (new SimpleRecords(null, $this->model, $this->model->getReadConnection()->query($params['sql'], $params['bind'])));

        //navigate los records
        $newResult = [];

        if ($this->request->hasQuery('relationships')) {
            $relationships = $this->request->getQuery('relationships', 'string');

            $newResult = QueryParser::parseRelationShips($relationships, $newResult);
        }

        //this means the want the response in a vuejs format
        if ($this->request->hasQuery('format')) {
            unset($params['limit']);
            unset($params['offset']);

            $newResult =[
              "data" => $newResult,
              "limit" => $this->request->getQuery('limit', 'int'),
              "page" => $this->request->getQuery('page', 'int'),
              'total_pages' => 100,
          ];
        }

        return $this->response($newResult);
    }
}
