<?php

namespace Baka\Http\Converter;

use Baka\Database\Model;
use Exception;

/**
 * Base QueryParser. Parse GET request for a API to a array Phalcon Model find and FindFirst can intepret.
 *
 * Supports queries with the following paramters:
 *   Searching:
 *     q=(searchField1:value1,searchField2:value2)
 *   Partial Responses:
 *     fields=(field1,field2,field3)
 *   Limits:
 *     limit=10
 *   Partials:
 *     offset=20
 */
class RequestUriToElasticSearch extends RequestUriToSql
{
    /**
     * @var array
     */
    protected $operators = [
        ':' => '=',
        '>' => '>=',
        '<' => '<=',
        '~' => '<>',
    ];

    /**
     * Pass the request.
     */
    public function __construct(array $request, Model $model)
    {
        $this->request = $request;
        $this->model = $model;
    }

    /**
    * Main method for parsing a query string.
    * Finds search paramters, partial response fields, limits, and offsets.
    * Sets Controller fields for these variables.
    *
    * @param  array $allowedFields Allowed fields array for search and partials
    * @return boolean              Always true if no exception is thrown
    */
    public function convert(): array
    {
        $params = [
            'subquery' => '',
        ];

        $hasSubquery = false;

        //if we find that we are using custom field this is a different beast so we have to send it
        //to another functino to deal with this shit
        if (array_key_exists('cq', $this->request)) {
            $params['cparams'] = $this->request['cq'];
        }

        //verify the user is searching for something
        if (array_key_exists('q', $this->request)) {
            $params['params'] = $this->request['q'];
        }

        // Check to see if the user wants certain columns returned
        if (array_key_exists('columns', $this->request)) {
            $this->parseColumns($this->request['columns']);
        } else {
            $this->columns = '*';
        }

        // Check the limit the user is asking for.
        if (array_key_exists('limit', $this->request)) {
            $limit = (int) $this->request['limit'];
            // Prevent ridiculous limits. Nothing above 200 and nothing below 1.
            if ($limit >= 1 && $limit <= 200) {
                $this->limit = $limit;
            } elseif ($limit > 200) {
                $this->limit = 200;
            } elseif ($limit < 1) {
                $this->limit = 25;
            }
        }

        // Check the page the user is asking for.
        if (array_key_exists('page', $this->request)) {
            $page = (int) $this->request['page'];
            // Prevent ridiculous pagination requests
            if ($page >= 1) {
                $this->page = $page;
            }
        }

        // Prepare the search parameters.
        $this->prepareParams($params);

        // Sorting logic for related searches.
        // @TODO Explore this in the future. There might be better ways to carry it out.
        $sort = '';
        if (array_key_exists('sort', $this->request)) {
            $sort = $this->request['sort'];

            if (!empty($sort)) {
                // Get the model, column and sort order from the sent parameter.
                list($modelColumn, $order) = explode('|', $sort);
                // Check to see whether this is a related sorting by looking for a .
                if (strpos($modelColumn, '.') !== false) {
                    // We are using a related sort.
                    // Get the namespace for the models from the configuration.
                    $modelNamespace = \Phalcon\Di::getDefault()->getConfig()->namespace->models;
                    // Get the model name and the sort column from the sent parameter
                    list($model, $column) = explode('.', $modelColumn);
                    // Convert the model name into camel case.
                    $modelName = str_replace(' ', '', ucwords(str_replace('_', ' ', $model)));
                    // Create the model name with the appended namespace.
                    $modelName = $modelNamespace . '\\' . $modelName;

                    // Make sure the model exists.
                    if (!class_exists($modelName)) {
                        throw new Exception('Related model does not exist.');
                    }

                    // Instance the model so we have access to the getSource() function.
                    $modelObject = new $modelName();
                    // Instance meta data memory to access the primary keys for the table.
                    $metaData = new \Phalcon\Mvc\Model\MetaData\Memory();
                    // Get the first matching primary key.
                    // @TODO This will hurt on compound primary keys.
                    $primaryKey = $metaData->getPrimaryKeyAttributes($modelObject)[0];
                    // We need the table to exist in the query in order for the related sort to work.
                    // Therefore we add it to comply with this by comparing the primary key to not being NULL.
                    $this->relationSearchFields[$modelName][] = [
                        $primaryKey, ':', '$$',
                    ];

                    $sort = " ORDER BY {$modelObject->getSource()}.{$column} {$order}";
                    unset($modelObject);
                } else {
                    $sort = " ORDER BY {$modelColumn} {$order}";
                }
            }
        }

        // Append any additional user parameters
        $this->appendAdditionalParams();
        //base on th eesarch params get the raw query
        $rawSql = $this->prepareCustomSearch();

        //sort
        if (!empty($sort)) {
            $rawSql['sql'] .= $sort;
        }

        // Calculate the corresponding offset
        $this->offset = ($this->page - 1) * $this->limit;
        $rawSql['sql'] .= " LIMIT {$this->limit} OFFSET {$this->offset}";

        return $rawSql;
    }

    /**
     * gien the request array , get the custom query to find the results.
     *
     * @param  array  $params
     * @return string
     */
    protected function prepareCustomSearch($hasSubquery = false): array
    {
        $metaData = new \Phalcon\Mvc\Model\MetaData\Memory();
        //$classReflection = (new \ReflectionClass($this->model));
        $classname = $this->model->getSource();

        $primaryKey = null;

        if ($primaryKey = $metaData->getPrimaryKeyAttributes($this->model)) {
            $primaryKey = $primaryKey[0];
        }

        $sql = '';

        $sql .= ' WHERE';

        // create normal sql search
        if (!empty($this->normalSearchFields)) {
            foreach ($this->normalSearchFields as $fKey => $searchFieldValues) {
                if (is_array(current($searchFieldValues))) {
                    foreach ($searchFieldValues as $csKey => $chainSearch) {
                        $sql .= !$csKey ? ' AND  (' : '';
                        $sql .= $this->prepareNormalSql($chainSearch, $classname, ($csKey ? 'OR' : ''), $fKey);
                        $sql .= ($csKey == count($searchFieldValues) - 1) ? ') ' : '';
                    }
                } else {
                    $sql .= $this->prepareNormalSql($searchFieldValues, $classname, 'AND', $fKey);
                }
            }
        }

        // create custom query sql
        if (!empty($this->customSearchFields)) {
            // print_r($this->customSearchFields);die();
            // We have to pre-process the fields in order to have them bundled together.
            $customSearchFields = [];

            foreach ($this->customSearchFields as $fKey => $searchFieldValues) {
                if (is_array(current($searchFieldValues))) {
                    foreach ($searchFieldValues as $csKey => $chainSearch) {
                        $searchTable = explode('.', $chainSearch[0])[0];
                        $customSearchFields[$fKey][$searchTable][] = $chainSearch;
                    }
                } else {
                    $searchTable = explode('.', $searchFieldValues[0])[0];

                    //we use strlen and not empty because if a user sends us 0, its consider empty
                    if (strlen($searchFieldValues[2])) {
                        $customSearchFields[$searchTable][] = $searchFieldValues;
                    }
                }
            }

            //organize the custom fields for nested mapping
            $newCustomFieldsParsed = [];
            $this->formatNestedArray($customSearchFields, $newCustomFieldsParsed);
            // print_R($customSearchFields);

            // With the stuff processed we now proceed to assemble the query

            /*             foreach ($customSearchFields as $fKey => $searchFieldValues) {
                            // If the key is an integer, this means the fields have to be OR'd inside the nesting
                            if (is_int($fKey)) {
                                $nestedSql = ' AND (';
                                $first = true;
                                foreach ($searchFieldValues as $csKey => $chainSearch) {
                                    if (count($chainSearch) > 1) {
                                        $nestedSql .= ' nested("' . $csKey . '",';
                                        foreach ($chainSearch as $cKey => $chain) {
                                            $nestedSql .= $prepareNestedSql($chain, $classname, ($cKey ? 'OR' : ''), $csKey . $cKey);
                                        }
                                        $nestedSql .= ') ';
                                    } else {
                                        $nestedSql .= !$first ? ' OR nested("' . $csKey . '",' : ' nested("' . $csKey . '",';
                                        $nestedSql .= $prepareNestedSql($chainSearch[0], $classname, '', $csKey);
                                        $nestedSql .= ') ';
                                    }
                                    $first = false;
                                }
                                $sql .= $nestedSql . ') ';
                            } else {
                                $nestedSql = ' AND nested("' . $fKey . '",';
                                foreach ($searchFieldValues as $csKey => $chainSearch) {
                                    $nestedSql .= $prepareNestedSql($chainSearch, $classname, ($csKey ? 'AND' : ''), $fKey);
                                }
                                $nestedSql .= ') ';
                                $sql .= $nestedSql;
                            }
                        } */

            $this->parseNestedStructure($newCustomFieldsParsed, $sql, $classname);
        }

        // Replace initial `AND ` or `OR ` to avoid SQL errors.
        $sql = str_replace(
            ['WHERE AND', 'WHERE OR', 'WHERE ( OR', '",  AND', '", AND'],
            ['WHERE', 'WHERE', 'WHERE (', '",', '",'],
            $sql
        );

        // Remove empty where from the end of the string.
        $sql = preg_replace('# WHERE$#', '', $sql);

        //sql string
        $countSql = 'SELECT COUNT(*) total FROM ' . $classname . $this->customTableJoins . $sql . $this->customConditions;
        $resultsSql = "SELECT {$this->columns} {$this->customColumns} FROM {$classname} {$this->customTableJoins} {$sql} {$this->customConditions}";
        //bind params
        $bindParams = array_combine($this->bindParamsKeys, $this->bindParamsValues);

        return [
            'sql' => strtr($resultsSql, $bindParams),
            'countSql' => strtr($countSql, $bindParams),
            'bind' => null,
        ];
    }

    /**
     * Prepare the SQL for a normal search.
     *
     * @param array $searchCriteria
     * @param string $classname
     * @param string $andOr
     * @param int $fKey
     *
     * @return string
     */
    protected function prepareNormalSql(array $searchCriteria, string $classname, string $andOr, int $fKey): string
    {
        $sql = '';
        $textFields = $this->getTextFields($classname);
        list($searchField, $operator, $searchValues) = $searchCriteria;
        $operator = $this->operators[$operator];

        if (trim($searchValues) !== '') {
            if ($searchValues == '%%') {
                $sql .= ' ' . $andOr . ' (' . $searchField . ' IS NULL';
                $sql .= ' OR ' . $searchField . ' = ""';

                if ($this->model->$searchField === 0) {
                    $sql .= ' OR ' . $searchField . ' = 0';
                }

                $sql .= ')';
            } elseif ($searchValues == '$$') {
                $sql .= ' ' . $andOr . ' (' . $searchField . ' IS NOT NULL';
                $sql .= ' OR ' . $searchField . ' != ""';

                if ($this->model->$searchField === 0) {
                    $sql .= ' OR ' . $searchField . ' != 0';
                }

                $sql .= ')';
            } else {
                if (strpos($searchValues, '|')) {
                    $searchValues = explode('|', $searchValues);
                } else {
                    $searchValues = [$searchValues];
                }

                foreach ($searchValues as $vKey => $value) {
                    if (preg_match('#^%[^%]+%|%[^%]+|[^%]+%$#i', $value)
                        || $value == '%%'
                    ) {
                        $operator = 'LIKE';
                    }

                    if (!$vKey) {
                        $sql .= ' ' . $andOr . ' (' . $searchField . ' ' . $operator . ' :f' . $searchField . $fKey . $vKey;
                    } else {
                        $sql .= ' OR ' . $searchField . ' ' . $operator . ' :f' . $searchField . $fKey . $vKey;
                    }

                    $this->bindParamsKeys[] = ':f' . $searchField . $fKey . $vKey;
                    $this->bindParamsValues[] = "'{$value}'";
                }

                $sql .= ')';
            }
        }

        return $sql;
    }

    /**
     * Parse nested structure to create its recursive tree
     * nested(key ,
     *  attributes = value
     *  AND attributes = values
     *  AND nested(key2,
     *          attributes = values
     *      )
     * ).
     *
     * @param array $newCustomFieldsParsed
     * @param string $sql
     * @param string $classname
     * @return string
     */
    protected function parseNestedStructure(array &$newCustomFieldsParsed, string &$sql, string $classname): string
    {
        $csKey = 1;
        foreach ($newCustomFieldsParsed as $fKey => $nestedFields) {
            $nestedSql = ' AND nested("' . $fKey . '", ';

            foreach ($nestedFields as $nestedKey => $nestedValue) {
                if ($nestedKey == 'plain') {
                    foreach ($nestedFields[$nestedKey] as $chainSearch) {
                        $chainSearch[0] = $fKey . '.' . $chainSearch[0];
                        $nestedSql .= $this->prepareNestedSql($chainSearch, $classname, ($csKey ? 'AND' : ''), $fKey);
                    }
                } else {
                    $newNestedArray = [
                        $fKey . '.' . $nestedKey => $nestedValue
                    ];
                    $this->parseNestedStructure($newNestedArray, $nestedSql, $classname);
                }
            }

            $nestedSql .= ') ';
            $sql .= $nestedSql;
        }

        return $sql;
    }

    /**
     * Prepare nested sql.
     *
     * @param array $searchCriteria
     * @param string $classname
     * @param string $andOr
     * @param string $fKey
     * @return string
     */
    protected function prepareNestedSql(array $searchCriteria, string $classname, string $andOr, string $fKey): string
    {
        $sql = '';
        list($searchField, $operator, $searchValues) = $searchCriteria;
        $operator = $this->operators[$operator];

        if (trim($searchValues) !== '') {
            if ($searchValues == '%%') {
                $sql .= ' ' . $andOr . ' (' . $searchField . ' IS NULL';
                $sql .= ' OR ' . $searchField . ' = ""';

                if ($this->model->$searchField === 0) {
                    $sql .= ' OR ' . $searchField . ' = 0';
                }

                $sql .= ')';
            } elseif ($searchValues == '$$') {
                $sql .= ' ' . $andOr . ' (' . $searchField . ' IS NOT NULL';
                $sql .= ' OR ' . $searchField . ' != ""';

                if ($this->model->$searchField === 0) {
                    $sql .= ' OR ' . $searchField . ' ) != 0';
                }

                $sql .= ')';
            } else {
                if (strpos($searchValues, '|')) {
                    $searchValues = explode('|', $searchValues);
                } else {
                    $searchValues = [$searchValues];
                }

                $sqlArray = [];
                foreach ($searchValues as $vKey => $value) {
                    if ((preg_match('#^%[^%]+%|%[^%]+|[^%]+%$#i', $value))
                        || $value == '%%'
                    ) {
                        $operator = 'LIKE';
                    }

                    if (!$vKey) {
                        $sql .= ' ' . $andOr . ' (' . $searchField . ' ' . $operator . ' :f' . $searchField . $fKey . $vKey;
                    } else {
                        $sql .= ' OR ' . $searchField . ' ' . $operator . ' :f' . $searchField . $fKey . $vKey;
                    }

                    $this->bindParamsKeys[] = ':f' . $searchField . $fKey . $vKey;
                    $this->bindParamsValues[] = "'{$value}'";
                }

                $sql .= ')';
            }
        }

        return $sql;
    }

    /**
     * Given a customfield array format it to a clear structure to handle multi level nested queries.
     *
     * @param array $customSearchFields
     * @param [type] $newCustomFieldsParsed
     * @return array
     */
    protected function formatNestedArray(array $customSearchFields, &$newCustomFieldsParsed): array
    {
        foreach ($customSearchFields as $customFieldIndex => $customFieldValue) {
            foreach ($customFieldValue as $cValueKey => $cValueValue) {
                $cNewKey = explode('.', $cValueValue[0]);

                if (count($cNewKey) == 3) {
                    $cValueValue[0] = str_replace($customFieldIndex . '.' . $cNewKey[1] . '.', '', $cValueValue[0]);

                    $newCustomFieldsParsed[$customFieldIndex][$cNewKey[1]]['plain'][] = $cValueValue;
                } elseif (count($cNewKey) == 2) {
                    $cValueValue[0] = str_replace($customFieldIndex . '.', '', $cValueValue[0]);

                    $newCustomFieldsParsed[$customFieldIndex]['plain'][] = $cValueValue;
                } else {
                    $cValueValue[0] = str_replace($customFieldIndex . '.' . $cNewKey[1] . '.', '', $cValueValue[0]);

                    $this->formatSubNestedArray($newCustomFieldsParsed[$customFieldIndex][$cNewKey[1]], $cValueValue);
                }
            }
        }

        return $newCustomFieldsParsed;
    }

    /**
     * Recursive funcition to format the sub sub nested queries.
     *
     * @param array $subNested
     * @param array $subNestedValue
     * @return void
     */
    protected function formatSubNestedArray(array &$subNested, array $subNestedValue): void
    {
        foreach ($subNestedValue as $key => $value) {
            if ($key == 0) {
                $cNewKey = explode('.', $value);
                $subNestedValue[0] = str_replace($cNewKey[0] . '.', '', $subNestedValue[0]);

                if (count($cNewKey) == 2) {
                    $subNested[$cNewKey[0]]['plain'][] = $subNestedValue;
                } else {
                    //print_r($subNestedValue);
                    if (!isset($subNested[$cNewKey[1]])) {
                        $subNested[$cNewKey[1]] = [];
                    }
                    $this->formatSubNestedArray($subNested, $subNestedValue);
                }
            }
        }
    }

    /**
     * Preparse the parameters to be used in the search.
     *
     * @return void
     */
    protected function prepareParams(array $unparsed): void
    {
        $this->customSearchFields = array_key_exists('cparams', $unparsed) ? $this->parseSearchParameters($unparsed['cparams'])['mapped'] : [];
        $this->normalSearchFields = array_key_exists('params', $unparsed) ? $this->parseSearchParameters($unparsed['params'])['mapped'] : [];
    }

    /**
     * Parse the requested columns to be returned.
     *
     * @param string $columns
     *
     * @return void
     */
    protected function parseColumns(string $columns): void
    {
        // Split the columns string into individual columns
        $columns = explode(',', $columns);

        foreach ($columns as &$column) {
            $column = preg_replace('/[^a-zA-_Z]/', '', $column);
            if (strpos($column, '.') === false) {
                $column = "{$column}";
            } else {
                $as = str_replace('.', '_', $column);
                $column = "{$column} {$as}";
            }
        }

        $this->columns = implode(', ', $columns);
    }

    /**
     * Based on the given relaitonship , add the relation array to the Resultset.
     *
     * @param  string $relationships
     * @param  Model $results
     * @return array
     */
    public static function parseRelationShips(string $relationships, &$results) : array
    {
        $relationships = explode(',', $relationships);
        $newResults = [];
        if (!($results instanceof Model)) {
            throw new Exception(_('Result needs to be a Baka Model'));
        }
        $newResults = $results->toFullArray();
        foreach ($relationships as $relationship) {
            if ($results->$relationship) {
                $callRelationship = 'get' . ucfirst($relationship);
                $newResults[$relationship] = $results->$callRelationship();
            }
        }
        unset($results);
        return $newResults;
    }
}
