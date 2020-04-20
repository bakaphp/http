<?php

namespace Baka\Http\Contracts\Api;

use Phalcon\Http\RequestInterface;
use Baka\Http\Converter\RequestUriToElasticSearch;
use Baka\Elasticsearch\Client;

trait CrudElasticBehaviorTrait
{
    use CrudCustomFieldsBehaviorTrait;

    /**
     * We dont need you in elastic
     *
     * @param RequestInterface $request
     * @param array|object $results
     * @return array
     */
    protected function appendRelationshipsToResult(RequestInterface $request, $results)
    {
        return $results;
    }

    /**
    * Given a request it will give you the SQL to process.
    *
    * @param RequestInterface $request
    * @return string
    */
    protected function processRequest(RequestInterface $request): array
    {
        //parse the rquest
        $parse = new RequestUriToElasticSearch($request->getQuery(), $this->model);
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

        $client = new Client('http://' . current($this->config->elasticSearch['hosts']));
        $results = $client->findBySql($processedRequest['sql']);

        return [
            'results' => $results,
            'total' => 0 //@todo fix this
        ];
    }
}
