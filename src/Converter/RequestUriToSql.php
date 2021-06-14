<?php

namespace Baka\Http\Converter;

use Baka\Database\CustomFields\CustomFields;
use Baka\Database\CustomFields\Modules;
use Baka\Database\Model;
use Baka\Http\Contracts\Converter\ConverterInterface;
use Baka\Http\Contracts\Converter\CustomQueriesTrait;
use Exception;
use Phalcon\Di;
use Phalcon\Di\Injectable;
use Phalcon\Mvc\Model\MetaData\Memory as MetaDataMemory;
use Phalcon\Mvc\Model\ResultsetInterface;
use ReflectionClass;

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
class RequestUriToSql extends Injectable implements ConverterInterface
{
    use CustomQueriesTrait;

    /**
     * @var array
     */
    protected $request;

    /**
     * @var Baka\Database\Model
     */
    protected $model;

    /**
     * @var string
     */
    protected $columns;

    /**
     * @var int
     */
    protected $page = 1;

    /**
     * @var int
     */
    protected $limit = 25;

    /**
     * @var string
     */
    protected $sort = null;

    /**
     * @var int
     */
    protected $offset = 25;

    /**
     * @var array
     */
    protected $relationSearchFields = [];
    protected $additionalRelationSearchFields = [];

    /**
     * @var array
     */
    protected $customSearchFields = [];
    protected $additionalCustomSearchFields = [];

    /**
     * @var array
     */
    protected $normalSearchFields = [];
    protected $additionalSearchFields = [];

    /**
     * @var array
     */
    protected $operators = [
        ':' => '=',
        '>' => '>=',
        '<' => '<=',
        '~' => '!=',
    ];

    /**
     * @var array
     */
    protected $bindParamsKeys = [];

    /**
     * @var array
     */
    protected $bindParamsValues = [];

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
     *
     * @return bool              Always true if no exception is thrown
     */
    public function convert() : array
    {
        $params = [
            'subquery' => '',
        ];

        $hasSubquery = false;

        // Check to see if the user is trying to query a relationship
        if (array_key_exists('rq', $this->request)) {
            $params['rparams'] = $this->request['rq'];
        }

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
            $this->columns = "{$this->model->getSource()}.*";
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

        // Sorting logic for related searches.
        if (array_key_exists('sort', $this->request)) {
            if (!empty($this->request['sort'])) {
                $this->setCustomSort(trim($this->request['sort']));
            }
        }

        // Prepare the search parameters.
        $this->prepareParams($params);

        // Append any additional user parameters
        $this->appendAdditionalParams();
        //base on th eesarch params get the raw query
        $rawSql = $this->prepareCustomSearch();

        if (!is_null($this->sort)) {
            $rawSql['sql'] .= $this->sort;
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
     *
     * @return string
     */
    protected function prepareCustomSearch($hasSubquery = false) : array
    {
        $metaData = new MetaDataMemory();
        $classReflection = (new ReflectionClass($this->model));
        $classname = $this->model->getSource();

        $primaryKey = null;

        if ($primaryKey = $metaData->getPrimaryKeyAttributes($this->model)) {
            $primaryKey = $primaryKey[0];
        }

        $customClassname = $classname . '_custom_fields';
        $bindParamsKeys = [];
        $bindParamsValues = [];

        $sql = '';

        if (!empty($this->relationSearchFields)) {
            foreach ($this->relationSearchFields as $model => $searchFields) {
                $modelObject = new $model();
                $model = $modelObject->getSource();

                $relatedKey = $metaData->getPrimaryKeyAttributes($modelObject)[0];
                $relation = $this->model->getModelsManager()->getRelationsBetween(get_class($this->model), get_class($modelObject));
                $relationKey = (isset($relation) && count($relation)) ? $relation[0]->getFields() : $relatedKey;

                $sql .= " INNER JOIN {$model} ON {$model}.{$relatedKey} = (";
                $sql .= "SELECT {$model}.{$relatedKey} FROM {$model} WHERE {$model}.{$relatedKey} = {$classname}.{$relationKey}";

                foreach ($searchFields as $fKey => $searchFieldValues) {
                    if (is_array(current($searchFieldValues))) {
                        foreach ($searchFieldValues as $csKey => $chainSearch) {
                            $sql .= !$csKey ? ' (' : '';
                            $sql .= $this->prepareRelatedSql($chainSearch, $model, 'OR', $fKey);
                            $sql .= ($csKey == count($searchFieldValues) - 1) ? ') ' : '';
                        }
                    } else {
                        $sql .= $this->prepareRelatedSql($searchFieldValues, $model, 'AND', $fKey);
                    }
                }

                $sql .= ' LIMIT 1)';
            }

            unset($modelObject);
        }

        // create custom query sql
        if (!empty($this->customSearchFields)) {
            $modules = Modules::findFirstByName($classReflection->getShortName());

            $sql .= ' INNER JOIN ' . $customClassname . ' ON ' . $customClassname . '.id = (';
            $sql .= 'SELECT ' . $customClassname . '.id FROM ' . $customClassname . ' WHERE ' . $customClassname . '.' . $classname . '_id = ' . $classname . '.id';

            foreach ($this->customSearchFields as $fKey => $searchFieldValues) {
                if (is_array(current($searchFieldValues))) {
                    foreach ($searchFieldValues as $csKey => $chainSearch) {
                        $sql .= !$csKey ? ' (' : '';
                        $sql .= $this->prepareCustomSql($chainSearch, $modules, $customClassname, 'OR', $fKey);
                        $sql .= ($csKey == count($searchFieldValues) - 1) ? ') ' : '';
                    }
                } else {
                    $sql .= $this->prepareCustomSql($searchFieldValues, $modules, $customClassname, 'AND', $fKey);
                }
            }

            $sql .= ' LIMIT 1)';
        }

        $sql .= ' WHERE';

        // create normal sql search
        if (!empty($this->normalSearchFields)) {
            foreach ($this->normalSearchFields as $fKey => $searchFieldValues) {
                if (is_array(current($searchFieldValues))) {
                    foreach ($searchFieldValues as $csKey => $chainSearch) {
                        $sql .= !$csKey ? ' OR  (' : '';
                        $sql .= $this->prepareNormalSql($chainSearch, $classname, ($csKey ? 'OR' : ''), $fKey);
                        $sql .= ($csKey == count($searchFieldValues) - 1) ? ') ' : '';
                    }
                } else {
                    $sql .= $this->prepareNormalSql($searchFieldValues, $classname, 'AND', $fKey);
                }
            }
        }

        // Replace initial `AND ` or `OR ` to avoid SQL errors.
        $sql = str_replace(
            ['WHERE AND', 'WHERE OR', 'WHERE ( OR'],
            ['WHERE', 'WHERE', 'WHERE ('],
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
            'sql' => $resultsSql,
            'countSql' => $countSql,
            'bind' => $bindParams,
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
    protected function prepareNormalSql(array $searchCriteria, string $classname, string $andOr, int $fKey) : string
    {
        $sql = '';
        $textFields = $this->getTextFields($classname);
        list($searchField, $operator, $searchValues) = $searchCriteria;
        $operator = $this->operators[$operator];

        if (trim($searchValues) !== '') {
            if ($searchValues == '%%') {
                $sql .= ' ' . $andOr . ' (' . $classname . '.' . $searchField . ' IS NULL';
                $sql .= ' OR ' . $classname . '.' . $searchField . ' = ""';

                if ($this->model->$searchField === 0) {
                    $sql .= ' OR ' . $classname . '.' . $searchField . ' = 0';
                }

                $sql .= ')';
            } elseif ($searchValues == '$$') {
                $sql .= ' ' . $andOr . ' (' . $classname . '.' . $searchField . ' IS NOT NULL';
                $sql .= ' OR ' . $classname . '.' . $searchField . ' != ""';

                if ($this->model->$searchField === 0) {
                    $sql .= ' OR ' . $classname . '.' . $searchField . ' != 0';
                }

                $sql .= ')';
            } else {
                if (strpos($searchValues, '|')) {
                    $searchValues = explode('|', $searchValues);
                } else {
                    $searchValues = [$searchValues];
                }

                foreach ($searchValues as $vKey => $value) {
                    if ((in_array($searchField, $textFields)
                        && preg_match('#^%[^%]+%|%[^%]+|[^%]+%$#i', $value))
                        || $value == '%%'
                    ) {
                        $operator = 'LIKE';
                    }

                    if ($value == 'null') {
                        $logicConector = !$vKey ? ' ' . $andOr . ' (' : ' OR ';
                        $sql .= $logicConector . $classname . '.' . $searchField . ' IS NULL';
                    } else {
                        if (!$vKey) {
                            $sql .= ' ' . $andOr . ' (' . $classname . '.' . $searchField . ' ' . $operator . ' :f' . $searchField . $fKey . $vKey;
                        } else {
                            $sql .= ' OR ' . $classname . '.' . $searchField . ' ' . $operator . ' :f' . $searchField . $fKey . $vKey;
                        }

                        $this->bindParamsKeys[] = 'f' . $searchField . $fKey . $vKey;
                        $this->bindParamsValues[] = $value;
                    }
                }

                $sql .= ')';
            }
        }

        return $sql;
    }

    /**
     * Prepare the SQL for a related search.
     *
     * @param array $searchCriteria
     * @param string $classname
     * @param string $andOr
     * @param int $fKey
     *
     * @return string
     */
    protected function prepareRelatedSql(array $searchCriteria, string $classname, string $andOr, int $fKey) : string
    {
        $sql = '';
        $textFields = $this->getTextFields($classname);
        list($searchField, $operator, $searchValues) = $searchCriteria;
        $operator = $this->operators[$operator];

        if (trim($searchValues) !== '') {
            if ($searchValues == '%%') {
                $sql .= ' ' . $andOr . ' (' . $classname . '.' . $searchField . ' IS NULL';
                $sql .= ' OR ' . $classname . '.' . $searchField . ' = ""';

                if ($this->model->$searchField === 0) {
                    $sql .= ' OR ' . $classname . '.' . $searchField . ' = 0';
                }

                $sql .= ')';
            } elseif ($searchValues == '$$') {
                $sql .= ' ' . $andOr . ' (' . $classname . '.' . $searchField . ' IS NOT NULL';
                $sql .= ' OR ' . $classname . '.' . $searchField . ' != ""';

                if ($this->model->$searchField === 0) {
                    $sql .= ' OR ' . $classname . '.' . $searchField . ' != 0';
                }

                $sql .= ')';
            } else {
                if (strpos($searchValues, '|')) {
                    $searchValues = explode('|', $searchValues);
                } else {
                    $searchValues = [$searchValues];
                }

                foreach ($searchValues as $vKey => $value) {
                    if (in_array($searchField, $textFields)
                        && preg_match('#^%[^%]+%|%[^%]+|[^%]+%$#i', $value)
                    ) {
                        $operator = 'LIKE';
                    }

                    if (!$vKey) {
                        $sql .= ' ' . $andOr . ' (' . $classname . '.' . $searchField . ' ' . $operator . ' :rf' . $searchField . $fKey . $vKey;
                    } else {
                        $sql .= ' OR ' . $classname . '.' . $searchField . ' ' . $operator . ' :rf' . $searchField . $fKey . $vKey;
                    }

                    $this->bindParamsKeys[] = 'rf' . $searchField . $fKey . $vKey;
                    $this->bindParamsValues[] = $value;
                }

                $sql .= ')';
            }
        }

        return $sql;
    }

    /**
     * Prepare the SQL for a custom fields search.
     *
     * @param array $searchCriteria
     * @param Model $modules
     * @param string $classname
     * @param string $andOr
     * @param int $fKey
     *
     * @return string
     */
    protected function prepareCustomSql(array $searchCriteria, Model $modules, string $classname, string $andOr, int $fKey) : string
    {
        $sql = '';
        list($searchField, $operator, $searchValue) = $searchCriteria;
        $operator = $this->operators[$operator];

        if (trim($searchValue) !== '') {
            $customFields = CustomFields::findFirst([
                'modules_id = ?0 AND name = ?1',
                'bind' => [$modules->id, $searchField],
            ]);

            $customFieldValue = $classname . '.value';
            if ($customFields->type->name == 'number') {
                $customFieldValue = 'CAST(' . $customFieldValue . ' AS INT)';
            }

            $sql .= ' AND ' . $classname . '.custom_fields_id = :cfi' . $searchField;

            $this->bindParamsKeys[] = 'cfi' . $searchField;
            $this->bindParamsValues[] = $customFields->id;

            if ($searchValue == '%%') {
                $sql .= ' ' . $andOr . ' (' . $classname . '.value IS NULL OR ' . $classname . '.value = "")';
            } elseif ($searchValue == '$$') {
                $sql .= ' ' . $andOr . ' (' . $classname . '.value IS NOT NULL OR ' . $classname . '.value != "")';
            } else {
                if (strpos($searchValue, '|')) {
                    $searchValue = explode('|', $searchValue);
                } else {
                    $searchValue = [$searchValue];
                }

                foreach ($searchValue as $vKey => $value) {
                    if (preg_match('#^%[^%]+%|%[^%]+|[^%]+%$#i', $value)) {
                        $operator = 'LIKE';
                    }

                    if (!$vKey) {
                        $sql .= ' ' . $andOr . ' (' . $customFieldValue . ' ' . $operator . ' :cfv' . $searchField . $fKey . $vKey;
                    } else {
                        $sql .= ' OR ' . $customFieldValue . ' ' . $operator . ' :cfv' . $searchField . $fKey . $vKey;
                    }

                    $this->bindParamsKeys[] = 'cfv' . $searchField . $fKey . $vKey;
                    $this->bindParamsValues[] = $value;
                }

                $sql .= ')';
            }
        }

        return $sql;
    }

    /**
     * Preparse the parameters to be used in the search.
     *
     * @return void
     */
    protected function prepareParams(array $unparsed) : void
    {
        $this->relationSearchFields = array_key_exists('rparams', $unparsed) ? $this->parseRelationParameters($unparsed['rparams']) : $this->relationSearchFields;
        $this->customSearchFields = array_key_exists('cparams', $unparsed) ? $this->parseSearchParameters($unparsed['cparams'])['mapped'] : [];
        $this->normalSearchFields = array_key_exists('params', $unparsed) ? $this->parseSearchParameters($unparsed['params'])['mapped'] : [];
    }

    /**
     * Parse relationship query parameters.
     *
     * @param  array $unparsed
     *
     * @return array
     */
    protected function parseRelationParameters(array $unparsed) : array
    {
        $parseRelationParameters = [];
        $modelNamespace = Di::getDefault()->getConfig()->namespace->models;

        foreach ($unparsed as $model => $query) {
            $modelName = str_replace(' ', '', ucwords(str_replace('_', ' ', $model)));
            $modelName = $modelNamespace . '\\' . $modelName;

            if (!class_exists($modelName)) {
                throw new Exception('Related model does not exist.');
            }

            $parseRelationParameters[$modelName] = $this->parseSearchParameters($query)['mapped'];
        }

        return $parseRelationParameters;
    }

    /**
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
     *
     * @return array            An array of fieldname=>value search parameters
     */
    public function parseSearchParameters(string $unparsed) : array
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

                if (!$hasChain) {
                    $mapped[$key] = $splitField;
                } else {
                    $mapped[$key][] = $splitField;
                }

                $search[$splitField[0]] = $splitField[2];
            }
        }

        return [
            'mapped' => $mapped,
            'search' => $search,
        ];
    }

    /**
     * Parses out the subquery parameters from a request.
     *
     * in = ::, not in = !::
     *
     * Unparsed, they will look like this:
     *    internet_special(id::vehicles_id)
     * Parsed:
     *     Array('action' => in, 'firstField' => id, 'secondField' => vehicles_id,'model' => MyDealer\Models\InternetSpecial)
     *
     * *
     * @param  string $unparsed Unparsed search string
     *
     * @return array            An array of fieldname=>value search parameters
     */
    protected function parseSubquery(string $unparsed) : array
    {
        // Strip parens that come with the request string
        $tableName = explode('(', $unparsed, 2);
        //print_r($tableName);die();
        $tableName = strtolower($tableName[0]);

        $modelName = str_replace('_', ' ', $tableName);
        $modelName = str_replace(' ', '', ucwords($modelName));

        //Add the namespace to the model name
        $model = $this->config['namespace']['models'] . '\\' . $modelName;

        $unparsed = str_replace($tableName, '', $unparsed);
        $unparsed = trim($unparsed, '()');

        // Now we have an array of "key:value" strings.
        $splitFields = explode(',', $unparsed);

        if (strpos($splitFields[0], '!::') !== false) {
            $action = 'not in';
            $fieldsToRelate = explode('!::', $splitFields[0]);
        } elseif (strpos($splitFields[0], '::') !== false) {
            $action = 'in';
            $fieldsToRelate = explode('::', $splitFields[0]);
        } else {
            throw new Exception('Error Processing Subquery', 1);
        }

        $subquery = [
            'action' => $action,
            'firstField' => $fieldsToRelate[0],
            'secondField' => $fieldsToRelate[1],
            'model' => $model,
        ];

        return $subquery;
    }

    /**
     * Prepare conditions to search in record.
     *
     * @param  string $unparsed
     *
     * @return array
     */
    protected function prepareSearch(array $unparsed, bool $isSearch = false, $hasSubquery = false) : array
    {
        $statement = [
            'conditions' => '1 = 1',
            'bind' => [],
        ];

        if ($isSearch) {
            $mapped = $this->parseSearchParameters($unparsed['params']);
            $conditions = '1 = 1';

            $tmpMapped = $mapped;

            foreach ($tmpMapped as $key => $value) {
                if (strpos($value, '~') !== false) {
                    unset($tmpMapped[$key]);
                    $betweenMap[$key] = explode('~', $value);
                }
            }

            $keys = array_keys($tmpMapped);
            $values = array_values($tmpMapped);

            $di = Di::getDefault();

            foreach ($keys as $key => $field) {
                if ($di->get('config')->database->adapter == 'Postgresql') {
                    $conditions .= " AND CAST({$field} AS TEXT) LIKE ?{$key}";
                } else {
                    $conditions .= " AND {$field} LIKE ?{$key}";
                }
            }

            if (isset($betweenMap)) {
                foreach ($betweenMap as $key => $fields) {
                    $binds = count($values);
                    $conditions .= ' AND ' . $key . ' BETWEEN ?' . $binds . ' AND ?' . ($binds + 1);
                    $values = array_merge($values, $fields);
                }
            }

            if ($hasSubquery) {
                $subquery = $this->parseSubquery($unparsed['subquery']);
                $conditions .= ' AND ' . $subquery['firstField'] . ' ' . $subquery['action'] . ' (select ' . $subquery['secondField'] . ' FROM ' . $subquery['model'] . ')';
            }

            $statement = [
                'conditions' => $conditions,
                'bind' => $values,
            ];
        }

        return $statement;
    }

    /**
     * Parses out partial fields to return in the response.
     * Unparsed:
     *     (id,name,location)
     * Parsed:
     *     array('id', 'name', 'location').
     *
     * @param  string $unparsed Unparsed string of fields to return in partial response
     *
     * @return array            Array of fields to return in partial response
     */
    protected function parsePartialFields(string $unparsed) : array
    {
        $fields = explode(',', trim($unparsed, '()'));

        // Avoid returning array with empty value
        if (count($fields) == 1 && current($fields) == '') {
            return [];
        }

        return $fields;
    }

    /**
     * get the text field from this model database
     * so we can do like search.
     *
     * @param  string $table
     *
     * @return array
     */
    protected function getTextFields($table) : array
    {
        $columnsData = $this->model->getReadConnection()->describeColumns($table);
        $textFields = [];

        foreach ($columnsData as $column) {
            switch ($column->getType()) {
                case \Phalcon\Db\Column::TYPE_VARCHAR:
                case \Phalcon\Db\Column::TYPE_TEXT:
                    $textFields[] = $column->getName();
                    break;
            }
        }

        return $textFields;
    }

    /**
     * Append any defined additional parameters.
     *
     * @return void
     */
    public function appendAdditionalParams() : void
    {
        if (!empty($this->additionalSearchFields)) {
            $this->normalSearchFields = array_merge_recursive($this->normalSearchFields, $this->additionalSearchFields);
        }

        if (!empty($this->additionalCustomSearchFields)) {
            $this->customSearchFields = array_merge_recursive($this->customSearchFields, $this->additionalCustomSearchFields);
        }

        if (!empty($this->additionalRelationSearchFields)) {
            $this->relationSearchFields = array_merge_recursive($this->relationSearchFields, $this->additionalRelationSearchFields);
        }
    }

    /**
     * Append additional search parameters.
     *
     * @param array $params
     *
     * @return void
     */
    public function appendParams(array $params) : void
    {
        $this->additionalSearchFields = $params;
    }

    /**
     * Append additional search parameters.
     *
     * @param array $params
     *
     * @return void
     */
    public function appendCustomParams(array $params) : void
    {
        $this->additionalCustomSearchFields = $params;
    }

    /**
     * Append additional search parameters.
     *
     * @param array $params
     *
     * @return void
     */
    public function appendRelationParams(array $params) : void
    {
        $this->additionalRelationSearchFields = $params;
    }

    /**
     * Parse the requested columns to be returned.
     *
     * @param string $columns
     *
     * @return void
     */
    protected function parseColumns(string $columns) : void
    {
        // Split the columns string into individual columns
        $columns = explode(',', $columns);

        foreach ($columns as &$column) {
            $column = preg_replace('/[^a-zA-_Z]/', '', $column);
            if (strpos($column, '.') === false) {
                $column = "{$this->model->getSource()}.{$column}";
            } else {
                $as = str_replace('.', '_', $column);
                $column = "{$column} {$as}";
            }
        }

        $this->columns = implode(', ', $columns);
    }

    /**
     * Get the limit.
     *
     * @return int
     */
    public function getLimit() : int
    {
        return $this->limit;
    }

    /**
     * Get the page.
     *
     * @return int
     */
    public function getPage() : int
    {
        return $this->page;
    }

    /**
     * Get the offset.
     *
     * @return int
     */
    public function getOffset() : int
    {
        return $this->offset;
    }

    /**
     * Based on the given relaitonship , add the relation array to the Resultset.
     *
     * @param  string $relationships
     * @param  [array|object] $results     by reference to clean the object
     *
     * @return mixed
     */
    public static function parseRelationShips(string $relationships, &$results) : array
    {
        $relationships = explode(',', $relationships);

        $newResults = [];

        //if its a list
        if ($results instanceof ResultsetInterface && count($results) >= 1) {
            foreach ($results as $key => $result) {
                //clean records conver to array
                $newResults[$key] = $result->toFullArray();
                foreach ($relationships as $relationship) {
                    if ($results[$key]->$relationship) {
                        $callRelationship = 'get' . ucfirst($relationship);
                        $newResults[$key][$relationship] = $results[$key]->$callRelationship();
                    }
                }
            }
        } else {
            //if its only 1 record
            if ($results instanceof Model) {
                $newResults = $results->toFullArray();
                foreach ($relationships as $relationship) {
                    if ($results->$relationship) {
                        $callRelationship = 'get' . ucfirst($relationship);

                        $newResults[$relationship] = $results->$callRelationship();
                    }
                }
            }
        }

        unset($results);
        return $newResults;
    }

    /**
     * Get table columns.
     */
    public function getTableColumns() : array
    {
        $fields = $this->model->getReadConnection()->describeColumns($this->model->getSource());
        $columns = [];

        foreach ($fields as $field) {
            $columns[$field->getName()] = $field->getName();
        }

        return $columns;
    }

    /**
     * Set CustomSort for the query.
     *
     * @param string $sort
     *
     * @return string
     */
    public function setCustomSort(?string $sort) : void
    {
        if (!is_null($sort)) {
            // Get the model, column and sort order from the sent parameter.
            list($modelColumn, $order) = explode('|', $sort);
            $columnsData = $this->getTableColumns();

            $order = strtolower($order) === 'asc' ? 'ASC' : 'DESC';
            // Check to see whether this is a related sorting by looking for a .
            if (strpos($modelColumn, '.') !== false) {
                // We are using a related sort.
                // Get the namespace for the models from the configuration.
                $modelNamespace = Di::getDefault()->getConfig()->namespace->models;
                // Get the model name and the sort column from the sent parameter
                list($model, $column) = explode('.', $modelColumn);

                $modelColumn = preg_replace("/[^a-zA-Z0-9_\s]/", '', $modelColumn);
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
                $metaData = new MetaDataMemory();

                // Get the first matching primary key.
                // @TODO This will hurt on compound primary keys.
                $primaryKey = $metaData->getPrimaryKeyAttributes($modelObject)[0];
                // We need the table to exist in the query in order for the related sort to work.
                // Therefore we add it to comply with this by comparing the primary key to not being NULL.
                if ($metaData->hasAttribute($modelObject, $column)) {
                    $this->relationSearchFields[$modelName][] = [
                        $primaryKey, ':', '$$',
                    ];

                    $this->sort = " ORDER BY {$modelObject->getSource()}.{$column} {$order}";
                }
                unset($modelObject);
            } else {
                if (isset($columnsData[$modelColumn])) {
                    $this->sort = " ORDER BY {$modelColumn} {$order}";
                }
            }
        }
    }
}
