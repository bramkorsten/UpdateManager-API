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


  public function setInstanceSetting()
  {
    if (!isset($_POST['Authorization']) || !isset($_POST['setting']) || !isset($_POST['value'])) {
      $this->response['error'] = 'Invalid request';
      $this->response['message'] = "Not all required fields are set correctly";
      $this->dieWithResponse();
    }
    $token = $_POST['Authorization'];
    $token = $this->cleanInput($token);
    $setting = $this->cleanInput($_POST['setting']);

    $this->con = new Connection();
    $db = $this->con->connect($this->databaseData);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    try {
      $result = $db->query("SELECT * from instances WHERE `auth_token` = '{$token}'")->fetchAll(PDO::FETCH_OBJ);
      if (!$result) {
        $this->response['error'] = '401 Not Authorized';
        $this->response['message'] = "The Application token provided is not registered on the server. Please make sure it is valid.";
        $this->dieWithResponse();
      } else {
        $stmt = $db->prepare("UPDATE `instances` SET {$setting} = :value WHERE `auth_token` = :token");
        $stmt->execute(array(
          'value' => $this->cleanInput($_POST['value']),
          'token' => $token
        ));
      }
      $this->response['error'] = false;
      $this->response['message'] = "200 OK! Setting Changed";
      $this->dieWithResponse();
    } catch (\Exception $e) {
      $this->response['error'] = '500 Server Error';
      $this->response['message'] = $e;
      $this->dieWithResponse();
    }
  }


  public function getInstanceInformation()
  {

    if (!isset($_POST['Authorization'])) {
      $this->response['error'] = '401 Not Authorized';
      $this->response['message'] = "You are not authorized to visit this endpoint. Please provide Authentication...";
      $this->dieWithResponse();
    }
    $token = $_POST['Authorization'];
    $token = $this->cleanInput($token);
    $instance = array();
    $modulesForInstance = array();
    $this->con = new Connection();
    $db = $this->con->connect($this->databaseData);
    try {
      $result = $db->query("SELECT * from instances WHERE `auth_token` = '{$token}'")->fetchAll(PDO::FETCH_OBJ);
      if (!$result) {
        $this->response['error'] = '404 Not Found';
        $this->response['message'] = "The Application token provided is not registered on the server. Please make sure it is valid.";
        $this->dieWithResponse();
      } else {
        $i = 0;
        $rows = array();
        foreach ($result as $row) {
          $rows[$i] = (array)$row;
          $i++;
        }
        if ($i > 1) {
          $this->response['error'] = '500 Multiple Instances with unique ID';
          $this->response['message'] = "The application key you've provided is not unique, but should be. For safety reasons, please contact the developer";
          $this->response['result'] = $rows;
          $this->dieWithResponse();
        }
        $instance = $rows[0];
        $allModules = array();
        $result = $db->query("SELECT * FROM `modules`")->fetchAll(PDO::FETCH_OBJ);
        $i = 0;
        foreach ($result as $row) {
          $allModules[$i] = (array)$row;
          $i++;
        }
        $installableModules = explode(",", $instance['modules']);
        $i = 0;
        foreach($installableModules as $moduleId) {
          $foundIndex = array_search($moduleId, array_column($allModules, 'id'));
          if ($foundIndex === false) {
            $modulesForInstance[$i] = "false";
            $i++;
          }
          else {
            $modulesForInstance[$i]['id'] = $allModules[$foundIndex]['id'];
            $modulesForInstance[$i]['name'] = $allModules[$foundIndex]['name'];
            $modulesForInstance[$i]['description'] = $allModules[$foundIndex]['description'];
            $modulesForInstance[$i]['visible'] = $allModules[$foundIndex]['visible'];
            $modulesForInstance[$i]['deprecated'] = $allModules[$foundIndex]['deprecated'];
            $i++;
          }
        }
        //print_r($modulesForInstance);
        $i = 0;
        foreach ($modulesForInstance as $module) {
          if ($module != "false") {
            $id = $module['id'];
            try {
              $result = $db->query("SELECT * from module_versions WHERE `module_id` = '$id' ORDER BY release_date DESC")->fetchAll(PDO::FETCH_OBJ);
              if (!$result) {
                $modulesForInstance[$i]['versions'] = "false";
              } else {
                foreach($result as $index => $row) {
                  $row = (array)$row;
                  if ($row['public']) {
                    if($row['release_type'] <= $instance['release_type']) {
                      $modulesForInstance[$i]['versions'][$index] = $row;
                    }
                  }
                }
                $modulesForInstance[$i]['versions'] = array_slice($modulesForInstance[$i]['versions'], 0, 3);
                $i++;
              }
            }

            catch (\Exception $e) {
              $this->response['error'] = 'request_error';
              $this->response['message'] = 'Something went wrong while fetching entries -> ' . $e;
              $this->dieWithResponse();
            }
          }
        }
      }
    } catch (\Exception $e) {
      $this->response['error'] = '500 Server Error';
      $this->response['message'] = $e;
      $this->dieWithResponse();
    }

    $instance['modules'] = $modulesForInstance;
    $coreVersions = array();
    try {
      $result = $db->query("SELECT * from core_versions ORDER BY release_date DESC")->fetchAll(PDO::FETCH_OBJ);
      if (!$result) {
        $coreVersions = "false";
      } else {
        foreach($result as $index => $row) {
          $row = (array)$row;
          if ($row['public']) {
            if($row['release_type'] <= $instance['release_type']) {
              $coreVersions[$index] = $row;
            }
          }
        }
        $coreVersions = array_slice($coreVersions, 0, 3);
        $instance['core_versions'] = $coreVersions;
      }
    }

    catch (\Exception $e) {
      $this->response['error'] = 'request_error';
      $this->response['message'] = 'Something went wrong while fetching entries -> ' . $e;
      $this->dieWithResponse();
    }
    $this->response['error'] = 'false';
    $this->response['instance'] = $instance;
    $this->dieWithResponse();
    // print_r($instance);
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

            case 'newClient':
              $managerExtention->createClient();
              break;

            case 'updateClient':
              $managerExtention->updateClient();
              break;

            case 'deleteClient':
              $managerExtention->deleteClient();
              break;

            case 'newInstance':
              $managerExtention->createNewInstance();
              break;

            case 'updateInstance':
              $managerExtention->updateInstance();
              break;

            case 'deleteInstance':
              $managerExtention->deleteInstance();
              break;

            case 'newModule':
              $managerExtention->createModule();
              break;

            case 'updateModule':
              $managerExtention->updateModule();
              break;

            case 'deleteModule':
              $managerExtention->deleteModule();
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

      case 'instance':
        $this->getInstanceInformation();
        break;

      case 'setinstance':
        $this->setInstanceSetting();
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
