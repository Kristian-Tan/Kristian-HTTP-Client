# Kristian HTTP Client

## Dependency
- PHP: minimum version 5.3, curl or file_get_contents

## Usage
```php
require_once("KristianHTTPClient.php");
$client = new KristianHTTPClient();
$client->request_url = "http://localhost/www/index.php";
$client->execute();
var_dump($client->response_code);
```

### Inputs
- request_url = string
- request_port = int (if not set, will default to 80 for http and 443 for https)
- request_header = array (default empty array),
    may be filled with ```["MyKey1: MyValue1","MyKey2: MyValue2"] ``` or ```[["MyKey1"=>"MyValue1"],["MyKey2"=>"MyValue2"]]```
- request_method = string (default "GET")
- request_body = string (default null), request body (also called post variable) that can be assigned to contain json
- php_api = string enum "file_get_contents" or "curl" (default "file_get_contents"), php api to be used when making request
- ssl_verify = boolean (default true),
- ssl_cacert_file = string (default "cacert.pem"), path to cacert file

### Outputs
- response_code = int
- response_body = string
- response_header = array (will be formatted in 2 dimensional array, example: ```[["MyKey1"=>"MyValue1"],["MyKey2"=>"MyValue2"]]```)
