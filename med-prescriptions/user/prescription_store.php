<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

require_user();
$me = current_user();

$note = trim($_POST['note'] ?? '');
$delivery_address = trim($_POST['delivery_address'] ?? '');
$delivery_time_slot = trim($_POST['delivery_time_slot'] ?? '');

$errors = [];
if ($delivery_address === '') $errors[] = 'Delivery address is required.';
if ($delivery_time_slot === '') $errors[] = 'Delivery time slot is required.';

$files = $_FILES['images'] ?? null;
$validUploads = [];

if ($files && is_array($files['name'])) {
  $selectedCount = 0;
  for ($i=0; $i<count($files['name']); $i++) {
    if (($files['name'][$i] ?? '') !== '') $selectedCount++;
  }
  if ($selectedCount > MAX_IMAGES) {
    $errors[] = 'You can upload up to ' . MAX_IMAGES . ' images.';
  } else {
    for ($i=0; $i<count($files['name']); $i++) {
      if (($files['name'][$i] ?? '') === '') continue;
      $one = [
        'name' => $files['name'][$i],
        'type' => $files['type'][$i],
        'tmp_name' => $files['tmp_name'][$i],
        'error' => $files['error'][$i],
        'size' => $files['size'][$i],
      ];
      if (!is_valid_image_upload($one)) {
        $errors[] = 'Invalid image: ' . e($one['name']);
      } else {
        $validUploads[] = $one;
      }
    }
  }
}

if ($errors) {

  $_SESSION['form_errors'] = $errors;
  header('Location: ' . BASE_URL . '/user/prescription_new.php');
  exit;
}

$mysqli = db();
$stmt = $mysqli->prepare('INSERT INTO prescriptions (user_id, note, delivery_address, delivery_time_slot) VALUES (?,?,?,?)');
$stmt->bind_param('isss', $me['id'], $note, $delivery_address, $delivery_time_slot);
if (!$stmt->execute()) {
  die('Failed to save prescription.');
}
$prescription_id = $stmt->insert_id;

if ($validUploads) {
  $stmtImg = $mysqli->prepare('INSERT INTO prescription_images (prescription_id, image_path) VALUES (?,?)');
  foreach ($validUploads as $img) {
    $rel = save_uploaded_image($img);
    if ($rel) {
      $stmtImg->bind_param('is', $prescription_id, $rel);
      $stmtImg->execute();
    }
  }
}

header('Location: ' . BASE_URL . '/user/dashboard.php');
exit;
