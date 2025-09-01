<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/mailer.php';

require_user();
$me = current_user();

$qid  = (int)($_POST['qid'] ?? 0);
$days = max(1, min(30, (int)($_POST['days'] ?? 7)));

if (!$qid) {
  header('Location: ' . BASE_URL . 'pharmacy/prescriptions.php');
  exit;
}

$con = db();

/** Helper: check if a column exists (so we don't reference missing cols) */
function column_exists(mysqli $con, string $table, string $column): bool {
  $t = $con->real_escape_string($table);
  $c = $con->real_escape_string($column);
  $res = $con->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return $res && $res->num_rows > 0;
}
$hasExpiresAt = column_exists($con, 'quotations', 'expires_at');
$hasSentAt    = column_exists($con, 'quotations', 'sent_at');

/** Load quotation + recipient + ownership (pharmacy) */
$sql = "SELECT q.id, q.status, q.prescription_id, q.pharmacy_id, q.total, q.expires_at,
               p.user_id, u.email
        FROM quotations q
        JOIN prescriptions p ON p.id = q.prescription_id
        JOIN users u ON u.id = p.user_id
        WHERE q.id = ?";
$stmt = $con->prepare($sql);
$stmt->bind_param('i', $qid);
$stmt->execute();
$quote = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quote) {
  error_log("Send quote: quotation not found id=$qid");
  header('Location: ' . BASE_URL . 'pharmacy/prescriptions.php');
  exit;
}

/** Ensure this quote belongs to the logged-in pharmacy (avoid tampering) */
if (!isset($quote['pharmacy_id']) || (int)$quote['pharmacy_id'] !== (int)$me['id']) {
  error_log("Send quote: unauthorized pharmacy user={$me['id']} for qid=$qid");
  header('Location: ' . BASE_URL . 'pharmacy/prescriptions.php');
  exit;
}

/** Compute/refresh expiry */
$expiresAt = (new DateTime("+$days days"))->format('Y-m-d 23:59:59');

/** DB writes in a transaction */
$con->begin_transaction();

try {
  // 1) Update quotation -> sent (+timestamps if they exist)
  if ($hasExpiresAt && $hasSentAt) {
    $upd = $con->prepare("UPDATE quotations SET status='sent', expires_at=?, sent_at=NOW() WHERE id=?");
    $upd->bind_param('si', $expiresAt, $qid);
  } elseif ($hasExpiresAt) {
    $upd = $con->prepare("UPDATE quotations SET status='sent', expires_at=? WHERE id=?");
    $upd->bind_param('si', $expiresAt, $qid);
  } elseif ($hasSentAt) {
    // no expires_at column
    $upd = $con->prepare("UPDATE quotations SET status='sent', sent_at=NOW() WHERE id=?");
    $upd->bind_param('i', $qid);
  } else {
    $upd = $con->prepare("UPDATE quotations SET status='sent' WHERE id=?");
    $upd->bind_param('i', $qid);
  }
  $upd->execute();
  $upd->close();

  // 2) Mark the linked prescription as 'quoted'
  $pupd = $con->prepare("UPDATE prescriptions SET status='quoted' WHERE id=?");
  $pupd->bind_param('i', $quote['prescription_id']);
  $pupd->execute();
  $pupd->close();

  $con->commit();
} catch (Throwable $e) {
  $con->rollback();
  error_log("quotation_send txn failed for qid=$qid: " . $e->getMessage());
  header('Location: ' . BASE_URL . 'pharmacy/prescriptions.php');
  exit;
}

/** Build and send email */
$to   = $quote['email'];
$subj = "Your Quotation #$qid";
$link = rtrim(BASE_URL, '/') . "/user/quotation_show.php?id=$qid";
$expiryText = $hasExpiresAt ? htmlspecialchars(date('Y-m-d', strtotime($expiresAt))) : '—';

$html = '
  <div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:#222">
    <h2 style="margin:0 0 10px">Your quotation is ready</h2>
    <p>Quotation ID: <b>'.(int)$qid.'</b></p>
    <p>Expires on: <b>'.$expiryText.'</b></p>
    <p><a href="'.$link.'" style="background:#16a34a;color:#fff;padding:10px 14px;text-decoration:none;border-radius:6px;display:inline-block">View &amp; Respond</a></p>
    <p>If the button doesn\'t work, copy this link into your browser:<br><a href="'.$link.'">'.$link.'</a></p>
  </div>
';

$ok = false;
try {
  $ok = send_email($to, $subj, $html);
} catch (Throwable $t) {
  error_log("send_email threw for qid=$qid: " . $t->getMessage());
}

if (!$ok) {
  error_log("Email NOT sent for quote $qid to $to");
}

/** Back to list */
header('Location: ' . BASE_URL . 'pharmacy/prescriptions.php');
exit;
