<?php
require_once __DIR__ . '/config.php';

function db() {
  static $mysqli = null;
  if ($mysqli === null) {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($mysqli->connect_error) {
      die('DB connection failed: ' . $mysqli->connect_error);
    }
    $mysqli->set_charset('utf8mb4');
  }
  return $mysqli;
}
