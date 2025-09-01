<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

require_user();
$me = current_user();

$qid = (int)($_POST['qid'] ?? 0);
$decision = strtolower(trim((string)($_POST['decision'] ?? '')));
if (!$qid || !in_array($decision, ['accept','reject'], true)) {
  header('Location: ' . BASE_URL . 'user/dashboard.php');
  exit;
}

$con = db();

// Load the quotation + owning user + prescription id
$sql = "SELECT q.id, q.status, q.expires_at, q.prescription_id, p.user_id
        FROM quotations q
        JOIN prescriptions p ON p.id = q.prescription_id
        WHERE q.id = ?";
$stmt = $con->prepare($sql);
$stmt->bind_param('i', $qid);
$stmt->execute();
$quote = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quote || (int)$quote['user_id'] !== (int)$me['id']) {
  header('Location: ' . BASE_URL . 'user/dashboard.php');
  exit;
}

// Helper to check if a column exists (so we don't crash if timestamps aren't there)
function column_exists(mysqli $con, string $table, string $column): bool {
  $t = $con->real_escape_string($table);
  $c = $con->real_escape_string($column);
  $res = $con->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return $res && $res->num_rows > 0;
}

$hasAcceptedAt = column_exists($con, 'quotations', 'accepted_at');
$hasRejectedAt = column_exists($con, 'quotations', 'rejected_at');

// Is the quotation still actionable?
$open = ($quote['status'] === 'sent' && (empty($quote['expires_at']) || strtotime($quote['expires_at']) > time()));

$con->begin_transaction();

try {
  if ($open) {
    if ($decision === 'accept') {
      // Update quotation
      if ($hasAcceptedAt) {
        $u = $con->prepare("UPDATE quotations SET status='accepted', accepted_at=NOW() WHERE id=?");
      } else {
        $u = $con->prepare("UPDATE quotations SET status='accepted' WHERE id=?");
      }
      $u->bind_param('i', $qid);
      $u->execute();
      $u->close();

      // Update prescription status -> accepted
      $pupd = $con->prepare("UPDATE prescriptions SET status='accepted' WHERE id=?");
      $pupd->bind_param('i', $quote['prescription_id']);
      $pupd->execute();
      $pupd->close();

      // (Optional) If you want to auto-reject other quotations for this prescription:
      // $x = $con->prepare("UPDATE quotations SET status='rejected' WHERE prescription_id=? AND id<>?");
      // $x->bind_param('ii', $quote['prescription_id'], $qid);
      // $x->execute();
      // $x->close();

    } else { // reject
      // Update quotation
      if ($hasRejectedAt) {
        $u = $con->prepare("UPDATE quotations SET status='rejected', rejected_at=NOW() WHERE id=?");
      } else {
        $u = $con->prepare("UPDATE quotations SET status='rejected' WHERE id=?");
      }
      $u->bind_param('i', $qid);
      $u->execute();
      $u->close();

      // Update prescription status -> rejected
      // NOTE: If you only want to mark the prescription rejected when *all* quotations are rejected,
      // replace this with logic to check other quotations. Per your request, we set it to 'rejected' directly.
      $pupd = $con->prepare("UPDATE prescriptions SET status='rejected' WHERE id=?");
      $pupd->bind_param('i', $quote['prescription_id']);
      $pupd->execute();
      $pupd->close();
    }
  }
  // If not open, do nothing to statuses.

  $con->commit();
} catch (Throwable $e) {
  $con->rollback();
  error_log('quotation_decide error: ' . $e->getMessage());
}

// Always redirect back to dashboard
header('Location: ' . BASE_URL . 'user/dashboard.php');
exit;
