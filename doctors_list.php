<?php
// doctors_list.php
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
    $res = $mysqli->query("SELECT id, name, email, mobile, role, created_at, status FROM users WHERE role='user' ORDER BY id DESC");
    $out = [];
    while($r = $res->fetch_assoc()) $out[] = $r;
    send_json(['success'=>true,'doctors'=>$out]);
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
    
    $stmt = $mysqli->prepare("UPDATE users SET name=?, email=?, mobile=? WHERE id=? AND role='user'");
    $stmt->bind_param('sssi',$name,$email,$mobile,$id);
    $ok = $stmt->execute();
    send_json(['success'=>$ok]);
}

if($action === 'delete'){
    $id = intval($_GET['id'] ?? 0);
    if(!$id) send_json(['success'=>false,'message'=>'invalid id']);
    $stmt = $mysqli->prepare("DELETE FROM users WHERE id=? AND role='user'");
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    send_json(['success'=>$ok]);
}

if($action === 'disable'){
    $id = intval($_GET['id'] ?? 0);
    if(!$id) send_json(['success'=>false,'message'=>'invalid id']);
    $stmt = $mysqli->prepare("UPDATE users SET status='disabled' WHERE id=? AND role='user'");
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    send_json(['success'=>$ok]);
}

if($action === 'enable'){
    $id = intval($_GET['id'] ?? 0);
    if(!$id) send_json(['success'=>false,'message'=>'invalid id']);
    $stmt = $mysqli->prepare("UPDATE users SET status='active' WHERE id=? AND role='user'");
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    send_json(['success'=>$ok]);
}

send_json(['success'=>false,'message'=>'invalid action']);
$mysqli->close();
