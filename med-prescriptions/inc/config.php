<?php
define('BASE_URL', '/Xiteb/med-prescriptions/'); // trailing slash OK

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'med_prescriptions');
define('DB_USER', 'root');
define('DB_PASS', '');

define('UPLOAD_DIR', __DIR__ . '/../public/uploads/prescriptions/');
define('UPLOAD_REL', '/uploads/prescriptions/');
define('MAX_IMAGES', 5);

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Optional dev settings:
// date_default_timezone_set('Asia/Colombo');
// error_reporting(E_ALL); ini_set('display_errors', 1);
