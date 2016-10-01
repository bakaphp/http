# Baka Http

Baka Http


# QueryParser

Parse GET request for a API , giving the user the correct phalcon model params to perform a search

`GET - /v1/?q=(searchField1:value1,searchField2:value2)&fields=id_pct,alias,latitude,longitude,category,chofer,phone,coords,last_report&limit=1&page=2&sort=id_pct|desc`

```
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