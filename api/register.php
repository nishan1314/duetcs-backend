<?php
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../utils/helpers.php';
require_once '../utils/email.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, false, 'Method not allowed');
}

// Check if registration is enabled
$conn = getDBConnection();
if ($conn) {
    $settingResult = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key = 'enable_registration' LIMIT 1");
    if ($settingResult && $settingResult->num_rows > 0) {
        $row = $settingResult->fetch_assoc();
        if ($row['setting_value'] !== 'true' && $row['setting_value'] !== '1') {
            closeDBConnection($conn);
            sendResponse(403, false, 'Registration is currently disabled. Please try again later.');
        }
    }
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$requiredFields = ['fullName', 'email', 'studentId', 'department', 'yearSemester', 'whyJoin', 'password'];
foreach ($requiredFields as $field) {
    if (empty($input[$field])) {
        sendResponse(400, false, "Field '$field' is required");
    }
}

// Sanitize and extract data
$fullName = sanitizeInput($input['fullName']);
$email = sanitizeInput($input['email']);
$studentId = sanitizeInput($input['studentId']);
$department = sanitizeInput($input['department']);
$yearSemester = sanitizeInput($input['yearSemester']);
$whyJoin = sanitizeInput($input['whyJoin']);
$password = $input['password'];
$profileImageBase64 = isset($input['profileImage']) ? $input['profileImage'] : null;
$phoneNumber = isset($input['phoneNumber']) && !empty($input['phoneNumber']) ? sanitizeInput($input['phoneNumber']) : null;

// Handle profile image upload
$profileImagePath = null;
if ($profileImageBase64 && !empty($profileImageBase64)) {
    // Extract base64 data
    if (preg_match('/^data:image\/(\w+);base64,/', $profileImageBase64, $matches)) {
        $imageType = $matches[1];
        $profileImageBase64 = substr($profileImageBase64, strpos($profileImageBase64, ',') + 1);
        $imageData = base64_decode($profileImageBase64);
        
        if ($imageData !== false) {
            // Generate unique filename
            $fileName = 'profile_' . uniqid() . '_' . time() . '.' . $imageType;
            $uploadDir = '../uploads/profiles/';
            
            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $filePath = $uploadDir . $fileName;
            
            // Save the file
            if (file_put_contents($filePath, $imageData)) {
                // Store relative path for database
                $profileImagePath = 'uploads/profiles/' . $fileName;
            }
        }
    }
}

// Validate email
if (!isValidEmail($email)) {
    sendResponse(400, false, 'Invalid email format');
}

// Validate password strength (minimum 6 characters)
if (strlen($password) < 6) {
    sendResponse(400, false, 'Password must be at least 6 characters long');
}

// Get database connection
$conn = getDBConnection();
if (!$conn) {
    sendResponse(500, false, 'Database connection failed');
}

// Check if email already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt_params = [$email];
$stmt->execute($stmt_params ?? null);
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $stmt->close();
    closeDBConnection($conn);
    sendResponse(409, false, 'Email already registered');
}
$stmt->close();

// Check if student ID already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE student_id = ?");
$stmt_params = [$studentId];
$stmt->execute($stmt_params ?? null);
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $stmt->close();
    closeDBConnection($conn);
    sendResponse(409, false, 'Student ID already registered');
}
$stmt->close();

// Hash password
$hashedPassword = hashPassword($password);

// Insert user into database
$stmt = $conn->prepare("INSERT INTO users (full_name, email, student_id, department, year_semester, phone_number, why_join, password, profile_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt_params = [$fullName, $email, $studentId, $department, $yearSemester, $phoneNumber, $whyJoin, $hashedPassword, $profileImagePath];

if (!$stmt->execute()) {
    $stmt->close();
    closeDBConnection($conn);
    sendResponse(500, false, 'Registration failed. Please try again.');
}

$userId = $stmt->lastInsertId();
$stmt->close();

// Generate 6-digit verification code with 15-minute expiry
$verificationCode = generateVerificationCode();
$expiryMinutes = 15;
$expiresAt = getExpiryTimestampMinutes($expiryMinutes);

// Insert verification code
$stmt = $conn->prepare("INSERT INTO email_verifications (user_id, verification_token, expires_at) VALUES (?, ?, ?)");
$stmt_params = [$userId, $verificationCode, $expiresAt];

if (!$stmt->execute()) {
    $stmt->close();
    closeDBConnection($conn);
    sendResponse(500, false, 'Failed to generate verification code');
}

$stmt->close();
closeDBConnection($conn);

// Send verification email with code
$emailSent = sendVerificationEmail($email, $fullName, $verificationCode, $expiryMinutes);

if (!$emailSent) {
    error_log("Failed to send verification email to: " . $email);
}

// Return success response
sendResponse(201, true, 'Registration successful! Please check your email for the verification code.', [
    'userId' => $userId,
    'email' => $email
]);
?>
