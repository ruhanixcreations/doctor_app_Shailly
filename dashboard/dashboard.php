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
$SESSION_TTL = 60 * 60 * 24 * 7; // 7 days in seconds
ini_set('session.gc_maxlifetime', (string)$SESSION_TTL);
ini_set('session.cookie_lifetime', (string)$SESSION_TTL);

$cookieParams = [
    'lifetime' => $SESSION_TTL,
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
        // Handle logo upload - add new logo (not replacing)
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
        $filename = 'logo_' . $client_id . '_' . time() . '_' . uniqid() . '.' . $extension;
        $filepath = $upload_dir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $logo_url = '/doctor_app/uploads/logos/' . $filename;
            
            // Make the newly uploaded logo ACTIVE by default (required so it shows for doctor + prescriptions)
            $conn->begin_transaction();
            try {
                $stmt0 = $conn->prepare("UPDATE clinic_logos SET is_active = 0 WHERE client_id = ?");
                $stmt0->bind_param("s", $client_id);
                $stmt0->execute();
                $stmt0->close();
                
                $stmt = $conn->prepare("INSERT INTO clinic_logos (client_id, logo_path, is_active, uploaded_at) VALUES (?, ?, 1, NOW())");
                $stmt->bind_param("ss", $client_id, $logo_url);
                
                if ($stmt->execute()) {
                    $newId = $conn->insert_id;
                    $conn->commit();
                    echo json_encode(['success' => true, 'logo_url' => $logo_url, 'logo_id' => $newId, 'is_active' => 1]);
                } else {
                    $conn->rollback();
                    echo json_encode(['success' => false, 'message' => 'Failed to save logo to database']);
                }
                $stmt->close();
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Failed to upload logo: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
        }
        
        $conn->close();
        exit;
    }
    
    if ($action === 'list_logos') {
        // List all logos for a client
        $client_id = $_GET['client_id'] ?? null;
        
        if (!$client_id) {
            echo json_encode(['success' => false, 'message' => 'Client ID is required']);
            exit;
        }
        
        $stmt = $conn->prepare("SELECT id, logo_path, is_active, uploaded_at FROM clinic_logos WHERE client_id = ? ORDER BY uploaded_at DESC");
        $stmt->bind_param("s", $client_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $logos = [];
        while ($row = $result->fetch_assoc()) {
            $logos[] = $row;
        }
        
        echo json_encode(['success' => true, 'logos' => $logos]);
        
        $stmt->close();
        $conn->close();
        exit;
    }

    if ($action === 'list_doctors') {
        // List doctors (used by receptionist to pick clinic for logos)
        $res = $conn->query("SELECT id, name, client_id FROM users WHERE role='user' ORDER BY name ASC LIMIT 2000");
        $doctors = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $doctors[] = $row;
            }
        }
        echo json_encode(['success' => true, 'doctors' => $doctors]);
        $conn->close();
        exit;
    }
    
    if ($action === 'apply_logo') {
        // Set a logo as active (and deactivate others)
        $data = json_decode(file_get_contents('php://input'), true);
        $client_id = $data['client_id'] ?? null;
        $logo_id = $data['logo_id'] ?? null;
        
        if (!$client_id || !$logo_id) {
            echo json_encode(['success' => false, 'message' => 'Client ID and Logo ID are required']);
            exit;
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Deactivate all logos for this client
            $stmt1 = $conn->prepare("UPDATE clinic_logos SET is_active = 0 WHERE client_id = ?");
            $stmt1->bind_param("s", $client_id);
            $stmt1->execute();
            $stmt1->close();
            
            // Activate the selected logo
            $stmt2 = $conn->prepare("UPDATE clinic_logos SET is_active = 1 WHERE id = ? AND client_id = ?");
            $stmt2->bind_param("is", $logo_id, $client_id);
            $stmt2->execute();
            
            if ($stmt2->affected_rows > 0) {
                $conn->commit();
                echo json_encode(['success' => true]);
            } else {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Logo not found or does not belong to this client']);
            }
            
            $stmt2->close();
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to apply logo: ' . $e->getMessage()]);
        }
        
        $conn->close();
        exit;
    }
    
    if ($action === 'get_logo') {
        // Get active logo for a client
        $client_id = $_GET['client_id'] ?? null;
        
        if (!$client_id) {
            echo json_encode(['success' => false, 'message' => 'Client ID is required']);
            exit;
        }
        
        // Prefer active logo; fallback to most recent logo if none is marked active
        $stmt = $conn->prepare("SELECT logo_path FROM clinic_logos WHERE client_id = ? AND is_active = 1 ORDER BY uploaded_at DESC LIMIT 1");
        $stmt->bind_param("s", $client_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'logo_url' => $row['logo_path']]);
        } else {
            $stmt2 = $conn->prepare("SELECT logo_path FROM clinic_logos WHERE client_id = ? ORDER BY uploaded_at DESC LIMIT 1");
            $stmt2->bind_param("s", $client_id);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            if ($row2 = $result2->fetch_assoc()) {
                echo json_encode(['success' => true, 'logo_url' => $row2['logo_path'], 'note' => 'No active logo; returning latest uploaded logo']);
            } else {
                echo json_encode(['success' => false, 'message' => 'No logo found']);
            }
            $stmt2->close();
        }
        
        $stmt->close();
        $conn->close();
        exit;
    }
    
    if ($action === 'delete_logo') {
        // Delete specific logo by ID
        $data = json_decode(file_get_contents('php://input'), true);
        $client_id = $data['client_id'] ?? null;
        $logo_id = $data['logo_id'] ?? null;
        
        if (!$client_id || !$logo_id) {
            echo json_encode(['success' => false, 'message' => 'Client ID and Logo ID are required']);
            exit;
        }
        
        // Get logo path and delete file
        $stmt = $conn->prepare("SELECT logo_path FROM clinic_logos WHERE id = ? AND client_id = ?");
        $stmt->bind_param("is", $logo_id, $client_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $filepath = __DIR__ . '/../' . $row['logo_path'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            
            // Delete from database
            $stmt2 = $conn->prepare("DELETE FROM clinic_logos WHERE id = ? AND client_id = ?");
            $stmt2->bind_param("is", $logo_id, $client_id);
            
            if ($stmt2->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete from database']);
            }
            $stmt2->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Logo not found or does not belong to this client']);
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