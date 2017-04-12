# Baka Http

Baka Http

## Table of Contents
1. [Testing](#markdown-header-testing)
2. [REST CRUD](#markdown-header-routes)
    1. [Controller Configuration](#markdown-header-controllers)
3. [QueryParser](#markdown-header-QueryParser)

## Testing
```
codecept run
```

## Routes Configuration

To avoid having to create Controller for CRUD api we provide the \Baka\Http\Rest\CrudController

Add to your routes.php

```
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

```
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

`GET - /v1/?q=(searchField1:value1,searchField2:value2)&fields=id_pct,alias,latitude,longitude,category,chofer,phone,coords,last_report&limit=1&page=2&sort=id_pct|desc`
`GET - /v1/?q=(searchField1:value1,searchField2:value2)&with=vehicles_media[seriesField:value]` //filter by relationships
`GET - /v1/?q=(searchField1:value1,searchField2:value2)&with=vehicles_media[seriesField:value]&relationships=direcciones` //add to the array a relationship of this model


```
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

# QueryParser CustomFields

Parse GET request for a API , given the same params as before but with cq (for custom domains) , this give out a normal SQL statement for a raw query

`GET - /v1/?q=(searchField1:value1,searchField2:value2)&cq=(member_id:1)&q=(leads_status_id:1)`
 relationship of this model


```
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


# API Custom Fields CRUD

The CRUD handles the default behaviero:
- GET /v1/leads -> get all
- GET /v1/leads/1 -> get one
- POST /v1/leads -> create
- PUT /v1/leads/1 -> update
- DELETE /v1/leads/1 -> delete

In other to use the custom fields you need to extend you controller from CrudCustomFieldsController and define the method onConstruct on this method you define the model of the custom field and the model of the value of this custom fields

```
public function onConstruct()
{
    $this->model = new Leads();
    $this->customModel = new LeadsCustomFields();
}
```

Thats it, your controller now manages the custom fields as if they wher properties of the main class

# Normal API CRUD

Just extend your API controller from CrudController and you will have the following functions

The CRUD handles the default behaviero:
- GET /v1/leads -> get all
- GET /v1/leads/1 -> get one
- POST /v1/leads -> create
- PUT /v1/leads/1 -> update
- DELETE /v1/leads/1 -> delete

createFields and updateFields are needed to be define in other to create the field
