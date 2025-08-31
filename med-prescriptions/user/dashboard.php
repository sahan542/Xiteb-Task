<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

require_user();
$me = current_user();

$mysqli = db();
// Query: include quotation_id (latest one if there are multiple)
$stmt = $mysqli->prepare('
  SELECT
    p.*,
    (SELECT COUNT(*) FROM prescription_images pi WHERE pi.prescription_id = p.id) AS img_count,
    (
      SELECT q.id
      FROM quotations q
      WHERE q.prescription_id = p.id
      ORDER BY q.created_at DESC
      LIMIT 1
    ) AS quotation_id
  FROM prescriptions p
  WHERE p.user_id = ?
  ORDER BY p.created_at DESC
');


$stmt->bind_param('i', $me['id']);
$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>My Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

  <!-- Top Navigation -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
      <span class="navbar-brand fw-bold">Xiteb Prescription Mangement System</span>
      <div class="d-flex align-items-center">
        <span class="text-white me-3">Welcome, <?= e($me['name']) ?></span>

        <a href="<?= e(BASE_URL) ?>/user/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
      </div>
    </div>
  </nav>

  <!-- Content -->
  <div class="container my-5">

  <div class="d-flex justify-content-end mb-3">
    <a href="<?= e(BASE_URL) ?>/user/prescription_new.php"
       class="btn btn-primary btn-sm">
      + New Prescription
    </a>
  </div>
  <div class="card shadow rounded-3">

      <div class="card-body">
        <h3 class="card-title mb-4">Manage My Prescriptions</h3>

        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-dark">
              <tr>
                <th>ID</th>
                <th>Note</th>
                <th>Delivery Address</th>
                <th>Time Slot</th>
                <th>Images</th>
                <th>Status</th>
                <th>Created</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$rows): ?>
                <tr>
                  <td colspan="7" class="text-center text-muted">No prescriptions yet.</td>
                </tr>
              <?php else: foreach ($rows as $r): ?>
                <tr>
                  <td><?= e($r['id']) ?></td>
                  <td><?= e($r['note']) ?></td>
                  <td><?= e($r['delivery_address']) ?></td>
                  <td><?= e($r['delivery_time_slot']) ?></td>
                  <td><?= e($r['img_count']) ?></td>
<td>
  <?php
    // normalize status value
    $status = strtolower(trim((string)$r['status']));
    $badgeClass = ($status === 'quoted') ? 'bg-info text-dark' : 'bg-secondary';
  ?>
  <span class="badge <?= $badgeClass ?>"><?= e($r['status']) ?></span>

  <?php if ($status === 'quoted' && isset($r['quotation_id'])): ?>
    <a href="<?= e(BASE_URL) ?>/user/quotation_show.php?id=<?= e($r['quotation_id']) ?>"
       class="btn btn-sm btn-outline-info ms-2">
      View Quotation
    </a>
  <?php endif; ?>
</td>


                  <td><?= e($r['created_at']) ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</body>
</html>
