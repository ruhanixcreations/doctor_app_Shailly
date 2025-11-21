<?php
// dashboard.php
// Securely redirect to signup page and pass role via query string.
// Creates app sessions folder if needed (best-effort).

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Create & use an app-specific sessions directory (sibling to this file or adjust as needed)
$sessionDir = __DIR__ . "/../sessions";
if (!file_exists($sessionDir)) {
    @mkdir($sessionDir, 0777, true);
    @chmod($sessionDir, 0777);
}
if (is_dir($sessionDir) && is_writable($sessionDir)) {
    session_save_path($sessionDir);
}

// Align cookie params with signup.php (adjust domain/secure as required)
$cookieParams = [
    'lifetime' => 0,
    'path' => '/',
    'domain' => '.ruhanixlegal.in',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
];
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params($cookieParams);
} else {
    session_set_cookie_params(
        $cookieParams['lifetime'],
        $cookieParams['path'],
        $cookieParams['domain'],
        $cookieParams['secure'],
        $cookieParams['httponly']
    );
}

// start session (no output expected before this)
@session_start();

// Accept role param from GET if provided and valid; also support default receptionist when link used
$allowed_roles = ['receptionalist', 'user', 'admin'];
$role = $_GET['role'] ?? 'receptionalist';
$role = strtolower(trim($role));
if (in_array($role, $allowed_roles)) {
    $_SESSION['preselected_role'] = $role;
    $_SESSION['preselected_role_set_at'] = time();
}

// build redirect path; try absolute path then fallback to relative in same folder
$signup_path = '/doctor_app/signup/signup.html'; // adjust if your signup.html is at a different absolute path

$absolutePath = $_SERVER['DOCUMENT_ROOT'] . $signup_path;
if (!file_exists($absolutePath)) {
    // fallback to relative path (assumes signup.html sits next to this file or in parent)
    $signup_path = 'signup.html';
}

// Append role as query param to signup page so frontend will include it explicitly in POST
$sep = (strpos($signup_path, '?') === false) ? '?' : '&';
$redirectUrl = $signup_path . $sep . 'role=' . urlencode($role);

// Redirect (ensure no output before header)
header("Location: $redirectUrl");
exit;
