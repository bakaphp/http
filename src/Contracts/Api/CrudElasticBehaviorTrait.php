<?php

namespace Baka\Http\Contracts\Api;

use Phalcon\Http\Request;
use Phalcon\Mvc\ModelInterface;
use Exception;

trait CrudElasticBehaviorTrait
{
    use CrudBehaviorTrait {
        CrudBehaviorTrait::processCreate as processCreateParent;
        CrudBehaviorTrait::processEdit as processEditParent;
    }

    /**
    * Given a request it will give you the SQL to process.
    *
    * @param Request $request
    * @return string
    */
    protected function processRequest(Request $request): array
    {
        //parse the rquest
        $parse = new RequestUriToElasticSearch($request->getQuery(), $this->model);
        $parse->setCustomColumns($this->customColumns);
        $parse->setCustomTableJoins($this->customTableJoins);
        $parse->setCustomConditions($this->customConditions);
        $parse->appendParams($this->additionalSearchFields);
        $parse->appendCustomParams($this->additionalCustomSearchFields);
        $parse->appendRelationParams($this->additionalRelationSearchFields);

        //conver to SQL
        return $parse->convert();
    }

    /**
     * Process the create request and trecurd the boject.
     *
     * @return ModelInterface
     * @throws Exception
     */
    protected function processCreate(Request $request): ModelInterface
    {
        $this->processCreateParent($request);

        //set the custom fields to create
        $this->model->setCustomFields($request);

        return $this->model;
    }

    /**
     * Process the update request and return the object.
     *
     * @param Request $request
     * @param ModelInterface $record
     * @throws Exception
     * @return ModelInterface
     */
    protected function processEdit(Request $request, ModelInterface $record): ModelInterface
    {
        $record = $this->processEditParent($request, $record);

        //set the custom fields to update
        $record->setCustomFields($request);

        return $record;
    }
}
