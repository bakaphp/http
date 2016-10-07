<?php

namespace Baka\Http;

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
class QueryParser
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
        $params = '';
        $isSearch = false;

        //verify the user is search for something
        if (array_key_exists('q', $this->request)) {
            $params = $this->request['q'];
            $isSearch = true;
        }

        //initialize the model params
        $modelSearchParams = $this->prepareSearch($params, $isSearch);

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
        $unparsed = urldecode($unparsed);
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
     * Prepare conditions to search in record
     *
     * @param  string $unparsed
     * @return array
     */
    protected function prepareSearch(string $unparsed, bool $isSearch = false): array
    {
        $statement = [
            'conditions' => "1 = 1",
            'bind' => [],
        ];

        if ($isSearch) {
            $mapped = $this->parseSearchParameters($unparsed);
            $conditions = '1 = 1';
            $keys = array_keys($mapped);
            array_unshift($keys, 1);
            unset($keys[0]);

            $values = array_values($mapped);
            array_unshift($values, 1);
            unset($values[0]);
            foreach ($keys as $key => $field) {
                $conditions .= " AND {$field} = ?{$key}";
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
}
