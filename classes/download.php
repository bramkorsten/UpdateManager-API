<?php

require_once('_db.php');
use BramKorsten\MakeItLive\DatabaseDetails as DatabaseDetails;
require_once('connection.php');
use BramKorsten\MakeItLive\Connection;

header("Pragma: public");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: public");

$file = NULL;
$response = array();
if (isset($_GET['file'])) {
  $file = $_GET['file'];
  if ($file == 'test.zip') {
    header('Content-Type: application/json');
    $response['response'] = '200 OK - Received file test.zip';
    echo(json_encode($response));
    die();
  }
} else {
  header("HTTP/1.1 401 Unauthorized");
  exit;
}

$details = new DatabaseDetails();
$databaseData = $details->getDatabaseDetails();
$con = new Connection();
$db = $con->connect($databaseData);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$token = null;
$headers = apache_request_headers();
if(isset($headers['Authorization'])){
  $authorization = $headers['Authorization'];
  $token = str_replace('token ', "", $authorization);
  try {
    $result = $db->query("SELECT * from instances WHERE `auth_token` = '{$token}'")->fetchAll(PDO::FETCH_OBJ);
    if (!$result) {
      header("HTTP/1.1 401 Unauthorized");
      exit;
    } else {
      $root = "../downloads/";
      $file = $_GET['file'];
      header("Content-Description: File Transfer");
      header("Content-type: application/octet-stream");
      header("Content-Disposition: attachment; filename=\"".$file."\"");
      header("Content-Transfer-Encoding: binary");
      header("Content-Length: ".filesize($root.$file));
      ob_end_flush();
      @readfile($root.$file);
    }
  } catch (\Exception $e) {
    header("HTTP/1.1 500 Error while checking info");
    exit;
  }
} else {
  header("HTTP/1.1 401 Unauthorized");
  exit;
}

?>
