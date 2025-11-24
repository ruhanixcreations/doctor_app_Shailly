<?php
// receptionalist_list.php
// Actions: list, delete, toggle_status
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
    // Ensure status column exists only on list action
    $checkColumn = $mysqli->query("SHOW COLUMNS FROM users LIKE 'status'");
    if($checkColumn && $checkColumn->num_rows === 0){
        $mysqli->query("ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'active' AFTER role");
    }
    // Set default status for NULL values
    $mysqli->query("UPDATE users SET status = 'active' WHERE (status IS NULL OR status = '') AND role='receptionalist'");
    
    $res = $mysqli->query("SELECT id, name, email, mobile, role, COALESCE(status, 'active') as status, created_at FROM users WHERE role='receptionalist' ORDER BY id DESC");
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
    if(!$id) send_json(['success'=>false,'message'=>'Invalid ID']);
    
    // Get current status using COALESCE to handle NULL
    $stmt = $mysqli->prepare("SELECT id, COALESCE(status, 'active') as status FROM users WHERE id=? AND role='receptionalist'");
    $stmt->bind_param('i', $id);
    
    if(!$stmt->execute()){
        send_json(['success'=>false,'message'=>'Database error: ' . $stmt->error]);
    }
    
    $result = $stmt->get_result();
    if($result->num_rows === 0){
        $stmt->close();
        send_json(['success'=>false,'message'=>'User not found']);
    }
    
    $user = $result->fetch_assoc();
    $currentStatus = trim($user['status']);
    $stmt->close();
    
    // Toggle status
    $newStatus = ($currentStatus === 'active') ? 'disabled' : 'active';
    
    // Update status in database
    $updateStmt = $mysqli->prepare("UPDATE users SET status=? WHERE id=? AND role='receptionalist'");
    $updateStmt->bind_param('si', $newStatus, $id);
    
    if(!$updateStmt->execute()){
        $updateStmt->close();
        send_json(['success'=>false,'message'=>'Failed to update: ' . $updateStmt->error]);
    }
    
    $affected = $updateStmt->affected_rows;
    $updateStmt->close();
    
    if($affected === 0){
        send_json(['success'=>false,'message'=>'No rows updated. Current status: ' . $currentStatus]);
    }
    
    // If disabling user, destroy their active sessions
    if($newStatus === 'disabled'){
        $sessionDir = __DIR__ . "/../sessions";
        $sessionsDeleted = 0;
        
        if(file_exists($sessionDir)){
            $files = glob($sessionDir . "/sess_*");
            if($files){
                foreach($files as $file){
                    $sessionData = @file_get_contents($file);
                    // Look for user_id in session data
                    if($sessionData && (strpos($sessionData, "user_id\";i:$id;") !== false || strpos($sessionData, "user_id|i:$id;") !== false)){
                        if(@unlink($file)){
                            $sessionsDeleted++;
                        }
                    }
                }
            }
        }
        
        send_json(['success'=>true, 'new_status'=>$newStatus, 'message'=>'User disabled and ' . $sessionsDeleted . ' session(s) deleted']);
    } else {
        send_json(['success'=>true, 'new_status'=>$newStatus, 'message'=>'User enabled successfully']);
    }
}

send_json(['success'=>false,'message'=>'invalid action']);
$mysqli->close();