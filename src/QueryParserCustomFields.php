<?php

namespace Baka\Http;

use Baka\Database\CustomFields\CustomFields;
use Baka\Database\CustomFields\Modules;
use Baka\Database\Model;

/**
 * Base QueryParser. Parse GET request for a API to a array Phalcon Model find and FindFirst can intepret
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
class QueryParserCustomFields extends QueryParser
{
    /**
     * @var array
     */
    protected $request;

    /**
     * @param Baka\Database\Model
     */
    protected $model;

    /**
     * Pass the request
     * @param array $request [description]
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
    public function request(): array
    {
        $params = [
            'params' => '',
            'subquery' => '',
        ];

        $customSearch = false;
        $hasSubquery = false;

        //if we find that we are using custom field this is a different beast so we have to send it
        //to another functino to deal with this shit
        if (array_key_exists('cq', $this->request)) {
            $params['cparams'] = $this->request['cq'];
            $customSearch = true;
        }

        //verify the user is searching for something
        if (array_key_exists('q', $this->request)) {
            $params['params'] = $this->request['q'];
        }

        //base on th eesarch params get the raw query
        $rawSql = $this->prepareSearch($params, $customSearch);

        //now lets update the querys

        //filter the field
        if (array_key_exists('fields', $this->request)) {
            $fields = $this->request['fields'];

            $modelSearchParams['columns'] = $this->parsePartialFields($fields);
        }

        // Set limits and offset, elsewise allow them to have defaults set in the Controller
        $page = array_key_exists('page', $this->request) ? $this->request['page'] : 1;
        if (array_key_exists('limit', $this->request)) {
            $limit = $this->request['limit'];
        }

        //sort
        if (array_key_exists('sort', $this->request) && !empty($this->request['sort'])) {
            $sort = $this->request['sort'];
            $sort = str_replace('|', ' ', $sort);

            $rawSql['sql'] .= ' ORDER BY ' . $sort;
        }

        //limit
        if (isset($limit)) {
            $offset = ($page - 1) * $limit;

            $limit = $limit;
            $offset = $offset;
            $rawSql['sql'] .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
        }

        return $rawSql;
    }

    /**
     * gien the request array , get the custom query to find the results
     *
     * @param  array  $params
     * @return string
     */
    protected function prepareSearch(array $unparsed, bool $customSearch = false, $hasSubquery = false): array
    {
        $classReflection = (new \ReflectionClass($this->model));
        $classname = $this->model->getSource();
        $customClassname = $classname . '_custom_fields';
        $bindParamsKeys = [];
        $bindParamsValues = [];

        $customSearchFields = $customSearch ? $this->parseSearchParameters($unparsed['cparams'])['mapped'] : null;
        $normalSearchFields = $this->parseSearchParameters($unparsed['params'])['mapped'];

        $sql = '';

        $operators = [
            ':' => '=',
            '>' => '>=',
            '<' => '<=',
        ];

        // create custom query sql
        if (!empty($customSearchFields)) {
            $modules = Modules::findFirstByName($classReflection->getShortName());

            $sql .= ' INNER JOIN ' . $customClassname . ' ON ' . $customClassname . '.id = (';
            $sql .= 'SELECT ' . $customClassname . '.id FROM ' . $customClassname . ' WHERE ' . $customClassname . '.' . $classname . '_id = ' . $classname . '.id';

            foreach ($customSearchFields as $fKey => $searchFieldValues) {
                list($searchField, $operator, $searchValue) = $searchFieldValues;
                $operator = $operators[$operator];

                if (trim($searchValue) !== '') {
                    $customFields = CustomFields::findFirst([
                        'modules_id = ?0 AND name = ?1',
                        'bind' => [$modules->id, $searchField],
                    ]);

                    $sql .= ' AND ' . $customClassname . '.custom_fields_id = :cfi' . $searchField;

                    $bindParamsKeys[] = 'cfi' . $searchField;
                    $bindParamsValues[] = $customFields->id;

                    if ($searchValue == '%%') {
                        $sql .= ' AND (' . $customClassname . '.value IS NULL OR ' . $customClassname . '.value = "")';
                    } elseif ($searchValue == '$$') {
                        $sql .= ' AND (' . $customClassname . '.value IS NOT NULL OR ' . $customClassname . '.value != "")';
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
                                $sql .= ' AND (' . $customClassname . '.value ' . $operator . ' :cfv' . $searchField . $fKey . $vKey;
                            } else {
                                $sql .= ' OR ' . $customClassname . '.value ' . $operator . ' :cfv' . $searchField . $fKey . $vKey;
                            }

                            $bindParamsKeys[] = 'cfv' . $searchField . $fKey . $vKey;
                            $bindParamsValues[] = $value;
                        }

                        $sql .= ')';
                    }
                }
            }

            $sql .= ' LIMIT 1)';
        }

        $sql .= ' WHERE 1 = 1';

        // create normal sql search
        if (!empty($normalSearchFields)) {
            $textFields = $this->getTextFields($classname);

            foreach ($normalSearchFields as $fKey => $searchFieldValues) {
                list($searchField, $operator, $searchValues) = $searchFieldValues;
                $operator = $operators[$operator];

                if (trim($searchValues) !== '') {
                    if ($searchValues == '%%') {
                        $sql .= ' AND (' . $classname . '.' . $searchField . ' IS NULL';
                        $sql .= ' OR ' . $classname . '.' . $searchField . ' = ""';

                        if ($this->model->$searchField === 0) {
                            $sql .= ' OR ' . $classname . '.' . $searchField . ' = 0';
                        }

                        $sql .= ')';
                    } elseif ($searchValues == '$$') {
                        $sql .= ' AND (' . $classname . '.' . $searchField . ' IS NOT NULL';
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
                                $sql .= ' AND (' . $classname . '.' . $searchField . ' ' . $operator . ' :f' . $searchField . $fKey . $vKey;
                            } else {
                                $sql .= ' OR ' . $classname . '.' . $searchField . ' ' . $operator . ' :f' . $searchField . $fKey . $vKey;
                            }

                            $bindParamsKeys[] = 'f' . $searchField . $fKey . $vKey;
                            $bindParamsValues[] = $value;
                        }

                        $sql .= ')';
                    }
                }
            }
        }

        //sql string
        $resultsSql = 'SELECT ' . $classname . '.*' . ' FROM ' . $classname . $sql;
        //bind params
        $bindParams = array_combine($bindParamsKeys, $bindParamsValues);

        return [
            'sql' => $resultsSql,
            'bind' => $bindParams,
        ];
    }

    /**
     * Parses out the search parameters from a request.
     * Unparsed, they will look like this:
     *    (name:Benjamin Framklin,location:Philadelphia)
     * Parsed:
     *     array('name'=>'Benjamin Franklin', 'location'=>'Philadelphia')
     *
     * @param  string $unparsed Unparsed search string
     * @return array            An array of fieldname=>value search parameters
     */
    protected function parseSearchParameters(string $unparsed): array
    {
        // $unparsed = urldecode($unparsed);
        // Strip parens that come with the request string
        $unparsed = trim($unparsed, '()');

        // Now we have an array of "key:value" strings.
        $splitFields = explode(',', $unparsed);
        $mapped = [];
        $search = [];

        // Split the strings at their colon, set left to key, and right to value.
        foreach ($splitFields as $field) {
            $splitField = preg_split('#(:|>|<)#', $field, -1, PREG_SPLIT_DELIM_CAPTURE);

            // TEMP: Fix for strings that contain semicolon
            if (count($splitField) > 3) {
                $splitField[2] = implode('', array_splice($splitField, 2));
            }

            $mapped[] = $splitField;
            $search[$splitField[0]] = $splitField[2];
        }

        return [
            'mapped' => $mapped,
            'search' => $search,
        ];
    }

    /**
     * get the text field from this model database
     * so we can do like search
     *
     * @param  string $table
     * @return array
     */
    private function getTextFields($table): array
    {
        $columnsData = $this->db->describeColumns($table);
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
}
