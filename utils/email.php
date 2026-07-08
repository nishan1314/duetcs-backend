<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/email-config.php';
require_once __DIR__ . '/../config/database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if notifications are enabled in site settings
function areNotificationsEnabled() {
    try {
        $db = Database::getInstance()->getConnection();
        $result = $db->query("SELECT setting_value FROM site_settings WHERE setting_key = 'enable_notifications' LIMIT 1");
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['setting_value'] === 'true' || $row['setting_value'] === '1';
        }
        return true; // Default to enabled if setting not found
    } catch (Exception $e) {
        error_log("Error checking notification settings: " . $e->getMessage());
        return true; // Default to enabled on error
    }
}

// Create PHPMailer instance
function createMailer() {
    $mail = new PHPMailer(true);
    
    if (ENABLE_EMAIL_SENDING) {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;
    }
    
    // From settings
    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    
    return $mail;
}

// Send verification email with 6-digit code
function sendVerificationEmail($toEmail, $fullName, $verificationCode, $expiryMinutes = 15) {
    // Check if notifications are enabled
    if (!areNotificationsEnabled()) {
        error_log("Email notifications disabled - skipping verification email to: " . $toEmail);
        return true; // Return true so registration continues
    }
    
    $subject = "Email Verification - DUET Computer Society";
    
    $message = "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                line-height: 1.8; 
                color: #333; 
                background-color: #f4f4f4;
                margin: 0;
                padding: 0;
            }
            .container { 
                max-width: 600px; 
                margin: 30px auto; 
                background: #ffffff;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }
            .logo-section {
                text-align: center;
                padding: 30px 20px;
                background: #f8f9fa;
                border-bottom: 1px solid #eee;
            }
            .logo {
                max-width: 80px;
                height: auto;
            }
            .content { 
                padding: 30px 40px;
            }
            .content p { 
                margin-bottom: 16px; 
                font-size: 15px;
            }
            .code-box {
                text-align: center;
                margin: 25px 0;
                padding: 20px;
                background: #f8f9fa;
                border-radius: 8px;
            }
            .code-label {
                font-size: 13px;
                color: #666;
                margin-bottom: 8px;
            }
            .verification-code { 
                font-size: 32px;
                font-weight: bold;
                letter-spacing: 6px;
                color: #009966;
                font-family: 'Courier New', monospace;
            }
            .expiry-note {
                font-size: 14px;
                color: #666;
                margin: 15px 0;
            }
            .security-note {
                font-size: 13px;
                color: #888;
                margin-top: 20px;
            }
            .footer { 
                text-align: center; 
                padding: 25px;
                color: #666; 
                font-size: 13px;
                background: #f8f9fa;
                border-top: 1px solid #eee;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <!-- DUET Logo -->
            <div class='logo-section'>
                <img src='https://i.postimg.cc/gJYv6QHz/duetcs.png' alt='DUET' class='logo' />
            </div>
            
            <!-- Content -->
            <div class='content'>
                <p>Dear <strong>" . htmlspecialchars($fullName) . "</strong>,</p>
                
                <p>Greetings from <strong>DUET Computer Society (DUETCS)</strong>.</p>
                
                <p>Thank you for registering for DUETCS membership. To complete your registration, please verify your email address using the code below:</p>
                
                <div class='code-box'>
                    <p class='code-label'>Verification Code:</p>
                    <p class='verification-code'>" . $verificationCode . "</p>
                </div>
                
                <p class='expiry-note'>This code will expire in <strong>" . $expiryMinutes . " minutes</strong>.</p>
                
                <p class='security-note'>Please do not share this code with anyone.</p>
                
                <p class='security-note'>If you did not request this registration, you may safely ignore this email.</p>
                
                <p>Regards,<br><strong>DUET Computer Society (DUETCS)</strong></p>
            </div>
            
            <!-- Footer -->
            <div class='footer'>
                <p>Dhaka University of Engineering & Technology, Gazipur</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Log email details for development
    error_log("Verification Email for: " . $toEmail);
    error_log("Verification Code: " . $verificationCode);
    
    // Send email using PHPMailer
    try {
        $mail = createMailer();
        $mail->addAddress($toEmail, $fullName);
        $mail->Subject = $subject;
        
        // Add preheader text (visible in email preview)
        $preheader = "Your verification code is: " . $verificationCode;
        $preheaderHtml = "<div style='display:none;max-height:0;overflow:hidden;'>" . $preheader . "</div>";
        
        $mail->Body = $preheaderHtml . $message;
        
        if (ENABLE_EMAIL_SENDING) {
            $mail->send();
            return true;
        } else {
            // Development mode: just log
            error_log("Email would be sent to: " . $toEmail . " (sending disabled in config)");
            return true;
        }
    } catch (Exception $e) {
        error_log("Failed to send email: " . $mail->ErrorInfo);
        return false;
    }
}

// Send welcome email after verification
function sendWelcomeEmail($toEmail, $fullName) {
    // Check if notifications are enabled
    if (!areNotificationsEnabled()) {
        error_log("Email notifications disabled - skipping welcome email to: " . $toEmail);
        return true;
    }
    $subject = "Welcome to DUET Computer Society!";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎉 Email Verified Successfully!</h1>
            </div>
            <div class='content'>
                <p>Hello <strong>" . htmlspecialchars($fullName) . "</strong>,</p>
                <p>Congratulations! Your email has been verified successfully.</p>
                <p>You can now access all features of DUET Computer Society. Log in to your dashboard to get started!</p>
                <p>Here's what you can do:</p>
                <ul>
                    <li>Participate in coding competitions</li>
                    <li>Attend workshops and seminars</li>
                    <li>Network with fellow tech enthusiasts</li>
                    <li>Access exclusive learning resources</li>
                </ul>
                <p>Welcome aboard! 🚀</p>
            </div>
            <div class='footer'>
                <p>&copy; 2025 DUET Computer Society. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    error_log("Welcome Email for: " . $toEmail);
    
    // Send email using PHPMailer
    try {
        $mail = createMailer();
        $mail->addAddress($toEmail, $fullName);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        if (ENABLE_EMAIL_SENDING) {
            $mail->send();
            return true;
        } else {
            // Development mode: just log
            error_log("Welcome email would be sent to: " . $toEmail . " (sending disabled in config)");
            return true;
        }
    } catch (Exception $e) {
        error_log("Failed to send welcome email: " . $mail->ErrorInfo);
        return false;
    }
}

// Send password reset email with link
function sendPasswordResetEmail($toEmail, $fullName, $resetToken) {
    // Check if notifications are enabled
    if (!areNotificationsEnabled()) {
        error_log("Email notifications disabled - skipping password reset email to: " . $toEmail);
        return true;
    }
    
    $subject = "Password Reset - DUET Computer Society";
    $resetLink = getFrontendUrl() . "/reset-password?token=" . $resetToken;
    
    $message = "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                line-height: 1.8; 
                color: #333; 
                background-color: #f4f4f4;
                margin: 0;
                padding: 0;
            }
            .container { 
                max-width: 600px; 
                margin: 30px auto; 
                background: #ffffff;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }
            .logo-section {
                text-align: center;
                padding: 30px 20px;
                background: #f8f9fa;
                border-bottom: 1px solid #eee;
            }
            .logo {
                max-width: 80px;
                height: auto;
            }
            .content { 
                padding: 30px 40px;
            }
            .content p { 
                margin-bottom: 16px; 
                font-size: 15px;
            }
            .btn-container {
                text-align: center;
                margin: 30px 0;
            }
            .reset-btn {
                display: inline-block;
                padding: 14px 40px;
                background: #009966;
                color: #ffffff;
                text-decoration: none;
                border-radius: 6px;
                font-weight: bold;
                font-size: 16px;
            }
            .link-box {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 6px;
                word-break: break-all;
                font-size: 13px;
                color: #666;
                margin: 20px 0;
            }
            .expiry-note {
                font-size: 14px;
                color: #666;
                margin: 15px 0;
            }
            .security-note {
                font-size: 13px;
                color: #888;
                margin-top: 20px;
            }
            .footer { 
                text-align: center; 
                padding: 25px;
                color: #666; 
                font-size: 13px;
                background: #f8f9fa;
                border-top: 1px solid #eee;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <!-- DUET Logo -->
            <div class='logo-section'>
                <img src='https://i.postimg.cc/gJYv6QHz/duetcs.png' alt='DUETCS' class='logo' />
            </div>
            
            <!-- Content -->
            <div class='content'>
                <p>Dear <strong>" . htmlspecialchars($fullName) . "</strong>,</p>
                
                <p>We received a request to reset your password for your DUET Computer Society account.</p>
                
                <p>Click the button below to reset your password:</p>
                
                <div class='btn-container'>
                    <a href='" . $resetLink . "' class='reset-btn'>Reset Password</a>
                </div>
                
                <p class='expiry-note'>This link will expire in <strong>1 hour</strong>.</p>
                
                <p>If the button doesn't work, copy and paste this link into your browser:</p>
                <div class='link-box'>" . $resetLink . "</div>
                
                <p class='security-note'>If you did not request a password reset, please ignore this email. Your password will remain unchanged.</p>
                
                <p>Regards,<br><strong>DUET Computer Society (DUETCS)</strong></p>
            </div>
            
            <!-- Footer -->
            <div class='footer'>
                <p>Dhaka University of Engineering & Technology, Gazipur</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Log for development
    error_log("Password Reset Email for: " . $toEmail);
    error_log("Reset Link: " . $resetLink);
    
    // Send email using PHPMailer
    try {
        $mail = createMailer();
        $mail->addAddress($toEmail, $fullName);
        $mail->Subject = $subject;
        
        // Add preheader text
        $preheader = "Reset your DUET Computer Society password";
        $preheaderHtml = "<div style='display:none;max-height:0;overflow:hidden;'>" . $preheader . "</div>";
        
        $mail->Body = $preheaderHtml . $message;
        
        if (ENABLE_EMAIL_SENDING) {
            $mail->send();
            return true;
        } else {
            error_log("Password reset email would be sent to: " . $toEmail . " (sending disabled in config)");
            return true;
        }
    } catch (Exception $e) {
        error_log("Failed to send password reset email: " . $mail->ErrorInfo);
        return false;
    }
}
?>
