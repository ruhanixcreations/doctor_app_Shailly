<?php
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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }
    
    $email = strtolower(trim($_POST['email'] ?? ''));
    
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email is required']);
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }
    
    // Check if email is already used by another user
    $stmt = $conn->prepare("SELECT id FROM users WHERE LOWER(email) = ? AND id != ?");
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
    
    // Send email (in production, implement actual email sending)
    $subject = "Email Verification OTP";
    $message = "Your OTP for email verification is: $otp\n\nValid for 5 minutes. Please do not share this code with anyone.";
    $headers = "From: noreply@ruhanixlegal.in\r\nContent-Type: text/plain; charset=utf-8";
    
    // For testing, return OTP in response (remove in production)
    echo json_encode(['success' => true, 'message' => 'OTP sent successfully', 'otp' => $otp]);
    
    // Send email in background (uncomment in production)
    // if (function_exists('fastcgi_finish_request')) {
    //     fastcgi_finish_request();
    // }
    // mail($email, $subject, $message, $headers);
    
    exit;
}

// Verify OTP
if ($action === 'verify_otp') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }
    
    $email = strtolower(trim($_POST['email'] ?? ''));
    $otp = trim($_POST['otp'] ?? '');
    
    if (empty($email) || empty($otp)) {
        echo json_encode(['success' => false, 'message' => 'Email and OTP are required']);
        exit;
    }
    
    // Check if OTP session exists
    if (!isset($_SESSION['profile_otp']) || !isset($_SESSION['profile_otp_email']) || !isset($_SESSION['profile_otp_expiry'])) {
        echo json_encode(['success' => false, 'message' => 'OTP session expired. Please request a new OTP.']);
        exit;
    }
    
    // Check if OTP expired
    if (time() > $_SESSION['profile_otp_expiry']) {
        unset($_SESSION['profile_otp']);
        unset($_SESSION['profile_otp_email']);
        unset($_SESSION['profile_otp_expiry']);
        echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.']);
        exit;
    }
    
    // Verify email matches
    if (strtolower($_SESSION['profile_otp_email']) !== $email) {
        echo json_encode(['success' => false, 'message' => 'Email does not match the one OTP was sent to.']);
        exit;
    }
    
    // Verify OTP
    if ($_SESSION['profile_otp'] !== $otp) {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
        exit;
    }
    
    // OTP verified successfully
    $_SESSION['profile_email_verified'] = $email;
    unset($_SESSION['profile_otp']);
    unset($_SESSION['profile_otp_email']);
    unset($_SESSION['profile_otp_expiry']);
    
    echo json_encode(['success' => true, 'message' => 'Email verified successfully']);
    exit;
}

// Update user profile
if ($action === 'update') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }
    
    $name = trim($input['name'] ?? '');
    $mobile = trim($input['mobile'] ?? '');
    $email = strtolower(trim($input['email'] ?? ''));
    
    // Validation
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Name is required']);
        exit;
    }
    
    // Validate name (only letters and spaces)
    if (!preg_match('/^[A-Za-z\s]+$/', $name)) {
        echo json_encode(['success' => false, 'message' => 'Name should contain only letters and spaces']);
        exit;
    }
    
    if (empty($mobile)) {
        echo json_encode(['success' => false, 'message' => 'Mobile number is required']);
        exit;
    }
    
    // Validate mobile (exactly 10 digits)
    if (!preg_match('/^\d{10}$/', $mobile)) {
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
    
    // Get current user email
    $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current = $result->fetch_assoc();
    $stmt->close();
    
    $current_email = strtolower($current['email']);
    
    // If email changed, verify it was verified via OTP
    if ($email !== $current_email) {
        if (!isset($_SESSION['profile_email_verified']) || strtolower($_SESSION['profile_email_verified']) !== $email) {
            echo json_encode(['success' => false, 'message' => 'Please verify your new email address before saving']);
            exit;
        }
    }
    
    // Check if email is already used by another user
    $stmt = $conn->prepare("SELECT id FROM users WHERE LOWER(email) = ? AND id != ?");
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
    $stmt->bind_param("si", $mobile, $user_id);
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
    $stmt->bind_param("sssi", $name, $mobile, $email, $user_id);
    
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
        $stmt->close();
        exit;
    }
    
    $stmt->close();
    
    // Update session data
    $_SESSION['name'] = $name;
    $_SESSION['user_email'] = $email;
    
    // Clear email verification session
    if (isset($_SESSION['profile_email_verified'])) {
        unset($_SESSION['profile_email_verified']);
    }
    
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    exit;
}

// Invalid action
echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
