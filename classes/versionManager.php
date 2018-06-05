<?php
namespace BramKorsten\MakeItLive;

use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use FilesystemIterator;
use PDO;
require_once('_db.php');
use BramKorsten\MakeItLive\DatabaseDetails as DatabaseDetails;
require_once("connection.php");
use BramKorsten\MakeItLive\Connection;

error_reporting(E_ALL);
ini_set("display_errors", 1);

/**
 * deprecated: This class is replaced with packageManager.php
 * Old VersionManager for MakeItLive
 */
class VersionManager
{

  protected $databaseDetails;

  protected $packageVersion;

  protected $packageName;

  protected $packagePath;

  function __construct()
  {
    $details = new DatabaseDetails();
    $this->databaseDetails = $details->getDatabaseDetails();
  }


  public function getNewVersion($url, $name, $version)
  {
    set_time_limit(0); // unlimited max execution time
    $now = date("Ymd-Gi");
    $downloadPath = "../temp/archives/";
    $fileName = "gh-{$name}-{$version}-{$now}.zip";
    $header = array();
    $header[] = 'Authorization: token 2e24043bf4fb7875279c69d29e03d801ed9c9dab';
    $newVersionFile = fopen($downloadPath . $fileName, 'w');
    $options = array(
      CURLOPT_USERAGENT => "app",
      CURLOPT_FILE    => $newVersionFile,
      CURLOPT_TIMEOUT =>  28800, // set this to 8 hours so we dont timeout on big files
      CURLOPT_URL     => $url,
    );

    $ch = curl_init();
    curl_setopt_array($ch, $options);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $response = curl_exec($ch);
    if ($response === false) {
      echo(curl_error($ch));
    }
    curl_close($ch);
    \fclose($newVersionFile);

    $this->packageName = $name;
    $this->packageVersion = substr($version, 1);

    if ($this->makePackage($downloadPath, $fileName)) {
      $this->registerPackage($this->packageName, $this->packageVersion, $this->packagePath);
    }

  }

  public function makePackage($downloadPath, $fileName)
  {
    $uniqDir = \uniqid();
    $path = $downloadPath . $uniqDir;
    if (!\file_exists($path)) {
      mkdir($path, 0660, true);
      chmod($path,0774);
    }
    $zip = new ZipArchive;
    $res = $zip->open($downloadPath . $fileName);
    if ($res === TRUE) {
        $zip->extractTo($path);
        $zip->close();

        $directories = glob($path . '/*' , GLOB_ONLYDIR);
        $rootPath = realpath($directories[0] . "/");;

        $purgeFiles = array(
          '/.gitignore',
          '/.gitattributes',
          '/cms/.gitignore',
          '/cms/.gitattributes'
        );

        foreach ($purgeFiles as $file) {
          if(is_file(\realpath($rootPath . $file))) {
            unlink(\realpath($rootPath . $file));
          }
        }

        unlink($downloadPath . $fileName);

        $package = new ZipArchive();

        $this->packagePath = "../downloads/core/makeitlive-{$this->packageVersion}.upgrade.zip";

        $package->open($this->packagePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // Create recursive directory iterator
        /** @var SplFileInfo[] $files */
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootPath),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file)
        {
            // Skip directories (they would be added automatically)
            if (!$file->isDir())
            {
                // Get real and relative path for current file
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($rootPath) + 1);

                // Add current file to archive
                $package->addFile($filePath, $relativePath);
            }
        }
        $package->close();
        foreach( new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS ),
            RecursiveIteratorIterator::CHILD_FIRST ) as $value ) {
                $value->isFile() ? unlink( $value ) : rmdir( $value );
        }
        $this->packagePath = "https://bramkorsten.nl/api/downloads/core/makeitlive-{$this->packageVersion}.upgrade.zip";
        return true;
    }
    else {
      return false;
    }
  }

  public function registerPackage($name, $version, $url)
  {
    $db = new PDO("mysql:host={$this->databaseDetails['host']};dbname={$this->databaseDetails['db']}', '{$this->databaseDetails['username']}', '{$this->databaseDetails['password']}'");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $db->prepare("INSERT INTO `core_versions` (`version`, `release_type`, `release_date`, `public`, `upgrade_link`, `fresh_link`, `changelog_link`) VALUES (:version, :releasetype, CURRENT_TIMESTAMP, :public, :upgradelink, :freshlink, '')");
    $stmt->execute(
      [
        'version' => $version,
        'releasetype' => '1',
        'public' => '1',
        'upgradelink' => $url,
        'freshlink' => $url
      ]);
  }

}




?>
