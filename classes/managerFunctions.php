<?php

namespace BramKorsten\MakeItLive;

error_reporting(E_ALL);
ini_set("display_errors", 1);

use PDO;
use DateTime;
require_once('_db.php');
use BramKorsten\MakeItLive\DatabaseDetails as DatabaseDetails;

/**
 * All API functions for the updateManager. This class acts as an extention
 * to the main API.
 */
class ManagerFunctions
{

  protected $databaseDetails;

  function __construct($databaseData)
  {
    $this->databaseDetails = $databaseData;
  }

  public function getClients() {
    $host = $this->databaseDetails['host'];
    $db = $this->databaseDetails['db'];
    $user = $this->databaseDetails['username'];
    $pass = $this->databaseDetails['password'];

    $db = new PDO("mysql:host={$host};dbname={$db}", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    try {
      $response = array();
      $i = 0;
      $result = $db->query("SELECT * FROM `clients`")->fetchAll(PDO::FETCH_OBJ);
      foreach ($result as $row) {
        $response['results']['clients'][$i] = $row;
        $i++;
      }
      header('Content-Type: application/json');
      echo(json_encode($response, true));
      die();
    } catch (\PDOException $e) {
      print "Error!: " . $e->getMessage() . "<br/>";
    }

    die();

  }

  public function getInstancesForClient($id) {
    $host = $this->databaseDetails['host'];
    $db = $this->databaseDetails['db'];
    $user = $this->databaseDetails['username'];
    $pass = $this->databaseDetails['password'];

    $db = new PDO("mysql:host={$host};dbname={$db}", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    try {
      $response = array();
      $i = 0;
      $stmt = $db->prepare("SELECT * FROM `instances` WHERE `client_id` = :id");
      $stmt->execute(
        [
          'id' => $id
        ]);
        $result = $stmt->fetchAll(PDO::FETCH_OBJ);
      // $result = $db->query()->fetchAll(PDO::FETCH_OBJ);
      foreach ($result as $row) {
        $rowData = (array)$row;
        $response['results']['instances'][$i] = $rowData;
        $response['results']['instances'][$i]['expires_on'] = date_format(date_create_from_format('Y-m-d H:i:s', $rowData['expires_on']), "m/d/Y");
        $i++;
      }
      header('Content-Type: application/json');
      echo(json_encode($response, true));
      die();
    } catch (\PDOException $e) {
      print "Error!: " . $e->getMessage() . "<br/>";
    }

    die();

  }

  public function deleteInstance() {
    if (isset($_POST['instanceId'])) {
      $host = $this->databaseDetails['host'];
      $db = $this->databaseDetails['db'];
      $user = $this->databaseDetails['username'];
      $pass = $this->databaseDetails['password'];

      $db = new PDO("mysql:host={$host};dbname={$db}", $user, $pass);
      $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      try {
        $response = array();
        $stmt = $db->prepare("DELETE FROM `instances` WHERE `id` = :instanceId");
        if($stmt->execute(array('instanceId' => $_POST['instanceId']))) {
          $response['error'] = 0;
          $response['message'] = 'Instance deleted';
        } else {
          $response['error'] = 1;
          $response['message'] = 'Something went wrong while deleting an instance';
        }
        header('Content-Type: application/json');
        echo(json_encode($response, true));
        die();
      } catch (\PDOException $e) {
        $response['error'] = 1;
        $response['message'] = 'PDO Exeption: ' . $e->getMessage();
        header('Content-Type: application/json');
        echo(json_encode($response, true));
      }
    } else {
      $response = array();
      $response['error'] = 1;
      $response['message'] = "No instance ID specified...";
      header('Content-Type: application/json');
      echo(json_encode($response, true));
    }
    die();
  }

  public function deleteClient() {
    if (isset($_POST['clientId'])) {
      $host = $this->databaseDetails['host'];
      $db = $this->databaseDetails['db'];
      $user = $this->databaseDetails['username'];
      $pass = $this->databaseDetails['password'];

      $response = array();
      $db = new PDO("mysql:host={$host};dbname={$db}", $user, $pass);
      $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      try {
        $stmt = $db->prepare("DELETE FROM `instances` WHERE `client_id` = :clientId");
        if($stmt->execute(array('clientId' => $_POST['clientId']))) {
          $response['error'] = 0;
          $response['messages'][0] = 'Instances deleted succesfully';
        } else {
          $response['error'] = 1;
          $response['messages'][0] = 'Something went wrong while deleting instances of the client';
          header('Content-Type: application/json');
          echo(json_encode($response, true));
          die();
        }
      } catch (\PDOException $e) {
        $response['error'] = 1;
        $response['message'] = 'PDO Exeption: ' . $e->getMessage();
        header('Content-Type: application/json');
        echo(json_encode($response, true));
      }
      try {
        $stmt = $db->prepare("DELETE FROM `clients` WHERE `id` = :clientId");
        if($stmt->execute(array('clientId' => $_POST['clientId']))) {
          $response['error'] = 0;
          $response['messages'][1] = 'Client deleted successfully';
        } else {
          $response['error'] = 1;
          $response['messages'][1] = 'Something went wrong while deleting the client';
          header('Content-Type: application/json');
          echo(json_encode($response, true));
          die();
        }
      } catch (\PDOException $e) {
        $response['error'] = 1;
        $response['message'] = 'PDO Exeption: ' . $e->getMessage();
        header('Content-Type: application/json');
        echo(json_encode($response, true));
      }

      header('Content-Type: application/json');
      echo(json_encode($response, true));

    } else {
      $response = array();
      $response['error'] = 1;
      $response['message'] = "No instance ID specified...";
      header('Content-Type: application/json');
      echo(json_encode($response, true));
    }
    die();
  }

  public function getModules() {
    $host = $this->databaseDetails['host'];
    $databaseName = $this->databaseDetails['db'];
    $user = $this->databaseDetails['username'];
    $pass = $this->databaseDetails['password'];

    $db = new PDO("mysql:host={$host};dbname={$databaseName}", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    try {
      $response = array();
      $i = 0;
      $result = $db->query("SELECT * FROM `modules`")->fetchAll(PDO::FETCH_OBJ);
      foreach ($result as $row) {
        $response['results']['modules'][$i] = $row;
        $i++;
      }
      header('Content-Type: application/json');
      echo(json_encode($response, true));

    } catch (\PDOException $e) {
      print "Error!: " . $e->getMessage() . "<br/>";
    }

    die();

  }

  public function createNewInstance() {
    header('Content-Type: application/json');
    $response = array();
    if (isset($_POST['name']) && isset($_POST['name']) && isset($_POST['domain'])) {

      $host = $this->databaseDetails['host'];
      $databaseName = $this->databaseDetails['db'];
      $user = $this->databaseDetails['username'];
      $pass = $this->databaseDetails['password'];

      $bytes = openssl_random_pseudo_bytes(20);
      $token = bin2hex($bytes);



      $instance = array(
        'name' => $_POST['name'],
        'domain' => $_POST['domain'],
        'clientId' => $_POST['client'],
        'authToken' => $token,
        'active' => '0',
        'modules' => '',
        'requireWhitelist' => '',
        'whitelisted' => '',
        'hasExpirationDate' => '',
        'expirationDate' => ''
      );

      if (isset($_POST['enabled'])) {
        if (($_POST['enabled']) == "on") {
          $instance['active'] = '1';
        } else {
          $instance['active'] = '0';
        }
      }

      if (isset($_POST['modules'])) {
        $moduleString = implode(",", $_POST['modules']);
        $instance['modules'] = $moduleString;
      }

      if (isset($_POST['require_whitelist'])) {
        if (($_POST['require_whitelist']) == "on") {
          $instance['requireWhitelist'] = '1';
        } else {
          $instance['requireWhitelist'] = '0';
        }
      }

      if (isset($_POST['whitelisted'])) {
        $moduleString = implode(",", $_POST['whitelisted']);
        $instance['whitelisted'] = $moduleString;
      }

      if (isset($_POST['has_expiration_date'])) {
        if (($_POST['has_expiration_date']) == "on") {
          $instance['hasExpirationDate'] = '1';
          if (isset($_POST['new_expiration_date'])) {
            if (($_POST['new_expiration_date']) != "") {
              $instance['expirationDate'] = date_format(date_create_from_format('m/d/Y', $_POST['new_expiration_date']), 'Y-m-d H:i:s');
            } else {
              $instance['expirationDate'] = '';
            }
          }
        } else {
          $instance['hasExpirationDate'] = '0';
        }
      }


      $db = new PDO("mysql:host={$host};dbname={$databaseName}", $user, $pass);
      $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $stmt = $db->prepare("INSERT INTO `instances` (`name`, `client_id`, `auth_token`, `modules`, `active`, `domain`, `require_whitelist`, `whitelisted_ip`, `will_expire`, `expires_on`) VALUES (:name, :clientId, :authToken, :modules, :active, :domain, :requireWhitelist, :whitelisted, :hasExpirationDate, :expirationDate)");
      $stmt->execute($instance);

      $response['error'] = 0;
      $response['message'] = 'successfully created instance';
      $response['instance'] = $instance;

    }
    else {
      $response['error'] = 1;
      $response['message'] = 'Not all fields are set!';
      $response['instance'] = $instance;
    }
    echo(\json_encode($response));
  }

  public function createClient() {
    header('Content-Type: application/json');
    $response = array();
    if (isset($_POST['name'])) {

      $host = $this->databaseDetails['host'];
      $databaseName = $this->databaseDetails['db'];
      $user = $this->databaseDetails['username'];
      $pass = $this->databaseDetails['password'];

      $client = array(
        'name' => $_POST['name'],
        'description' => '',
        'active' => ''
      );

      if (isset($_POST['enabled'])) {
        if (($_POST['enabled']) == "on") {
          $client['active'] = '1';
        } else {
          $client['active'] = '0';
        }
      }

      if (isset($_POST['description'])) {
        $client['description'] = $_POST['description'];
      }

      $db = new PDO("mysql:host={$host};dbname={$databaseName}", $user, $pass);
      $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $stmt = $db->prepare("INSERT INTO `clients` (`name`, `description`, `active`) VALUES (:name, :description, :active)");
      $stmt->execute($client);

      $response['error'] = 0;
      $response['message'] = 'successfully created client';
      $response['client'] = $client;

    }
    else {
      $response['error'] = 1;
      $response['message'] = 'Not all fields are set!';
      $response['client'] = $client;
    }
    echo(\json_encode($response));
  }

  public function updateInstance() {
    header('Content-Type: application/json');
    $response = array();
    $oldInstance = array();
    if (isset($_POST['instanceId']) && isset($_POST['domain'])) {
      $host = $this->databaseDetails['host'];
      $databaseName = $this->databaseDetails['db'];
      $user = $this->databaseDetails['username'];
      $pass = $this->databaseDetails['password'];

      $db = new PDO("mysql:host={$host};dbname={$databaseName}", $user, $pass);
      $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      try {
        $stmt = $db->prepare("SELECT * FROM `instances` WHERE `id` = :id");
        $stmt->execute(
          [
            'id' => $_POST['instanceId']
          ]);
        $result = $stmt->fetchAll(PDO::FETCH_OBJ);
        if (isset($result[0])) {
          $oldInstance = (array)$result[0];
          $response['oldInstance'] = $oldInstance;
        } else {
          $response['error'] = 1;
          $response['message'] = "No instance found with id: '". $_POST['instanceId'] ."'";
        }
      } catch (\PDOException $e) {
        print "Error!: " . $e->getMessage() . "<br/>";
      }

      $oldInstance['updated_at'] = date("Y-m-d H:i:s");

      if (isset($_POST['name']) && $oldInstance['name'] != $_POST['name']) {
        $oldInstance['name'] = $_POST['name'];
      }
      if (isset($_POST['modules'])) {
        $moduleString = implode(",", $_POST['modules']);
      }
      if (isset($moduleString) && $oldInstance['modules'] != $moduleString) {
        $oldInstance['modules'] = $moduleString;
      }
      if (isset($_POST['whitelisted'])) {
        $whitelistString = implode(",", $_POST['whitelisted']);
      }
      if (isset($whitelistString) && $oldInstance['whitelisted_ip'] != $whitelistString) {
        $oldInstance['whitelisted_ip'] = $whitelistString;
      }
      if (isset($_POST['require_whitelist'])) {
        $enabled=0;
        if ($_POST['require_whitelist'] == "on") {
          $enabled=1;
        }
        if ($oldInstance['require_whitelist'] != $enabled) {
          $oldInstance['require_whitelist'] = $enabled;
        }
      } else {
        $oldInstance['require_whitelist'] = 0;
      }
      if (isset($_POST['enabled'])) {
        $enabled=0;
        if ($_POST['enabled'] == "on") {
          $enabled=1;
        }
        if ($oldInstance['active'] != $enabled) {
          $oldInstance['active'] = $enabled;
        }
      } else {
        $oldInstance['active'] = 0;
      }
      if (isset($_POST['has_expiration_date'])) {
        $enabled=0;
        if ($_POST['has_expiration_date'] == "on") {
          $enabled=1;
        }
        if ($oldInstance['will_expire'] != $enabled) {
          $oldInstance['will_expire'] = $enabled;
        }
      } else {
        $oldInstance['will_expire'] = 0;
      }

      if (isset($_POST['expiration_date']) && $oldInstance['expires_on'] != $_POST['expiration_date']) {
        $oldInstance['expires_on'] = date_format(date_create_from_format('m/d/Y', $_POST['expiration_date']), 'Y-m-d H:i:s');
      }
      if (isset($_POST['domain']) && $oldInstance['domain'] != $_POST['domain']) {
        $oldInstance['domain'] = $_POST['domain'];
      }

      $stmt = $db->prepare("UPDATE `instances` SET `name` = :name, `client_id` = :client_id, `auth_token` = :auth_token, `modules` = :modules, `active` = :active, `domain` = :domain, `require_whitelist` = :require_whitelist, `whitelisted_ip` = :whitelisted_ip, `will_expire` = :will_expire, `expires_on` = :expires_on, `updated_at` = :updated_at WHERE `id` = :id ");
      if ($stmt->execute(array(
        'name' => $oldInstance['name'],
        'client_id' => $oldInstance['client_id'],
        'auth_token' => $oldInstance['auth_token'],
        'modules' => $oldInstance['modules'],
        'active' => $oldInstance['active'],
        'domain' => $oldInstance['domain'],
        'require_whitelist' => $oldInstance['require_whitelist'],
        'whitelisted_ip' => $oldInstance['whitelisted_ip'],
        'will_expire' => $oldInstance['will_expire'],
        'expires_on' => $oldInstance['expires_on'],
        'id' => $oldInstance['id'],
        'updated_at' => $oldInstance['updated_at']
      ))) {
        $response['error'] = 0;
        $response['message'] = 'successfully updated instance';
        $response['newInstance'] = $oldInstance;
      } else {
        $response['error'] = 1;
        $response['message'] = 'Error while saving instance...';
        $response['newInstance'] = $oldInstance;
      }
    }

    else {
      $response['error'] = 1;
      $response['message'] = 'Not all fields are set!';
    }
    echo(\json_encode($response));
  }

  public function updateClient() {
    header('Content-Type: application/json');
    $response = array();
    $oldInstance = array();
    if (isset($_POST['clientId']) && isset($_POST['name'])) {
      $host = $this->databaseDetails['host'];
      $databaseName = $this->databaseDetails['db'];
      $user = $this->databaseDetails['username'];
      $pass = $this->databaseDetails['password'];

      $db = new PDO("mysql:host={$host};dbname={$databaseName}", $user, $pass);
      $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      try {
        $stmt = $db->prepare("SELECT * FROM `clients` WHERE `id` = :id");
        $stmt->execute(
          [
            'id' => $_POST['clientId']
          ]);
        $result = $stmt->fetchAll(PDO::FETCH_OBJ);
        if (isset($result[0])) {
          $client = (array)$result[0];
          $response['oldClientDetails'] = $client;
        } else {
          $response['error'] = 1;
          $response['message'] = "No client found with id: '". $_POST['clientId'] ."'";
        }
      } catch (\PDOException $e) {
        print "Error!: " . $e->getMessage() . "<br/>";
      }

      $client['updated_at'] = date("Y-m-d H:i:s");
      $client['name'] = $_POST['name'];
      if (isset($_POST['description'])) {
        $client['description'] = $_POST['description'];
      }
      if (isset($_POST['enabled'])) {
        $client['active'] = 1;
      }
      else {
        $client['active'] = 0;
      }

      $stmt = $db->prepare("UPDATE `clients` SET `name` = :name, `description` = :description, `active` = :active, `updated_at` = :updated_at WHERE `id` = :id ");
      if ($stmt->execute(array(
        'name' => $client['name'],
        'description' => $client['description'],
        'active' => $client['active'],
        'updated_at' => $client['updated_at'],
        'id' => $client['id']
      ))) {
        $response['error'] = 0;
        $response['message'] = 'successfully updated client';
        $response['newClientDetails'] = $client;
      } else {
        $response['error'] = 1;
        $response['message'] = 'Error while saving client...';
        $response['newClientDetails'] = $client;
      }
    }

    else {
      $response['error'] = 1;
      $response['message'] = 'Not all fields are set!';
    }
    echo(\json_encode($response));
  }
}


?>
