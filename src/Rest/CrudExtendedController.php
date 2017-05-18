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

        //relationships , but we have to change it to sparo full implementation
        if ($this->request->hasQuery('relationships')) {
            $relationships = $this->request->getQuery('relationships', 'string');

            $results = QueryParser::parseRelationShips($relationships, $results);
        }

        //this means the want the response in a vuejs format
        if ($this->request->hasQuery('format')) {
            unset($params['limit']);
            unset($params['offset']);

            $newResult =[
              "data" => $results,
              "limit" => $this->request->getQuery('limit', 'int'),
              "page" => $this->request->getQuery('page', 'int'),
              'total_pages' => 100,
          ];
        }

        return $this->response($newResult);
    }
}
