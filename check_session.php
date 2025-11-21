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

// Check session validity
if (
    (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) ||
    (isset($_SESSION['user_id'], $_SESSION['user_email']) && !empty($_SESSION['user_id']) && !empty($_SESSION['user_email']))
) {
    if (!isset($_SESSION['logged_in'])) {
        $_SESSION['logged_in'] = true;
    }

    // Check if user is disabled in database
    require_once(__DIR__ . '/connections.php');
    
    $userId = $_SESSION['user_id'] ?? null;
    if ($userId) {
        $stmt = $conn->prepare("SELECT is_active FROM users WHERE id=?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        // If user is disabled (is_active = 0), force logout
        if ($user && isset($user['is_active']) && $user['is_active'] == 0) {
            session_unset();
            session_destroy();
            echo json_encode([
                "success" => false,
                "message" => "Your account has been disabled",
                "disabled" => true
            ]);
            exit;
        }
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
    echo json_encode([
        "success" => false,
        "message" => "User not logged in"
    ]);
}
exit;
?>
