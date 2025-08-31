<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function send_email(string $to, string $subject, string $html): bool {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'sandbox.smtp.mailtrap.io';
        $mail->SMTPAuth   = true;
        $mail->Username   = '01ad88750444bb';   
        $mail->Password   = '6ea6aa84cc322c';         
        $mail->Port       = 587;                
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        $mail->setFrom('no-reply@med-prescriptions.local', 'Med Prescriptions');

        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = strip_tags($html);

        return $mail->send();
    } catch (Exception $e) {
        error_log("Mailer error: {$mail->ErrorInfo}");
        return false;
    }
}
