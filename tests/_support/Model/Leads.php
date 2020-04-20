<?php

namespace Test\Model;

class Leads extends \Baka\Database\Model
{
    /**
     * Specify the table.
     *
     * @return void
     */
    public function getSource()
    {
        return 'leads';
    }
}
