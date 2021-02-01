<?php

require_once("../KristianHTTPClient.php");

function assertEq($expected, $real, $msg=null, $dumpOnError=null) {
	if(empty($msg)) $msg = "";
	else $msg = ", message = '$msg' ";
	if($expected != $real) { 
        if(!empty($dumpOnError)) print_r($dumpOnError);
		throw new Exception("Assertion failed: expected = '$expected' while real = '$real' $msg \r\n", 1);
	} else {
		echo "Assertion passed: '$real' $msg \r\n";
	}
}

$uriHost = "dev244.ubaya.ac.id";
$uriPrefix = "/pwa-starter-demo-min3/kristian-http-client/tests";

$options = getopt("h::p::s::v::");
if(isset($options["h"])) $uriHost = $options["h"];
if(isset($options["p"])) $uriPrefix = $options["p"];
$isSkipHttps = isset($options["s"]);
$isVerifySsl = isset($options["v"]);

$testParameters = array(
    array(
        "uriPathToCaptureRequest" => $uriPrefix."/capture_request_json.php", 
        "uriHost" => $uriHost,
        "uriSchema" => "http://",
        "uriMethod" => "POST",
        "api" => "file_get_contents",
    ),
    array(
        "uriPathToCaptureRequest" => $uriPrefix."/capture_request_json.php", 
        "uriHost" => $uriHost,
        "uriSchema" => "https://",
        "uriMethod" => "POST",
        "api" => "file_get_contents",
    ),
    array(
        "uriPathToCaptureRequest" => $uriPrefix."/capture_request_json.php", 
        "uriHost" => $uriHost,
        "uriSchema" => "http://",
        "uriMethod" => "GET",
        "api" => "file_get_contents",
    ),
    array(
        "uriPathToCaptureRequest" => $uriPrefix."/capture_request_json.php", 
        "uriHost" => $uriHost,
        "uriSchema" => "https://",
        "uriMethod" => "GET",
        "api" => "file_get_contents",
    ),
    array(
        "uriPathToCaptureRequest" => $uriPrefix."/capture_request_json.php", 
        "uriHost" => $uriHost,
        "uriSchema" => "http://",
        "uriMethod" => "PUT",
        "api" => "file_get_contents",
    ),
    array(
        "uriPathToCaptureRequest" => $uriPrefix."/capture_request_json.php", 
        "uriHost" => $uriHost,
        "uriSchema" => "https://",
        "uriMethod" => "PUT",
        "api" => "file_get_contents",
    ),
    array(
        "uriPathToCaptureRequest" => $uriPrefix."/capture_request_json.php", 
        "uriHost" => $uriHost,
        "uriSchema" => "http://",
        "uriMethod" => "POST",
        "api" => "curl",
    ),
    array(
        "uriPathToCaptureRequest" => $uriPrefix."/capture_request_json.php", 
        "uriHost" => $uriHost,
        "uriSchema" => "https://",
        "uriMethod" => "POST",
        "api" => "curl",
    ),
    array(
        "uriPathToCaptureRequest" => $uriPrefix."/capture_request_json.php", 
        "uriHost" => $uriHost,
        "uriSchema" => "http://",
        "uriMethod" => "GET",
        "api" => "curl",
    ),
    array(
        "uriPathToCaptureRequest" => $uriPrefix."/capture_request_json.php", 
        "uriHost" => $uriHost,
        "uriSchema" => "https://",
        "uriMethod" => "GET",
        "api" => "curl",
    ),
    array(
        "uriPathToCaptureRequest" => $uriPrefix."/capture_request_json.php", 
        "uriHost" => $uriHost,
        "uriSchema" => "http://",
        "uriMethod" => "PUT",
        "api" => "curl",
    ),
    array(
        "uriPathToCaptureRequest" => $uriPrefix."/capture_request_json.php", 
        "uriHost" => $uriHost,
        "uriSchema" => "https://",
        "uriMethod" => "PUT",
        "api" => "curl",
    ),
);

foreach ($testParameters as $idx => $testParameter) {

    $uriPathToCaptureRequest = $testParameter["uriPathToCaptureRequest"];
    $uriHost = $testParameter["uriHost"];
    $uriSchema = $testParameter["uriSchema"];
    $uriMethod = $testParameter["uriMethod"];
    $api = $testParameter["api"];
    echo "\r\n\r\nRUN #".($idx+1)."/".count($testParameters)."\r\n";
    print_r($testParameter);

    if($uriSchema == "https://" && $isSkipHttps == true) {
        echo "HTTPS test skipped! \r\n";
        continue;
    }

    file_put_contents("dumprequest.json", "");
    assertEq("", file_get_contents("dumprequest.json"));

    //$uriPathToCaptureRequest = $uriPrefix."/capture_request_json.php";
    //$uriHost = "localhost:8080";
    //$uriSchema = "http://";
    //$uriMethod = "POST";

    $rand1 = rand();
    $rand2 = rand();

    $client = new KristianHTTPClient();
    $client->request_url = $uriSchema.$uriHost.$uriPathToCaptureRequest;
    $client->request_header = array(
        "Custom-Header-1: custom-value-1",
        "Custom-Header-2: custom-value-2",
        "Custom-Header-3: custom-value-3",
        "Custom-Header-Rand: ".$rand1,
    );
    $client->request_method = $uriMethod;
    $body = array("body-key-1"=>"body-value-1", "body-key-2"=>"body-value-2", "body-key-3"=>"body-value-3", "body-key-rand"=>$rand2);
    $client->request_body = json_encode($body);
    $client->php_api = $api;
    $client->ssl_verify = $isVerifySsl;
    $client->execute();

    //var_dump($client);

    // assert responses
    assertEq("200", $client->response_code, null, $client);
    assertEq(json_encode(array("status"=>"ok")), $client->response_body);

    // assert correct request
    $result = json_decode(file_get_contents("dumprequest.json"), true); //var_dump($result);
    assertEq($uriPathToCaptureRequest, $result[0]["uri"]);
    assertEq($uriMethod, $result[0]["method"]);
    assertEq($uriHost, $result[0]["headers"]["Host"]);
    assertEq("custom-value-1", $result[0]["headers"]["Custom-Header-1"]);
    assertEq("custom-value-2", $result[0]["headers"]["Custom-Header-2"]);
    assertEq("custom-value-3", $result[0]["headers"]["Custom-Header-3"]);
    assertEq($rand1, $result[0]["headers"]["Custom-Header-Rand"]);
    if($uriMethod != "GET") assertEq(json_encode($body), $result[0]["body"]);
    // quirk: curl CANNOT write in http request body if the method is GET

}

echo "\r\nTest passed\r\n";
