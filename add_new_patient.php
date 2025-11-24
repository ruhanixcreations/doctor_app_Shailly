<?php
header('Content-Type: application/json; charset=utf-8');

// --- DB CONFIGURATION ---
$DB_HOST = 'localhost';
$DB_USER = 'ruhanixl_doctorApp';
$DB_PASS = '@aashi12345678@';
$DB_NAME = 'ruhanixl_doctorApp';

// --- Define Root Directories ---
$UPLOAD_ROOT_DIR_ORIGINAL = __DIR__ . '/uploads/'; 
$BASE_URL_ROOT_ORIGINAL = 'uploads/'; 
$UPLOAD_ROOT_DIR_DOCTOR = dirname(__DIR__) . '/uploads/'; 

$MAX_FILE_SIZE = 15 * 1024 * 1024; // 15MB per file

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'DB connection failed: '.$mysqli->connect_error]);
    exit;
}

function send_json($arr, $code=200){
    http_response_code($code);
    echo json_encode($arr);
    exit;
}

// === GET DOCTORS (No change) ===
if(isset($_GET['action']) && $_GET['action'] === 'get_doctors'){
    $res = $mysqli->query("SELECT id, name, client_id FROM users WHERE role='user' AND status='active' ORDER BY name ASC");
    $doctors = [];
    if($res){
        while($row = $res->fetch_assoc()){
            $doctors[] = ['id'=>$row['id'], 'name'=>$row['name'], 'client_id'=>$row['client_id']];
        }
    }
    send_json(['success'=>true, 'doctors'=>$doctors]);
}

// === POST PATIENT SAVE ===
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    send_json(['success'=>false,'message'=>'Invalid request method'],405);
}

// Read fields from POST
$client_id_local = trim($_POST['client_id'] ?? ''); 
$patient_name = trim($_POST['patient_name'] ?? '');
$mobile = trim($_POST['mobile'] ?? '');
$age = trim($_POST['age'] ?? '');
$weight = trim($_POST['weight'] ?? '');
$doctor_id = trim($_POST['doctor'] ?? ''); 

// --- Input Validation --- (omitted for brevity)
if($client_id_local === '') send_json(['success'=>false,'message'=>'Validation Error: Client ID missing (from local storage)'],400);
if($doctor_id === '' || !is_numeric($doctor_id)) send_json(['success'=>false,'message'=>'Validation Error: Please select a valid doctor'],400);

$allowed_mimes = ['image/jpeg','image/png','image/webp','image/gif','application/pdf'];
$allowed_exts = ['jpg','jpeg','png','webp','gif','pdf'];

$saved_paths = []; 

// --- STEP 1: Fetch the selected doctor's details and format JSON ---
$stmt = $mysqli->prepare("SELECT id, client_id, name FROM users WHERE id = ? AND role = 'user'");
if(!$stmt) send_json(['success'=>false,'message'=>'DB Prepare Error (Doctor Lookup): '.$mysqli->error],500);
$stmt->bind_param('i', $doctor_id);
if(!$stmt->execute()) send_json(['success'=>false,'message'=>'DB Execute Error (Doctor Lookup): '.$stmt->error],500);
$result = $stmt->get_result();
$doctor_client_data = $result->fetch_assoc();
$stmt->close();

if (!$doctor_client_data) {
    send_json(['success'=>false,'message'=>'Selected doctor not found or invalid in DB.'], 404);
}

// Extract required variables
$doctor_client_id = $doctor_client_data['client_id']; // For Path 2
$doctor_name = $doctor_client_data['name'];
$doctor_user_id = $doctor_client_data['id'];

// Create the JSON string to save in the 'doctor' column
$doctor_json = json_encode([
    'id' => $doctor_user_id, 
    'client_id' => $doctor_client_id, 
    'name' => $doctor_name
]);

// Build patient_id 
$random = random_int(100000, 999999);
$datePart = date('d/m/Y');
$patient_id = 'PATIENT' . $random . '-' . str_replace('/', '', $datePart); 

// --- STEP 2: Define Full Target Paths (Using $doctor_client_id for path 2) ---

// 1. Original Path: doctor_app/add_new_patient/uploads/{client_id_local}/{patient_id}/
$NESTED_DIR_ORIGINAL = $client_id_local . '/' . $patient_id . '/';
$UPLOAD_DIR_ORIGINAL = $UPLOAD_ROOT_DIR_ORIGINAL . $NESTED_DIR_ORIGINAL;
$BASE_URL_PATH_ORIGINAL = $BASE_URL_ROOT_ORIGINAL . $NESTED_DIR_ORIGINAL; 

// 2. Doctor Path: doctor_app/uploads/{doctor_client_id}/{patient_id}/
$NESTED_DIR_DOCTOR = $doctor_client_id . '/' . $patient_id . '/';
$UPLOAD_DIR_DOCTOR = $UPLOAD_ROOT_DIR_DOCTOR . $NESTED_DIR_DOCTOR;

// Create both destination directories if they don't exist
if (!is_dir($UPLOAD_DIR_ORIGINAL)) {
    if (!mkdir($UPLOAD_DIR_ORIGINAL, 0755, true)) { 
        send_json(['success'=>false,'message'=>'File System Error: Failed to create Original directory: '.$UPLOAD_DIR_ORIGINAL], 500);
    }
}
if (!is_dir($UPLOAD_DIR_DOCTOR)) {
    if (!mkdir($UPLOAD_DIR_DOCTOR, 0755, true)) { 
        @rmdir($UPLOAD_DIR_ORIGINAL); 
        send_json(['success'=>false,'message'=>'File System Error: Failed to create Doctor directory: '.$UPLOAD_DIR_DOCTOR], 500);
    }
}

/**
 * Handles the file upload: moves to the ORIGINAL path, then copies to the DOCTOR path.
 * (Function body remains unchanged from the previous verified version)
 */
function handle_file_upload($file_array, $max_size, $allowed_mimes, $allowed_exts, $upload_dir_original, $upload_dir_doctor, $base_url_path_original, &$saved_paths) {
    
    if ($file_array['error'] === UPLOAD_ERR_NO_FILE) return;
    if ($file_array['error'] !== UPLOAD_ERR_OK) send_json(['success'=>false,'message'=>'File upload error: '.$file_array['name'].' (Code '.$file_array['error'].')'],400);
    if ($file_array['size'] > $max_size) send_json(['success'=>false,'message'=>'File too large: '.$file_array['name']],400);

    $mime = mime_content_type($file_array['tmp_name']);
    $ext = strtolower(pathinfo($file_array['name'], PATHINFO_EXTENSION));
    if(!in_array($mime, $allowed_mimes) && !in_array($ext, $allowed_exts)) send_json(['success'=>false,'message'=>'Invalid file type: '.$file_array['name']],400);

    $safeName = bin2hex(random_bytes(8)).'_'.$file_array['name'];
    $safeName = preg_replace('/[^a-zA-Z0-9_.\-]/','_',$safeName);
    
    $dest_original = $upload_dir_original . $safeName;
    if(!move_uploaded_file($file_array['tmp_name'], $dest_original)){
        send_json(['success'=>false,'message'=>'File System Error: Failed to move file to original path: '.$file_array['name']],500);
    }
    
    $dest_doctor = $upload_dir_doctor . $safeName;
    if(!copy($dest_original, $dest_doctor)){
        @unlink($dest_original); 
        send_json(['success'=>false,'message'=>'File System Error: Failed to copy file to doctor path: '.$file_array['name']],500);
    }
    
    $saved_paths[] = $base_url_path_original . $safeName; 
}


// Process files for both locations (omitted file list setup for brevity)
$files_to_process = [];
// ... file processing logic ...

// Handle single PDF upload
if(isset($_FILES['report_pdf']) && $_FILES['report_pdf']['error'] !== UPLOAD_ERR_NO_FILE){
    $files_to_process[] = $_FILES['report_pdf'];
}

// Handle multiple file uploads (images)
if(isset($_FILES['files']) && is_array($_FILES['files']['name'])){
    foreach($_FILES['files']['name'] as $i => $name){
        $files_to_process[] = [
          'name'=> $name,
          'tmp_name'=> $_FILES['files']['tmp_name'][$i],
          'error'=> $_FILES['files']['error'][$i],
          'size'=> $_FILES['files']['size'][$i]
        ];
    }
}

foreach($files_to_process as $f){
    handle_file_upload($f, $MAX_FILE_SIZE, $allowed_mimes, $allowed_exts, 
        $UPLOAD_DIR_ORIGINAL, $UPLOAD_DIR_DOCTOR, $BASE_URL_PATH_ORIGINAL, $saved_paths);
}

$report_file_value = count($saved_paths) ? implode(',', $saved_paths) : null;

// Handle previous prescription upload
$previous_prescription_path = null;
if(isset($_FILES['previous_prescription']) && $_FILES['previous_prescription']['error'] !== UPLOAD_ERR_NO_FILE){
    $temp_paths = [];
    handle_file_upload($_FILES['previous_prescription'], $MAX_FILE_SIZE, $allowed_mimes, $allowed_exts, 
        $UPLOAD_DIR_ORIGINAL, $UPLOAD_DIR_DOCTOR, $BASE_URL_PATH_ORIGINAL, $temp_paths);
    if(count($temp_paths) > 0) {
        $previous_prescription_path = $temp_paths[0];
    }
}

// Handle previous blood test upload
$previous_blood_test_path = null;
if(isset($_FILES['previous_blood_test']) && $_FILES['previous_blood_test']['error'] !== UPLOAD_ERR_NO_FILE){
    $temp_paths = [];
    handle_file_upload($_FILES['previous_blood_test'], $MAX_FILE_SIZE, $allowed_mimes, $allowed_exts, 
        $UPLOAD_DIR_ORIGINAL, $UPLOAD_DIR_DOCTOR, $BASE_URL_PATH_ORIGINAL, $temp_paths);
    if(count($temp_paths) > 0) {
        $previous_blood_test_path = $temp_paths[0];
    }
}

// --- STEP 3: Database Insert (Binding the new JSON string) ---

// First, ensure the new columns exist
$mysqli->query("SHOW COLUMNS FROM patient_list LIKE 'previous_prescription_file'");
if($mysqli->affected_rows === 0 || $mysqli->field_count === 0){
    $mysqli->query("ALTER TABLE patient_list ADD COLUMN previous_prescription_file TEXT NULL");
}
$mysqli->query("SHOW COLUMNS FROM patient_list LIKE 'previous_blood_test_file'");
if($mysqli->affected_rows === 0 || $mysqli->field_count === 0){
    $mysqli->query("ALTER TABLE patient_list ADD COLUMN previous_blood_test_file TEXT NULL");
}
$mysqli->query("SHOW COLUMNS FROM patient_list LIKE 'created_at'");
if($mysqli->affected_rows === 0 || $mysqli->field_count === 0){
    $mysqli->query("ALTER TABLE patient_list ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}

// The doctor_id variable is now replaced by the doctor_json string
$stmt = $mysqli->prepare("INSERT INTO patient_list (client_id, patient_id, patient_name, mobile, age, weight, doctor, report_file, previous_prescription_file, previous_blood_test_file, created_at)
  VALUES (?, ?, ?, ?, NULLIF(?,''), NULLIF(?,''), ?, ?, ?, ?, NOW())");

if(!$stmt) {
    send_json(['success'=>false,'message'=>'DB Prepare Error (Patient Insert): '.$mysqli->error],500);
}

// IMPORTANT CHANGE: Binding $doctor_json instead of $doctor_id and adding previous files
// The 'doctor' column should be large enough (VARCHAR/TEXT/JSON type) to store the JSON string.
$stmt->bind_param('ssssssssss', $client_id_local, $patient_id, $patient_name, $mobile, $age, $weight, $doctor_json, $report_file_value, $previous_prescription_path, $previous_blood_test_path);

if(!$stmt->execute()){
    // Clean up function (omitted for brevity)
    $cleanup = function($dir) {
        if (is_dir($dir)) {
            $files = glob($dir . '*');
            foreach ($files as $file) { @unlink($file); }
            @rmdir($dir);
        }
    };

    $cleanup($UPLOAD_DIR_ORIGINAL);
    $cleanup($UPLOAD_DIR_DOCTOR);
    
    send_json(['success'=>false,'message'=>'DB Execute Error (Patient Insert): '.$stmt->error],500);
}

$stmt->close();
$mysqli->close();

send_json(['success'=>true,'message'=>'Patient saved successfully','patient_id'=>$patient_id,'saved_files'=>$saved_paths]);
?>


