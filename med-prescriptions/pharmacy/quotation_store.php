<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

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


$qid = (int)($_POST['qid'] ?? $_GET['qid'] ?? 0);
if ($qid <= 0) { http_response_code(400); exit('Invalid quotation id'); }

$con = db();


if (isset($_GET['del'])) {
  $del = (int)$_GET['del'];
  $sql = "DELETE FROM quotation_items WHERE id=? AND quotation_id=?";
  $stmt = $con->prepare($sql);
  $stmt->bind_param('ii', $del, $qid);
  $stmt->execute();
  $stmt->close();
} else {
  $drug = trim((string)($_POST['drug'] ?? ''));
  $qty  = max(1, (int)($_POST['qty'] ?? 1));
  $unit = (float)($_POST['unit'] ?? 0);
  $line = $qty * $unit;

  if ($drug === '') { http_response_code(400); exit('Drug is required'); }

  $sql = "INSERT INTO quotation_items (quotation_id, drug, quantity, unit_price, line_total)
          VALUES (?,?,?,?,?)";
  $stmt = $con->prepare($sql);
  $stmt->bind_param('isidd', $qid, $drug, $qty, $unit, $line);
  $stmt->execute();
  $stmt->close();
}


$sql = "SELECT COALESCE(SUM(line_total),0) AS s FROM quotation_items WHERE quotation_id=?";
$stmt = $con->prepare($sql);
$stmt->bind_param('i', $qid);
$stmt->execute();
$sub = (float)$stmt->get_result()->fetch_assoc()['s'];
$stmt->close();

$sql = "UPDATE quotations SET subtotal=?, total=? WHERE id=?";
$stmt = $con->prepare($sql);
$stmt->bind_param('ddi', $sub, $sub, $qid);
$stmt->execute();
$stmt->close();


$sql = "SELECT prescription_id FROM quotations WHERE id=?";
$stmt = $con->prepare($sql);
$stmt->bind_param('i', $qid);
$stmt->execute();
$pid = (int)($stmt->get_result()->fetch_assoc()['prescription_id'] ?? 0);
$stmt->close();

if ($pid <= 0) { http_response_code(404); exit('Prescription not found'); }


header('Location: ' . BASE_URL . 'pharmacy/quotation_new.php?pid=' . $pid);
exit;
