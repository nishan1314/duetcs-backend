<?php
// Email Configuration
// For production, move these to environment variables

// SMTP Settings
define('SMTP_HOST', 'smtp.gmail.com');        // Gmail SMTP server (change for other providers)
define('SMTP_PORT', 587);                      // Port 587 for TLS, 465 for SSL
define('SMTP_ENCRYPTION', 'tls');              // 'tls' or 'ssl'
define('SMTP_USERNAME', 'nishandas655@gmail.com'); // Your email address
define('SMTP_PASSWORD', 'anyh muqo fdtk kucz');    // Gmail App Password (not regular password)

// Email From Settings
define('MAIL_FROM', 'noreply@duetcs.com');
define('MAIL_FROM_NAME', 'DUET Computer Society');

// Frontend URL - Dynamically detect the origin for email verification links
// This allows verification from any device on the network
function getFrontendUrl() {
    // Check if we have an origin header (from AJAX requests)
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        return $_SERVER['HTTP_ORIGIN'];
    }
    
    // Check referer header
    if (isset($_SERVER['HTTP_REFERER'])) {
        $parsed = parse_url($_SERVER['HTTP_REFERER']);
        $url = $parsed['scheme'] . '://' . $parsed['host'];
        if (isset($parsed['port'])) {
            $url .= ':' . $parsed['port'];
        }
        return $url;
    }
    
    // Default fallback
    return 'http://localhost:8080';
}

// For static uses where function can't be called
define('FRONTEND_URL', 'http://localhost:8080');

// Email Settings
define('ENABLE_EMAIL_SENDING', true); // Set to true to enable real email sending

/*
 * GMAIL SETUP INSTRUCTIONS:
 * 
 * 1. Enable 2-Factor Authentication:
 *    - Go to https://myaccount.google.com/security
 *    - Enable 2-Step Verification
 * 
 * 2. Generate App Password:
 *    - Go to https://myaccount.google.com/apppasswords
 *    - Select "Mail" and your device
 *    - Copy the 16-character password
 *    - Use it as SMTP_PASSWORD above
 * 
 * 3. Update Settings:
 *    - Set SMTP_USERNAME to your Gmail address
 *    - Set SMTP_PASSWORD to the app password
 *    - Set MAIL_FROM to your desired from address
 *    - Set ENABLE_EMAIL_SENDING to true
 * 
 * OTHER EMAIL PROVIDERS:
 * 
 * For other providers, update:
 * - SMTP_HOST (e.g., 'smtp.outlook.com', 'smtp.mail.yahoo.com')
 * - SMTP_PORT (check provider documentation)
 * - SMTP_ENCRYPTION (usually 'tls' or 'ssl')
 * - SMTP_USERNAME and SMTP_PASSWORD (your credentials)
 */
?>
