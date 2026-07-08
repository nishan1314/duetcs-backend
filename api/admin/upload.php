<?php
/**
 * File Upload API
 * Handle PDF and image file uploads with automatic image compression
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/admin-auth.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$adminAuth = new AdminAuth();

// Require authentication
$adminAuth->requireAuth();

/**
 * Compress and resize image with aggressive compression
 * Achieves up to 90% file size reduction while maintaining visual quality
 * @param string $sourcePath Source image path
 * @param string $destPath Destination path
 * @param int $maxWidth Maximum width (default 1200px)
 * @param int $quality JPEG quality (1-100, default 60 for high compression)
 * @return string|false Compressed file path or false on failure
 */
function compressImage($sourcePath, $destPath, $maxWidth = 1200, $quality = 60) {
    $imageInfo = getimagesize($sourcePath);
    if ($imageInfo === false) {
        return false;
    }
    
    $mimeType = $imageInfo['mime'];
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    
    // Create image resource based on type
    switch ($mimeType) {
        case 'image/jpeg':
            $source = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $source = imagecreatefrompng($sourcePath);
            break;
        case 'image/gif':
            $source = imagecreatefromgif($sourcePath);
            break;
        case 'image/webp':
            $source = imagecreatefromwebp($sourcePath);
            break;
        default:
            return false;
    }
    
    if (!$source) {
        return false;
    }
    
    // Calculate new dimensions if image is larger than max width
    $newWidth = $width;
    $newHeight = $height;
    
    if ($width > $maxWidth) {
        $ratio = $maxWidth / $width;
        $newWidth = $maxWidth;
        $newHeight = (int)($height * $ratio);
    }
    
    // Create new image with new dimensions
    $destination = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG and GIF
    if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
        imagealphablending($destination, false);
        imagesavealpha($destination, true);
        $transparent = imagecolorallocatealpha($destination, 255, 255, 255, 127);
        imagefilledrectangle($destination, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    // Resize image with high quality resampling
    imagecopyresampled($destination, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    // Enable interlacing for progressive JPEG (better perceived loading)
    imageinterlace($destination, true);
    
    // Try WebP first for best compression (if supported)
    $success = false;
    $finalPath = $destPath;
    
    // Check if WebP is supported and use it for best compression
    if (function_exists('imagewebp') && $mimeType !== 'image/gif') {
        $webpPath = preg_replace('/\.[^.]+$/', '.webp', $destPath);
        // WebP quality 70 gives excellent results with high compression
        if (imagewebp($destination, $webpPath, 70)) {
            $success = true;
            $finalPath = $webpPath;
        }
    }
    
    // Fallback to JPEG for non-transparent images if WebP failed
    if (!$success && $mimeType !== 'image/png' && $mimeType !== 'image/gif') {
        $jpegPath = preg_replace('/\.[^.]+$/', '.jpg', $destPath);
        if (imagejpeg($destination, $jpegPath, $quality)) {
            $success = true;
            $finalPath = $jpegPath;
        }
    }
    
    // For PNG with transparency, optimize PNG
    if (!$success && ($mimeType === 'image/png' || $mimeType === 'image/gif')) {
        $pngPath = preg_replace('/\.[^.]+$/', '.png', $destPath);
        // PNG compression level 9 (max compression)
        if (imagepng($destination, $pngPath, 9)) {
            $success = true;
            $finalPath = $pngPath;
        }
    }
    
    // Free memory
    imagedestroy($source);
    imagedestroy($destination);
    
    return $success ? $finalPath : false;
}

try {
    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds maximum upload size',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds maximum form size',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
        ];
        
        $errorCode = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
        $errorMessage = $errorMessages[$errorCode] ?? 'Unknown upload error';
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $errorMessage
        ]);
        exit;
    }
    
    $file = $_FILES['file'];
    $fileType = $_POST['type'] ?? 'document';
    
    // Validate file type
    $allowedTypes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp'
    ];
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid file type. Allowed: PDF, JPEG, PNG, GIF, WEBP'
        ]);
        exit;
    }
    
    // Check file size (max 10MB)
    $maxSize = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'File size exceeds maximum limit of 10MB'
        ]);
        exit;
    }
    
    // Determine upload directory based on file type
    $uploadDir = __DIR__ . '/../../uploads/';
    
    if ($mimeType === 'application/pdf') {
        $uploadDir .= 'notices/';
    } else {
        $uploadDir .= 'images/';
    }
    
    // Create directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
    $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);
    $uniqueName = $safeName . '_' . uniqid() . '.' . $extension;
    $filePath = $uploadDir . $uniqueName;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save uploaded file'
        ]);
        exit;
    }
    
    $finalPath = $filePath;
    $finalSize = $file['size'];
    
    // Compress images (not PDFs) with aggressive settings
    if ($mimeType !== 'application/pdf' && function_exists('imagecreatefromjpeg')) {
        // Max width 1200px, quality 60 for high compression
        $compressedPath = compressImage($filePath, $filePath, 1200, 60);
        if ($compressedPath) {
            $finalPath = $compressedPath;
            $finalSize = filesize($finalPath);
            
            // If original and compressed are different, delete original
            if ($finalPath !== $filePath && file_exists($filePath)) {
                unlink($filePath);
            }
            
            $uniqueName = basename($finalPath);
        }
    }
    
    // Build relative URL
    $relativeUrl = 'uploads/' . ($mimeType === 'application/pdf' ? 'notices/' : 'images/') . $uniqueName;
    
    echo json_encode([
        'success' => true,
        'message' => 'File uploaded successfully',
        'data' => [
            'url' => $relativeUrl,
            'name' => $file['name'],
            'size' => $finalSize,
            'originalSize' => $file['size'],
            'mimeType' => $mimeType,
            'compressed' => ($finalSize < $file['size'])
        ]
    ]);
    
} catch (Exception $e) {
    error_log("File upload error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred during upload'
    ]);
}
