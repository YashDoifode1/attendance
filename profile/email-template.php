<?php

function sendPasswordResetEmail($email, $token) {
    $app_name = "Professional Web Consultant";
    $app_url = "http://localhost/attendance";
    $reset_link = "$app_url/auth/reset-password.php?token=$token";
    
    $subject = "🔐 Reset Your Password - $app_name";
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Password Reset</title>
        <style>
            body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; text-align: center; }
            .container { max-width: 500px; background: white; padding: 20px; margin: auto; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
            h2 { color: #333; }
            p { font-size: 16px; color: #555; }
            a.button {
                display: inline-block; padding: 10px 20px; margin-top: 20px;
                background: #007bff; color: white; text-decoration: none;
                font-size: 18px; border-radius: 5px;
            }
            a.button:hover { background: #0056b3; }
            .footer { margin-top: 20px; font-size: 14px; color: #777; }
        </style>
    </head>
    <body>
        <div class="container">
            <h2>Password Reset Request</h2>
            <p>Hello,</p>
            <p>We received a request to reset your password. Click the button below to set a new password:</p>
            <a href="'.$reset_link.'" class="button">Reset Password</a>
            <p>If you did not request this, please ignore this email.</p>
            <p class="footer">© '.date("Y").' '.$app_name.'. All rights reserved.</p>
        </div>
    </body>
    </html>';

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: no-reply@$app_url" . "\r\n";

    return mail($email, $subject, $message, $headers);
}
?>
