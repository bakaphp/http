<?php

use Baka\Http\Converter\RequestUriToSql;
use Test\Model\Leads;
use Phalcon\Mvc\Model\Resultset\Simple as SimpleRecords;

class UriToSqlTest extends PhalconUnitTestCase
{
    /**
     * Test a normal query with no conditional.
     *
     * @return boolean
     */
    public function testSimpleQuery()
    {
        $params = [];
        $params['limit'] = '10';
        $params['page'] = '1';
        $params['sort'] = 'id|desc';

        $leads = new Leads();
        $requestToSql = new RequestUriToSql($params, $leads);
        $request = $requestToSql->convert();
        //print_r('3');
        //throw new Exception('3');
        $results = (new SimpleRecords(null, $leads, $leads->getReadConnection()->query($request['sql'], $request['bind'])));
        $count = $leads->getReadConnection()->query($request['countSql'], $request['bind'])->fetch(\PDO::FETCH_OBJ)->total;

        //confirme records
        foreach ($results as $result) {
            $this->assertTrue($result->id > 0);
        }

        $this->assertEquals(3, count($results->toArray()));
        $this->assertEquals(3, $count);
    }

    /**
     * Test normal columns.
     *
     * @return void
     */
    public function testQueryColumns()
    {
        $params = [];
        $params['columns'] = '(users_id, firstname, lastname, is_deleted, is_Active, leads_owner_id)';
        $params['limit'] = '10';
        $params['page'] = '1';
        $params['sort'] = 'id|desc';

        $leads = new Leads();
        $requestToSql = new RequestUriToSql($params, $leads);
        $request = $requestToSql->convert();

        $results = (new SimpleRecords(null, $leads, $leads->getReadConnection()->query($request['sql'], $request['bind'])));
        $count = $leads->getReadConnection()->query($request['countSql'], $request['bind'])->fetch(\PDO::FETCH_OBJ)->total;

        //confirme records
        foreach ($results as $result) {
            //doesnt existe id
            $this->assertFalse(isset($result->id));
        }

        $this->assertEquals(3, count($results->toArray()));
        $this->assertEquals(3, $count);
    }

    /**
     * Test normal conditions.
     *
     * @return void
     */
    public function testQueryConditionals()
    {
        $params = [];
        $params['q'] = '(is_deleted:0)';
        $params['limit'] = '10';
        $params['page'] = '1';
        $params['sort'] = 'id|desc';

        $leads = new Leads();
        $requestToSql = new RequestUriToSql($params, $leads);
        $request = $requestToSql->convert();

        $results = (new SimpleRecords(null, $leads, $leads->getReadConnection()->query($request['sql'], $request['bind'])));
        $count = $leads->getReadConnection()->query($request['countSql'], $request['bind'])->fetch(\PDO::FETCH_OBJ)->total;

        //confirme records
        foreach ($results as $result) {
            $this->assertTrue($result->id > 0);
        }

        $this->assertEquals(2, count($results->toArray()));
        $this->assertEquals(2, $count);
    }

    /**
     * Test normal conditions.
     *
     * @return void
     */
    public function testQueryConditionalsWithAnd()
    {
        $params = [];
        $params['q'] = '(is_deleted:0,is_active:1)';
        $params['limit'] = '10';
        $params['page'] = '1';
        $params['sort'] = 'id|desc';

        $leads = new Leads();
        $requestToSql = new RequestUriToSql($params, $leads);
        $request = $requestToSql->convert();

        $results = (new SimpleRecords(null, $leads, $leads->getReadConnection()->query($request['sql'], $request['bind'])));
        $count = $leads->getReadConnection()->query($request['countSql'], $request['bind'])->fetch(\PDO::FETCH_OBJ)->total;

        //confirme records
        foreach ($results as $result) {
            $this->assertTrue($result->id > 0);
        }

        $this->assertEquals(2, count($results->toArray()));
        $this->assertEquals(2, $count);
    }

    /**
     * Test conditional with an OR.
     *
     * @return void
     */
    public function testQueryConditionalsWithOr()
    {
        $params = [];
        $params['q'] = '(is_deleted:0;companies_id:2)';
        $params['limit'] = '10';
        $params['page'] = '1';
        $params['sort'] = 'id|desc';

        $leads = new Leads();
        $requestToSql = new RequestUriToSql($params, $leads);

        $request = $requestToSql->convert();
        $results = (new SimpleRecords(null, $leads, $leads->getReadConnection()->query($request['sql'], $request['bind'])));
        $count = $leads->getReadConnection()->query($request['countSql'], $request['bind'])->fetch(\PDO::FETCH_OBJ)->total;

        //confirme records
        foreach ($results as $result) {
            $this->assertTrue($result->id > 0);
        }

        $this->assertEquals(2, count($results->toArray()));
        $this->assertEquals(2, $count);
    }

    /**
     * Test with and and Or.
     *
     * @return void
     */
    public function testQueryConditionalsWithAndOr()
    {
        $params = [];
        $params['q'] = '(is_deleted:0,is_active:1,leads_owner_id~0,id>0;created_at>0)';
        $params['limit'] = '10';
        $params['page'] = '1';
        $params['sort'] = 'id|desc';

        $leads = new Leads();
        $requestToSql = new RequestUriToSql($params, $leads);
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

    /**
     * Test with and and Or.
     *
     * @return void
     */
    public function testQueryConditionalsLimit()
    {
        $params = [];
        $params['q'] = '(is_deleted:0,is_active:1,leads_owner_id~0,id>0;created_at>0)';
        $params['limit'] = '1';
        $params['page'] = '1';
        $params['sort'] = 'id|desc';

        $leads = new Leads();
        $requestToSql = new RequestUriToSql($params, $leads);
        $request = $requestToSql->convert();

        $results = (new SimpleRecords(null, $leads, $leads->getReadConnection()->query($request['sql'], $request['bind'])));
        $count = $leads->getReadConnection()->query($request['countSql'], $request['bind'])->fetch(\PDO::FETCH_OBJ)->total;

        //confirme records
        foreach ($results as $result) {
            $this->assertTrue($result->id > 0);
        }

        $this->assertEquals(1, count($results->toArray()));

        //@todo check if the limit as to be thte total amount of the table or from the specific query
        $this->assertEquals(3, $count);
    }

    /**
     * Do relationship queries.
     *
     * @return void
     */
    public function testQueryConditionalsWithRelationships()
    {
    }

    /**
     * Do custom fields query.
     *
     * @return void
     */
    public function testQueryConditionalsWithCutomFields()
    {
    }

    /**
     * Specify a custom column.
     *
     * @return void
     */
    public function testQueryConditionalsWithCustomColumns()
    {
        $params = [];
        $params['q'] = '(is_deleted:0,is_active:1,leads_owner_id~0,id>0;created_at>0)';
        $params['columns'] = '(id)';
        $params['limit'] = '10';
        $params['page'] = '1';
        $params['sort'] = 'id|desc';

        $leads = new Leads();
        $requestToSql = new RequestUriToSql($params, $leads);
        $requestToSql->setCustomColumns('companies_id');
        $request = $requestToSql->convert();

        $results = (new SimpleRecords(null, $leads, $leads->getReadConnection()->query($request['sql'], $request['bind'])));
        $count = $leads->getReadConnection()->query($request['countSql'], $request['bind'])->fetch(\PDO::FETCH_OBJ)->total;

        //confirme records
        foreach ($results as $result) {
            $this->assertTrue(isset($result->companies_id));
            $this->assertFalse(isset($result->users_id));
        }

        $this->assertEquals(3, count($results->toArray()));
        $this->assertEquals(3, $count);
    }

    /**
     * Specify a custom table.
     *
     * @return void
     */
    public function testQueryConditionalsWithCustomTable()
    {
        $params = [];
        $params['q'] = '(is_deleted:0,is_active:1,leads_owner_id~0,id>0;created_at>0)';
        $params['limit'] = '10';
        $params['page'] = '1';
        $params['sort'] = 'id|desc';

        $leads = new Leads();
        $requestToSql = new RequestUriToSql($params, $leads);

        //join with the same table
        $requestToSql->setCustomTableJoins(' , leads as b');
        $request = $requestToSql->convert();

        $results = (new SimpleRecords(null, $leads, $leads->getReadConnection()->query($request['sql'], $request['bind'])));
        $count = $leads->getReadConnection()->query($request['countSql'], $request['bind'])->fetch(\PDO::FETCH_OBJ)->total;

        //confirme records
        foreach ($results as $result) {
            $this->assertTrue(isset($result->companies_id));
            $this->assertTrue(isset($result->users_id));
        }

        $this->assertEquals(9, count($results->toArray()));
        $this->assertEquals(9, $count);
    }

    /**
     * Do custom quer contiioanals.
     *
     * @return void
     */
    public function testQueryConditionalsWithCustomCondition()
    {
        $params = [];
        $params['q'] = '(is_deleted:0,is_active:1)';
        $params['limit'] = '10';
        $params['page'] = '1';
        $params['sort'] = 'id|desc';

        $leads = new Leads();
        $requestToSql = new RequestUriToSql($params, $leads);

        $requestToSql->setCustomConditions('AND leads_owner_id != 1');
        $request = $requestToSql->convert();

        $results = (new SimpleRecords(null, $leads, $leads->getReadConnection()->query($request['sql'], $request['bind'])));
        $count = $leads->getReadConnection()->query($request['countSql'], $request['bind'])->fetch(\PDO::FETCH_OBJ)->total;

        $this->assertTrue(empty($results->toArray()));
        $this->assertEquals(0, $count);
    }
}
