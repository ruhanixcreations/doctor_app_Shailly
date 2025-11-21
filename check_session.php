<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Session configuration
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Use the same session directory as other files
$sessionDir = __DIR__ . "/sessions";
if (!file_exists($sessionDir)) {
    mkdir($sessionDir, 0777, true);
}
session_save_path($sessionDir);
session_start();

date_default_timezone_set('Asia/Kolkata');

// CORS setup
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// Database connection
$DB_HOST='localhost'; 
$DB_USER='ruhanixl_doctorApp'; 
$DB_PASS='@aashi12345678@'; 
$DB_NAME='ruhanixl_doctorApp';
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

// Check session validity
if (
    (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) ||
    (isset($_SESSION['user_id'], $_SESSION['user_email']) && !empty($_SESSION['user_id']) && !empty($_SESSION['user_email']))
) {
    if (!isset($_SESSION['logged_in'])) {
        $_SESSION['logged_in'] = true;
    }

    $userId = $_SESSION['user_id'] ?? null;
    
    // Check if user is disabled
    if ($userId && !$mysqli->connect_errno) {
        $stmt = $mysqli->prepare("SELECT is_disabled FROM users WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if ($row['is_disabled'] == 1) {
                // User is disabled, destroy session
                $sessionId = session_id();
                
                // Remove from user_sessions table
                $deleteStmt = $mysqli->prepare("DELETE FROM user_sessions WHERE session_id = ?");
                $deleteStmt->bind_param('s', $sessionId);
                $deleteStmt->execute();
                $deleteStmt->close();
                
                // Destroy the session
                session_unset();
                session_destroy();
                
                $stmt->close();
                $mysqli->close();
                
                echo json_encode([
                    "success" => false,
                    "message" => "User account has been disabled",
                    "disabled" => true
                ]);
                exit;
            }
        }
        $stmt->close();
        
        // Track this session in the database
        $sessionId = session_id();
        $checkSession = $mysqli->prepare("SELECT id FROM user_sessions WHERE session_id = ?");
        $checkSession->bind_param('s', $sessionId);
        $checkSession->execute();
        $checkSession->store_result();
        
        if ($checkSession->num_rows == 0) {
            // Insert new session
            $insertSession = $mysqli->prepare("INSERT INTO user_sessions (user_id, session_id) VALUES (?, ?)");
            $insertSession->bind_param('is', $userId, $sessionId);
            $insertSession->execute();
            $insertSession->close();
        } else {
            // Update last_activity
            $updateSession = $mysqli->prepare("UPDATE user_sessions SET last_activity = NOW() WHERE session_id = ?");
            $updateSession->bind_param('s', $sessionId);
            $updateSession->execute();
            $updateSession->close();
        }
        $checkSession->close();
    }
    
    if (!$mysqli->connect_errno) {
        $mysqli->close();
    }

    echo json_encode([
        "success" => true,
        "message" => "User logged in",
        "user_id" => $_SESSION['user_id'] ?? null,
        "email" => $_SESSION['user_email'] ?? null,
        "role" => $_SESSION['user_role'] ?? null,
        "client_id" => $_SESSION['client_id'] ?? null,
        "name" => $_SESSION['name'] ?? null
    ]);
} else {
    if (!$mysqli->connect_errno) {
        $mysqli->close();
    }
    echo json_encode([
        "success" => false,
        "message" => "User not logged in"
    ]);
}
exit;
?>
