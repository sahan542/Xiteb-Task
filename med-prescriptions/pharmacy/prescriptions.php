<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/helpers.php'; // <-- add this so e() exists

if (session_status() === PHP_SESSION_NONE) session_start();

require_user();                 // <-- ensure logged in
$me = current_user();           // <-- so $me is defined

if (($_SESSION['user']['role'] ?? '') !== 'pharmacy') {
  header('Location: ' . BASE_URL . 'user/quotations.php');
  exit;
}

$con = db();
$sql = "
  SELECT p.*, u.email,
         (SELECT image_path 
            FROM prescription_images pi 
            WHERE pi.prescription_id = p.id 
            ORDER BY pi.id ASC LIMIT 1) AS first_img
  FROM prescriptions p
  JOIN users u ON u.id = p.user_id
  WHERE p.status IN ('pending','quoted','accepted','rejected')
  ORDER BY p.created_at DESC
";
$res  = $con->query($sql);
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

function img_url(?string $fname): string {
  if (!$fname) return '';

  return rtrim(BASE_URL, '/') . '/public/uploads/prescriptions/' . rawurlencode($fname);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Prescriptions</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(BASE_URL) ?>style.css">

  <style>
    .grid-table thead th,
    .grid-table tbody td {
      border: 1px solid #dee2e6;
      text-align: center;
      vertical-align: middle;
    }
    .rx-thumb {
      width: 80px; height: 80px;
      object-fit: cover;
      border-radius: .35rem;
      border: 1px solid #e9ecef;
      background: #f8f9fa;
    }
  </style>
</head>
<body class="bg-light">

  <!-- Top Navigation -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
      <span class="navbar-brand fw-bold">Xiteb Prescription Mangement System</span>
      <div class="d-flex align-items-center ms-auto">
        <span class="text-white me-3">Welcome, <?= e($me['name'] ?? 'User') ?></span>
        <a href="<?= e(BASE_URL) ?>user/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
      </div>
    </div>
  </nav>

<div class="container my-5">
  <div class="card shadow rounded-3">
    <div class="card-body">
      <h3 class="card-title mb-4">Prescriptions</h3>

      <div class="table-responsive">
        <table class="table table-hover align-middle grid-table">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>User</th>
              <th>Status</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
    <?php if (empty($rows)): ?>
      <tr>
        <td colspan="5" class="text-center text-muted py-4">No prescriptions found.</td>
      </tr>
    <?php else: ?>
      <?php $i = 1; foreach ($rows as $r): ?>
        <tr>
          <td><?= $i++ ?></td>
          <td><?= e($r['email']) ?></td>
          <td>
            <?php if ($r['status'] === 'pending'): ?>
              <span class="badge bg-warning text-dark">Pending</span>
            <?php elseif ($r['status'] === 'quoted'): ?>
              <span class="badge bg-info text-dark">Quoted</span>
            <?php else: ?>
              <span class="badge bg-secondary"><?= e($r['status']) ?></span>
            <?php endif; ?>
          </td>
          <td><?= e($r['created_at']) ?></td>
<td>
  <?php
    $st = strtolower(trim((string)$r['status'])); 
    $canPrepare = in_array($st, ['quoted','pending'], true);
  ?>
  <?php if ($canPrepare): ?>
    <a href="<?= e(BASE_URL) . 'pharmacy/quotation_new.php?pid=' . (int)$r['id'] ?>"
       class="btn btn-sm btn-outline-primary">
      Prepare quotation
    </a>
  <?php else: ?>
    <span class="text-muted">Done</span>
  <?php endif; ?>
</td>

        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

</body>
</html>
