<?php

use \Baka\Http\QueryParser;

class HttpTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test the $_GET parser
     *
     * @return boolean
     */
    public function testParser()
    {
        //fields=id_pct,alias,latitude,longitude,category,chofer,phone,coords,last_report
        $params = [];
        $params['q'] = '(searchField1:value1,searchField2:value2)';
        $params['fields'] = '(id_pct,alias,latitude,longitude,category,chofer,phone,coords,last_report)';
        $params['limit'] = '10';
        $params['page'] = '2';
        $params['sort'] = 'id_pct|desc';

        $parse = new QueryParser($params);
        print_r($parse->request());
        die();
    }

    /**
     * this runs before everyone
     */
    protected function setUp()
    {

    }

    protected function tearDown()
    {
    }
}
