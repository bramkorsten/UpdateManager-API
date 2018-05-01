<?php

namespace BramKorsten\MakeItLive;

use PDO;

require_once('_db.php');
use BramKorsten\MakeItLive\DatabaseDetails as DatabaseDetails;
require_once('connection.php');
use BramKorsten\MakeItLive\Connection;
require_once('versionManager.php');
use BramKorsten\MakeItLive\VersionManager as VersionManager;
require_once('managerFunctions.php');
use BramKorsten\MakeItLive\ManagerFunctions as ManagerFunctions;

error_reporting(E_ALL);
ini_set("display_errors", 1);

new Endpoint(rtrim($_GET['uri'], '/') . '/');

/**
 * Main API Handler Class
 */
class Endpoint
{

  /**
   * Database connection
   * @var Connection
   */
  protected $con;

  protected $databaseData;

  /**
   * Endpoint URL as array
   * @var array
   */
  protected $endpointURL;


  /**
   * Name of the module
   * @var string
   */
  protected $moduleName;

  /**
   * Raw endpoint URL
   * @var string
   */
  protected $rawURI;

  /**
   * Response to send back to the requesting server
   * @var array
   */
  protected $response = array();


  /**
   * Endpoint Construct
   * @param string $uri The url gotten by the htaccess file
   */

  function __construct($uri)
  {
    $details = new DatabaseDetails();
    $this->databaseData = $details->getDatabaseDetails();
    $this->rawURI = $this->cleanInput($uri);
    $this->endpointURL = $this->explodeEndpointURI($this->rawURI);

    $this->runEndpoint();
  }


  /**
   * Sanitize the given data
   * @param string $data Input to sanitize
   * @return string sanitized input
   */

  function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
  }


  /**
   * Encode all responses, set the correct header and disconnect from the database
   * if necessary. Then die().
   */
  public function dieWithResponse()
  {
    header('Content-type:application/json;charset=utf-8');
    echo(\json_encode($this->response));
    if ($this->con != null && $this->con->isConnected()) {
      $this->con->disconnect();
    }
    die();
  }


  /**
   * Explodes the received URL
   * @param  string $uri url to explode
   * @return string      exploded string
   */

  public function explodeEndpointURI($uri)
  {
    return explode('/', $uri);
  }


  /**
   * Get's the last 10 versions from the database
   */
  public function getCoreVersions($latest = false)
  {
    $this->con = new Connection();
    $db = $this->con->connect($this->databaseData);
    if($latest) {
      $result = $this->con->execute($db, "SELECT * from core_versions ORDER BY id DESC LIMIT 1");
    } else {
      $result = $this->con->execute($db, "SELECT * from core_versions ORDER BY id DESC LIMIT 10");
    }

    $i = 0;
    try {
      foreach($result as $row) {
        if ($row['public']) {
          $this->response['results'][$i]['version'] = $row['version'];
          $this->response['results'][$i]['release_type'] = $row['release_type'];
          $this->response['results'][$i]['release_date'] = $row['release_date'];
          $this->response['results'][$i]['public'] = $row['public'];
          $this->response['results'][$i]['changelog'] = $row['changelog_link'];
          $this->response['results'][$i]['packages']['upgrade_link'] = $row['upgrade_link'];
          $this->response['results'][$i]['packages']['fresh_link'] = $row['fresh_link'];
          $i++;
        }
      }
      $this->dieWithResponse();
    } catch (\Exception $e) {
      $this->response['error'] = 'request_error';
      $this->response['message'] = 'Something went wrong while fetching entries -> ' . $e;
      $this->dieWithResponse();
    }
  }


  /**
   * Get's the last 10 module versions using the 3rd endpointURL param.
   * @param  string $module sanitized modulename from URL
   */
  public function getModuleVersions($module)
  {
    print_r($this->endpointURL);
  }


  /**
   * Get's the last 10 module versions using the 3rd endpointURL param.
   * @param  string $module sanitized modulename from URL
   */
  public function getMultipleModuleVersions($modules)
  {
    $this->con = new Connection();
    $db = $this->con->connect($this->databaseData);
    foreach ($modules as $module) {
      $module = $this->cleanInput($module);
      $result = $this->con->execute($db, "SELECT * from module_versions WHERE `module_name` = '$module' ORDER BY release_date DESC LIMIT 1");

      try {
        foreach($result as $row) {
          if ($row['public']) {
            $this->response['results'][$module]['version'] = $row['version'];
            $this->response['results'][$module]['release_type'] = $row['release_type'];
            $this->response['results'][$module]['release_date'] = $row['release_date'];
            $this->response['results'][$module]['public'] = $row['public'];
            $this->response['results'][$module]['changelog'] = $row['changelog_link'];
            $this->response['results'][$module]['packages']['upgrade_link'] = $row['upgrade_link'];
          }
        }
    }
    catch (\Exception $e) {
      $this->response['error'] = 'request_error';
      $this->response['message'] = 'Something went wrong while fetching entries -> ' . $e;
      $this->dieWithResponse();
    }
  }
  $this->dieWithResponse();
}


  /**
   * Check what function to run in the core endpoint.
   */
  public function runCoreFunctions()
  {
    switch ($this->endpointURL[1]) {
      case 'releases':

        switch ($this->endpointURL[2]) {
          case 'latest':
            $this->getCoreVersions(true);
            break;

          default:
            $this->getCoreVersions();
            break;
          }

      default:
        $this->response['error'] = 'bad_request';
        $this->response['message'] = 'Not a valid endpoint';
        $this->dieWithResponse();
        break;
    }
  }


  public function runManagerFunctions()
  {
    $masterToken = "1b287f5748b49b31c66b2b4b9b024cad3c69a412";
    header("Access-Control-Allow-Origin: *");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST');
    header("Access-Control-Max-Age: 1000");
    header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token , Authorization');

    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
            // may also be using PUT, PATCH, HEAD etc
            header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

        exit(0);
    }
    $token = null;
    if(isset($_POST['authorization'])){
      $authorization = $_POST['authorization'];
      $token = str_replace('token ', "", $authorization);

      if ($token === $masterToken) {
        if (isset($_POST['request'])) {
          $managerExtention = new ManagerFunctions($this->databaseData);
          switch ($_POST['request']) {
            case 'getClients':
              $managerExtention->getClients();
              break;

            case 'getInstancesForClient':
              if (isset($_POST['clientId'])) {
                $managerExtention->getInstancesForClient($_POST['clientId']);
              }

              break;

            case 'getGlobalModules':
              $managerExtention->getModules();
              break;

            case 'newInstance':
              $managerExtention->createNewInstance();
              break;

            default:
              $this->response['error'] = 'bad_request';
              $this->response['message'] = 'Not a valid endpoint';
              $this->dieWithResponse();
              break;
          }
        } else {
          $this->response['error'] = 'bad_request';
          $this->response['message'] = 'Not a valid endpoint';
          $this->dieWithResponse();
        }
      }
      else {
        header("HTTP/1.1 401 Unauthorized");
        exit;
      }
    } else {
      header("HTTP/1.1 401 Unauthorized");
      exit;
    }
  }


  /**
   * Check what function to run in the modules endpoint.
   */
  public function runModuleFunctions($multiple = false)
  {
    if ($multiple) {
      switch ($this->endpointURL[1]) {
        case 'releases':
          if (isset($_POST['modules']) && ($_POST['modules'] != "")) {
            $modules = $_POST['modules'];
            $this->getMultipleModuleVersions($modules);
          } else {
            $this->response['error'] = 'bad_request';
            $this->response['message'] = 'No modules in request';
            $this->dieWithResponse();
          }
          break;

        default:
          $this->response['error'] = 'bad_request';
          $this->response['message'] = 'Not a valid endpoint';
          $this->dieWithResponse();
          break;
      }
    } else {
      if ($this->endpointURL[1] != NULL && $this->endpointURL[1] != '') {
        $this->moduleName = $this->cleanInput($this->endpointURL[1]);

        switch ($this->endpointURL[2]) {
          case 'releases':
            $this->getModuleVersions($this->moduleName);
            break;

          default:
            $this->response['error'] = 'bad_request';
            $this->response['message'] = 'Not a valid endpoint';
            $this->dieWithResponse();
            break;
        }
      } else {
        $this->response['error'] = 'bad_request';
        $this->response['message'] = 'Not a valid endpoint';
        $this->dieWithResponse();
      }
    }
  }


  public function runWebhook()
  {
    $secret = '#&B)r|UGUv8swtL+J&Ird(v3}$fKOh';
    $postBody = file_get_contents('php://input');
    $signature = hash_hmac('sha1', $postBody, $secret);
    // required data in headers - probably doesn't need changing
    $required_headers = array(
    	'REQUEST_METHOD' => 'POST',
    	'HTTP_X_GITHUB_EVENT' => '*',
    	'HTTP_USER_AGENT' => 'GitHub-Hookshot/*',
    	'HTTP_X_HUB_SIGNATURE' => 'sha1=' . $signature,
    );

    $headers_ok = \array_intersect($_SERVER, $required_headers);
    if($headers_ok) {
      $data = json_decode($_POST['payload'], true);
      if (\array_key_exists('action', $data)) {
        $webhookType = $data['action'];
      } else {
        $webhookType = "ping";
      }

      header('Content-Type: application/json');
      $jsonResponse['response'] = "200 OK! Received Webhook of type: {$webhookType}";
      echo(\json_encode($jsonResponse));

      if ($webhookType == "published") {
        $url = $data['release']['zipball_url'];
        $name = $data['repository']['name'];
        $version = $data['release']['tag_name'];
        $versionManager = new VersionManager();
        $versionManager->getNewVersion($url, $name, $version);
      }

    } else {
      http_response_code(403);
	    die("Forbidden\n");
    }
  }


  /**
   * Check whether the endpoints requests the core or modules
   */
  public function runEndpoint()
  {
    switch ($this->endpointURL[0]) {
      case 'core':
        $this->runCoreFunctions();
        break;

      case 'module':
        $this->runModuleFunctions();
        break;

      case 'modules':
        $this->runModuleFunctions(true);
        break;

      case 'manager':
        $this->runManagerFunctions();
        break;

      case 'webhook':
        $this->runWebhook();
        break;

      default:
        $this->response['error'] = 'bad_request';
        $this->response['message'] = 'Not a valid endpoint';
        $this->dieWithResponse();
        break;
    }
  }



}


 ?>
