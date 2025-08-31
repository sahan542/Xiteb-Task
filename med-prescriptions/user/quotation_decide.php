<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/mailer.php';

$qid = (int)($_POST['qid'] ?? 0);
$decision = $_POST['decision'] ?? '';

$con = db(); 

$stmt = $con->prepare("SELECT q.*, p.user_id, p.id AS pid, q.pharmacy_id, pharm.email AS pharmacy_email
  FROM quotations q
  JOIN prescriptions p ON p.id=q.prescription_id
  JOIN users pharm ON pharm.id=q.pharmacy_id
  WHERE q.id=?");
$stmt->bind_param('i', $qid);
$stmt->execute();
$result = $stmt->get_result();
$quote = $result->fetch_assoc();
$stmt->close();

if (!$quote || $quote['user_id'] != $_SESSION['user']['id'] || $quote['status']!=='sent') {
    http_response_code(400); exit;
}

if ($decision === 'accept') {
    $stmt = $con->prepare("UPDATE quotations SET status='accepted' WHERE id=?");
    $stmt->bind_param('i',$qid); $stmt->execute(); $stmt->close();

    $stmt = $con->prepare("UPDATE quotations SET status='rejected' WHERE prescription_id=? AND id<>? AND status='sent'");
    $stmt->bind_param('ii',$quote['prescription_id'],$qid); $stmt->execute(); $stmt->close();

    $stmt = $con->prepare("UPDATE prescriptions SET status='accepted' WHERE id=?");
    $stmt->bind_param('i',$quote['pid']); $stmt->execute(); $stmt->close();

    send_email($quote['pharmacy_email'], "Quotation #{$qid} accepted", "Customer accepted quotation #{$qid}.");
    $msg='accepted';
} else {
    $stmt = $con->prepare("UPDATE quotations SET status='rejected' WHERE id=?");
    $stmt->bind_param('i',$qid); $stmt->execute(); $stmt->close();

    $stmt = $con->prepare("UPDATE prescriptions SET status='rejected' WHERE id=?");
    $stmt->bind_param('i',$quote['pid']); $stmt->execute(); $stmt->close();

    send_email($quote['pharmacy_email'], "Quotation #{$qid} rejected", "Customer rejected quotation #{$qid}.");
    $msg='rejected';
}

$stmt = $con->prepare("INSERT INTO notifications(user_id,type,entity_id,message) VALUES(?,?,?,?)");
$type = 'quotation_'.$msg;
$message = "Quotation #{$qid} {$msg} by customer";
$stmt->bind_param('isis',$quote['pharmacy_id'],$type,$qid,$message);
$stmt->execute();
$stmt->close();

header("Location: /user/quotations.php");
