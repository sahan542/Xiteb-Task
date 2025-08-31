<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/mailer.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user'])) {
  $next = urlencode($_SERVER['REQUEST_URI'] ?? BASE_URL);
  header('Location: ' . BASE_URL . 'login.php?next=' . $next);
  exit;
}
if (($_SESSION['user']['role'] ?? '') !== 'pharmacy') {
  header('Location: ' . BASE_URL . 'user/quotations.php');
  exit;
}

$qid  = (int)($_POST['qid'] ?? 0);
$days = max(1, (int)($_POST['days'] ?? 7));

if ($qid <= 0) { http_response_code(400); exit('Invalid quotation id'); }

$con = db();

$sql = "SELECT q.*, p.user_id, u.email
        FROM quotations q
        JOIN prescriptions p ON p.id = q.prescription_id
        JOIN users u ON u.id = p.user_id
        WHERE q.id = ? AND q.pharmacy_id = ?";
$stmt = $con->prepare($sql);
$stmt->bind_param('ii', $qid, $_SESSION['user']['id']);
$stmt->execute();
$quote = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quote) { http_response_code(404); exit; }

$expiresAt = (new DateTime())->modify("+{$days} days")->format('Y-m-d H:i:s');

$sql = "UPDATE quotations SET status='sent', expires_at=? WHERE id=?";
$stmt = $con->prepare($sql);
$stmt->bind_param('si', $expiresAt, $qid);
$stmt->execute();
$stmt->close();

$sql = "UPDATE prescriptions SET status='quoted' WHERE id=?";
$stmt = $con->prepare($sql);
$stmt->bind_param('i', $quote['prescription_id']);
$stmt->execute();
$stmt->close();

$sql = "SELECT * FROM quotation_items WHERE quotation_id=?";
$stmt = $con->prepare($sql);
$stmt->bind_param('i', $qid);
$stmt->execute();
$res   = $stmt->get_result();
$items = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$trs = '';
foreach ($items as $i) {
  $trs .= '<tr>'
        . '<td>' . h($i['drug']) . '</td>'
        . '<td>' . (int)$i['quantity'] . '</td>'
        . '<td>' . number_format((float)$i['unit_price'], 2) . '</td>'
        . '<td>' . number_format((float)$i['line_total'], 2) . '</td>'
        . '</tr>';
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$link   = $scheme . '://' . $host . BASE_URL . 'user/quotation_show.php?id=' . $qid;

$html = "<h3>Your quotation is ready</h3>
<p>Expires: <b>{$expiresAt}</b></p>
<table border='1' cellpadding='6'>
  <tr><th>Drug</th><th>Qty</th><th>Unit</th><th>Amount</th></tr>{$trs}
  <tr><td colspan='3' align='right'><b>Total</b></td><td><b>" . number_format((float)$quote['total'], 2) . "</b></td></tr>
</table>
<p><a href='{$link}'>View / Accept / Reject</a></p>";

send_email($quote['email'], "Prescription quotation #{$qid}", $html);

$sql = "INSERT INTO notifications (user_id, type, entity_id, message) VALUES (?,?,?,?)";
$type = 'quotation_sent';
$msg  = 'A new quotation is available';
$stmt = $con->prepare($sql);
$stmt->bind_param('isis', $quote['user_id'], $type, $qid, $msg);
$stmt->execute();
$stmt->close();

header('Location: ' . BASE_URL . 'pharmacy/prescriptions.php');
exit;
