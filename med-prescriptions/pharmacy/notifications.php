<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

require_user();
$me   = current_user();
$role = strtolower((string)($me['role'] ?? ($_SESSION['user']['role'] ?? '')));

/* Role gating */
if ($role === 'user') {
  header('Location: ' . BASE_URL . 'user/dashboard.php');
  exit;
}
if ($role !== 'pharmacy') {
  header('Location: ' . BASE_URL . 'login.php');
  exit;
}

$con = db();

/* Unread count for navbar */
$unread = 0;
try {
  $stmt = $con->prepare("SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND seen = 0");
  $stmt->bind_param('i', $me['id']);
  $stmt->execute();
  $unread = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
  $stmt->close();
} catch (Throwable $e) { $unread = 0; }

$rows = [];
$stmt = $con->prepare("
  SELECT id, user_id, type, entity_id, message, seen, created_at
  FROM notifications
  WHERE user_id = ?
  ORDER BY created_at DESC, id DESC
");
$stmt->bind_param('i', $me['id']);
$stmt->execute();
$res = $stmt->get_result();
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

function notification_link(?string $type, ?int $entityId): ?string {
  $type = strtolower((string)$type);
  if (($type === 'quotation' || $type === 'quotation_sent' || $type === 'quotation_updated') && $entityId) {
    return rtrim(BASE_URL, '/') . '/user/quotation_show.php?id=' . (int)$entityId;
  }
  if (($type === 'prescription' || $type === 'prescription_status') && $entityId) {
    return rtrim(BASE_URL, '/') . '/user/prescription_show.php?id=' . (int)$entityId;
  }
  return null;
}
function notification_title(?string $type): string {
  $t = trim((string)$type);
  if ($t === '') return 'Notification';
  return ucwords(str_replace('_', ' ', strtolower($t)));
}
function seen_badge(int $seen): string { return $seen ? 'bg-secondary' : 'bg-primary'; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Notifications</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(BASE_URL) ?>style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <span class="navbar-brand fw-bold">Xiteb Prescription Mangement System</span>
    <div class="d-flex align-items-center ms-auto" style="margin-right:10px;">
      <a href="<?= e(BASE_URL) ?>pharmacy/notifications.php"
         class="btn btn-outline-warning btn-sm rounded-circle position-relative d-inline-flex align-items-center justify-content-center me-4"
         style="width:38px;height:38px" title="Notifications">
        <i class="bi bi-bell"></i>
        <?php if ($unread > 0): ?>
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
            <?= $unread > 99 ? '99+' : (int)$unread ?>
          </span>
        <?php endif; ?>
      </a>

      <span class="text-white me-3">Welcome, <?= e($me['name'] ?? 'User') ?></span>
      <a href="<?= e(BASE_URL) ?>user/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
    </div>
  </div>
</nav>

<div class="container my-5">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0">Notifications</h3>
    <a href="<?= e(BASE_URL) ?>pharmacy/prescriptions.php" class="btn btn-outline-secondary btn-sm">Back</a>
  </div>

  <?php if (!$rows): ?>
    <div class="text-center text-muted py-5">No notifications.</div>
  <?php else: ?>
    <div class="accordion" id="notifAccordion">
      <?php foreach ($rows as $i => $n): ?>
        <?php
          $nid        = (int)$n['id'];
          $title      = notification_title($n['type'] ?? '');
          $message    = nl2br(e($n['message'] ?? ''));
          $seen       = (int)$n['seen'];
          $created    = e(date('Y-m-d H:i', strtotime((string)$n['created_at'])));
          $link       = notification_link($n['type'] ?? null, isset($n['entity_id']) ? (int)$n['entity_id'] : null);
          $collapseId = 'collapseNotif' . $nid;
          $headingId  = 'headingNotif' . $nid;
        ?>
        <div class="card mb-2">
          <div class="card-header d-flex justify-content-between align-items-center" id="<?= $headingId ?>">
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-bell-fill text-warning"></i>
              <strong><?= e($title) ?></strong>
              <span class="badge <?= seen_badge($seen) ?>"><?= $seen ? 'Read' : 'New' ?></span>
            </div>
            <small class="text-muted"><?= $created ?></small>
          </div>

          <div class="card-body p-0">
            <button class="btn w-100 text-start px-3 py-2" type="button"
                    data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>"
                    aria-expanded="false" aria-controls="<?= $collapseId ?>">
              <span class="me-2"><i class="bi bi-chevron-down"></i></span>
              <span>View details</span>
            </button>

            <div id="<?= $collapseId ?>" class="collapse" data-bs-parent="#notifAccordion">
              <div class="px-3 pb-3">
                <p class="mb-2"><?= $message ?></p>
                <?php if ($link): ?>
                  <a href="<?= e($link) ?>" class="btn btn-sm btn-outline-primary">Open</a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
