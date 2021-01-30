<?php

require_once("../KristianHTTPClient.php");

function assertEq($expected, $real, $msg=null) {
	if(empty($msg)) $msg = "";
	else $msg = ", message = '$msg' ";
	if($expected != $real) { 
		throw new Exception("Assertion failed: expected = '$expected' while real = '$real' $msg \r\n", 1);
	} else {
		echo "Assertion passed: '$real' $msg \r\n";
	}
}

$client = new KristianHTTPClient();
$client->request_url = "http://localhost:8080/capture_request_json.php";
$client->request_header = array(
	"Custom-Header-1: custom-value-1",
	"Custom-Header-2: custom-value-2",
	"Custom-Header-3: custom-value-3",
);
$client->request_method = "POST";
$body = array("body-key-1"=>"body-value-1", "body-key-2"=>"body-value-2", "body-key-3"=>"body-value-3");
$client->request_body = json_encode($body);
$client->php_api = "curl";
$client->execute();

var_dump($client);

// assert responses
assertEq("200", $client->response_code);
assertEq(json_encode(array("status"=>"ok")), $client->response_body);

// assert correct request
$result = json_decode(file_get_contents("dumprequest.json"), true); var_dump($result);
assertEq("/capture_request_json.php", $result[0]["uri"]);
assertEq("POST", $result[0]["method"]);
assertEq("localhost:8080", $result[0]["headers"]["Host"]);
assertEq("custom-value-1", $result[0]["headers"]["Custom-Header-1"]);
assertEq("custom-value-2", $result[0]["headers"]["Custom-Header-2"]);
assertEq("custom-value-3", $result[0]["headers"]["Custom-Header-3"]);
assertEq(json_encode($body), $result[0]["body"]);
