<?php
// patient_history.php
// GET parameter: action=list  -> returns list of patients
// action=detail&patient_id=... -> returns patient details + appointments + reports
header('Content-Type: application/json; charset=utf-8');

$DB_HOST='localhost';
$DB_USER='ruhanixl_doctorApp';
$DB_PASS='@aashi12345678@';
$DB_NAME='ruhanixl_doctorApp';

$mysqli = new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
if($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'DB conn failed']);
    exit;
}

$action = $_GET['action'] ?? 'list';

if($action === 'list'){
    $res = $mysqli->query("SELECT id, patient_id, patient_name, mobile FROM patient_list ORDER BY id DESC LIMIT 1000");
    $out = [];
    while($r = $res->fetch_assoc()) $out[] = $r;
    echo json_encode(['success'=>true,'patients'=>$out]);
    exit;
}

if($action === 'detail'){
    $pid = $_GET['patient_id'] ?? '';
    if(!$pid) { echo json_encode(['success'=>false,'message'=>'patient_id required']); exit; }

    // patient info
    $stmt = $mysqli->prepare("SELECT * FROM patient_list WHERE patient_id=? LIMIT 1");
    $stmt->bind_param('s',$pid);
    $stmt->execute();
    $res = $stmt->get_result();
    $patient = $res->fetch_assoc();
    $stmt->close();

    if(!$patient) { echo json_encode(['success'=>false,'message'=>'patient not found']); exit; }

    // appointments table assumed: appointments (id, patient_id, doctor, date, time, notes)
    $appt = [];
    $stmt = $mysqli->prepare("SELECT id,doctor,date,time,notes FROM appointments WHERE patient_id=? ORDER BY date DESC, id DESC");
    $stmt->bind_param('s',$pid);
    $stmt->execute();
    $r = $stmt->get_result();
    while($row = $r->fetch_assoc()) $appt[] = $row;
    $stmt->close();

    // Blood reports: Get from patient_reports table (uploaded via upload_blood_report page)
    $blood_reports = [];
    $stmt = $mysqli->prepare("SELECT id, file_name, file_path, uploaded_at as created_at FROM patient_reports WHERE patient_id=? ORDER BY uploaded_at DESC");
    if($stmt){
        $stmt->bind_param('s', $pid);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) {
            // file_path is stored as 'blood_reports/client_id/patient_id/filename.pdf'
            // Path correction: blood_reports folder is in patient_history folder
            $filePath = $row['file_path'];
            $row['file_path'] = $filePath; // Use path as is, since blood_reports is in same folder
            $blood_reports[] = $row;
        }
        $stmt->close();
    }
    
    // Uploaded reports: Get from patient_list.report_file column (uploaded via add_new_patient page)
    // These are the reports uploaded when creating patient, NOT blood reports
    $uploaded_reports = [];
    if(isset($patient['report_file']) && !empty($patient['report_file'])){
        error_log("Found report_file in patient_list: " . $patient['report_file']);
        $filePaths = explode(',', $patient['report_file']);
        error_log("Split into " . count($filePaths) . " file paths");
        
        // Get created_at date from patient record
        $createdAt = $patient['created_at'] ?? null;
        
        foreach($filePaths as $path){
            $path = trim($path);
            if($path){
                // Extract filename from path
                $fileName = basename($path);
                // Correct path: files are in /doctor_app/add_new_patient/uploads/
                // We're in /doctor_app/patient_history/, so use ../add_new_patient/
                $correctedPath = '../add_new_patient/' . $path;
                $reportItem = [
                    'id' => 'patient_list',
                    'file_name' => $fileName,
                    'file_path' => $correctedPath,
                    'created_at' => $createdAt,
                    'previous_prescription_file' => null,
                    'previous_blood_test_file' => null
                ];
                $uploaded_reports[] = $reportItem;
                error_log("Added report to array: " . json_encode($reportItem));
            }
        }
    } else {
        error_log("No report_file found in patient_list or it's empty");
    }
    
    // Add previous prescription file if exists
    $patient['previous_prescription_file'] = $patient['previous_prescription_file'] ?? null;
    $patient['previous_blood_test_file'] = $patient['previous_blood_test_file'] ?? null;

    // prescriptions: fetch grouped by prescription_id (one row per prescription)
    $prescriptions = [];
    $stmt = $mysqli->prepare("SELECT 
                p.id as prescription_id,
                p.patient_id,
                p.created_at as prescription_date,
                COUNT(pi.id) as medicine_count,
                GROUP_CONCAT(pi.medicine_name SEPARATOR ', ') as medicines,
                MAX(pi.symptoms) as symptoms,
                MAX(pi.recommended_blood_test) as recommended_blood_test,
                MAX(pi.follow_up_date) as follow_up_date
                FROM prescriptions p
                LEFT JOIN prescription_items pi ON p.id = pi.prescription_id
                WHERE p.patient_id = ?
                GROUP BY p.id
                ORDER BY p.created_at DESC");
    $stmt->bind_param('s', $pid);
    $stmt->execute();
    $r = $stmt->get_result();
    while($row = $r->fetch_assoc()){
        $prescriptions[] = $row;
    }
    $stmt->close();

    // Prepare previous files paths
    $previous_prescription_path = null;
    $previous_blood_test_path = null;
    
    if(!empty($patient['previous_prescription_file'])){
        $previous_prescription_path = '../add_new_patient/' . $patient['previous_prescription_file'];
    }
    if(!empty($patient['previous_blood_test_file'])){
        $previous_blood_test_path = '../add_new_patient/' . $patient['previous_blood_test_file'];
    }

    echo json_encode(['success'=>true,'patient'=>$patient,'appointments'=>$appt,'blood_reports'=>$blood_reports,'uploaded_reports'=>$uploaded_reports,'prescriptions'=>$prescriptions,'other_info'=> '','previous_prescription_file'=>$previous_prescription_path,'previous_blood_test_file'=>$previous_blood_test_path]);
    exit;
}

// fallback
echo json_encode(['success'=>false,'message'=>'invalid action']);
$mysqli->close();