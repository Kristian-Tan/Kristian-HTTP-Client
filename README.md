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
- request_header = array (default empty array), should be filled with `["MyKey1: MyValue1","MyKey2: MyValue2"] `
- request_method = string (default "GET")
- request_body = string (default null), request body (also called post variable) that can be assigned to contain json (must not be set if using curl api with http GET method)
- php_api = string enum "file_get_contents" or "curl" (default "file_get_contents"), php api to be used when making request
- ssl_verify = boolean (default true),
- ssl_cacert_file = string (default "cacert.pem"), path to cacert file
- ssl_cacert_directory = string (default "/etc/ssl/certs"), path to cacert directory (for curl api only)

### Outputs
- response_code = int
- response_body = string
- response_header = array (will be formatted in 2 dimensional array, example: `[["MyKey1"=>"MyValue1"],["MyKey2"=>"MyValue2"]]`)

### Recommendations
- use file_get_content api, it's more supported (no need to install php-curl extension) and more flexible (can use post body with GET method), but cannot use cacert directory (must be a single file)
- response code will be 0 if request failed (ex: timeout, no internet connection, blocked by firewall, etc); please identify the cause by popular tools like wget/curl/telnet (or my python tool like `simple_tcp` module)
- run my test for sanity check:
    - install php, composer, git, unzip
    - go to `tests` directory then `composer install`
    - run webserver with `./vendor/bin/hyper-run -S 0.0.0.0:80 -s 0.0.0.0:443 -t . &` (using package `mpyw/php-hyper-builtin-server`)
    - run automated tests with `php run.php -hlocalhost -p`
- customize automated tests:
    - pass flags with `-pvalue`: that means that flag `-p` is set to `value` (must not be space-separated)
    - flag `-h` sets the host that the http request library should make a request to (normally should be set to localhost; except if testing ssl host verification, it should be pointed to FQDN that point to local machine)
    - flag `-p` sets url path prefix (normally should be empty string; except if you're serving the test from custom webserver in url http://your.localhost/some/path/here/kristian-http-client/ you should set it to `-p/some/path/here/kristian-http-client/tests`)
    - flag `-s` skips ssl (https) tests, useful for machine without https support (sacrificing test coverage)
    - flag `-v` forces ssl (https) host name verification against ca certificate (without this flag, the tests skips ssl verification by default; this behavior is intentional for easier test and easier integrations with ci/cd tools)
    - please note that the tests are just making a custom http request to local machine to file `tests/capture_request_json.php`, and that script will save the request content into json file `tests/capture_request_json.php`, which will be read and compared by the actual request
    - test coverage are 12 tests using method `GET`, `POST`, and `PUT`; using schema `HTTP` and `HTTPS`; using `curl` and `file_get_content` api
