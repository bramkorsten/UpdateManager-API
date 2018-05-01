<?php
$bytes = openssl_random_pseudo_bytes(20);
$hex   = bin2hex($bytes);
  echo($hex);
?>
