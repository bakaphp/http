<?php

use Baka\Http\Converter\RequestUriToSql;
use Phalcon\Mvc\Model\Resultset\Simpl as SimpleRecords;
use Baka\Http\Converter\RequestUriToElasticSearch;
use Baka\Elasticsearch\IndexBuilderStructure;
use Test\Indices\Leads;
use Baka\Elasticsearch\Client;

class UriToElasticSqlTest extends PhalconUnitTestCase
{
    /**
     * Create the index if it doesnt exist to run some test.
     *
     * @return void
     */
    public function testInitElastic()
    {
        $elasticsearch = new IndexBuilderStructure();
        if (!$elasticsearch->existIndices(Leads::class)) {
            $elasticsearch->createIndices(Leads::class);
            $lead = new Leads();
            $elasticsearch->indexDocument($lead);
            $lead->setId(2);
            $elasticsearch->indexDocument($lead);
            $lead->setId(3);
            $elasticsearch->indexDocument($lead);
        }
    }

    /**
     * Test a normal query with no conditional.
     *
     * @return boolean
     */
    public function testSimpleQuery()
    {
        //create the index first

        $params = [];
        //$params['q'] = ('is_deleted:0');
        //$params['cq'] = ('company.name:mc%');
        //$params['cq'] = ('eventsversions.events_types_id:1;participantsprograms.programs_id:2,custom_fields.sexo:f,companiesoffices.districts_id:1;companiesoffices.countries_id:2,eventsversionsparticipants.is_deleted:0|1,eventsversionsparticipants.eventsversionsdates.event_date>2019-04-01,eventsversionsparticipants.eventsversionsdates.event_date<2019-04-14');
        $params['limit'] = '10';
        $params['page'] = '1';
        $params['sort'] = 'id|desc';

        $leads = new Leads();
        $requestToSql = new RequestUriToElasticSearch($params, $leads);
        $request = $requestToSql->convert();

        $client = new Client('http://' . $this->_config->elasticSearch['hosts'][0]);
        $results = $client->findBySql($request['sql']);

        //confirme records
        foreach ($results as $result) {
            $this->assertTrue($result['id'] > 0);
        }

        $this->assertEquals(3, count($results));
    }

    /**
     * Test a normal query with no conditional.
     *
     * @return boolean
     */
    public function testQueryColumns()
    {
        //create the index first

        $params = [];
        $params['columns'] = '(id, users_id, firstname, lastname, is_deleted)';
        //$params['q'] = ('is_deleted:0');
        //$params['cq'] = ('company.name:mc%');
        //$params['cq'] = ('eventsversions.events_types_id:1;participantsprograms.programs_id:2,custom_fields.sexo:f,companiesoffices.districts_id:1;companiesoffices.countries_id:2,eventsversionsparticipants.is_deleted:0|1,eventsversionsparticipants.eventsversionsdates.event_date>2019-04-01,eventsversionsparticipants.eventsversionsdates.event_date<2019-04-14');
        $params['limit'] = '10';
        $params['page'] = '1';
        $params['sort'] = 'id|desc';

        $leads = new Leads();
        $requestToSql = new RequestUriToElasticSearch($params, $leads);
        $request = $requestToSql->convert();

        $client = new Client('http://' . $this->_config->elasticSearch['hosts'][0]);
        $results = $client->findBySql($request['sql']);

        //confirme records
        foreach ($results as $result) {
            $this->assertTrue($result['users_id'] > 0);
            $this->assertTrue(!empty($result['firstname']));
            $this->assertTrue(!empty($result['lastname']));
            $this->assertTrue($result['is_deleted'] == 0);
        }

        $this->assertEquals(3, count($results));
    }

    /**
     * Test a normal query with no conditional.
     *
     * @return boolean
     */
    public function testQueryConditionals()
    {
        //create the index first

        $params = [];
        $params['q'] = ('is_deleted:0');
        //$params['cq'] = ('company.name:mc%');
        //$params['cq'] = ('eventsversions.events_types_id:1;participantsprograms.programs_id:2,custom_fields.sexo:f,companiesoffices.districts_id:1;companiesoffices.countries_id:2,eventsversionsparticipants.is_deleted:0|1,eventsversionsparticipants.eventsversionsdates.event_date>2019-04-01,eventsversionsparticipants.eventsversionsdates.event_date<2019-04-14');
        $params['limit'] = '10';
        $params['page'] = '1';
        $params['sort'] = 'id|desc';

        $leads = new Leads();
        $requestToSql = new RequestUriToElasticSearch($params, $leads);
        $request = $requestToSql->convert();

        $client = new Client('http://' . $this->_config->elasticSearch['hosts'][0]);
        $results = $client->findBySql($request['sql']);

        //confirme records
        foreach ($results as $result) {
            $this->assertTrue($result['id'] > 0);
        }

        $this->assertEquals(3, count($results));
    }

    /**
     * Test a normal query with no conditional.
     *
     * @return boolean
     */
    public function testQueryConditionalsWithAnd()
    {
        //create the index first

        $params = [];
        $params['q'] = ('is_deleted:0,firstname:max%');
        //$params['cq'] = ('company.name:mc%');
        //$params['cq'] = ('eventsversions.events_types_id:1;participantsprograms.programs_id:2,custom_fields.sexo:f,companiesoffices.districts_id:1;companiesoffices.countries_id:2,eventsversionsparticipants.is_deleted:0|1,eventsversionsparticipants.eventsversionsdates.event_date>2019-04-01,eventsversionsparticipants.eventsversionsdates.event_date<2019-04-14');
        $params['limit'] = '10';
        $params['page'] = '1';
        $params['sort'] = 'id|desc';

        $leads = new Leads();
        $requestToSql = new RequestUriToElasticSearch($params, $leads);
        $request = $requestToSql->convert();

        $client = new Client('http://' . $this->_config->elasticSearch['hosts'][0]);
        $results = $client->findBySql($request['sql']);

        //confirme records
        foreach ($results as $result) {
            $this->assertTrue($result['id'] > 0);
        }

        $this->assertEquals(3, count($results));
    }

    /**
     * Test normal with Or.
     *
     * @return boolean
     */
    public function testQueryConditionalsWithOr()
    {
        //create the index first

        $params = [];
        $params['q'] = ('is_deleted:0;firstname:max%');
        //$params['cq'] = ('company.name:mc%');
        //$params['cq'] = ('eventsversions.events_types_id:1;participantsprograms.programs_id:2,custom_fields.sexo:f,companiesoffices.districts_id:1;companiesoffices.countries_id:2,eventsversionsparticipants.is_deleted:0|1,eventsversionsparticipants.eventsversionsdates.event_date>2019-04-01,eventsversionsparticipants.eventsversionsdates.event_date<2019-04-14');
        $params['limit'] = '10';
        $params['page'] = '1';
        $params['sort'] = 'id|desc';

        $leads = new Leads();
        $requestToSql = new RequestUriToElasticSearch($params, $leads);
        $request = $requestToSql->convert();

        $client = new Client('http://' . $this->_config->elasticSearch['hosts'][0]);
        $results = $client->findBySql($request['sql']);

        //confirme records
        foreach ($results as $result) {
            $this->assertTrue($result['id'] > 0);
        }

        $this->assertEquals(3, count($results));
    }

    /**
     * Test and and Or conditions.
     *
     * @return boolean
     */
    public function testQueryConditionalsWithAndOr()
    {
        //create the index first

        $params = [];
        $params['q'] = ('is_deleted:0,firstname:max%;companies_id>0');
        //$params['cq'] = ('company.name:mc%');
        //$params['cq'] = ('eventsversions.events_types_id:1;participantsprograms.programs_id:2,custom_fields.sexo:f,companiesoffices.districts_id:1;companiesoffices.countries_id:2,eventsversionsparticipants.is_deleted:0|1,eventsversionsparticipants.eventsversionsdates.event_date>2019-04-01,eventsversionsparticipants.eventsversionsdates.event_date<2019-04-14');
        $params['limit'] = '10';
        $params['page'] = '1';
        $params['sort'] = 'id|desc';

        $leads = new Leads();
        $requestToSql = new RequestUriToElasticSearch($params, $leads);
        $request = $requestToSql->convert();

        $client = new Client('http://' . $this->_config->elasticSearch['hosts'][0]);
        $results = $client->findBySql($request['sql']);

        //confirme records
        foreach ($results as $result) {
            $this->assertTrue($result['id'] > 0);
        }

        $this->assertEquals(3, count($results));
    }

    /**
     * Test limit.
     *
     * @return void
     */
    public function testQueryConditionalsLimit()
    {
        //create the index first

        $params = [];
        $params['q'] = ('is_deleted:0,firstname:max%;companies_id>0');
        //$params['cq'] = ('company.name:mc%');
        //$params['cq'] = ('eventsversions.events_types_id:1;participantsprograms.programs_id:2,custom_fields.sexo:f,companiesoffices.districts_id:1;companiesoffices.countries_id:2,eventsversionsparticipants.is_deleted:0|1,eventsversionsparticipants.eventsversionsdates.event_date>2019-04-01,eventsversionsparticipants.eventsversionsdates.event_date<2019-04-14');
        $params['limit'] = '2';
        $params['page'] = '1';
        $params['sort'] = 'id|desc';

        $leads = new Leads();
        $requestToSql = new RequestUriToElasticSearch($params, $leads);
        $request = $requestToSql->convert();

        $client = new Client('http://' . $this->_config->elasticSearch['hosts'][0]);
        $results = $client->findBySql($request['sql']);

        //confirme records
        foreach ($results as $result) {
            $this->assertTrue($result['id'] > 0);
        }

        $this->assertEquals(2, count($results));
    }

    /**
     * Test nested.
     *
     * @return void
     */
    public function testQueryWithNestedCondition()
    {
        //create the index first

        $params = [];
        $params['q'] = ('is_deleted:0,companies_id>0');
        $params['cq'] = ('company.name:mc%;company.id>0');

        $params['limit'] = '2';
        $params['page'] = '1';
        $params['sort'] = 'id|desc';

        $leads = new Leads();
        $requestToSql = new RequestUriToElasticSearch($params, $leads);
        $request = $requestToSql->convert();

        $client = new Client('http://' . $this->_config->elasticSearch['hosts'][0]);
        $results = $client->findBySql($request['sql']);

        //confirme records
        foreach ($results as $result) {
            $this->assertTrue($result['id'] > 0);
        }

        $this->assertEquals(2, count($results));
    }

    /**
     * Test nested 2 dimesional.
     *
     * @return void
     */
    public function testQueryWithNestedConditionTwoDimensional()
    {
        $params = [];
        $params['q'] = 'is_deleted:0,participants_statuses_id:,conference_discount:,first_name:,last_name:,identification:,gifts_id~,is_prospect:,participants_levels_id~,position:,departments_id:,is_axis_participant~,general_representative~,is_key_participant:,themes_areas_id~,professions_id~,companies_id~';
        $params['cq'] = 'custom_fields.sexo:m,custom_fields.tccode:,custom_fields.certificado_segundo_nombre:,custom_fields.certificado_segundo_apellido:,custom_fields.tcporcent:,themesareas.themes_id:,companiesoffices.address:,companiesoffices.countries_id~,companiesoffices.cities_id~,companiesoffices.districts_id~,participantseventsinterests.events_id~,participantsinterests.name:,participantsgroups.groups_id:,companies.companies_statuses_id:,companies.business_types_id~,companies.business_sectors_id~,companies.linked_companies_id~,companies.custom_fields.tamano~,companies.business_activities_id~,eventsversionsparticipants.companiesplans.plans_id~1,eventsversionsparticipants.companiesplans.algo:5,eventsversionsparticipants.companiesplans.company.name:5,eventsversionsparticipants.is_deleted:0,eventsversionsparticipants.eventsversions.events_id~2071,eventsversionsparticipants.events_versions_id~3998,eventsversionsparticipants.eventsversions.events_types_id~1,eventsversionsparticipants.eventsversions.events_classes_id~1,eventsversionsparticipants.eventsversions.events_categories_id~,eventsversionsparticipants.eventsversions.themes_id~,eventsversionsparticipants.eventsversions.themes_areas_id~,eventsversionsparticipants.eventsversions.affiliates_id~,eventsversionsparticipants.eventsversions.events_versions_statuses_id~,eventsversionsparticipants.channels_id~,eventsversionsparticipants.inscriptions_types_id~1|2|6|7|8|9,eventsversionsparticipants.eventsversionsfacilitators.facilitators_id~,eventsversionsparticipants.eventsversionsdates.event_date:,eventsversionsparticipants.eventsversionsdates.event_date:,eventsversionsparticipants.eventsversions.placesareas.places.countries_id~,eventsversionsparticipants.eventsversions.placesareas.places.cities_id~,participantsprograms.programs_id:,courtseypasses.courtsey_passes_motives_id:,courtseypasses.participants_id:,companiescourtseypasses.issue_date:,companiescourtseypasses.expiration_date:,eventsversionsparticipants.eventsversions.events.name:1,eventsversionsparticipants.eventsversions.events.company_name:1,eventsversionsparticipants.eventsversions.events.company.name:1';

        $params['limit'] = '2';
        $params['page'] = '1';
        $params['sort'] = 'id|desc';

        $leads = new Leads();
        $requestToSql = new RequestUriToElasticSearch($params, $leads);
        $request = $requestToSql->convert();

        echo $request['sql'];
        die();

        $client = new Client('http://' . $this->_config->elasticSearch['hosts'][0]);
        $results = $client->findBySql($request['sql']);

        //confirme records
        foreach ($results as $result) {
            $this->assertTrue($result['id'] > 0);
        }

        $this->assertEquals(2, count($results));
    }

    /**
     * Test nested 3 dimesional.
     *
     * @return void
     */
    public function testQueryWithNestedConditionThreeDimensional()
    {
    }

    /**
     * Test with and and Or.
     *
     * @return void
     */
    public function testQueryWithNestedConditionWithAndOr()
    {
        //create the index first

        $params = [];
        $params['q'] = ('is_deleted:0,companies_id>0');
        $params['cq'] = ('company.name:mc%,company.id>0;company.branch_id:1');

        $params['limit'] = '2';
        $params['page'] = '1';
        $params['sort'] = 'id|desc';

        $leads = new Leads();
        $requestToSql = new RequestUriToElasticSearch($params, $leads);
        $request = $requestToSql->convert();

        $client = new Client('http://' . $this->_config->elasticSearch['hosts'][0]);
        $results = $client->findBySql($request['sql']);

        //confirme records
        foreach ($results as $result) {
            $this->assertTrue($result['id'] > 0);
        }

        $this->assertEquals(2, count($results));
    }

    /**
     * Specify a custom column.
     *
     * @return void
     */
    public function testQueryConditionalsWithCustomColumns()
    {
        //create the index first
        $params = [];
        $params['q'] = ('is_deleted:0,companies_id>0');
        $params['cq'] = ('company.name:mc%,company.id>0;company.branch_id:1');
        $params['columns'] = '(id)';
        $params['limit'] = '2';
        $params['page'] = '1';
        $params['sort'] = 'id|desc';

        $leads = new Leads();
        $requestToSql = new RequestUriToElasticSearch($params, $leads);
        $requestToSql->setCustomColumns('companies_id');
        $request = $requestToSql->convert();

        $client = new Client('http://' . $this->_config->elasticSearch['hosts'][0]);
        $results = $client->findBySql($request['sql']);

        //confirme records
        foreach ($results as $result) {
            $this->assertTrue($result['id'] > 0);
            $this->assertTrue($result['companies_id'] > 0);
        }

        $this->assertEquals(2, count($results));
    }

    /**
     * Do custom quer contiioanals.
     *
     * @return void
     */
    public function testQueryConditionalsWithCustomCondition()
    {
        //create the index first
        $params = [];
        $params['q'] = ('is_deleted:0,companies_id>0');
        $params['cq'] = ('company.name:mc,company.id>0;company.branch_id:1');
        $params['columns'] = '(id)';
        $params['limit'] = '2';
        $params['page'] = '1';
        $params['sort'] = 'id|desc';

        $leads = new Leads();
        $requestToSql = new RequestUriToElasticSearch($params, $leads);
        $requestToSql->setCustomConditions('AND is_deleted = 1');

        $request = $requestToSql->convert();
        $client = new Client('http://' . $this->_config->elasticSearch['hosts'][0]);

        $results = $client->findBySql($request['sql']);

        $this->assertEquals(0, count($results));
    }
}
