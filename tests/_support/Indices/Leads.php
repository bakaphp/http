<?php

namespace Test\Indices;

use stdClass;

class Leads extends \Baka\Elasticsearch\Indices
{
    public $id = 1;

    /**
     * Index data.
     *
     * @return stdClass
     */
    public function data(): stdClass
    {
        $object = new stdClass();
        $object->id = $this->id;
        $this->setId($this->id);
        $object->users_id = 1;
        $object->companies_id = 2;
        $object->firstname = 'Max';
        $object->lastname = 'Castro';
        $object->email = 'wazadfadf@somethinggood.com';
        $object->is_deleted = 0;

        $company = [
            'id' => 1,
            'name' => 'mc',
            'url' => 'http://mctekk.com',
            'branch_id' => 1,
            'branch' => [
                'id' => 2,
                'name' => 'DN',
            ]
        ];

        $object->company = $company;
        return $object;
    }

    /**
     * Define the structure of thies index.
     *
     * @return array
     */
    public function structure(): array
    {
        return [
            'id' => $this->integer,
            'users_id' => $this->integer,
            'companies_id' => $this->integer,
            'firstname' => $this->text,
            'lastname' => $this->text,
            'email' => $this->text,
            'is_deleted' => $this->integer,
            'company' => [
                'id' => $this->integer,
                'name' => $this->text,
                'url' => $this->text,
                'branch_id' => $this->integer,
                'branch' => [
                    'id' => $this->integer,
                    'name' => $this->text,
                ]
            ]
        ];
    }
}
