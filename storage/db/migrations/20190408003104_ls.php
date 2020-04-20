<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class Ls extends AbstractMigration
{
    public function change()
    {
        $this->execute("ALTER DATABASE CHARACTER SET 'utf8mb4';");
        $this->execute("ALTER DATABASE COLLATE='utf8mb4_unicode_ci';");
        $this->table('leads', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'encoding' => 'utf8',
            'collation' => 'utf8_general_ci',
            'row_format' => 'DYNAMIC',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'precision' => '10',
                'identity' => 'enable',
            ])
            ->addColumn('users_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'precision' => '10',
                'after' => 'id',
            ])
            ->addColumn('companies_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'precision' => '10',
                'after' => 'users_id',
            ])
            ->addColumn('firstname', 'string', [
                'null' => true,
                'default' => 'NULL',
                'limit' => 45,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
                'after' => 'companies_id',
            ])
            ->addColumn('lastname', 'string', [
                'null' => true,
                'default' => 'NULL',
                'limit' => 45,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
                'after' => 'firstname',
            ])
            ->addColumn('email', 'string', [
                'null' => true,
                'default' => 'NULL',
                'limit' => 45,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
                'after' => 'lastname',
            ])
            ->addColumn('phone', 'string', [
                'null' => true,
                'default' => 'NULL',
                'limit' => 45,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
                'after' => 'email',
            ])
            ->addColumn('leads_owner_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'precision' => '10',
                'after' => 'phone',
            ])
            ->addColumn('leads_status_id', 'integer', [
                'null' => false,
                'default' => '1',
                'limit' => MysqlAdapter::INT_REGULAR,
                'precision' => '10',
                'after' => 'leads_owner_id',
            ])
            ->addColumn('created_at', 'datetime', [
                'null' => false,
                'after' => 'leads_status_id',
            ])
            ->addColumn('updated_at', 'datetime', [
                'null' => true,
                // 'default' => 'NULL',
                'after' => 'created_at',
            ])
            ->addColumn('is_deleted', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY,
                'precision' => '3',
                'after' => 'updated_at',
            ])
            ->addColumn('is_duplicated', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_REGULAR,
                'precision' => '10',
                'after' => 'is_deleted',
            ])
            ->addColumn('is_active', 'integer', [
                'null' => true,
                'default' => '1',
                'limit' => MysqlAdapter::INT_REGULAR,
                'precision' => '10',
                'after' => 'is_duplicated',
            ])
        ->addIndex(['users_id'], [
            'name' => 'users_id',
            'unique' => false,
        ])
        ->addIndex(['companies_id'], [
            'name' => 'companies_id',
            'unique' => false,
        ])
        ->addIndex(['leads_owner_id'], [
            'name' => 'leads_owner_id',
            'unique' => false,
        ])
        ->addIndex(['leads_status_id'], [
            'name' => 'leads_status_id',
            'unique' => false,
        ])
        ->addIndex(['email'], [
            'name' => 'email',
            'unique' => false,
        ])
        ->addIndex(['id', 'companies_id', 'is_deleted'], [
            'name' => 'id',
            'unique' => false,
        ])
            ->create();
    }
}
