<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/helpers.php';

$msg = '';
if (isset($_GET['registered'])) $msg = 'Registration successful. Please login.';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = strtolower(trim($_POST['email'] ?? ''));
  $password = $_POST['password'] ?? '';

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
  if ($password === '') $errors[] = 'Password is required.';

  if (!$errors) {
    $mysqli = db();
    $stmt = $mysqli->prepare('SELECT id,name,email,password,role FROM users WHERE email=? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    if ($row && password_verify($password, $row['password'])) {
      login_user($row);
      header('Location: ' . BASE_URL . '/user/dashboard.php');
      exit;
    } else {
      $errors[] = 'Invalid credentials.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Xiteb - Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <section class="login-section h-100">
    <div class="row g-0 h-100">

      <!-- Left Panel -->
      <div class="col-lg-5 d-flex align-items-center justify-content-center bg-dark text-white p-5">
        <div class="login-box" style="width:100%;max-width:400px;">

          <h2 class="fw-bold mb-2">Xiteb - Prescription Management System</h2>
          <h5 class="text-primary fw-bold mb-3">Hello there, Welcome Back</h5>
          <p class="mb-4">Log in to your system</p>

          <!-- Show messages -->
          <?php if ($msg): ?><div class="text-success mb-3"><?= e($msg) ?></div><?php endif; ?>
          <?php if ($errors): ?>
            <div class="alert alert-danger">
              <ul class="mb-0">
                <?php foreach($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <!-- Login Form -->
          <form method="post" action="">
            <div class="mb-3">
              <input type="email" name="email" class="form-control custom-input" 
                     placeholder="Email Address" value="<?= e($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="mb-4">
              <input type="password" name="password" class="form-control custom-input" 
                     placeholder="Password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 fw-bold">LOGIN</button>
          </form>

          <p class="mt-4 small">Version 3.64</p>
          <p class="small">
  Still haven't an account? 
  <a href="<?= BASE_URL ?>user/register.php" class="text-primary fw-bold">Signup</a>
</p>
        </div>
      </div>

      <!-- Right Panel -->
      <div class="col-lg-7 d-none d-lg-block bg-image"></div>
    </div>
  </section>
</body>
</html>
