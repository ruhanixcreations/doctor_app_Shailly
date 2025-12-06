<?php
// dashboard.php
// Handle logo management API requests and redirect to signup

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Create & use an app-specific sessions directory
$sessionDir = __DIR__ . "/../sessions";
if (!file_exists($sessionDir)) {
    @mkdir($sessionDir, 0777, true);
    @chmod($sessionDir, 0777);
}
if (is_dir($sessionDir) && is_writable($sessionDir)) {
    session_save_path($sessionDir);
}

// Align cookie params
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

@session_start();

// Check if this is an API request
$action = $_GET['action'] ?? $_POST['action'] ?? null;

// Handle API requests for logo management
if ($action) {
    header('Content-Type: application/json');
    require_once('../connections.php');
    
    if ($action === 'upload_logo') {
        // Handle logo upload
        $client_id = $_POST['client_id'] ?? null;
        
        if (!$client_id) {
            echo json_encode(['success' => false, 'message' => 'Client ID is required']);
            exit;
        }
        
        if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
            exit;
        }
        
        $file = $_FILES['logo'];
        $allowed_types = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif'];
        
        if (!in_array($file['type'], $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only PNG, JPG, and GIF are allowed']);
            exit;
        }
        
        if ($file['size'] > 2 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File size must be less than 2MB']);
            exit;
        }
        
        // Create uploads directory if it doesn't exist
        $upload_dir = __DIR__ . '/../uploads/logos/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'logo_' . $client_id . '_' . time() . '.' . $extension;
        $filepath = $upload_dir . $filename;
        
        // Delete old logo if exists
        $stmt = $conn->prepare("SELECT logo_path FROM clinic_logos WHERE client_id = ?");
        $stmt->bind_param("s", $client_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $old_path = __DIR__ . '/../' . $row['logo_path'];
            if (file_exists($old_path)) {
                unlink($old_path);
            }
        }
        $stmt->close();
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $logo_url = '/doctor_app/uploads/logos/' . $filename;
            
            // Save to database
            $stmt = $conn->prepare("INSERT INTO clinic_logos (client_id, logo_path, uploaded_at) VALUES (?, ?, NOW()) 
                                     ON DUPLICATE KEY UPDATE logo_path = ?, uploaded_at = NOW()");
            $stmt->bind_param("sss", $client_id, $logo_url, $logo_url);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'logo_url' => $logo_url]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save logo to database']);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
        }
        
        $conn->close();
        exit;
    }
    
    if ($action === 'get_logo') {
        // Get current logo
        $client_id = $_GET['client_id'] ?? null;
        
        if (!$client_id) {
            echo json_encode(['success' => false, 'message' => 'Client ID is required']);
            exit;
        }
        
        $stmt = $conn->prepare("SELECT logo_path FROM clinic_logos WHERE client_id = ?");
        $stmt->bind_param("s", $client_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'logo_url' => $row['logo_path']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No logo found']);
        }
        
        $stmt->close();
        $conn->close();
        exit;
    }
    
    if ($action === 'delete_logo') {
        // Delete logo
        $data = json_decode(file_get_contents('php://input'), true);
        $client_id = $data['client_id'] ?? null;
        
        if (!$client_id) {
            echo json_encode(['success' => false, 'message' => 'Client ID is required']);
            exit;
        }
        
        // Get logo path and delete file
        $stmt = $conn->prepare("SELECT logo_path FROM clinic_logos WHERE client_id = ?");
        $stmt->bind_param("s", $client_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $filepath = __DIR__ . '/../' . $row['logo_path'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            
            // Delete from database
            $stmt2 = $conn->prepare("DELETE FROM clinic_logos WHERE client_id = ?");
            $stmt2->bind_param("s", $client_id);
            
            if ($stmt2->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete from database']);
            }
            $stmt2->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'No logo found']);
        }
        
        $stmt->close();
        $conn->close();
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// Original redirect logic for non-API requests
$allowed_roles = ['receptionalist', 'user', 'admin'];
$role = $_GET['role'] ?? 'receptionalist';
$role = strtolower(trim($role));
if (in_array($role, $allowed_roles)) {
    $_SESSION['preselected_role'] = $role;
    $_SESSION['preselected_role_set_at'] = time();
}

$signup_path = '/doctor_app/signup/signup.html';
$absolutePath = $_SERVER['DOCUMENT_ROOT'] . $signup_path;
if (!file_exists($absolutePath)) {
    $signup_path = 'signup.html';
}

$sep = (strpos($signup_path, '?') === false) ? '?' : '&';
$redirectUrl = $signup_path . $sep . 'role=' . urlencode($role) . '&added_by_admin=true';

header("Location: $redirectUrl");
exit;
