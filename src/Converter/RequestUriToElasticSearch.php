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
        '~' => '=',
        ':' => 'LIKE',
        '>' => '>=',
        '<' => '<=',
        '!' => '<>',
        '¬' => 'BETWEEN',
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

                $sort = " ORDER BY {$modelColumn} {$order}";
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

            /**
             * organize the custom fields for nested mapping.
             * @todo reduce the # of cycles to format the cutom structure to 1
             */
            $newCustomFieldsParsed = [];
            $this->formatNestedArray($customSearchFields, $newCustomFieldsParsed);
            $this->parseNestedStructureToSql($newCustomFieldsParsed, $sql, $classname);
        }

        // Replace initial `AND ` or `OR ` to avoid SQL errors.
        $sql = str_replace(
            ['WHERE AND', 'WHERE OR', 'WHERE ( OR', '",  AND', '", AND', '",  OR', '", OR'],
            ['WHERE', 'WHERE', 'WHERE (', '",', '",', '",', '",'],
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
                if (strpos($searchValues, '|') && $operator != 'BETWEEN') {
                    $searchValues = explode('|', $searchValues);
                } else {
                    //format between
                    if ($operator == 'BETWEEN') {
                        $searchValues = str_replace('|', "' AND '", $searchValues);
                    }
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
    protected function parseNestedStructureToSql(array &$newCustomFieldsParsed, string &$sql, string $classname): string
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
                    $this->parseNestedStructureToSql($newNestedArray, $nestedSql, $classname);
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
        list($searchField, $operator, $searchValues, $conditionOperator) = $searchCriteria;
        $operator = $this->operators[$operator];

        if (trim($searchValues) !== '') {
            if ($searchValues == '%%') {
                $sql .= ' ' . $conditionOperator . ' (' . $searchField . ' IS NULL';
                $sql .= ' OR ' . $searchField . ' = ""';

                if ($this->model->$searchField === 0) {
                    $sql .= ' OR ' . $searchField . ' = 0';
                }

                $sql .= ')';
            } elseif ($searchValues == '$$') {
                $sql .= ' ' . $conditionOperator . ' (' . $searchField . ' IS NOT NULL';
                $sql .= ' OR ' . $searchField . ' != ""';

                if ($this->model->$searchField === 0) {
                    $sql .= ' OR ' . $searchField . ' ) != 0';
                }

                $sql .= ')';
            } else {
                if (strpos($searchValues, '|') && $operator != 'BETWEEN') {
                    $searchValues = explode('|', $searchValues);
                } else {
                    //format between
                    if ($operator == 'BETWEEN') {
                        $searchValues = str_replace('|', "' AND '", $searchValues);
                    }
                    $searchValues = [$searchValues];
                }

                $sqlArray = [];
                foreach ($searchValues as $vKey => $value) {
                    if ((preg_match('#^%[^%]+%|%[^%]+|[^%]+%$#i', $value))
                        || $value == '%%'
                    ) {
                        $operator = 'LIKE';
                    }

                    $bindParamsKey = ':f' . $searchField . $fKey . $vKey;
                    /**
                     * if we find the bidnparam key its means it we are dealing with > and < search, so to avoid
                     * overwriting the value we have to hange the vKey value.
                     */
                    if ($vKeyFound = array_search($bindParamsKey, $this->bindParamsKeys)) {
                        $bindParamsKey = ':f' . $searchField . $fKey . ($vKeyFound + 1);
                    }

                    if (!$vKey) {
                        $sql .= ' ' . $andOr . ' (' . $searchField . ' ' . $operator . ' ' . $bindParamsKey;
                    } else {
                        $sql .= ' OR ' . $searchField . ' ' . $operator . ' ' . $bindParamsKey;
                    }

                    $this->bindParamsKeys[] = $bindParamsKey;
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
        $this->customSearchFields = array_key_exists('cparams', $unparsed) ? $this->parseSearchParametersCustomFields($unparsed['cparams'])['mapped'] : [];
        $this->normalSearchFields = array_key_exists('params', $unparsed) ? $this->parseSearchParameters($unparsed['params'])['mapped'] : [];
    }

    /**
     * Clone the other function to reuse especifictly for nested custom fields
     * Parses out the search parameters from a request.
     * Unparsed, they will look like this:
     *    (name:Benjamin Framklin,location:Philadelphia)
     * Parsed:
     *    [
     *      [
     *          'id_delete',
     *          ':',
     *          0
     *      ],[
     *          'id',
     *          '>',
     *           0
     *      ]
     *     ].
     *
     * @param  string $unparsed Unparsed search string
     * @return array  An array of fieldname=>value search parameters
     */
    public function parseSearchParametersCustomFields(string $unparsed): array
    {
        // $unparsed = urldecode($unparsed);
        // Strip parens that come with the request string
        $unparsed = trim($unparsed, '()');

        // Now we have an array of "key:value" strings.
        $splitFields = explode(',', $unparsed);
        $sqlFilersOperators = implode('|', array_keys($this->operators));

        $mapped = [];
        $search = [];

        // Split the strings at their colon, set left to key, and right to value.
        foreach ($splitFields as $key => $fieldChain) {
            $hasChain = strpos($fieldChain, ';') !== false;
            $fieldChain = explode(';', $fieldChain);

            foreach ($fieldChain as $field) {
                $splitField = preg_split('#(' . $sqlFilersOperators . ')#', $field, -1, PREG_SPLIT_DELIM_CAPTURE);

                if (count($splitField) > 3) {
                    $splitField[2] = implode('', array_splice($splitField, 2));
                }

                if (!strlen($splitField[2])) {
                    continue;
                }

                if ($hasChain) {
                    $splitField[] = 'OR';
                } else {
                    $splitField[] = 'AND';
                }

                $mapped[] = $splitField;
                $search[$splitField[0]] = $splitField[2];
            }
        }

        return [
            'mapped' => $mapped,
            'search' => $search,
        ];
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
            $column = preg_replace('/[^a-zA-_.Z0-9]/', '', $column);
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
        //elastic search doesnt use relationships
        return [];
    }
}
