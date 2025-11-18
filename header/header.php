<?php
header('Content-Type: application/json; charset=UTF-8');

$sessionDir = __DIR__ . '/../sessions';
if (!is_dir($sessionDir)) {
    @mkdir($sessionDir, 0777, true);
}
if (is_dir($sessionDir) && is_writable($sessionDir)) {
    session_save_path($sessionDir);
}

$host = $_SERVER['HTTP_HOST'] ?? '';
$domain = '.ruhanixlegal.in';
if ($host) {
    $normalizedHost = explode(':', $host)[0];
    if ($normalizedHost === 'localhost' || $normalizedHost === '127.0.0.1') {
        $domain = $normalizedHost;
    }
}

$cookieConfig = [
    'lifetime' => 0,
    'path' => '/',
    'domain' => $domain,
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax'
];

if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params($cookieConfig);
} else {
    session_set_cookie_params(
        $cookieConfig['lifetime'],
        $cookieConfig['path'],
        $cookieConfig['domain'],
        $cookieConfig['secure'],
        $cookieConfig['httponly']
    );
}

@session_start();

$loggedIn = !empty($_SESSION['logged_in']);
$userName = $_SESSION['user_name'] ?? null;
$userEmail = $_SESSION['user_email'] ?? null;
$label = $userName ?: $userEmail ?: 'User';
$initial = strtoupper(substr(trim($label), 0, 1));

$response = [
    'loggedIn' => $loggedIn,
    'userName' => $userName,
    'userEmail' => $userEmail,
    'initial' => $initial ?: 'U',
    'signin' => '/doctor_app/signin/signin.html',
    'signup' => '/doctor_app/signup/signup.html'
];

echo json_encode($response);
