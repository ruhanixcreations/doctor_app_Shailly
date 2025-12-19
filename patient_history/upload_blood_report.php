<?php
header('Content-Type: application/json; charset=utf-8');

// --- DB CONFIGURATION ---
$DB_HOST = 'localhost';
$DB_USER = 'ruhanixl_doctorApp';
$DB_PASS = '@aashi12345678@';
$DB_NAME = 'ruhanixl_doctorApp';

// --- Define Upload Directories ---
$UPLOAD_ROOT_DIR = __DIR__ . '/blood_reports/';
$BASE_URL_ROOT = 'blood_reports/';

$MAX_FILE_SIZE = 15 * 1024 * 1024; // 15MB per file

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'DB connection failed: '.$mysqli->connect_error]);
    exit;
}

// Create patient_reports table if it doesn't exist
$createTableSQL = "CREATE TABLE IF NOT EXISTS patient_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id VARCHAR(100) NOT NULL,
    prescription_id INT NULL,
    client_id VARCHAR(100) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path TEXT NOT NULL,
    uploaded_at DATETIME NOT NULL,
    INDEX idx_patient_id (patient_id),
    INDEX idx_prescription_id (prescription_id),
    INDEX idx_client_id (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if(!$mysqli->query($createTableSQL)){
    error_log("Failed to create patient_reports table: " . $mysqli->error);
}

function send_json($arr, $code=200){
    http_response_code($code);
    echo json_encode($arr);
    exit;
}

// === HANDLE DELETE REQUEST ===
if($_SERVER['REQUEST_METHOD'] === 'DELETE'){
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if(!$data){
        send_json(['success'=>false,'message'=>'Invalid JSON data'],400);
    }
    
    $report_id = $data['report_id'] ?? '';
    $patient_id = $data['patient_id'] ?? '';
    $prescription_id = $data['prescription_id'] ?? '';
    
    if(!$report_id){
        send_json(['success'=>false,'message'=>'Report ID is required'],400);
    }
    
    // Get the file path from database before deleting
    $stmt = $mysqli->prepare("SELECT file_path, patient_id FROM patient_reports WHERE id = ?");
    if(!$stmt){
        send_json(['success'=>false,'message'=>'Database error: '.$mysqli->error],500);
    }
    
    $stmt->bind_param('i', $report_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $report = $result->fetch_assoc();
    $stmt->close();
    
    if(!$report){
        send_json(['success'=>false,'message'=>'Report not found'],404);
    }
    
    // Verify patient_id matches (security check)
    if($patient_id && $report['patient_id'] !== $patient_id){
        send_json(['success'=>false,'message'=>'Unauthorized: Patient ID mismatch'],403);
    }
    
    // Delete the physical file
    $file_path = __DIR__ . '/' . $report['file_path'];
    $file_deleted = false;
    if(file_exists($file_path)){
        $file_deleted = @unlink($file_path);
        if(!$file_deleted){
            error_log("Failed to delete file: " . $file_path);
        }
    }
    
    // Delete from database
    $stmt = $mysqli->prepare("DELETE FROM patient_reports WHERE id = ?");
    if(!$stmt){
        send_json(['success'=>false,'message'=>'Database error: '.$mysqli->error],500);
    }
    
    $stmt->bind_param('i', $report_id);
    if($stmt->execute()){
        $stmt->close();
        send_json([
            'success'=>true,
            'message'=>'Blood report deleted successfully',
            'file_deleted'=>$file_deleted
        ]);
    } else {
        $error = $stmt->error;
        $stmt->close();
        send_json(['success'=>false,'message'=>'Failed to delete report: '.$error],500);
    }
}

// === POST BLOOD REPORT UPLOAD ===
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    send_json(['success'=>false,'message'=>'Invalid request method'],405);
}

// Read fields from POST
$client_id = trim($_POST['client_id'] ?? '');
$patient_id = trim($_POST['patient_id'] ?? '');
$prescription_id = trim($_POST['prescription_id'] ?? '');

// --- Input Validation ---
if($client_id === ''){
    send_json(['success'=>false,'message'=>'Validation Error: Client ID missing'],400);
}
if($patient_id === ''){
    send_json(['success'=>false,'message'=>'Validation Error: Patient ID missing'],400);
}

$allowed_mimes = ['image/jpeg','image/png','image/webp','image/gif','application/pdf'];
$allowed_exts = ['jpg','jpeg','png','webp','gif','pdf'];

$saved_paths = [];

// --- Create upload directory structure ---
// Directory structure: blood_reports/{client_id}/{patient_id}/
$NESTED_DIR = $client_id . '/' . $patient_id . '/';
$UPLOAD_DIR = $UPLOAD_ROOT_DIR . $NESTED_DIR;
$BASE_URL_PATH = $BASE_URL_ROOT . $NESTED_DIR;

// Create destination directory if it doesn't exist
if (!is_dir($UPLOAD_DIR)) {
    if (!mkdir($UPLOAD_DIR, 0755, true)) {
        send_json(['success'=>false,'message'=>'File System Error: Failed to create upload directory: '.$UPLOAD_DIR], 500);
    }
}

/**
 * Handles the file upload
 */
function handle_file_upload($file_array, $max_size, $allowed_mimes, $allowed_exts, $upload_dir, $base_url_path, &$saved_paths) {
    if ($file_array['error'] === UPLOAD_ERR_NO_FILE) return;
    if ($file_array['error'] !== UPLOAD_ERR_OK){
        send_json(['success'=>false,'message'=>'File upload error: '.$file_array['name'].' (Code '.$file_array['error'].')'],400);
    }
    if ($file_array['size'] > $max_size){
        send_json(['success'=>false,'message'=>'File too large: '.$file_array['name'].' (Max 15MB)'],400);
    }

    $mime = mime_content_type($file_array['tmp_name']);
    $ext = strtolower(pathinfo($file_array['name'], PATHINFO_EXTENSION));
    if(!in_array($mime, $allowed_mimes) && !in_array($ext, $allowed_exts)){
        send_json(['success'=>false,'message'=>'Invalid file type: '.$file_array['name']],400);
    }

    // Generate unique filename with timestamp
    $safeName = 'blood_report_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $safeName = preg_replace('/[^a-zA-Z0-9_.\-]/','_',$safeName);
    
    $dest = $upload_dir . $safeName;
    if(!move_uploaded_file($file_array['tmp_name'], $dest)){
        send_json(['success'=>false,'message'=>'File System Error: Failed to move file: '.$file_array['name']],500);
    }
    
    $saved_paths[] = $base_url_path . $safeName;
}

// Process files
$files_to_process = [];

// Handle single PDF upload
if(isset($_FILES['blood_report_pdf']) && $_FILES['blood_report_pdf']['error'] !== UPLOAD_ERR_NO_FILE){
    $files_to_process[] = $_FILES['blood_report_pdf'];
}

// Handle multiple file uploads (images)
if(isset($_FILES['files']) && is_array($_FILES['files']['name'])){
    foreach($_FILES['files']['name'] as $i => $name){
        if($_FILES['files']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        $files_to_process[] = [
            'name'=> $name,
            'tmp_name'=> $_FILES['files']['tmp_name'][$i],
            'error'=> $_FILES['files']['error'][$i],
            'size'=> $_FILES['files']['size'][$i]
        ];
    }
}

if(count($files_to_process) === 0){
    send_json(['success'=>false,'message'=>'No files uploaded'],400);
}

foreach($files_to_process as $f){
    handle_file_upload($f, $MAX_FILE_SIZE, $allowed_mimes, $allowed_exts, 
        $UPLOAD_DIR, $BASE_URL_PATH, $saved_paths);
}

if(count($saved_paths) === 0){
    send_json(['success'=>false,'message'=>'No files were saved'],500);
}

// --- Database Insert ---
// Insert each uploaded file into patient_reports table
$mysqli->begin_transaction();
try{
    $stmt = $mysqli->prepare("INSERT INTO patient_reports (patient_id, prescription_id, client_id, file_name, file_path, uploaded_at) VALUES (?, ?, ?, ?, ?, NOW())");
    
    if(!$stmt){
        throw new Exception('DB Prepare Error: '.$mysqli->error);
    }
    
    foreach($saved_paths as $path){
        $fileName = basename($path);
        $prescId = $prescription_id !== '' ? $prescription_id : null;
        
        $stmt->bind_param('sssss', $patient_id, $prescId, $client_id, $fileName, $path);
        if(!$stmt->execute()){
            throw new Exception('DB Execute Error: '.$stmt->error);
        }
    }
    
    $stmt->close();
    $mysqli->commit();
    
    send_json([
        'success'=>true,
        'message'=>'Blood report(s) uploaded successfully',
        'patient_id'=>$patient_id,
        'prescription_id'=>$prescription_id,
        'saved_files'=>$saved_paths,
        'file_count'=>count($saved_paths)
    ]);
    
} catch(Exception $ex){
    $mysqli->rollback();
    
    // Clean up uploaded files on error
    foreach($saved_paths as $path){
        $fullPath = __DIR__ . '/' . $path;
        if(file_exists($fullPath)){
            @unlink($fullPath);
        }
    }
    
    send_json(['success'=>false,'message'=>'Database error: '.$ex->getMessage()],500);
}

$mysqli->close();
?>