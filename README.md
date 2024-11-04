# Kristian HTTP Client

## Dependency
- PHP: minimum version 5.3, curl or file_get_contents
- For automated tests only: composer, git, unzip
- The goal of this project is to be of maximum compatibility with php of any version
- PHP 5.3 minimum above is only assumed because it's the lowest php version this library has been tested against, if its known to be compatible with lower version, please report so I can update the above information

## Usage
- without composer:
    - download file `src/HTTPClient.php`, then rename it into `KristianHTTPClient.php`
    - optionally, rename class into `KristianHTTPClient` by editing `KristianHTTPClient.php`, or, just import it with alias e.g.: `use KristianTan\HTTPClient as KristianHTTPClient`
    - note: `KristianHTTPClient` is old name before using composer and namespace, importing `KristianTan\HTTPClient` with alias is intended to maintain backward compatibility
```php
require_once("KristianHTTPClient.php");
use KristianTan\HTTPClient as KristianHTTPClient;
$client = new KristianHTTPClient();
$client->request_url = "http://localhost/www/index.php";
$client->execute();
var_dump($client->response_code);
```
- using composer:
    - `composer require kristian-tan/http-client`
```php
require_once("vendor/autoload.php");
use KristianTan\HTTPClient as KristianHTTPClient;
$client = new KristianHTTPClient();
$client->request_url = "http://localhost/www/index.php";
$client->execute();
var_dump($client->response_code);
```


### Inputs
- `request_url` = string
- `request_port` = int (if not set, will default to `80` for http and `443` for https)
- `request_header` = array (default empty array), should be filled with either:
    - `array("MyKey1: MyValue1","MyKey2: MyValue2") `, or
    - `array("MyKey1" => "MyValue1","MyKey2" => "MyValue2")`
- `request_method` = string (default `"GET"`)
- `request_body` = string (default `null`), request body (also called post variable) that can be assigned to contain json (must not be set if using curl api with http GET method)
- `php_api` = string enum `"file_get_contents"` or `"curl"` or `"gnu_curl"` (default `"file_get_contents"`), php api to be used when making request
- `ssl_verify` = boolean (default `true`),
- `ssl_cacert_file` = string (default `"cacert.pem"`), path to cacert file
- `ssl_cacert_directory` = string (default `"/etc/ssl/certs"`), path to cacert directory (for curl api only)
- `request_http_proxy` = string (if not set, will not use http proxy), http proxy to be used when making request (not SOCKS proxy, SOCKS not supported), format `host:port`
- `request_http_timeout` = int (if not set, will use respective php_api's default), timeout in seconds
- `debug` = string enum (default `null`) or `"var"` or `"echo"` or a function/callable, where:
    - `null` = discard all debug information,
    - `"var"` = save to variable,
    - `"echo"` = print debug information to stdout/echo command,
    - function/callable = call the function/callable, with first argument containing multiline string containing all debug information
- `debug_level` = string enum (default `"NONE"`), enum `"NONE"`,`"ERROR"`,`"WARNING"`,`"INFO"`,`"DEBUG"`

### Outputs
- `response_code` = int
- `response_body` = string
- `response_header` = array (will be formatted in 2 dimensional array, example: `[["MyKey1"=>"MyValue1"],["MyKey2"=>"MyValue2"]]`)
- `debug_output` = string, will only be written if `$client->debug="var";` is set (and `debug_level` not NONE)

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

### GNU CURL API
- for systems without curl php extension enabled, and when file_get_contents also broke (e.g.: http headers spilling into response body or getaddrinfo error), and you also don't have sudo/admin to the target machine, then php_api=gnu_curl may be tried
- first, install curl via your package manager (or if you don't have root or if your package manager is no longer supported, download a statically linked curl binary from https://github.com/moparisthebest/static-curl)
- set the permission of curl binary to be executable (it should be automatic if installing via package manager, otherwise it can be done with: ```chmod a+x ./bin/curl```, assuming you put it in ./bin inside your php directory)
- add the directory that contains curl binary to PATH variable (it should be automatic if installing via package manager, otherwise it can be done with such php code: ```putenv("PATH=".getenv("PATH").":".getcwd()."/bin");```, assuming you put it in ./bin inside your php directory)

### Debugging/Logging Example
- save debug information to variable, then dump it:
```php
<?php
$client = new KristianHTTPClient();
$client->request... = ...;

$client->debug = "var"; // save debug information to variable $client->debug_output
$client->debug_level = "DEBUG"; // highest/most verbose debug
$client->execute();
var_dump($client->debug_output);
```

- print debug information (simple):
```php
<?php
$client = new KristianHTTPClient();
$client->request... = ...;

$client->debug = "echo"; // just print the debug information
$client->debug_level = "DEBUG"; // highest/most verbose debug
$client->execute();
```

- do something to the debug information (example: log to syslog):
```php
<?php
$client = new KristianHTTPClient();
$client->request... = ...;

$client->debug = function($string) {
    openlog("kristian-http-client", LOG_NDELAY, LOG_LOCAL4);
    syslog(LOG_DEBUG, $string);
    closelog();
};
$client->debug_level = "DEBUG"; // highest/most verbose debug
$client->execute();
```
