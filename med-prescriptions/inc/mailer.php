<?php
function send_email($to, $subject, $html) {
  $headers  = "MIME-Version: 1.0\r\n";
  $headers .= "Content-type:text/html;charset=UTF-8\r\n";
  $headers .= "From: Med Prescriptions <no-reply@localhost>\r\n";
  return mail($to, $subject, $html, $headers);
}
