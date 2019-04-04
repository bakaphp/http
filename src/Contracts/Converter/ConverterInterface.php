<?php

namespace Baka\Http\Contracts\Converter;

interface ConverterInterface
{
    /**
     * Convert a Request to a whatever syntaxt we specify
     *
     * @return void
     */
    public function convert();
}
