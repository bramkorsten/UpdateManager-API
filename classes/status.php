<?php
header("Access-Control-Allow-Origin: *");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST');
header('Content-Type: application/json');

$response = array();
$response["status"] = "200 OK";
$response["status_code"] = 200;
$response["core_version"] = "3.1.0";
$response['updatemanager_version'] = "1.0.0";
$response['message'] = "{$response['status']}. Running updatemanager version " . $response['updatemanager_version'];

echo(json_encode($response, true));
die();
 ?>
