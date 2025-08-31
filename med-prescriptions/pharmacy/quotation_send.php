<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

require_user();
$me = current_user();

$qid  = (int)($_POST['qid'] ?? 0);
$days = max(1, min(30, (int)($_POST['days'] ?? 7)));

if (!$qid) {
  header('Location: ' . BASE_URL . 'pharmacy/quotations.php');
  exit;
}

$con = db();

// Load quotation + recipient
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
  header('Location: ' . BASE_URL . 'pharmacy/quotations.php'); exit;
}

// Only allow sending from draft (or you can relax to allow re-send of 'sent')
if ($quote['status'] !== 'draft') {
  // proceed anyway to overwrite sent date? your call:
  // header('Location: ' . BASE_URL . 'pharmacy/quotations.php'); exit;
}

// Mark as sent + expiry
$expiresAt = (new DateTime("+$days days"))->format('Y-m-d 23:59:59');
$upd = $con->prepare("UPDATE quotations SET status='sent', expires_at=?, sent_at=NOW() WHERE id=?");
$upd->bind_param('si', $expiresAt, $qid);
$upd->execute();
$upd->close();

// Build email
$to   = $quote['email'];
$subj = "Your Quotation #$qid";
$link = rtrim(BASE_URL, '/') . "/user/quotation_show.php?id=$qid";
$body = "Hello,\n\nYour pharmacy has sent a quotation.\n\n"
      . "Quotation ID: $qid\n"
      . "Expires on: " . date('Y-m-d', strtotime($expiresAt)) . "\n\n"
      . "View & respond: $link\n\n"
      . "Thank you.";

// --- Sending options ---
// 1) Native mail() — requires a working MTA on the server:
$headers  = "From: no-reply@example.com\r\n";
$headers .= "Reply-To: no-reply@example.com\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

$sent = @mail($to, $subj, $body, $headers);

// 2) PHPMailer via SMTP (recommended) — uncomment + configure to use:
/*
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
require_once __DIR__ . '/../vendor/autoload.php';

try {
  $mail = new PHPMailer(true);
  $mail->isSMTP();
  $mail->Host       = 'smtp.yourhost.com';
  $mail->SMTPAuth   = true;
  $mail->Username   = 'smtp_user';
  $mail->Password   = 'smtp_pass';
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // or PHPMailer::ENCRYPTION_SMTPS
  $mail->Port       = 587;

  $mail->setFrom('no-reply@example.com', 'Pharmacy');
  $mail->addAddress($to);
  $mail->Subject = $subj;
  $mail->Body    = $body;

  $sent = $mail->send();
} catch (Throwable $e) {
  error_log("PHPMailer error for quote $qid: " . $e->getMessage());
  $sent = false;
}
*/

if (!$sent) {
  error_log("Email NOT sent for quote $qid to $to");
} else {
  error_log("Email sent for quote $qid to $to");
}

// Redirect wherever makes sense for the pharmacy UI
header('Location: ' . BASE_URL . 'pharmacy/quotations.php');
exit;
