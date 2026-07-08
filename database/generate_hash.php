<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Password Hash</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        .result {
            background: #f9f9f9;
            padding: 15px;
            border-left: 4px solid #4CAF50;
            margin: 20px 0;
            font-family: monospace;
            word-break: break-all;
        }
        .instructions {
            background: #fff3cd;
            padding: 15px;
            border-left: 4px solid #ffc107;
            margin: 20px 0;
        }
        button {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #45a049;
        }
        .error {
            color: red;
            margin: 10px 0;
        }
        .success {
            color: green;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>System Admin Password Hash Generator</h1>
        
        <div class="instructions">
            <strong>⚠️ Important:</strong> This page generates a password hash for the super admin account.
            You need to run this through a PHP server (XAMPP, WAMP, etc.) for it to work.
        </div>

        <div id="hashResult"></div>
        
        <button onclick="location.reload()">Refresh Page</button>
    </div>

    <?php if (function_exists('password_hash')): ?>
    <script>
        // PHP is available, show the hash
        const hashResult = document.getElementById('hashResult');
        const password = 'adminduetcs';
        const hash = '<?php echo password_hash("adminduetcs", PASSWORD_DEFAULT); ?>';
        
        hashResult.innerHTML = `
            <div class="success">✓ Password hash generated successfully!</div>
            <div class="result">
                <strong>Password:</strong> ${password}<br><br>
                <strong>Hash:</strong><br>${hash}
            </div>
            <div class="instructions">
                <strong>Next Steps:</strong><br>
                1. Copy the hash above<br>
                2. Open phpMyAdmin<br>
                3. Run this SQL query:<br><br>
                <code style="display: block; background: #f5f5f5; padding: 10px; margin-top: 10px;">
                UPDATE system_admin SET password = '${hash}' WHERE email = 'duetcs@duet.ac.bd';
                </code>
            </div>
        `;
    </script>
    <?php else: ?>
    <script>
        const hashResult = document.getElementById('hashResult');
        hashResult.innerHTML = `
            <div class="error">❌ PHP is not available</div>
            <div class="instructions">
                <strong>You need to:</strong><br>
                1. Save this file as <code>generate_hash.php</code> in your XAMPP/htdocs folder<br>
                2. Start XAMPP Apache server<br>
                3. Open <code>http://localhost/generate_hash.php</code> in your browser<br>
                <br>
                <strong>OR run the PHP setup script:</strong><br>
                <code>cd backend/database && php setup_system_admin.php</code>
            </div>
        `;
    </script>
    <?php endif; ?>
</body>
</html>
