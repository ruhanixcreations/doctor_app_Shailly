<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ✅ Same session setup as signin.php
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '.ruhanixlegal.in',
    'secure' => true,       // ✅ HTTPS site requires this
    'httponly' => true,
    'samesite' => 'Lax'
]);

// ✅ Use the SAME session directory as signin.php
$sessionDir = "/home4/ruhanixlegal/public_html/environment_project/sessions";
if (!file_exists($sessionDir)) {
    mkdir($sessionDir, 0777, true);
}
session_save_path($sessionDir);
session_start();

date_default_timezone_set('Asia/Kolkata');

// ✅ CORS setup
$allowed_origins = [
    "https://ruhanixlegal.in",
    "http://ruhanixlegal.in",
    "https://www.ruhanixlegal.in",
    "http://www.ruhanixlegal.in"
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Headers: Content-Type");
    header("Vary: Origin");
}
header("Content-Type: application/json; charset=UTF-8");

// ✅ Check session validity
if (
    (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) ||
    (isset($_SESSION['user_id'], $_SESSION['user_email']) && !empty($_SESSION['user_id']) && !empty($_SESSION['user_email']))
) {
    if (!isset($_SESSION['logged_in'])) {
        $_SESSION['logged_in'] = true;
    }

    echo json_encode([
        "success" => true,
        "message" => "User logged in",
        "user_id" => $_SESSION['user_id'],
        "email" => $_SESSION['user_email'],
        "role" => $_SESSION['user_role'] ?? null,
        "client_id" => $_SESSION['client_id'] ?? null
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "User not logged in"
    ]);
}
exit;
?>
