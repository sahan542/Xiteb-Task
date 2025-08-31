<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/mailer.php'; // <-- ensure this includes PHPMailer + config

require_user();
$me = current_user();

$qid  = (int)($_POST['qid'] ?? 0);
$days = max(1, min(30, (int)($_POST['days'] ?? 7)));

if (!$qid) {
  header('Location: ' . BASE_URL . 'pharmacy/quotations.php');
  exit;
}

$con = db();

/** Load quotation + recipient user */
$sql = "SELECT q.*, p.user_id, u.email
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
  header('Location: ' . BASE_URL . 'pharmacy/quotations.php');
  exit;
}

// Mark as sent + set expiry
$expiresAt = (new DateTime("+$days days"))->format('Y-m-d 23:59:59');
$upd = $con->prepare("UPDATE quotations SET status='sent', expires_at=?, sent_at=NOW() WHERE id=?");
$upd->bind_param('si', $expiresAt, $qid);
$upd->execute();
$upd->close();

// Build email
$to   = $quote['email'];
$subj = "Your Quotation #$qid";
$link = rtrim(BASE_URL, '/') . "/user/quotation_show.php?id=$qid";

$html = "
  <div style=\"font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:#222\">
    <h2 style=\"margin:0 0 10px\">Your quotation is ready</h2>
    <p>Quotation ID: <b>$qid</b></p>
    <p>Expires on: <b>" . htmlspecialchars(date('Y-m-d', strtotime($expiresAt))) . "</b></p>
    <p><a href=\"$link\" style=\"background:#16a34a;color:#fff;padding:10px 14px;text-decoration:none;border-radius:6px;display:inline-block\">View & Respond</a></p>
    <p>If the button doesn't work, copy this link into your browser:<br><a href=\"$link\">$link</a></p>
  </div>
";

// Send via PHPMailer SMTP
$ok = send_email($to, $subj, $html);

if (!$ok) {
  error_log("Email NOT sent for quote $qid to $to (see MAIL_LOG_FILE if set)");
}

// Redirect back to pharmacy list (or wherever)
header('Location: ' . BASE_URL . 'pharmacy/quotations.php');
exit;
