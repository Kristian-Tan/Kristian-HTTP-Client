<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");

$targetFile = './dumprequest.json';

$result = array(
	"method"=>$_SERVER['REQUEST_METHOD'],
	"uri"=>$_SERVER['REQUEST_URI'],
	"protocol"=>$_SERVER['SERVER_PROTOCOL'],
	"headers"=>array(),
	"body"=>file_get_contents('php://input'),
);
foreach ($_SERVER as $name => $value) {
	if (preg_match('/^HTTP_/',$name)) {
		// convert HTTP_HEADER_NAME to Header-Name
		$name = strtr(substr($name,5),'_',' ');
		$name = ucwords(strtolower($name));
		$name = strtr($name,' ','-');
		// add to list
		$result["headers"][$name] = $value;
	}
}

//$oldFileContent = file_get_contents($targetFile);
//$oldFileContent = json_decode($oldFileContent, true);
if(empty($oldFileContent)) $oldFileContent = array();
$oldFileContent[] = $result;
$newFileContent = json_encode($oldFileContent);
file_put_contents($targetFile, $newFileContent);

echo json_encode(array("status"=>"ok"));

