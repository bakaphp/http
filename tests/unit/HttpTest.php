<?php

use Baka\Http\Transformers\QueryParser;

class HttpTest extends PhalconUnitTestCase
{
    use \Baka\Http\Api\SdkTrait;

    /**
     * Test the $_GET parser.
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
        $request = $parse->request();

        //confirm the keys we are expecting from the parser
        //@todo look for ways to validate the SQL directly with a local database
        $this->assertArrayHasKey('bind', $request);
        $this->assertArrayHasKey('columns', $request);
        $this->assertArrayHasKey('order', $request);
        $this->assertArrayHasKey('limit', $request);
        $this->assertArrayHasKey('offset', $request);
    }

    /**
     * Test for the SDK.
     *
     * @deprecated version
     * @return void
     */
    public function testSdk()
    {
        $userData = ['user_id' => 1, 'agency' => 2];
        $this->setApiVersion(getenv('API_VERSION'));
        $this->setApiPublicKey(getenv('API_PUBLIC_KEY'));
        $this->setApiPrivateKey(getenv('API_PRIVATE_KEY'));
        $this->setApiHeaders([
            'users-id' => $userData['user_id'],
            'agencies-id' => $userData['agency'],
        ]);
    }
}
