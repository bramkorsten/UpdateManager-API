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
 * VersionManager for MakeItLive
 */
class packageCreator
{

  protected $coreRepoName = "cms-test"; // This repo will create new core packages instead of module packages

  protected $updaterRepoName = "packageupdater"; // This repo will create new packages of the updater

  protected $databaseDetails;

  protected $downloadPath = "../temp/archives/"; // Where to save the downloaded packages

  protected $packageVersion;

  protected $packageName;

  protected $packagePath;

  protected $publicDownloadLocation = "../downloads";

  protected $secret = 'secrettoken'; // Use this secret on every webhook of all repositories

  function __construct()
  {
    $details = new DatabaseDetails();
    $this->databaseDetails = $details->getDatabaseDetails();
  }



  public function webhook()
  {
    $postBody = file_get_contents('php://input');
    $signature = hash_hmac('sha1', $postBody, $this->secret);
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
        // TODO: add a ping response to see if the repo has been registered in the app
      }

      header('Content-Type: application/json');
      $jsonResponse['response'] = "200 OK! Received Webhook of type: {$webhookType}";
      echo(\json_encode($jsonResponse));

      if ($webhookType == "published") {
        if ($data['release']['draft'] == false) {
          $url = $data['release']['zipball_url'];
          $name = $data['repository']['name'];
          $version = $data['release']['tag_name'];
          $version = substr($version, 1);
          $releaseType = ($data['release']['prerelease'] ? 2 : 1);

          $this->createNewPackage($name, $version, $url, $releaseType);
        }
      }

    } else {
      http_response_code(403);
	    die("Forbidden\n");
    }
  }

  public function createNewPackage($name, $version, $url, $releaseType)
  {
    if ($name == $this->coreRepoName) {
      $releasePath = $this->getRelease($url, $name, $version);

      $packagePath = "";
      if ($packagePath = $this->makePackage($releasePath, "makeitlive-core", $version, "core")) {
        $this->registerCorePackageVersion($version, $packagePath, $releaseType);
      }
    } else if ($name == $this->updaterRepoName) {
      $releasePath = $this->getRelease($url, $name, $version);

      $packagePath = "";
      if ($packagePath = $this->makePackage($releasePath, $name, $version, "updater")) {
        $this->registerUpdaterPackageVersion($version, $packagePath, $releaseType);
      }
    } else {
      $releasePath = $this->getRelease($url, $name, $version);

      $packagePath = "";
      if ($packagePath = $this->makePackage($releasePath, $name, $version, "modules")) {
        $this->registerModulePackageVersion($name, $version, $packagePath, $releaseType);
      }
    }
  }


  public function getRelease($url, $name, $version)
  {
    set_time_limit(0); // unlimited max execution time
    $now = date("Ymd-Gi");

    $downloadPath = $this->downloadPath;
    $fileName = "gh-{$name}-{$version}-{$now}.zip";

    $header = array();
    $header[] = 'Authorization: token {token for github}'
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

    return $fileName;

  }

  public function makePackage($releaseLocation, $releaseName, $version, $subDir)
  {
    $uniqDir = \uniqid();
    $path = $this->downloadPath . $uniqDir;
    if (!\file_exists($path)) {
      mkdir($path, 0660, true);
      chmod($path,0774);
    }
    $zip = new ZipArchive;
    $res = $zip->open($this->downloadPath . $releaseLocation);
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

        unlink($this->downloadPath . $releaseLocation);

        $package = new ZipArchive();
        $packageLocation = "{$this->publicDownloadLocation}/{$subDir}/{$releaseName}-{$version}.upgrade.zip";
        if (!\file_exists("{$this->publicDownloadLocation}/{$subDir}")) {
          mkdir("{$this->publicDownloadLocation}/{$subDir}", 0660, true);
          chmod("{$this->publicDownloadLocation}/{$subDir}",0774);
        }
        echo($packageLocation);
        $package->open($packageLocation, ZipArchive::CREATE | ZipArchive::OVERWRITE);

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
        return "https://bramkorsten.nl/api/downloads/{$subDir}/{$releaseName}-{$version}.upgrade.zip";
    }
    else {
      return false;
    }
  }

  public function registerUpdaterPackageVersion($version, $url, $releaseType)
  {
    $db = new PDO("mysql:host={$this->databaseDetails['host']};dbname={$this->databaseDetails['db']}", $this->databaseDetails['username'], $this->databaseDetails['password']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $db->prepare("INSERT INTO `updater_versions` (`version`, `release_type`, `release_date`, `public`, `link`, `changelog_link`) VALUES (:version, :releasetype, CURRENT_TIMESTAMP, :public, :link, '')");
    $stmt->execute(
      [
        'version' => $version,
        'releasetype' => $releaseType,
        'public' => '1',
        'link' => $url
      ]);
  }

  public function registerCorePackageVersion($version, $url, $releaseType)
  {
    $db = new PDO("mysql:host={$this->databaseDetails['host']};dbname={$this->databaseDetails['db']}", $this->databaseDetails['username'], $this->databaseDetails['password']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $db->prepare("INSERT INTO `core_versions` (`version`, `release_type`, `release_date`, `public`, `upgrade_link`, `fresh_link`, `changelog_link`) VALUES (:version, :releasetype, CURRENT_TIMESTAMP, :public, :upgradelink, :freshlink, '')");
    $stmt->execute(
      [
        'version' => $version,
        'releasetype' => $releaseType,
        'public' => '1',
        'upgradelink' => $url,
        'freshlink' => $url
      ]);
  }

  public function registerModulePackageVersion($name, $version, $url, $releaseType)
  {
    $db = new PDO("mysql:host={$this->databaseDetails['host']};dbname={$this->databaseDetails['db']}", $this->databaseDetails['username'], $this->databaseDetails['password']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $name = strtolower($name);
    $stmt = $db->prepare("SELECT `id` FROM `modules` WHERE `name` = :name");
    $stmt->execute(['name' => $name]);
    $result = $stmt->fetch(PDO::FETCH_OBJ);
    if ($result) {
      $moduleId = $result->id;

      $stmt = $db->prepare("INSERT INTO `module_versions` (`module_id`, `module_name`, `version`, `release_type`, `release_date`, `public`, `upgrade_link`, `changelog_link`) VALUES (:module_id, :module_name, :version, :releasetype, CURRENT_TIMESTAMP, :public, :upgradelink, '')");
      $stmt->execute(
        [
          'module_id' => $moduleId,
          'module_name' => $name,
          'version' => $version,
          'releasetype' => $releaseType,
          'public' => '1',
          'upgradelink' => $url
        ]);
    }


  }

}

$packageCreator = new packageCreator();
$packageCreator->webhook();




?>
