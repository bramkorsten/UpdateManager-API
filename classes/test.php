<?php
namespace BramKorsten\MakeItLive;

require_once('versionManager.php');
use BramKorsten\MakeItLive\VersionManager as VersionManager;

$versionManager = new VersionManager();
$versionManager->getNewVersion("https://api.github.com/repos/bramkorsten/cms-test/zipball/v1.0.0", "core", "v3.2.0");



?>
