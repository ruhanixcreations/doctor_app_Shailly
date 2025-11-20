<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '.ruhanixlegal.in',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

$sessionDir = __DIR__ . "/../sessions";
if (!file_exists($sessionDir)) mkdir($sessionDir, 0777, true);
session_save_path($sessionDir);
session_start();

$allowed_origins = [
    "https://ruhanixlegal.in",
    "https://www.ruhanixlegal.in",
    "http://ruhanixlegal.in",
    "http://www.ruhanixlegal.in"
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Headers: Content-Type");
    header("Vary: Origin");
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

header("Content-Type: application/json; charset=UTF-8");

require_once '../connections.php';

function generateClientId($conn, $prefix = 'RUHA', $length = 6) {
    do {
        $client_id = $prefix . mt_rand(pow(10, $length - 1), pow(10, $length) - 1);
        $stmt = $conn->prepare("SELECT id FROM users WHERE client_id = ?");
        $stmt->bind_param("s", $client_id);
        $stmt->execute();
        $stmt->store_result();
    } while ($stmt->num_rows > 0);
    $stmt->close();
    return $client_id;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'send_otp':
        $email = strtolower(trim($_POST['email'] ?? ''));
        $page = $_POST['page'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email']);
            exit;
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE LOWER(email) = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($page === 'register' && $stmt->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Email already exists in the database.']);
            $stmt->close();
            exit;
        }
        $stmt->close();

        $otp = strval(rand(100000, 999999));
        $_SESSION['otp'] = $otp;
        $_SESSION['otp_email'] = $email;
        $_SESSION['signup_otp_expiry'] = time() + 300; // 5 minutes

        $update = $conn->prepare("UPDATE users SET otp = ? WHERE LOWER(email) = ?");
        if ($update) {
            $update->bind_param("ss", $otp, $email);
            $update->execute();
            $update->close();
        }

        $subject = "Your OTP Code";
        $message = "Your OTP is: $otp\n\nValid for 5 minutes. Please do not share this code with anyone.";
        $headers = "From: noreply@ruhanixlegal.in\r\nContent-Type: text/plain; charset=utf-8";

        echo json_encode(['success' => true, "otp" => $otp]);

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            if (ob_get_level()) ob_end_flush();
            flush();
        }

        @mail($email, $subject, $message, $headers);
        break;

    case 'verify_otp':
        $email = $_POST['email'] ?? '';
        $enteredOtp = $_POST['otp'] ?? '';

        file_put_contents(__DIR__ . '/../sessions/debug_signup_log.txt',
            "VERIFY_SIGNUP_OTP: SESSION_OTP={$_SESSION['otp']} | POST_OTP={$enteredOtp} | SESSION_EMAIL={$_SESSION['otp_email']} | POST_EMAIL={$email}\n",
            FILE_APPEND
        );

        if (
            !isset($_SESSION['otp']) ||
            !isset($_SESSION['otp_email']) ||
            strtolower($email) !== strtolower($_SESSION['otp_email']) ||
            $enteredOtp != $_SESSION['otp']
        ) {
            echo json_encode(["success" => false, "message" => "Invalid email or OTP."]);
            exit;
        }

        if (!isset($_SESSION['signup_otp_expiry']) || time() > $_SESSION['signup_otp_expiry']) {
            unset($_SESSION['otp']);
            echo json_encode(["success" => false, "message" => "OTP expired. Please resend."]);
            exit;
        }

        $_SESSION['otp_verified'] = true;
        echo json_encode(["success" => true, "message" => "OTP verified successfully."]);
        break;

    case 'check_mobile':
        $mobile = trim($_POST['mobile'] ?? '');
        $mobile_clean = preg_replace('/\D+/', '', $mobile);

        if (empty($mobile_clean) || strlen($mobile_clean) < 6) {
            echo json_encode(['success' => false, 'message' => 'Invalid mobile number.']);
            exit;
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE mobile = ?");
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param("s", $mobile_clean);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Mobile number already exists.']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Mobile available.']);
        }
        $stmt->close();
        break;

    case 'register':
        if (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true) {
            echo json_encode(["success" => false, "message" => "OTP not verified."]);
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $mobile = trim($_POST['mobile'] ?? '');
        $password = $_POST['password'] ?? '';

        // Default role
        $role = 'user';

        // Accept role from POST (frontend) if provided and allowed
        if (!empty($_POST['role'])) {
            $candidate = strtolower(trim($_POST['role']));
            $allowed = ['receptionalist', 'user', 'admin'];
            if (in_array($candidate, $allowed)) {
                $role = $candidate;
            }
        }

        // fallback to session preselected_role if present
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (isset($_SESSION['preselected_role']) && !empty($_SESSION['preselected_role'])) {
            $candidate2 = strtolower(trim($_SESSION['preselected_role']));
            $allowed = ['receptionalist', 'user', 'admin'];
            if (in_array($candidate2, $allowed)) {
                $role = $candidate2;
            }
            unset($_SESSION['preselected_role']);
        }

        if (empty($name) || empty($email) || empty($mobile) || empty($password)) {
            echo json_encode(["success" => false, "message" => "Name, Email, Mobile and Password are required."]);
            exit;
        }

        if (strlen($password) < 6) {
            echo json_encode(["success" => false, "message" => "Password must be at least 6 characters."]);
            exit;
        }

        $mobile_clean = preg_replace('/\D+/', '', $mobile);

        $check = $conn->prepare("SELECT id, client_id FROM users WHERE LOWER(email) = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $check->bind_result($user_id, $client_id);
            $check->fetch();
            echo json_encode(["success" => false, "message" => "Email already registered."]);
            $check->close();
            exit;
        }
        $check->close();

        $checkm = $conn->prepare("SELECT id FROM users WHERE mobile = ?");
        if (!$checkm) {
            echo json_encode(["success" => false, "message" => "Database error: " . $conn->error]);
            exit;
        }
        $checkm->bind_param("s", $mobile_clean);
        $checkm->execute();
        $checkm->store_result();
        if ($checkm->num_rows > 0) {
            $checkm->close();
            echo json_encode(["success" => false, "message" => "Mobile number already registered."]);
            exit;
        }
        $checkm->close();

        $client_id = generateClientId($conn);

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (name, email, mobile, role, client_id, password) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            echo json_encode(["success" => false, "message" => "Database error: " . $conn->error]);
            exit;
        }

        $stmt->bind_param("ssssss", $name, $email, $mobile_clean, $role, $client_id, $password_hash);
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_role'] = $role;
            $_SESSION['client_id'] = $client_id;
            $_SESSION['logged_in'] = true;
            $_SESSION['last_activity'] = time();

            $next = $_POST['next'] ?? '';
            $redirectUrl = "/doctor_app/dashboard/dashboard.html";
            if ($next === 'upload_tree') $redirectUrl = "/doctor_app/dashboard/upload_video/upload_video.html";

            echo json_encode([
                "success" => true,
                "message" => "Registration successful.",
                "user_id" => $user_id,
                "client_id" => $client_id,
                "redirect" => $redirectUrl,
                "role" => $role,
                "name" => $name,
                "mobile" => $mobile_clean
            ]);
        } else {
            echo json_encode(["success" => false, "message" => "Failed to register user."]);
        }

        $stmt->close();
        break;

    default:
        echo json_encode(["success" => false, "message" => "Unknown action."]);
        break;
}

$conn->close();
