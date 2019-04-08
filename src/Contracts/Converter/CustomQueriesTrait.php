<?php

namespace Baka\Http\Contracts\Converter;

trait CustomQueriesTrait
{
    /**
     * Add additional columns in search.
     *
     * @var string
     */
    protected $customColumns = null;

    /**
     * Add additional table Join
     *
     * @var string
     */
    protected $customTableJoins = null;

    /**
     * Add additional conditionals
     *
     * @var string
     */
    protected $customConditions = null;

     /**
     * Set the custom columns provide by the user.
     *
     * @param string $query
     * @return void
     */
    public function setCustomColumns(string $query) : void
    {
        $this->customColumns = ' ,' . $query;
    }

    /**
     * Set the custom table by the user
     * you can do inner joins or , table . If you are just adding a table you will need to specify the ,
     *
     * @param string $query
     * @return void
     */
    public function setCustomTableJoins(string $query) : void
    {
        $this->customTableJoins = ' ' . $query;
    }

    /**
     * set custom conditions for the query , need to start with and AND or OR
     *
     * @param string $query
     * @return void
     */
    public function setCustomConditions(string $query) : void
    {
        $this->customConditions = ' ' . $query;
    }
}