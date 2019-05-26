<?php

declare(strict_types=1);

namespace Baka\Http\Request;

use Phalcon\Http\Request as PhalconRequest;

class Baka extends PhalconRequest
{
    /**
     * Get the data from a POST request.
     *
     * @return array
     */
    public function getPostData(): array
    {
        $data = $this->getPost() ?: $this->getJsonRawBody(true);

        return $data ?: [];
    }

    /**
     * Get the data from a POST request.
     *
     * @return void
     */
    public function getPutData()
    {
        $data = $this->getPut() ?: $this->getJsonRawBody(true);

        return $data ?: [];
    }
}
