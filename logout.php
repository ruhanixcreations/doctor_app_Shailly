<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: https://ruhanixlegal.in');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ✅ Same session setup as signin.php & check_session.php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '.ruhanixlegal.in',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// ✅ Use the SAME session directory as signin.php and check_session.php
$sessionDir = __DIR__ . "/../sessions";
if (!file_exists($sessionDir)) {
    mkdir($sessionDir, 0777, true);
}
session_save_path($sessionDir);

session_start();

// ✅ Clear all session variables
$_SESSION = [];

// ✅ Delete session cookie properly
if (ini_get("session.use_cookies")) {
    setcookie(session_name(), '', time() - 42000, '/', '.ruhanixlegal.in', false, true);
}

// ✅ Destroy session
session_destroy();

echo json_encode([
    "success" => true,
    "message" => "Logout successful and session destroyed"
]);
exit;
?>
