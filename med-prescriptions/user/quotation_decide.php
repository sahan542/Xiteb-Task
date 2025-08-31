<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

require_user();
$me = current_user();

$qid = (int)($_POST['qid'] ?? 0);
$decision = strtolower(trim((string)($_POST['decision'] ?? '')));
$allowed = ['accept', 'reject'];
if (!$qid || !in_array($decision, $allowed, true)) {
  header('Location: ' . BASE_URL . 'user/dashboard.php');
  exit;
}

$con = db();

/**
 * Ensure this quotation belongs to the logged-in user
 * and is still actionable (status = 'sent' and not expired).
 */
$sql = "SELECT q.id, q.status, q.expires_at, p.user_id
        FROM quotations q
        JOIN prescriptions p ON p.id = q.prescription_id
        WHERE q.id = ?";
$stmt = $con->prepare($sql);
$stmt->bind_param('i', $qid);
$stmt->execute();
$quote = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quote || (int)$quote['user_id'] !== (int)$me['id']) {
  // Not yours or not found → just go back to dashboard
  header('Location: ' . BASE_URL . 'user/dashboard.php');
  exit;
}

// Check if open
$open = ($quote['status'] === 'sent' && (empty($quote['expires_at']) || strtotime($quote['expires_at']) > time()));

if ($open) {
  if ($decision === 'accept') {
    $upd = $con->prepare("UPDATE quotations SET status='accepted', accepted_at=NOW() WHERE id=?");
  } else { // reject
    $upd = $con->prepare("UPDATE quotations SET status='rejected', rejected_at=NOW() WHERE id=?");
  }
  $upd->bind_param('i', $qid);
  $upd->execute();
  $upd->close();
}

// Always redirect to dashboard after decision (or if not open)
header('Location: ' . BASE_URL . 'user/dashboard.php');
exit;
