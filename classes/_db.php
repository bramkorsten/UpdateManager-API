<?php

namespace BramKorsten\MakeItLive;

/**
 * Class for checking which database details to use.
 */
class DatabaseDetails
{

  function __construct()
  {

  }

  public function getDatabaseDetails()
  {
    $databaseData = array();

    $servername = strtolower($_SERVER["SERVER_NAME"]);
    if (substr($servername, -14) == "bramkorsten.nl") {
      $databaseData = array (
                        'host' => 'localhost',
                        'username' => 'bramkor_mil',
                        'password' => 'ditiseentest',
                        'db'=> 'bramkor_MakeItLive'
                      );
    }
    elseif ($servername == "localhost") {
      $databaseData = array (
                        'host' => 'localhost',
                        'username' => 'root',
                        'password' => '',
                        'db'=> 'updatemanager'
                      );
    }
    else {
      $databaseData = array (
                        'host' => 'localhost',
                        'username' => 'root',
                        'password' => 'root',
                        'db'=> 'db'
                      );
    }
    return $databaseData;
  }
}
