<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '.ruhanixlegal.in',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

$sessionDir = __DIR__ . "/sessions";
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

require_once 'connections.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

// Get user profile
if ($action === 'get') {
    $stmt = $conn->prepare("SELECT id, name, email, mobile, client_id, role, status FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
    
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Format role for display
    $roleDisplay = $user['role'];
    if ($roleDisplay === 'user') {
        $roleDisplay = 'Doctor';
    } else if ($roleDisplay === 'receptionalist') {
        $roleDisplay = 'Receptionist';
    }
    $user['role'] = $roleDisplay;
    
    // Format status
    $user['status'] = ucfirst($user['status']);
    
    echo json_encode(['success' => true, 'user' => $user]);
    exit;
}

// Send OTP for email verification
if ($action === 'send_otp') {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }
    
    $email = strtolower(trim($input['email'] ?? ''));
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit;
    }
    
    // Check if email is already used by another user
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email is already in use by another account']);
        $stmt->close();
        exit;
    }
    $stmt->close();
    
    // Generate OTP
    $otp = strval(rand(100000, 999999));
    $_SESSION['profile_otp'] = $otp;
    $_SESSION['profile_otp_email'] = $email;
    $_SESSION['profile_otp_expiry'] = time() + 300; // 5 minutes
    
    // Send email (async)
    $subject = "Your OTP Code - Profile Update";
    $message = "Your OTP for profile update is: $otp\n\nValid for 5 minutes. Please do not share this code with anyone.";
    $headers = "From: noreply@ruhanixlegal.in\r\nContent-Type: text/plain; charset=utf-8";
    
    echo json_encode(['success' => true, 'message' => 'OTP sent to your email']);
    
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        if (ob_get_level()) ob_end_flush();
        flush();
    }
    
    @mail($email, $subject, $message, $headers);
    exit;
}

// Verify OTP
if ($action === 'verify_otp') {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }
    
    $email = strtolower(trim($input['email'] ?? ''));
    $otp = trim($input['otp'] ?? '');
    
    if (!isset($_SESSION['profile_otp']) || !isset($_SESSION['profile_otp_email'])) {
        echo json_encode(['success' => false, 'message' => 'No OTP request found. Please send OTP first.']);
        exit;
    }
    
    if (strtolower($email) !== strtolower($_SESSION['profile_otp_email'])) {
        echo json_encode(['success' => false, 'message' => 'Email mismatch']);
        exit;
    }
    
    if ($otp != $_SESSION['profile_otp']) {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP']);
        exit;
    }
    
    if (!isset($_SESSION['profile_otp_expiry']) || time() > $_SESSION['profile_otp_expiry']) {
        unset($_SESSION['profile_otp']);
        unset($_SESSION['profile_otp_email']);
        unset($_SESSION['profile_otp_expiry']);
        echo json_encode(['success' => false, 'message' => 'OTP expired. Please resend.']);
        exit;
    }
    
    $_SESSION['profile_otp_verified'] = true;
    echo json_encode(['success' => true, 'message' => 'OTP verified successfully']);
    exit;
}

// Update user profile
if ($action === 'update') {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }
    
    $name = trim($input['name'] ?? '');
    $mobile = trim($input['mobile'] ?? '');
    $email = trim($input['email'] ?? '');
    
    // Validation
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Name is required']);
        exit;
    }
    
    // Name validation - only letters and spaces
    if (!preg_match('/^[A-Za-z\s]+$/', $name)) {
        echo json_encode(['success' => false, 'message' => 'Name should contain only letters']);
        exit;
    }
    
    if (empty($mobile)) {
        echo json_encode(['success' => false, 'message' => 'Mobile number is required']);
        exit;
    }
    
    // Mobile validation - exactly 10 digits
    $mobile_clean = preg_replace('/\D/', '', $mobile);
    if (strlen($mobile_clean) !== 10) {
        echo json_encode(['success' => false, 'message' => 'Mobile number must be exactly 10 digits']);
        exit;
    }
    
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email is required']);
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }
    
    // Get current user data to check if email changed
    $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_user = $result->fetch_assoc();
    $stmt->close();
    
    // If email changed, verify OTP was completed
    if (strtolower($current_user['email']) !== strtolower($email)) {
        if (!isset($_SESSION['profile_otp_verified']) || $_SESSION['profile_otp_verified'] !== true) {
            echo json_encode(['success' => false, 'message' => 'Please verify your new email with OTP']);
            exit;
        }
        
        // Clear OTP verification session after use
        unset($_SESSION['profile_otp']);
        unset($_SESSION['profile_otp_email']);
        unset($_SESSION['profile_otp_expiry']);
        unset($_SESSION['profile_otp_verified']);
    }
    
    // Check if email is already used by another user
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email is already in use by another account']);
        $stmt->close();
        exit;
    }
    $stmt->close();
    
    // Check if mobile is already used by another user
    $stmt = $conn->prepare("SELECT id FROM users WHERE mobile = ? AND id != ?");
    $stmt->bind_param("si", $mobile_clean, $user_id);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Mobile number is already in use by another account']);
        $stmt->close();
        exit;
    }
    $stmt->close();
    
    // Update user profile
    $stmt = $conn->prepare("UPDATE users SET name = ?, mobile = ?, email = ? WHERE id = ?");
    $stmt->bind_param("sssi", $name, $mobile_clean, $email, $user_id);
    
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
        $stmt->close();
        exit;
    }
    
    $stmt->close();
    
    // Update session data
    $_SESSION['name'] = $name;
    $_SESSION['email'] = $email;
    $_SESSION['mobile'] = $mobile_clean;
    
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    exit;
}

// Invalid action
echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>