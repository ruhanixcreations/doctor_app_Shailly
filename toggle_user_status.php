<?php
// toggle_user_status.php - Handle user status toggle and session invalidation
header('Content-Type: application/json; charset=utf-8');

$DB_HOST='localhost'; 
$DB_USER='ruhanixl_doctorApp'; 
$DB_PASS='@aashi12345678@'; 
$DB_NAME='ruhanixl_doctorApp';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if($mysqli->connect_errno){
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'DB connection failed']);
    exit;
}

function send_json($arr){ 
    echo json_encode($arr); 
    exit; 
}

// Get request data
$raw = file_get_contents('php://input'); 
$data = json_decode($raw, true);

$userId = intval($data['user_id'] ?? 0);
$newStatus = trim($data['status'] ?? '');

// Validate inputs
if(!$userId) {
    send_json(['success'=>false, 'message'=>'Invalid user ID']);
}

if(!in_array($newStatus, ['active', 'inactive'])) {
    send_json(['success'=>false, 'message'=>'Invalid status. Must be active or inactive']);
}

// First, check if status and session_version columns exist, if not add them
$checkColumns = $mysqli->query("SHOW COLUMNS FROM users LIKE 'status'");
if($checkColumns->num_rows === 0) {
    $mysqli->query("ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'active'");
}

$checkSessionVersion = $mysqli->query("SHOW COLUMNS FROM users LIKE 'session_version'");
if($checkSessionVersion->num_rows === 0) {
    $mysqli->query("ALTER TABLE users ADD COLUMN session_version INT DEFAULT 1");
}

// Update user status and increment session_version to invalidate all sessions
$stmt = $mysqli->prepare("UPDATE users SET status=?, session_version = session_version + 1 WHERE id=?");
$stmt->bind_param('si', $newStatus, $userId);
$success = $stmt->execute();
$stmt->close();

if($success) {
    // If disabling user, also try to clean up their session files
    if($newStatus === 'inactive') {
        $sessionDir = __DIR__ . "/sessions";
        if(file_exists($sessionDir) && is_dir($sessionDir)) {
            // Get user email to find their session files
            $userStmt = $mysqli->prepare("SELECT email FROM users WHERE id=?");
            $userStmt->bind_param('i', $userId);
            $userStmt->execute();
            $result = $userStmt->get_result();
            if($row = $result->fetch_assoc()) {
                $userEmail = $row['email'];
                
                // Scan session directory for this user's sessions
                $files = scandir($sessionDir);
                foreach($files as $file) {
                    if($file === '.' || $file === '..') continue;
                    
                    $filepath = $sessionDir . '/' . $file;
                    if(is_file($filepath)) {
                        // Read session file and check if it belongs to this user
                        $sessionData = @file_get_contents($filepath);
                        if($sessionData && (strpos($sessionData, $userEmail) !== false || strpos($sessionData, "user_id|i:$userId") !== false)) {
                            @unlink($filepath); // Delete the session file
                        }
                    }
                }
            }
            $userStmt->close();
        }
    }
    
    send_json(['success'=>true, 'message'=>'User status updated successfully']);
} else {
    send_json(['success'=>false, 'message'=>'Failed to update user status']);
}

$mysqli->close();
?>
