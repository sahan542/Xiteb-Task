<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
logout_user();
header('Location: ' . BASE_URL . '/user/login.php');
exit;
