<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/helpers.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $email = strtolower(trim($_POST['email'] ?? ''));
  $password = $_POST['password'] ?? '';
  $confirm_password = $_POST['confirm_password'] ?? '';
  $address = trim($_POST['address'] ?? '');
  $contact_no = trim($_POST['contact_no'] ?? '');
  $dob = $_POST['dob'] ?? null;

  if ($name === '') $errors[] = 'Name is required.';
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';

  $pwdStrong = (bool) preg_match('/^(?=.*[\W_])(?=.*([A-Z]|\d)).{6,}$/', $password);
  if (!$pwdStrong) $errors[] = 'Password must be at least 6 characters and include a special character and an uppercase letter or a number.';
  if ($password !== $confirm_password) $errors[] = 'Passwords do not match.';

  if ($contact_no !== '' && !preg_match('/^\d{10}$/', $contact_no)) {
    $errors[] = 'Contact number must be exactly 10 digits.';
  }

  if (!empty($dob)) {
    $ts = strtotime($dob);
    if ($ts === false) {
      $errors[] = 'Invalid date of birth.';
    } elseif ($ts > time()) {
      $errors[] = 'Date of birth cannot be in the future.';
    }
  }

  if (!$errors) {
    $mysqli = db();
    $stmt = $mysqli->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
      $errors[] = 'Email already registered.';
    } else {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $mysqli->prepare('INSERT INTO users (name,email,password,address,contact_no,dob,role) VALUES (?,?,?,?,?,?, "user")');
      $stmt->bind_param('ssssss', $name, $email, $hash, $address, $contact_no, $dob);
      if ($stmt->execute()) {
        header('Location: ' . BASE_URL . '/user/login.php?registered=1');
        exit;
      } else {
        $errors[] = 'Registration failed.';
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Register - Xiteb</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <section class="register-section h-100">
    <div class="row g-0 h-100">

      <div class="col-lg-7 d-flex align-items-center justify-content-center bg-dark text-white p-5">
        <div class="register-box" style="width:100%;max-width:720px;">

          <h2 class="fw-bold mb-2">Xiteb - Prescription Management System</h2>
          <h5 class="text-primary fw-bold mb-3">Create your account</h5>
          <p class="mb-4">Fill in your details to get started.</p>

          <?php if ($errors): ?>
            <div class="alert alert-danger">
              <ul class="mb-0">
                <?php foreach($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <form method="post" action="" id="registerForm" novalidate>
            <div class="row g-3">
              <div class="col-md-6">
                <label for="name" class="form-label text-white">Full Name</label>
                <input id="name" name="name" class="form-control custom-input"
                       placeholder="Full Name" value="<?= e($_POST['name'] ?? '') ?>" required>
                <div class="invalid-feedback">Name is required.</div>
              </div>

              <div class="col-md-6">
                <label for="email" class="form-label text-white">Email Address</label>
                <input id="email" type="email" name="email" class="form-control custom-input"
                       placeholder="Email Address" value="<?= e($_POST['email'] ?? '') ?>" required>
                <div class="invalid-feedback" id="emailError">Please enter a valid email.</div>
              </div>

              <div class="col-md-6">
                <label for="password" class="form-label text-white">Password</label>
                <input id="password" type="password" name="password" class="form-control custom-input"
                       placeholder="Password" required>
                <div class="invalid-feedback" id="passwordStrengthMsg">
                  Password must be at least 6 characters and include a special character and an uppercase letter or a number.
                </div>
              </div>

              <div class="col-md-6">
                <label for="confirm_password" class="form-label text-white">Confirm Password</label>
                <input id="confirm_password" type="password" name="confirm_password" class="form-control custom-input"
                       placeholder="Confirm Password" required>
                <div class="invalid-feedback" id="passwordMatchMsg">Passwords do not match.</div>
              </div>

              <div class="col-md-6">
                <label for="contact_no" class="form-label text-white">Contact Number</label>
                <input id="contact_no" name="contact_no" class="form-control custom-input"
                       inputmode="numeric" pattern="\d{10}" placeholder="10-digit number"
                       value="<?= e($_POST['contact_no'] ?? '') ?>">
                <div class="invalid-feedback" id="phoneError">Contact number must be exactly 10 digits.</div>
              </div>

              <div class="col-md-6">
                <label for="dob" class="form-label text-white">Date of Birth</label>
                <input id="dob" type="date" name="dob" class="form-control custom-input"
                       value="<?= e($_POST['dob'] ?? '') ?>">
                <div class="invalid-feedback" id="dobError">Date of birth cannot be in the future.</div>
              </div>

              <div class="col-12">
                <label for="address" class="form-label text-white">Address</label>
                <textarea id="address" name="address" class="form-control custom-input"
                          rows="2" placeholder="Address"><?= e($_POST['address'] ?? '') ?></textarea>
              </div>

              <div class="col-12">
                <button type="submit" class="btn btn-primary w-100 fw-bold">REGISTER</button>
              </div>
            </div>
          </form>

          <p class="mt-4 small">
            Already have an account?
<a href="<?= e(BASE_URL) ?>/user/login.php" class="text-primary">Login here</a>

          </p>
        </div>
      </div>

      <div class="col-lg-5 d-none d-lg-block bg-image"></div>
    </div>
  </section>

<script>
  (function() {
    const form = document.getElementById('registerForm');

    const email = document.getElementById('email');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    const contact = document.getElementById('contact_no');
    const dob = document.getElementById('dob');

    // track if a user has interacted (typed) with a field
    const touched = {
      email: false,
      password: false,
      confirm_password: false,
      contact_no: false,
      dob: false
    };

    // validators
    const pwdStrong = (val) => /^(?=.*[\W_])(?=.*([A-Z]|\d)).{6,}$/.test(val);
    const isValidEmail = (val) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
    const isTenDigits = (val) => /^\d{10}$/.test(val);
    const notFutureDate = (val) => {
      if (!val) return true; // optional field
      const d = new Date(val);
      const today = new Date();
      d.setHours(0,0,0,0); today.setHours(0,0,0,0);
      return d <= today;
    };

    // add/remove Bootstrap invalid state; show only if `show` is true
    function setValidity(input, isValid, show) {
      if (show && !isValid) {
        input.classList.add('is-invalid');
      } else {
        input.classList.remove('is-invalid');
      }
    }

    // individual validators wrapped with "show only when touched"
    function validateEmail(show = false) {
      const ok = isValidEmail(email.value.trim());
      setValidity(email, ok, show);
      return ok;
    }
    function validatePassword(show = false) {
      const ok = pwdStrong(password.value);
      setValidity(password, ok, show);
      // if user typed in confirm field, recheck match visibility using its own touched state
      validatePasswordMatch(touched.confirm_password);
      return ok;
    }
    function validatePasswordMatch(show = false) {
      const ok = password.value === confirmPassword.value && confirmPassword.value.length > 0;
      setValidity(confirmPassword, ok, show);
      return ok;
    }
    function validatePhone(show = false) {
      // enforce digits-only silently; no message until user typed
      const digitsOnly = contact.value.replace(/\D/g, '');
      if (digitsOnly !== contact.value) contact.value = digitsOnly;
      const ok = (digitsOnly === '' || isTenDigits(digitsOnly));
      setValidity(contact, ok, show);
      return ok;
    }
    function validateDob(show = false) {
      const ok = notFutureDate(dob.value);
      setValidity(dob, ok, show);
      return ok;
    }

    // mark field as touched on first keystroke and validate with show=true
    function onFirstInputTouch(el, key) {
      if (!touched[key]) touched[key] = true;
    }

    // events — only show messages after typing in that specific field
    email.addEventListener('input', () => {
      onFirstInputTouch(email, 'email');
      validateEmail(touched.email);
    });

    password.addEventListener('input', () => {
      onFirstInputTouch(password, 'password');
      validatePassword(touched.password);
    });

    confirmPassword.addEventListener('input', () => {
      onFirstInputTouch(confirmPassword, 'confirm_password');
      validatePasswordMatch(touched.confirm_password);
    });

    contact.addEventListener('input', () => {
      onFirstInputTouch(contact, 'contact_no');
      validatePhone(touched.contact_no);
    });

    dob.addEventListener('input', () => {
      onFirstInputTouch(dob, 'dob');
      validateDob(touched.dob);
    });
    dob.addEventListener('change', () => {
      onFirstInputTouch(dob, 'dob');
      validateDob(touched.dob);
    });

    // on submit: show messages for all fields (regardless of touched)
    form.addEventListener('submit', (e) => {
      const v1 = validateEmail(true);
      const v2 = validatePassword(true);
      const v3 = validatePasswordMatch(true);
      const v4 = validatePhone(true);
      const v5 = validateDob(true);

      if (!(v1 && v2 && v3 && v4 && v5)) {
        e.preventDefault();
      }
    });
  })();
</script>

</body>
</html>
