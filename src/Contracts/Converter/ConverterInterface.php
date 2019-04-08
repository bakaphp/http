<?php

namespace Baka\Http\Contracts\Converter;

interface ConverterInterface
{
    public function setCustomColumns(string $query);
    public function setCustomTableJoins(string $query);
    public function setCustomConditions(string $query);

    /**
     * Convert a Request to a whatever syntaxt we specify.
     *
     * @return void
     */
    public function convert();
}
