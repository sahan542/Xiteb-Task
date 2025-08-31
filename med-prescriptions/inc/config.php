<?php
define('BASE_URL', 'http://localhost/Xiteb/med-prescriptions/'); 

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'med_prescriptions');
define('DB_USER', 'root');
define('DB_PASS', '');

define('UPLOAD_DIR', __DIR__ . '/../public/uploads/prescriptions/');

define('UPLOAD_REL', '/public/uploads/prescriptions/'); 

define('MAX_IMAGES', 5);

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

