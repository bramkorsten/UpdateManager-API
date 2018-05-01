<?php

header("Pragma: public");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: public");

$file = NULL;

if (isset($_GET['file'])) {
  $file = $_GET['file'];
  if ($file == 'test.zip') {
    header('Content-Type: application/json');
    $response = array(
      'response' => '200 OK - Received file test.zip'
    );
    echo(json_encode($response));
    die();
  }
} else {
  header("HTTP/1.1 401 Unauthorized");
  exit;
}

$defaultToken = "a01e1c1af9fbe37caf4bd572eeca9fdb4bab9485";



$token = null;
$headers = apache_request_headers();
if(isset($headers['Authorization'])){
  $authorization = $headers['Authorization'];
  $token = str_replace('token ', "", $authorization);

  if ($token === $defaultToken) {
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
  else {
    header("HTTP/1.1 401 Unauthorized");
    exit;
  }
} else {
  header("HTTP/1.1 401 Unauthorized");
  exit;
}

?>
