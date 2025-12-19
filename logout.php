<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Keep sessions valid for 7 days unless user logs out
$SESSION_TTL = 60 * 60 * 24 * 7; // 7 days in seconds
ini_set('session.gc_maxlifetime', (string)$SESSION_TTL);
ini_set('session.cookie_lifetime', (string)$SESSION_TTL);

// Session configuration - MUST MATCH signin.php and signup.php
session_set_cookie_params([
    'lifetime' => $SESSION_TTL,
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

// Clear all session variables
$_SESSION = [];

// Delete session cookie properly
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000, 
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy session
session_destroy();

echo json_encode([
    "success" => true,
    "message" => "Logout successful and session destroyed"
]);
exit;
?>