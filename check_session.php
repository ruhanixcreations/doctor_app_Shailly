<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Session configuration - MUST MATCH signin.php and signup.php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '.ruhanixlegal.in',
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

// Check session validity
if (
    (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) ||
    (isset($_SESSION['user_id'], $_SESSION['user_email']) && !empty($_SESSION['user_id']) && !empty($_SESSION['user_email']))
) {
    if (!isset($_SESSION['logged_in'])) {
        $_SESSION['logged_in'] = true;
    }

    // Additional validation: Check user status and session version
    $user_id = $_SESSION['user_id'] ?? null;
    if ($user_id) {
        // Connect to database to check user status
        $DB_HOST = 'localhost';
        $DB_USER = 'ruhanixl_doctorApp';
        $DB_PASS = '@aashi12345678@';
        $DB_NAME = 'ruhanixl_doctorApp';
        
        $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
        if (!$mysqli->connect_errno) {
            // Check if status column exists
            $checkColumns = $mysqli->query("SHOW COLUMNS FROM users LIKE 'status'");
            $hasStatusColumn = ($checkColumns && $checkColumns->num_rows > 0);
            
            // Check if session_version column exists
            $checkSessionVersion = $mysqli->query("SHOW COLUMNS FROM users LIKE 'session_version'");
            $hasSessionVersion = ($checkSessionVersion && $checkSessionVersion->num_rows > 0);
            
            // Build query based on available columns
            $query = "SELECT id";
            if ($hasStatusColumn) {
                $query .= ", COALESCE(status, 'active') as status";
            }
            if ($hasSessionVersion) {
                $query .= ", COALESCE(session_version, 1) as session_version";
            }
            $query .= " FROM users WHERE id = ?";
            
            $stmt = $mysqli->prepare($query);
            if ($stmt) {
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    // Check if user is inactive
                    if ($hasStatusColumn && isset($row['status']) && $row['status'] === 'inactive') {
                        // User is disabled, destroy session and log them out
                        session_unset();
                        session_destroy();
                        echo json_encode([
                            "success" => false,
                            "message" => "Your account has been disabled. Please contact administrator."
                        ]);
                        $stmt->close();
                        $mysqli->close();
                        exit;
                    }
                    
                    // Check session version mismatch
                    if ($hasSessionVersion && isset($row['session_version'])) {
                        $db_session_version = intval($row['session_version']);
                        $stored_session_version = intval($_SESSION['session_version'] ?? 1);
                        
                        if ($db_session_version !== $stored_session_version) {
                            // Session version mismatch, user was logged out from all devices
                            session_unset();
                            session_destroy();
                            echo json_encode([
                                "success" => false,
                                "message" => "Your session has been invalidated. Please log in again."
                            ]);
                            $stmt->close();
                            $mysqli->close();
                            exit;
                        }
                    }
                } else {
                    // User not found in database
                    session_unset();
                    session_destroy();
                    echo json_encode([
                        "success" => false,
                        "message" => "User not found"
                    ]);
                    $stmt->close();
                    $mysqli->close();
                    exit;
                }
                $stmt->close();
            }
            $mysqli->close();
        }
    }

    echo json_encode([
        "success" => true,
        "message" => "User logged in",
        "user_id" => $_SESSION['user_id'] ?? null,
        "email" => $_SESSION['user_email'] ?? null,
        "role" => $_SESSION['user_role'] ?? null,
        "client_id" => $_SESSION['client_id'] ?? null,
        "name" => $_SESSION['user_name'] ?? null
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "User not logged in"
    ]);
}
exit;
?>