<?php

namespace Baka\Http\Contracts\Converter;

use Exception;
use Phalcon\Mvc\Model\MetaData\Memory as MetaDataMemory;
use Phalcon\Di;

trait CustomQueriesTrait
{
    /**
     * Add additional columns in search.
     *
     * @var string
     */
    protected $customColumns = null;

    /**
     * Add additional table Join.
     *
     * @var string
     */
    protected $customTableJoins = null;

    /**
     * Add additional conditionals.
     *
     * @var string
     */
    protected $customConditions = null;

    /**
     * Custom Limit.
     *
     * @var int
     */
    protected $limit = 25;

    /**
     * Custom Sort.
     *
     * @var string
     */
    protected $sort = null;

    /**
    * Set the custom columns provide by the user.
    *
    * @param string $query
    * @return void
    */
    public function setCustomColumns(?string $query) : void
    {
        if (!is_null($query)) {
            $this->customColumns = ' ,' . $query;
        }
    }

    /**
     * Set the custom table by the user
     * you can do inner joins or , table . If you are just adding a table you will need to specify the ,.
     *
     * @param string $query
     * @return void
     */
    public function setCustomTableJoins(?string $query) : void
    {
        if (!is_null($query)) {
            $this->customTableJoins = ' ' . $query;
        }
    }

    /**
     * set custom conditions for the query , need to start with and AND or OR.
     *
     * @param string $query
     * @return void
     */
    public function setCustomConditions(?string $query) : void
    {
        if (!is_null($query)) {
            $this->customConditions = ' ' . $query;
        }
    }

    /**
     * Overwrite the limit of the current Request.
     *
     * @param integer $limit
     * @return void
     */
    public function setCustomLimit(?int $limit): void
    {
        if (!is_null($limit)) {
            $this->limit = $limit;
        }
    }
}
