<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';  

$uid = $_SESSION['user']['id'] ?? 0;
if (!$uid) { http_response_code(401); exit('Unauthorized'); }

$con = db();

$sql = "SELECT q.*,
       (SELECT image_path FROM prescription_images pi 
         WHERE pi.prescription_id=q.prescription_id 
         ORDER BY pi.id ASC LIMIT 1) AS first_img
       FROM quotations q
       JOIN prescriptions p ON p.id=q.prescription_id
       WHERE p.user_id=?
       ORDER BY q.created_at DESC";

$stmt = $con->prepare($sql);
$stmt->bind_param('i', $uid);
$stmt->execute();
$res = $stmt->get_result();
$qts = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function badge_class_for_status($status, $expires_at) {
  $status = strtolower((string)$status);
  $expired = ($expires_at && strtotime($expires_at) !== false && strtotime($expires_at) < time());
  if ($status === 'accepted') return 'bg-success';
  if ($status === 'rejected') return 'bg-danger';
  if ($status === 'sent' && $expired) return 'bg-warning text-dark';
  return 'bg-secondary';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>My Quotations</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">


  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>style.css">

  <style>

    .grid-table thead th,
    .grid-table tbody td,
    .grid-table tfoot td {
      border: 1px solid #dee2e6;  
      text-align: center;
      vertical-align: middle;
    }
    .rx-thumb {
      width: 70px; height: 70px; object-fit: cover; border-radius: .35rem;
      border: 1px solid #e9ecef; background: #f8f9fa;
    }
    .table thead th { white-space: nowrap; }
  </style>
</head>
<body class="bg-light">


<nav class="navbar navbar-dark bg-dark">
  <div class="container-fluid">
    <span class="navbar-brand fw-bold">Xiteb</span>
  </div>
</nav>

<div class="container my-5">
  <div class="card shadow rounded-3">
    <div class="card-body">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h3 class="card-title mb-0">My Quotations</h3>
        <div class="d-flex gap-2">
          <a href="<?= h(BASE_URL) ?>user/prescription_new.php" class="btn btn-primary btn-sm">+ New Prescription</a>
          <a href="<?= h(BASE_URL) ?>user/logout.php" class="btn btn-outline-secondary btn-sm">Logout</a>
        </div>
      </div>

      <div class="table-responsive">
<table class="table table-hover align-middle grid-table">
  <thead class="table-dark">
    <tr>
      <th>#</th>
      <th>Status</th>
      <th>Total</th>
      <th>Expires</th>
      <th>Action</th>
    </tr>
  </thead>
  <tbody>
  <?php if (!$qts): ?>
    <tr>
      <td colspan="5" class="text-center text-muted py-4">No quotations yet.</td>
    </tr>
  <?php else: ?>
    <?php $i = 1; foreach ($qts as $q): ?>
      <?php
        $badgeClass = badge_class_for_status($q['status'] ?? '', $q['expires_at'] ?? null);
      ?>
      <tr>
        <td><?= $i++ ?></td>
        <td>
          <span class="badge <?= h($badgeClass) ?>">
            <?= h($q['status']) ?>
          </span>
        </td>
        <td><?= number_format((float)($q['total'] ?? 0), 2) ?></td>
        <td><?= h($q['expires_at'] ?? '') ?></td>
        <td>
          <div class="d-flex justify-content-center gap-2">
            <a class="btn btn-sm btn-outline-primary"
               href="<?= h(BASE_URL . 'user/quotation_show.php?id=' . (int)$q['id']) ?>">
              View
            </a>
            <?php if (($q['status'] ?? '') === 'sent'): ?>
              <form method="post" action="<?= h(BASE_URL) ?>user/quotation_decide.php" class="d-inline">
                <input type="hidden" name="qid" value="<?= (int)$q['id'] ?>">
                <div class="d-flex gap-2">
                  <button name="decision" value="accept" class="btn btn-sm btn-success">Accept</button>
                  <button name="decision" value="reject" class="btn btn-sm btn-danger"
                          onclick="return confirm('Reject this quotation?')">Reject</button>
                </div>
              </form>
            <?php endif; ?>
          </div>
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
