<?php

namespace Baka\Http\Rest;

use Baka\Http\QueryParserCustomFields;
use Baka\Http\QueryParser;
use Phalcon\Mvc\Model\Resultset\Simple as SimpleRecords;

/**
 * Default REST API Base Controller
 */
class CrudExtendedController extends CrudController
{

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
