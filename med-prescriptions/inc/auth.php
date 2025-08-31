<?php
require_once __DIR__ . '/config.php';   // <-- so BASE_URL & session exist
require_once __DIR__ . '/db.php';

function url(string $path = ''): string {
  return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

function current_user() { return $_SESSION['user'] ?? null; }

function is_logged_in(): bool { return !empty($_SESSION['user']); }

function is_role(string $role): bool {
  return isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === $role;
}

function require_user() {
  if (!is_logged_in()) {
    $next = urlencode($_SERVER['REQUEST_URI'] ?? url());
    header('Location: ' . url('user/login.php') . '?next=' . $next);
    exit;
  }
}

function require_role(string $role) {
  require_user();
  if (!is_role($role)) {
    // redirect somewhere safe instead of hard 403
    header('Location: ' . url('user/quotations.php'));
    exit;
  }
}

function login_user(array $row) {
  // regenerate to prevent session fixation
  if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
  }
  $_SESSION['user'] = [
    'id'    => (int)$row['id'],
    'name'  => $row['name'],
    'email' => $row['email'],
    'role'  => $row['role']
  ];
}

function logout_user() {
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
      $params['path'], $params['domain'],
      $params['secure'], $params['httponly']
    );
  }
  session_destroy();
}
