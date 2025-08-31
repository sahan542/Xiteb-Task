<?php
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function time_slots_2h() {
  $out = [];
  for ($h = 8; $h <= 20; $h += 2) {
    $start = sprintf('%02d:00', $h);
    $end   = sprintf('%02d:00', $h + 2);
    $out[] = "$start-$end";
  }
  return $out;
}

function is_valid_image_upload(array $file): bool {
  if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return false;
  if (($file['size'] ?? 0) <= 0 || $file['size'] > 5 * 1024 * 1024) return false;
  $type = @mime_content_type($file['tmp_name']);
  return in_array($type, ['image/jpeg','image/png','image/webp'], true);
}

function save_uploaded_image(array $file): ?string {
  $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
  $name = bin2hex(random_bytes(8)) . '.' . strtolower($ext ?: 'jpg');
  $dest = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $name;
  if (!is_dir(UPLOAD_DIR)) { @mkdir(UPLOAD_DIR, 0775, true); }
  if (move_uploaded_file($file['tmp_name'], $dest)) {
    return UPLOAD_REL . $name;
  }
  return null;
}
