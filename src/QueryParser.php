<?php

namespace Baka\Http;

use Phalcon\Mvc\Model\ResultsetInterface;
use Phalcon\Mvc\Model;

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
class QueryParser extends \Phalcon\Di\Injectable
{
    /**
     * @var array
     */
    protected $request;

    /**
     * Pass the request
     * @param array $request [description]
     */
    public function __construct(array $request)
    {
        $this->request = $request;
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

        $isSearch = false;
        $hasSubquery = false;

        //verify the user is searching for something
        if (array_key_exists('q', $this->request)) {
            $params['params'] = $this->request['q'];
            $isSearch = true;
        }

        //verify the request has a subquery
        if (array_key_exists('subq', $this->request)) {
            $params['subquery'] = $this->request['subq'];
            $hasSubquery = true;
        }

        //initialize the model params
        $modelSearchParams = $this->prepareSearch($params, $isSearch, $hasSubquery);

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

            $modelSearchParams['order'] = $sort;
        }

        //limit
        if (isset($limit)) {
            $offset = ($page - 1) * $limit;

            $modelSearchParams['limit'] = $limit;
            $modelSearchParams['offset'] = $offset;
        }

        return $modelSearchParams;
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
        // Strip parens that come with the request string
        $unparsed = trim($unparsed, '()');

        // Now we have an array of "key:value" strings.
        $splitFields = explode(',', $unparsed);
        $mapped = [];

        // Split the strings at their colon, set left to key, and right to value.
        foreach ($splitFields as $field) {
            $splitField = explode(':', $field);
            $mapped[$splitField[0]] = $splitField[1];
        }

        return $mapped;
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
     * @return array            An array of fieldname=>value search parameters
     */
    protected function parseSubquery(string $unparsed): array
    {
        // Strip parens that come with the request string
        $tableName = explode("(", $unparsed, 2);
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
            throw new \Exception("Error Processing Subquery", 1);
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
     * Prepare conditions to search in record
     *
     * @param  string $unparsed
     * @return array
     */
    protected function prepareSearch(array $unparsed, bool $isSearch = false, $hasSubquery = false): array
    {
        $statement = [
            'conditions' => "1 = 1",
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

            foreach ($keys as $key => $field) {
                $conditions .= " AND {$field} LIKE ?{$key}";
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
     *     array('id', 'name', 'location')
     *
     * @param  string $unparsed Unparsed string of fields to return in partial response
     * @return array            Array of fields to return in partial response
     */
    protected function parsePartialFields(string $unparsed): array
    {
        $fields = explode(',', trim($unparsed, '()'));

        // Avoid returning array with empty value
        if (count($fields) == 1 && current($fields) == '') {
            return [];
        }

        return $fields;
    }

    /**
     * Based on the given relaitonship , add the relation array to the Resultset
     *
     * @param  string $relationships
     * @param  [array|object] $results     by reference to clean the object
     * @return mixed
     */
    public static function parseRelationShips(string $relationships, &$results): array
    {
        $relationships = explode(',', $relationships);

        $newResults = [];

        //if its a list
        if ($results instanceof ResultsetInterface && count($results) >= 1) {
            foreach ($results as $key => $result) {
                //clean records conver to array
            $newResults[$key] = $result->toArray();
                foreach ($relationships as $relationship) {
                    if ($results[$key]->$relationship) {
                        $newResults[$key][$relationship] = $results[$key]->$relationship;
                    }
                }
            }
        } else {
            //if its only 1 record
            if ($results instanceof Model) {
                $newResults = $results->toArray();
                foreach ($relationships as $relationship) {
                    if ($results->$relationship) {
                        $newResults[$relationship] = $results->$relationship;
                    }
                }
            }
        }

        unset($results);
        return $newResults;
    }
}
