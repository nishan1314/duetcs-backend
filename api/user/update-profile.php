<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';
require_once '../../utils/helpers.php';

// Start session
session_start();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, false, 'Method not allowed');
}

// Get session token from header
$headers = getallheaders();
$sessionToken = $headers['Authorization'] ?? null;

if (!$sessionToken) {
    sendResponse(401, false, 'Unauthorized - No session token provided');
}

// Remove "Bearer " prefix if present
$sessionToken = str_replace('Bearer ', '', $sessionToken);

// Get database connection
$conn = getDBConnection();
if (!$conn) {
    sendResponse(500, false, 'Database connection failed');
}

// Verify session token and get user
$stmt = $conn->prepare("
    SELECT u.id 
    FROM users u
    JOIN login_sessions ls ON u.id = ls.user_id
    WHERE ls.session_token = ? AND ls.expires_at > NOW()
");
$stmt_params = [$sessionToken];
$stmt->execute($stmt_params ?? null);
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    closeDBConnection($conn);
    sendResponse(401, false, 'Invalid or expired session');
}

$user = $result->fetch_assoc();
$userId = $user['id'];
$stmt->close();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Prepare update fields
$updateFields = [];
$params = [];
$types = "";

if (isset($input['profile_image']) && !empty($input['profile_image'])) {
    $profileImageBase64 = $input['profile_image'];
    $profileImagePath = null;
    
    // Extract base64 data and save as file
    if (preg_match('/^data:image\/(\w+);base64,/', $profileImageBase64, $matches)) {
        $imageType = $matches[1];
        $profileImageBase64 = substr($profileImageBase64, strpos($profileImageBase64, ',') + 1);
        $imageData = base64_decode($profileImageBase64);
        
        if ($imageData !== false) {
            // Generate unique filename
            $fileName = 'profile_' . uniqid() . '_' . time() . '.' . $imageType;
            $uploadDir = '../../uploads/profiles/';
            
            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $filePath = $uploadDir . $fileName;
            
            // Save the file
            if (file_put_contents($filePath, $imageData)) {
                // Store relative path for database
                $profileImagePath = 'uploads/profiles/' . $fileName;
                $updateFields[] = "profile_image = ?";
                $params[] = $profileImagePath;
                $types .= "s";
            }
        }
    }
}

// Handle full name update
if (isset($input['full_name']) && !empty($input['full_name'])) {
    $fullName = sanitizeInput($input['full_name']);
    if (strlen($fullName) < 2 || strlen($fullName) > 100) {
        closeDBConnection($conn);
        sendResponse(400, false, 'Full name must be between 2 and 100 characters');
    }
    $updateFields[] = "full_name = ?";
    $params[] = $fullName;
    $types .= "s";
}

// Handle department update
if (isset($input['department']) && !empty($input['department'])) {
    $department = sanitizeInput($input['department']);
    // Validate department is one of the valid options
    $validDepartments = ['CSE', 'EEE', 'CE', 'ME', 'IPE', 'TE', 'ARCHI', 'CHEM', 'BME', 'ETE', 'cse', 'eee', 'ce', 'me', 'ipe', 'te', 'archi', 'chem', 'bme', 'ete'];
    if (!in_array($department, $validDepartments)) {
        closeDBConnection($conn);
        sendResponse(400, false, 'Invalid department');
    }
    $updateFields[] = "department = ?";
    $params[] = strtoupper($department);
    $types .= "s";
}

if (isset($input['year_semester'])) {
    $updateFields[] = "year_semester = ?";
    $params[] = sanitizeInput($input['year_semester']);
    $types .= "s";
}

// Handle phone number update
if (isset($input['phone_number'])) {
    $phoneNumber = sanitizeInput($input['phone_number']);
    // Basic phone number validation (allow empty to clear, or 10-15 digits with optional +)
    if (!empty($phoneNumber) && !preg_match('/^\+?[0-9]{10,15}$/', preg_replace('/[\s\-]/', '', $phoneNumber))) {
        closeDBConnection($conn);
        sendResponse(400, false, 'Invalid phone number format');
    }
    $updateFields[] = "phone_number = ?";
    $params[] = empty($phoneNumber) ? null : $phoneNumber;
    $types .= "s";
}

// Handle Codeforces handle update
$codeforcesHandle = null;
if (isset($input['codeforces_handle'])) {
    $codeforcesHandle = sanitizeInput($input['codeforces_handle']);
}

// Check if there are fields to update
if (empty($updateFields) && $codeforcesHandle === null) {
    closeDBConnection($conn);
    sendResponse(400, false, 'No fields to update');
}

// Update user profile fields if any
if (!empty($updateFields)) {
    // Add user ID to params
    $params[] = $userId;
    $types .= "i";

    // Build and execute update query
    $sql = "UPDATE users SET " . implode(", ", $updateFields) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        $stmt->close();
        closeDBConnection($conn);
        sendResponse(500, false, 'Failed to update profile');
    }
    $stmt->close();
}

// Handle Codeforces handle
if ($codeforcesHandle !== null) {
    if (empty($codeforcesHandle)) {
        // Delete existing handle if empty string provided
        $stmt = $conn->prepare("DELETE FROM coder_handles WHERE user_id = ?");
        $stmt_params = [$userId];
        $stmt->execute($stmt_params ?? null);
        $stmt->close();
    } else {
        // Verify the handle exists on Codeforces
        $cfUrl = 'https://codeforces.com/api/user.info?handles=' . urlencode($codeforcesHandle) . '&checkHistoricHandles=false';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $cfUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $cfResponse = curl_exec($ch);
        $cfHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($cfHttpCode !== 200) {
            closeDBConnection($conn);
            sendResponse(400, false, 'Invalid Codeforces handle. Please check and try again.');
        }
        
        $cfData = json_decode($cfResponse, true);
        if (!$cfData || $cfData['status'] !== 'OK') {
            closeDBConnection($conn);
            sendResponse(400, false, 'Codeforces handle not found. Please verify your handle.');
        }
        
        // Use INSERT ... ON DUPLICATE KEY UPDATE for upsert
        $stmt = $conn->prepare("
            INSERT INTO coder_handles (user_id, codeforces_handle) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE codeforces_handle = VALUES(codeforces_handle), updated_at = CURRENT_TIMESTAMP
        ");
        $stmt_params = [$userId, $codeforcesHandle];
        
        if (!$stmt->execute()) {
            $stmt->close();
            closeDBConnection($conn);
            
            // Check if it's a duplicate handle error
            if ($conn->errno === 1062) {
                sendResponse(400, false, 'This Codeforces handle is already linked to another account');
            }
            sendResponse(500, false, 'Failed to update Codeforces handle');
        }
        $stmt->close();
        
        // Also sync to codeforces_handles table for Achievements page visibility
        // Get user's full name for the name field
        $stmtUser = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
        $stmt_params = [$userId];
        $stmtUser->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
        $userResult = $stmtUser->get_result();
        $userName = null;
        if ($userResult->num_rows > 0) {
            $userName = $userResult->fetch_assoc()['full_name'];
        }
        $stmtUser->close();
        
        // Insert or ignore into codeforces_handles (admin table) - links user handle to achievements page
        $stmtSync = $conn->prepare("
            INSERT INTO codeforces_handles (handle, name) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE name = COALESCE(name, VALUES(name)), updated_at = CURRENT_TIMESTAMP
        ");
        $stmt_params = [$codeforcesHandle, $userName];
        $stmtSync->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
        $stmtSync->close();
    }
}

closeDBConnection($conn);

// Return success response
sendResponse(200, true, 'Profile updated successfully');
?>
