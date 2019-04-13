<?php

use Baka\Http\Converter\RequestUriToSql;
use Test\Model\Leads;
use Phalcon\Mvc\Model\Resultset\Simple as SimpleRecords;
use Baka\Http\Converter\RequestUriToElasticSearch;

class UriToElasticSqlTest extends PhalconUnitTestCase
{
    /**
     * Test a normal query with no conditional.
     *
     * @return boolean
     */
    public function testSimpleQuery()
    {
        return ;
        //$params['q'] = ('is_delete:0');
        $params['cq'] = ('branches.dfadfadf.is_deleted:0;subscriptions.plan.id~3');
        $params['limit'] = '10';
        $params['page'] = '1';
        $params['sort'] = 'id|desc';

        $leads = new Leads();
        $requestToSql = new RequestUriToElasticSearch($params, $leads);
        $request = $requestToSql->convert();

        $results = (new SimpleRecords(null, $leads, $leads->getReadConnection()->query($request['sql'], $request['bind'])));
        $count = $leads->getReadConnection()->query($request['countSql'], $request['bind'])->fetch(\PDO::FETCH_OBJ)->total;

        //confirme records
        foreach ($results as $result) {
            $this->assertTrue($result->id > 0);
        }

        $this->assertEquals(3, count($results->toArray()));
        $this->assertEquals(3, $count);
    }
}
