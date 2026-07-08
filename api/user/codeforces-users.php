<?php
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

try {
    // Get database connection
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Fetch all codeforces handles from the admin-managed table first, fallback to old table
    $handles = [];
    
    // Try new table first (codeforces_handles - admin managed)
    $result = $conn->query("SELECT handle FROM codeforces_handles");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $handles[] = $row['handle'];
        }
    }
    
    // If no handles in new table, try old table as fallback
    if (empty($handles)) {
        $result = $conn->query("SELECT codeforces_handle FROM coder_handles");
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $handles[] = $row['codeforces_handle'];
            }
        }
    }
    
    closeDBConnection($conn);
    
    // If no handles found, return empty array
    if (empty($handles)) {
        echo json_encode([
            'success' => true,
            'count' => 0,
            'data' => []
        ]);
        exit;
    }
    
    // Join handles with semicolon for Codeforces API
    $handlesStr = implode(';', $handles);
    
    // Fetch data from Codeforces API using user.info endpoint
    $url = 'https://codeforces.com/api/user.info?handles=' . urlencode($handlesStr) . '&checkHistoricHandles=false';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception('Failed to fetch from Codeforces API: ' . $error);
    }
    
    if ($httpCode !== 200) {
        throw new Exception('Codeforces API returned error code: ' . $httpCode);
    }
    
    $data = json_decode($response, true);
    
    if (!$data || $data['status'] !== 'OK') {
        $errorMsg = isset($data['comment']) ? $data['comment'] : 'Invalid response from Codeforces API';
        throw new Exception($errorMsg);
    }
    
    // Map the response to our format
    $users = array_map(function($user) {
        return [
            'handle' => $user['handle'] ?? '',
            'rating' => $user['rating'] ?? 0,
            'maxRating' => $user['maxRating'] ?? 0,
            'rank' => $user['rank'] ?? 'unrated',
            'maxRank' => $user['maxRank'] ?? 'unrated',
            'country' => $user['country'] ?? '',
            'organization' => $user['organization'] ?? '',
            'avatar' => $user['titlePhoto'] ?? $user['avatar'] ?? '',
            'titlePhoto' => $user['titlePhoto'] ?? '',
            'lastOnlineTimeSeconds' => $user['lastOnlineTimeSeconds'] ?? 0
        ];
    }, $data['result']);
    
    // Filter out users who haven't visited in over 1 year (365 days)
    $oneYearAgo = time() - (365 * 24 * 60 * 60);
    $users = array_filter($users, function($user) use ($oneYearAgo) {
        return $user['lastOnlineTimeSeconds'] >= $oneYearAgo;
    });
    $users = array_values($users); // Re-index array after filtering
    
    // Sort by rating (descending)
    usort($users, function($a, $b) {
        return $b['rating'] - $a['rating'];
    });
    
    echo json_encode([
        'success' => true,
        'count' => count($users),
        'data' => $users
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
