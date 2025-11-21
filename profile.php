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
    $stmt = $conn->prepare("SELECT id, name, email, client_id, role, status FROM users WHERE id = ?");
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

// Update user profile
if ($action === 'update') {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }
    
    $name = trim($input['name'] ?? '');
    $email = trim($input['email'] ?? '');
    
    // Validation
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Name is required']);
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
    
    // Update user profile
    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
    $stmt->bind_param("ssi", $name, $email, $user_id);
    
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
        $stmt->close();
        exit;
    }
    
    $stmt->close();
    
    // Update session data
    $_SESSION['name'] = $name;
    $_SESSION['email'] = $email;
    
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    exit;
}

// Invalid action
echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
