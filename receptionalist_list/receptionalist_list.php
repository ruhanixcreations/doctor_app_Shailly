<?php
// receptionalist_list.php
// Actions: list, delete
header('Content-Type: application/json; charset=utf-8');

$DB_HOST='localhost'; $DB_USER='ruhanixl_doctorApp'; $DB_PASS='@aashi12345678@'; $DB_NAME='ruhanixl_doctorApp';
$mysqli = new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
if($mysqli->connect_errno){
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'DB connection failed']);
    exit;
}

$action = $_GET['action'] ?? 'list';

function send_json($arr){ echo json_encode($arr); exit; }

if($action === 'list'){
    // First ensure status column exists
    $checkColumns = $mysqli->query("SHOW COLUMNS FROM users LIKE 'status'");
    if($checkColumns->num_rows === 0) {
        $mysqli->query("ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'active'");
    }
    
    $res = $mysqli->query("SELECT id, name, email, mobile, role, created_at, COALESCE(status, 'active') as status FROM users WHERE role='receptionalist' ORDER BY id DESC");
    $out = [];
    while($r = $res->fetch_assoc()) $out[] = $r;
    send_json(['success'=>true,'receptionists'=>$out]);
}

if($action === 'update'){
    $raw = file_get_contents('php://input'); $d = json_decode($raw,true);
    $id = intval($d['id'] ?? 0); if(!$id) send_json(['success'=>false,'message'=>'invalid id']);
    $name = trim($d['name'] ?? ''); if(!$name) send_json(['success'=>false,'message'=>'name required']);
    $email = strtolower(trim($d['email'] ?? '')); if(!$email) send_json(['success'=>false,'message'=>'email required']);
    $mobile = trim($d['mobile'] ?? '');
    $password = trim($d['password'] ?? '');
    $emailChanged = boolval($d['emailChanged'] ?? false);
    $otpVerified = boolval($d['otpVerified'] ?? false);
    
    // If email changed, require OTP verification
    if($emailChanged && !$otpVerified){
        send_json(['success'=>false,'message'=>'Email change requires OTP verification']);
    }
    
    // Check if new email already exists (excluding current user)
    $checkStmt = $mysqli->prepare("SELECT id FROM users WHERE LOWER(email)=? AND id!=?");
    $checkStmt->bind_param('si', $email, $id);
    $checkStmt->execute();
    $checkStmt->store_result();
    if($checkStmt->num_rows > 0){
        send_json(['success'=>false,'message'=>'Email already exists']);
    }
    $checkStmt->close();
    
    // Update with or without password
    if($password){
        // Validate password length
        if(strlen($password) < 6){
            send_json(['success'=>false,'message'=>'Password must be at least 6 characters']);
        }
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare("UPDATE users SET name=?, email=?, mobile=?, password=? WHERE id=? AND role='receptionalist'");
        $stmt->bind_param('ssssi',$name,$email,$mobile,$hashedPassword,$id);
    } else {
        $stmt = $mysqli->prepare("UPDATE users SET name=?, email=?, mobile=? WHERE id=? AND role='receptionalist'");
        $stmt->bind_param('sssi',$name,$email,$mobile,$id);
    }
    $ok = $stmt->execute();
    send_json(['success'=>$ok]);
}

if($action === 'delete'){
    $id = intval($_GET['id'] ?? 0);
    if(!$id) send_json(['success'=>false,'message'=>'invalid id']);
    // Optional: prevent deleting last admin etc. Here delete if role is receptionist
    $stmt = $mysqli->prepare("DELETE FROM users WHERE id=? AND role='receptionalist'");
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    send_json(['success'=>$ok]);
}

if($action === 'toggle_status'){
    $raw = file_get_contents('php://input'); 
    $data = json_decode($raw, true);
    $userId = intval($data['user_id'] ?? 0);
    $newStatus = trim($data['status'] ?? '');
    
    if(!$userId) send_json(['success'=>false, 'message'=>'Invalid user ID']);
    if(!in_array($newStatus, ['active', 'inactive'])) send_json(['success'=>false, 'message'=>'Invalid status']);
    
    // Ensure columns exist
    $checkColumns = $mysqli->query("SHOW COLUMNS FROM users LIKE 'status'");
    if($checkColumns->num_rows === 0) {
        $mysqli->query("ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'active'");
    }
    $checkSessionVersion = $mysqli->query("SHOW COLUMNS FROM users LIKE 'session_version'");
    if($checkSessionVersion->num_rows === 0) {
        $mysqli->query("ALTER TABLE users ADD COLUMN session_version INT DEFAULT 1");
    }
    
    // Update user status and increment session_version
    $stmt = $mysqli->prepare("UPDATE users SET status=?, session_version = session_version + 1 WHERE id=? AND role='receptionalist'");
    $stmt->bind_param('si', $newStatus, $userId);
    $success = $stmt->execute();
    $stmt->close();
    
    if($success && $newStatus === 'inactive') {
        // Clean up session files
        $sessionDir = __DIR__ . "/sessions";
        if(file_exists($sessionDir) && is_dir($sessionDir)) {
            $userStmt = $mysqli->prepare("SELECT email FROM users WHERE id=?");
            $userStmt->bind_param('i', $userId);
            $userStmt->execute();
            $result = $userStmt->get_result();
            if($row = $result->fetch_assoc()) {
                $userEmail = $row['email'];
                $files = scandir($sessionDir);
                foreach($files as $file) {
                    if($file === '.' || $file === '..') continue;
                    $filepath = $sessionDir . '/' . $file;
                    if(is_file($filepath)) {
                        $sessionData = @file_get_contents($filepath);
                        if($sessionData && (strpos($sessionData, $userEmail) !== false || strpos($sessionData, "user_id|i:$userId") !== false)) {
                            @unlink($filepath);
                        }
                    }
                }
            }
            $userStmt->close();
        }
    }
    
    send_json(['success'=>$success, 'message'=>'User status updated successfully']);
}

send_json(['success'=>false,'message'=>'invalid action']);
$mysqli->close();