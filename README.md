# 🔍 Search Query Parser

The Query String Parser library performs search query text parsing.

This library is perfect for integrating complex search (like Google search) into your application. Small part of base code (javascript) is come from https://github.com/nepsilon/search-query-parser

## Example usage:
```php
$parser = new dobron\SearchQueryParser\Parser([
  'keywords' => 'site,title,inurl'
]);
$result = $parser->parse('site:en.wikipedia.org/ title:Slovakia "cities and towns" -education inurl:wiki/');

echo json_encode($result, JSON_PRETTY_PRINT);

var_dump($result->getQueries());
```

## Output:
```json
{
    "text": [
        {
            "column": "text",
            "operator": "like",
            "negate": false,
            "value": "%cities and towns%"
        }
    ],
    "match": {
        "site": {
            "column": "site",
            "operator": "=",
            "negate": false,
            "value": "en.wikipedia.org\/"
        },
        "title": {
            "column": "title",
            "operator": "=",
            "negate": false,
            "value": "Slovakia"
        },
        "inurl": {
            "column": "inurl",
            "operator": "=",
            "negate": false,
            "value": "wiki\/"
        }
    },
    "excluded": {
        "text": [
            {
                "column": "text",
                "operator": "not like",
                "negate": true,
                "value": "%education%"
            }
        ]
    },
    "offsets": [
        {
            "keyword": "site",
            "value": "en.wikipedia.org\/",
            "offsetStart": 0,
            "offsetEnd": 22
        },
        {
            "keyword": "title",
            "value": "Slovakia",
            "offsetStart": 23,
            "offsetEnd": 37
        },
        {
            "text": "cities and towns",
            "offsetStart": 38,
            "offsetEnd": 54
        },
        {
            "keyword": "inurl",
            "value": "wiki\/",
            "offsetStart": 68,
            "offsetEnd": 79
        }
    ]
}
```

```text
array(5) {
  [0]=>
  array(4) {
    ["column"]=>
    string(4) "site"
    ["operator"]=>
    string(1) "="
    ["negate"]=>
    bool(false)
    ["value"]=>
    string(17) "en.wikipedia.org/"
  }
  [1]=>
  array(4) {
    ["column"]=>
    string(5) "title"
    ["operator"]=>
    string(1) "="
    ["negate"]=>
    bool(false)
    ["value"]=>
    string(8) "Slovakia"
  }
  [2]=>
  array(4) {
    ["column"]=>
    string(5) "inurl"
    ["operator"]=>
    string(1) "="
    ["negate"]=>
    bool(false)
    ["value"]=>
    string(5) "wiki/"
  }
  [3]=>
  array(4) {
    ["column"]=>
    string(4) "text"
    ["operator"]=>
    string(8) "not like"
    ["negate"]=>
    bool(true)
    ["value"]=>
    string(11) "%education%"
  }
  [4]=>
  array(4) {
    ["column"]=>
    string(4) "text"
    ["operator"]=>
    string(4) "like"
    ["negate"]=>
    bool(false)
    ["value"]=>
    string(18) "%cities and towns%"
  }
}
```

### Options:
* `keywords`, that can be separated by commas (,). Accepts an array of strings.
* `ranges`, that can be separated by a hyphen (-). Accepts an array of strings.
* `offsets`, a boolean controls the behaviour of the returned query. If set to `true`, the query will contain the offsets object. If set to `false`, the query will not contain the offsets object. Defaults to `true`.

### Queries:

| Type                                        | Example                                    |
| ------------------------------------------- | ------------------------------------------ |
| Query for tags                              | `cat`                                      |
| Query for multiple tags                     | `cat dog`, `"Hello World"`                 |
| Exclude results containing a certain word   | `cat -dog`                                 |
| Query for equality                          | `author:John Snow`, `author:"John Snow"`   |
| Query for multiple equality                 | `author:me,John Snow`                      |
| Query for values in range                   | `date:2000/01/01-2020/01/01`, `price:5-50` |
| Query for values greater than another value | `price:>10`, `price:>=10`                  |
| Query for values less than another value    | `price:<100`, `price:<=100`                |
| Mix query for tags and condition            | `price:>10 price:<100`                     |
| Filter qualifiers based on exclusion        | `price:>10 -language:php`                  |

### How to use it?

Install it using:

```shell
composer require dobron/query-string-parser
```

### Test:
```shell
php vendor/bin/phpunit --testdox tests
```