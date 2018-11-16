# Baka Http

PhalconPHP package to create fast RESTful API's providing a simple way to create fast CRUD's

## Table of Contents
1. [Testing](#markdown-header-testing)
2. [REST CRUD](#markdown-header-routes)
    1. [Controller Configuration](#markdown-header-controllers)
3. [QueryParser](#markdown-header-QueryParser)
4. [QueryParser Extended](#markdown-header-QueryParser-Extended)

## Testing
```
codecept run
```

## Routes Configuration

To avoid having to create Controller for CRUD api we provide the \Baka\Http\Rest\CrudController

Add to your routes.php

```php
<?php
/**
 * Need to understand if using this can be a performance disadvantage in the future
 */
$defaultCrudRoutes = [
    'business',
    'clients',
    'contacts',
    'modules',
    'customFields' => 'custom-fields',
    'leads',
    'products',
    'productType' => 'product-type',
    'users',
    'sellers',
];

$router = new RouterCollection($application);

foreach ($defaultCrudRoutes as $key => $route) {

    //set the controller name
    $name = is_int($key) ? $route : $key;
    $controllerName = ucfirst($name) . 'Controller';

    $router->get('/v1/' . $route, [
      'Mesocom\Controllers\\' . $controllerName,
      'index',
    ]);

    $router->post('/v1/' . $route, [
      'Mesocom\Controllers\\' . $controllerName,
      'create',
    ]);

    $router->get('/v1/' . $route . '/{id}', [
      'Mesocom\Controllers\\' . $controllerName,
      'getById',
    ]);

    $router->put('/v1/' . $route . '/{id}', [
      'Mesocom\Controllers\\' . $controllerName,
      'edit',
    ]);

    $router->delete('/v1/' . $route . '/{id}', [
      'Mesocom\Controllers\\' . $controllerName,
      'delete',
    ]);

    /**
    * Mounting routes
    */
    $router->mount();
}
```

## Controller configuration

Add

```php
<?php

class AnyController extends Baka\Http\Rest\CrudController

/**
 * set objects
 *
 * @return void
 */
public function onConstruct()
{
    $this->model = new Clients();
    $this->customModel = new ClientsCustomFields();
}
```


# QueryParser

Parse GET request for a API , giving the user the correct phalcon model params to perform a search

```
//search by fieds and specify the list of fields
GET - /v1/?q=(searchField1:value1,searchField2:value2)&fields=id_pct,alias,latitude,longitude,category,chofer,phone,coords,last_report&limit=1&page=2&sort=id_pct|desc

//filter by relationships
GET - /v1/?q=(searchField1:value1,searchField2:value2)&with=vehicles_media[seriesField:value] 

//add to the array a relationship of this model
GET - /v1/?q=(searchField1:value1,searchField2:value2)&with=vehicles_media[seriesField:value]&relationships=direccione
```


```php
<?php

$parse = new QueryParser($this->request->getQuery());
$parse->request();

[conditions] => 1 = 1 AND searchField1 = ?1 AND searchField2 = ?2
[bind] => Array
    (
        [1] => value1
        [2] => value2
    )

[columns] => Array
    (
        [0] => id_pct
        [1] => alias
        [2] => latitude
        [3] => longitude
        [4] => category
        [5] => chofer
        [6] => phone
        [7] => coords
        [8] => last_report
    )

[order] => id_pct desc
[limit] => 10
[offset] => 10
```

# ~~QueryParser CustomFields~~ (DEPRECATED)

Parse GET request for a API , given the same params as before but with cq (for custom domains) , this give out a normal SQL statement for a raw query

`GET - /v1/?q=(searchField1:value1,searchField2:value2)&cq=(member_id:1)&q=(leads_status_id:1)`
 relationship of this model


```php
<?php

$request = $this->request->getQuery();
$parse = new QueryParserCustomFields($request, $this->model);
$params = $parse->request();
$newRecordList = [];

$recordList = (new SimpleRecords(null, $this->model, $this->model->getReadConnection()->query($params['sql'], $params['bind'])));

//navigate los records
$newResult = [];
foreach ($recordList as $key => $record) {

    //field the object
    foreach ($record->getAllCustomFields() as $key => $value) {
        $record->{$key} = $value;
    }

    $newResult[] = $record->toFullArray();
}

unset($recordList);
```

# QueryParser Extended

The extended query parser allows you to append search parameters directly via the controller without having to rewrite the function code.

Features include the ability to search within a model, within a model's custom fields and within a model's descendant relationships.

Parameters are passed in the format `field` `operator` `value`. Valid operators are `:`, `>`, `<`.

Multiple fields can be search by separating them with a `,`. You can search a field by several values by separating said values with `|` (equivalent to SQL's `OR`).

### Query the Model
`GET - /v1/model?q=(field1:value1,field2:value2|value3)`

### Query the Custom Fields
`GET - /v1/model?cq=(field1>value1)`

### Query related Models
Querying related models demands a slightly different structure. Each related model that we want queried must be passed as they are named in the system, `_` is used to separate camel cases.

`GET - /v1/model?rq[model_name]=(field1<value1|value2)`

### `Between`
While between is strictly not supported at this time, you can produce the same result following this procedure:

`GET - /v1/model?q=(field1>value1,field1<value2)`

### Like, Empty or Not
There is another nice feature that you can use to query the model.

#### Like
* `GET - /v1/model?q=(field1:%value)`
* `GET - /v1/model?q=(field1:value%)`
* `GET - /v1/model?q=(field1:%value%)`

#### Empty
You can tell the query parser to make sure a field is empty. In the case of integer properties, the query parser will ask the model if the default value for a property is `0`. If it is, it will include `0` as an empty value.

`GET - /v1/model?q=(field1:%%)`

#### Not
This is the opposite of the above Empty.

`GET - /v1/model?q=(field1:$$)`

### One for all, and all for One
You can use all the above described feature together in one query.

`GET - /v1/model?q=(field1:value1|value2,field2>value3,field2<value4,field3:$$)&cq=(field4:value5)&rq[model_name]=(field5>value6)`

_Just remember to escape any special character you want to send through a query string to avoid unwanted results._

## Usage
In order to access the extended query parser features your controller has to extend from `CrudExtendedController`.

```php
<?php

class ExampleController extends \Baka\Http\Rest\CrudExtendedController
```

To append additional search parameters you simply do this:
```
<?php

public function index($id = null): Response
{
    $this->additionalSearchFields = [
        ['field', ':', 'value'],
    ];

    return parent::index();
}
```

This method uses the operators that are passed to the query parser via the URL query. Valid operators are (with their SQL equivalents):
```php
<?php

$operators = [
    ':' => '=',
    '>' => '>=',
    '<' => '<=',
];
```

# API Custom Fields CRUD

The CRUD handles the default behavior:
- GET /v1/leads -> get all
- GET /v1/leads/1 -> get one
- POST /v1/leads -> create
- PUT /v1/leads/1 -> update
- DELETE /v1/leads/1 -> delete

In other to use the custom fields you need to extend you controller from CrudCustomFieldsController and define the method `onConstruct()` on this method you define the model of the custom field and the model of the value of this custom fields

```php
<?php
public function onConstruct()
{
    $this->model = new Leads();
    $this->customModel = new LeadsCustomFields();
}
```

Thats it, your controller now manages the custom fields as if they wher properties of the main class

# Normal API CRUD

Just extend your API controller from CrudController and you will have the following functions:

The CRUD handles the default behaviero:
- GET /v1/leads -> get all
- GET /v1/leads/1 -> get one
- POST /v1/leads -> create
- PUT /v1/leads/1 -> update
- DELETE /v1/leads/1 -> delete

createFields and updateFields are needed to be define in other to create the field
