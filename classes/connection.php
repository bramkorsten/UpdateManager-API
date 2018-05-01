<?php

/**
 * Update information API for MakeItLive CMS
 *
 * @author Bram Korsten <exentory@gmail.com>
 *
 * @link https://bramkorsten.nl
 * @licence MIT Licence
 *
 * @version 1.0.0
 */

namespace BramKorsten\MakeItLive;

use PDO;

/**
 * Connection class for MakeItLive remote API
 */
class Connection
{

  /**
   * @var string
   */
  protected $version = '1.0.0';

  /**
   * @var PDO connection variable
   */
  protected $db;

  function __construct()
  {
    $this->db = null;
  }


  public function connect($databaseData)
  {
    $host = $databaseData['host'];
    $db = $databaseData['db'];
    $user = $databaseData['username'];
    $pass = $databaseData['password'];
    try {
        $this->db = new PDO("mysql:host={$host};dbname={$db}", $user, $pass);
        return $this->db;
    } catch (PDOException $e) {
        print "Error!: " . $e->getMessage() . "<br/>";
        die();
    }
  }

  public function disconnect()
  {
    $this->db = null;
  }

  public function execute($db, $query)
  {
    try {
      return $db->query($query);
    } catch (\PDOException $e) {
      print "Error!: " . $e->getMessage() . "<br/>";
    }

  }

  public function isConnected()
  {
    if ($this->db == null) {
      return false;
    } else {
      return true;
    }
  }

}
