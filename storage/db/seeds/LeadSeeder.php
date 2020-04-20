<?php

use Phinx\Seed\AbstractSeed;

class LeadSeeder extends AbstractSeed
{
    public function run()
    {
        $data = [
            [
                'users_id' => 1,
                'companies_id' => 1,
                'firstname' => 'Max',
                'lastname' => 'Castro',
                'email' => 'something@about.com',
                'phone' => '5555555555',
                'leads_owner_id' => 1,
                'leads_status_id' => 1,
                'created_at' => date('Y-m-d H:m:s'),
                'updated_at' => date('Y-m-d H:m:s'),
                'is_deleted' => 0,
                'is_duplicated' => 0,
                'is_active' => 1,
            ],
            [
                'users_id' => 1,
                'companies_id' => 2,
                'firstname' => 'Leo',
                'lastname' => 'Castro',
                'email' => 'anotheremail@about.com',
                'phone' => '5555555555',
                'leads_owner_id' => 1,
                'leads_status_id' => 2,
                'created_at' => date('Y-m-d H:m:s'),
                'updated_at' => date('Y-m-d H:m:s'),
                'is_deleted' => 0,
                'is_duplicated' => 0,
                'is_active' => 1,
            ],
            [
                'users_id' => 1,
                'companies_id' => 3,
                'firstname' => 'Campo',
                'lastname' => 'Castro',
                'email' => 'somethingelse@about.com',
                'phone' => '5555555555',
                'leads_owner_id' => 1,
                'leads_status_id' => 3,
                'created_at' => date('Y-m-d H:m:s'),
                'updated_at' => date('Y-m-d H:m:s'),
                'is_deleted' => 1,
                'is_duplicated' => 0,
                'is_active' => 1,
            ],
        ];

        $posts = $this->table('leads');
        $posts->insert($data)
              ->save();
    }
}
