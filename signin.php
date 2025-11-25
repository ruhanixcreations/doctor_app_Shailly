<?php
// signin.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '.ruhanixlegal.in', // adjust
    'secure' => false, // set true with HTTPS in production
    'httponly' => true,
    'samesite' => 'Lax'
]);

$sessionDir = __DIR__ . "/../sessions";
if (!file_exists($sessionDir)) mkdir($sessionDir, 0777, true);
session_save_path($sessionDir);
session_start();

date_default_timezone_set('Asia/Kolkata');

// CORS
$allowed_origins = [
    "https://ruhanixlegal.in",
    "http://ruhanixlegal.in",
    "https://www.ruhanixlegal.in",
    "http://www.ruhanixlegal.in"
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Headers: Content-Type");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

header("Content-Type: application/json; charset=UTF-8");

require_once '../connections.php'; // $conn (mysqli)

$raw = file_get_contents("php://input");
$input = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false && $raw) {
    $input = json_decode($raw, true) ?: [];
} else {
    $input = $_POST ?: [];
}
$action = $input['action'] ?? $_POST['action'] ?? null;

/* send_otp */
if ($action === 'send_otp') {
    $email = strtolower(trim($input['email'] ?? $_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['success'=>false,'message'=>'Invalid email']); exit; }
    $stmt = $conn->prepare("SELECT id, name, client_id, mobile FROM users WHERE LOWER(email)=LOWER(?) LIMIT 1");
    $stmt->bind_param("s",$email); $stmt->execute(); $res=$stmt->get_result();
    if ($res->num_rows===0){ echo json_encode(['success'=>false,'message'=>'User not registered']); exit; }
    $user=$res->fetch_assoc();
    $otp = (string) rand(100000,999999);
    $_SESSION['signin_otp_email']=$email;
    $_SESSION['signin_otp_code']=$otp;
    $_SESSION['signin_otp_expiry']=time()+300;
    $_SESSION['signin_otp_sent_at']=time();
    echo json_encode(['success'=>true,'message'=>'OTP sent successfully']);
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    else { if (ob_get_level()) ob_end_flush(); flush(); }
    $subject = "Your OTP for login"; $message = "Your OTP is: $otp\nValid for 5 minutes."; $headers = "From: noreply@ruhanixlegal.in\r\nContent-Type: text/plain; charset=utf-8";
    @mail($email,$subject,$message,$headers);
    exit;
}

/* verify_otp */
if ($action === 'verify_otp') {
    $email = strtolower(trim($input['email'] ?? $_POST['email'] ?? ''));
    $otp = trim($input['otp'] ?? $_POST['otp'] ?? '');
    if (!$email || !$otp) { echo json_encode(['success'=>false,'message'=>'Missing email or otp']); exit; }
    if (!isset($_SESSION['signin_otp_code']) || !isset($_SESSION['signin_otp_email'])) { echo json_encode(['success'=>false,'message'=>'OTP session not found. Resend OTP.']); exit; }
    if (strtolower($_SESSION['signin_otp_email']) !== strtolower($email)) { echo json_encode(['success'=>false,'message'=>'Email does not match OTP session']); exit; }
    if (time() > ($_SESSION['signin_otp_expiry'] ?? 0)) { unset($_SESSION['signin_otp_code'],$_SESSION['signin_otp_email'],$_SESSION['signin_otp_expiry'],$_SESSION['signin_otp_sent_at']); echo json_encode(['success'=>false,'message'=>'OTP expired. Please resend OTP.']); exit; }
    if ($_SESSION['signin_otp_code'] != $otp) { echo json_encode(['success'=>false,'message'=>'OTP did not match']); exit; }
    
    // Check if status column exists and get session_version
    $checkStatus = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
    $hasStatus = ($checkStatus && $checkStatus->num_rows > 0);
    $checkSessionVer = $conn->query("SHOW COLUMNS FROM users LIKE 'session_version'");
    $hasSessionVer = ($checkSessionVer && $checkSessionVer->num_rows > 0);
    
    $query = "SELECT id,name,client_id,mobile,role";
    if ($hasStatus) $query .= ",COALESCE(status,'active') as status";
    if ($hasSessionVer) $query .= ",COALESCE(session_version,1) as session_version";
    $query .= " FROM users WHERE LOWER(email)=LOWER(?) LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s",$email); $stmt->execute(); $res=$stmt->get_result();
    if ($res->num_rows===0){ echo json_encode(['success'=>false,'message'=>'User not found']); exit; }
    $user=$res->fetch_assoc();
    
    // Check if user is disabled
    if ($hasStatus && isset($user['status']) && $user['status'] === 'inactive') {
        echo json_encode(['success'=>false,'message'=>'Your account has been disabled. Please contact administrator.']);
        exit;
    }
    
    $_SESSION['pre_auth_user_id']=(int)$user['id'];
    $_SESSION['pre_auth_email']=$email;
    $_SESSION['pre_auth_time']=time();
    unset($_SESSION['signin_otp_code'],$_SESSION['signin_otp_email'],$_SESSION['signin_otp_expiry'],$_SESSION['signin_otp_sent_at']);
    echo json_encode(['success'=>true,'message'=>'OTP verified','user_id'=>$user['id'],'name'=>$user['name'],'client_id'=>$user['client_id'] ?? null,'mobile'=>$user['mobile'] ?? null,'status'=>$user['status'] ?? 'active']);
    exit;
}

/* complete_otp_login */
if ($action === 'complete_otp_login') {
    $email = strtolower(trim($input['email'] ?? $_POST['email'] ?? ''));
    if (!$email) { echo json_encode(['success'=>false,'message'=>'Missing email']); exit; }
    if (!isset($_SESSION['pre_auth_email']) || strtolower($_SESSION['pre_auth_email']) !== strtolower($email)) { echo json_encode(['success'=>false,'message'=>'OTP verification required']); exit; }
    
    // Check if status and session_version columns exist
    $checkStatus = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
    $hasStatus = ($checkStatus && $checkStatus->num_rows > 0);
    $checkSessionVer = $conn->query("SHOW COLUMNS FROM users LIKE 'session_version'");
    $hasSessionVer = ($checkSessionVer && $checkSessionVer->num_rows > 0);
    
    $query = "SELECT id,name,client_id,mobile,role";
    if ($hasStatus) $query .= ",COALESCE(status,'active') as status";
    if ($hasSessionVer) $query .= ",COALESCE(session_version,1) as session_version";
    $query .= " FROM users WHERE LOWER(email)=LOWER(?) LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s",$email); $stmt->execute(); $res=$stmt->get_result();
    if ($res->num_rows===0){ echo json_encode(['success'=>false,'message'=>'User not found']); exit; }
    $user=$res->fetch_assoc();
    
    // Check if user is disabled
    if ($hasStatus && isset($user['status']) && $user['status'] === 'inactive') {
        echo json_encode(['success'=>false,'message'=>'Your account has been disabled. Please contact administrator.']);
        exit;
    }
    
    session_regenerate_id(true);
    $_SESSION['user_id']=(int)$user['id'];
    $_SESSION['user_email']=$email;
    $_SESSION['user_role']=$user['role'] ?? 'user';
    $_SESSION['client_id']=$user['client_id'] ?? null;
    $_SESSION['mobile']=$user['mobile'] ?? null;
    $_SESSION['user_name']=$user['name'] ?? null;
    $_SESSION['logged_in']=true; $_SESSION['session_issued_at']=time();
    // Store session_version for validation
    if ($hasSessionVer && isset($user['session_version'])) {
        $_SESSION['session_version'] = intval($user['session_version']);
    }
    unset($_SESSION['pre_auth_user_id'],$_SESSION['pre_auth_email'],$_SESSION['pre_auth_time']);
    $redirect="/doctor_app/dashboard/dashboard.html";
    echo json_encode(['success'=>true,'message'=>'Signed in via OTP','user_id'=> (int)$user['id'],'client_id'=>$user['client_id'] ?? null,'name'=>$user['name'] ?? null,'role'=>$user['role'] ?? 'user','email'=>$email,'redirect'=>$redirect]);
    exit;
}

/* login_password (NO longer requires pre_auth) */
if ($action === 'login_password') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    if (!$email || !$password) { echo json_encode(['success'=>false,'message'=>'Missing email or password']); exit; }
    
    // Check if status and session_version columns exist
    $checkStatus = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
    $hasStatus = ($checkStatus && $checkStatus->num_rows > 0);
    $checkSessionVer = $conn->query("SHOW COLUMNS FROM users LIKE 'session_version'");
    $hasSessionVer = ($checkSessionVer && $checkSessionVer->num_rows > 0);
    
    // fetch user & password
    $query = "SELECT id,password,name,client_id,mobile,role";
    if ($hasStatus) $query .= ",COALESCE(status,'active') as status";
    if ($hasSessionVer) $query .= ",COALESCE(session_version,1) as session_version";
    $query .= " FROM users WHERE LOWER(email)=LOWER(?) LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s",$email); $stmt->execute(); $res=$stmt->get_result();
    if ($res->num_rows===0){ echo json_encode(['success'=>false,'message'=>'User not found']); exit; }
    $user = $res->fetch_assoc();
    
    // Check if user is disabled
    if ($hasStatus && isset($user['status']) && $user['status'] === 'inactive') {
        echo json_encode(['success'=>false,'message'=>'Your account has been disabled. Please contact administrator.']);
        exit;
    }
    
    $storedHash = $user['password'] ?? '';
    $passwordOk = false;
    if ($storedHash) {
        if (strpos($storedHash,'$2y$')===0 || strpos($storedHash,'$2a$')===0 || strpos($storedHash,'$argon2')!==false) {
            if (password_verify($password,$storedHash)) $passwordOk = true;
        } else {
            if ($storedHash === $password) $passwordOk = true;
        }
    } else {
        echo json_encode(['success'=>false,'message'=>'No password set for this account. Use Forgot Password to set one.']); exit;
    }
    if (!$passwordOk) { echo json_encode(['success'=>false,'message'=>'Wrong password']); exit; }
    // create session
    session_regenerate_id(true);
    $_SESSION['user_id']=(int)$user['id'];
    $_SESSION['user_email']=$email;
    $_SESSION['user_role']=$user['role'] ?? 'user';
    $_SESSION['client_id']=$user['client_id'] ?? null;
    $_SESSION['mobile']=$user['mobile'] ?? null;
    $_SESSION['user_name']=$user['name'] ?? null;
    $_SESSION['logged_in']=true; $_SESSION['session_issued_at']=time();
    // Store session_version for validation
    if ($hasSessionVer && isset($user['session_version'])) {
        $_SESSION['session_version'] = intval($user['session_version']);
    }
    unset($_SESSION['pre_auth_user_id'],$_SESSION['pre_auth_email'],$_SESSION['pre_auth_time']);
    $redirect="/doctor_app/dashboard/dashboard.html";
    echo json_encode(['success'=>true,'message'=>'Signed in successfully','user_id'=>(int)$user['id'],'client_id'=>$user['client_id'] ?? null,'name'=>$user['name'] ?? null,'role'=>$user['role'] ?? 'user','email'=>$email,'redirect'=>$redirect]);
    exit;
}

/* fallback */
http_response_code(400);
echo json_encode(['success'=>false,'message'=>'Invalid request']);
exit;
?>
