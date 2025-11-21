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
    // Add is_active column if it doesn't exist (silently fail if already exists)
    $checkCol = $mysqli->query("SHOW COLUMNS FROM users LIKE 'is_active'");
    if($checkCol->num_rows == 0){
        $mysqli->query("ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1 COMMENT '1=active, 0=disabled'");
    }
    
    $res = $mysqli->query("SELECT id, name, email, mobile, role, created_at, COALESCE(is_active, 1) as is_active FROM users WHERE role='receptionalist' ORDER BY id DESC");
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
    
    $stmt = $mysqli->prepare("UPDATE users SET name=?, email=?, mobile=? WHERE id=? AND role='receptionalist'");
    $stmt->bind_param('sssi',$name,$email,$mobile,$id);
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
    $id = intval($_GET['id'] ?? 0);
    if(!$id) send_json(['success'=>false,'message'=>'invalid id']);
    
    // Get current status
    $stmt = $mysqli->prepare("SELECT is_active FROM users WHERE id=? AND role='receptionalist'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if(!$user) send_json(['success'=>false,'message'=>'user not found']);
    
    // Toggle status
    $newStatus = ($user['is_active'] == 1) ? 0 : 1;
    $updateStmt = $mysqli->prepare("UPDATE users SET is_active=? WHERE id=? AND role='receptionalist'");
    $updateStmt->bind_param('ii', $newStatus, $id);
    $ok = $updateStmt->execute();
    $updateStmt->close();
    
    // If user was disabled, delete their active sessions
    if($newStatus == 0){
        // Delete session files for this user - use parent directory to match signin.php
        $sessionDir = __DIR__ . "/../sessions";
        if(is_dir($sessionDir)){
            $files = scandir($sessionDir);
            foreach($files as $file){
                if($file == '.' || $file == '..') continue;
                $filePath = $sessionDir . '/' . $file;
                if(is_file($filePath)){
                    $sessionData = file_get_contents($filePath);
                    // Check if this session belongs to the disabled user with exact match
                    // Match pattern: user_id";i:123; where 123 is exact ID with semicolon after
                    if(preg_match('/user_id";i:' . preg_quote($id, '/') . ';/', $sessionData)){
                        unlink($filePath);
                    }
                }
            }
        }
    }
    
    send_json(['success'=>$ok, 'new_status'=>$newStatus]);
}

send_json(['success'=>false,'message'=>'invalid action']);
$mysqli->close();
