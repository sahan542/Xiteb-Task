<?php
require_once __DIR__ . '/inc/mailer.php';

$ok = send_email('test@example.com', 'Test Mailtrap Email', '<p>Hello from Mailtrap test!</p>');

echo $ok ? "✅ Sent OK" : "❌ Failed (check error log)";
