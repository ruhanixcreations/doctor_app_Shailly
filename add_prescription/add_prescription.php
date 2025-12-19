<?php
// add_prescription.php
header('Content-Type: application/json; charset=utf-8');

$DB_HOST='localhost';
$DB_USER='ruhanixl_doctorApp';
$DB_PASS='@aashi12345678@';
$DB_NAME='ruhanixl_doctorApp';

$mysqli = new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
if($mysqli->connect_errno) { echo json_encode(['success'=>false,'message'=>'DB error']); exit; }

function resolve_effective_client_id($mysqli, $patient_id, $client_candidate){
    $candidate = trim((string)$client_candidate);
    if($candidate !== ''){
        return $candidate;
    }
    $patient_id = trim((string)$patient_id);
    if($patient_id === ''){
        return '';
    }
    $stmt = $mysqli->prepare("SELECT client_id FROM patient_list WHERE patient_id = ? LIMIT 1");
    if(!$stmt){
        error_log("resolve_effective_client_id prepare failed: ".$mysqli->error);
        return '';
    }
    $stmt->bind_param('s', $patient_id);
    if(!$stmt->execute()){
        error_log("resolve_effective_client_id execute failed: ".$stmt->error);
        $stmt->close();
        return '';
    }
    $res = $stmt->get_result();
    $client_id = '';
    if($res){
        $row = $res->fetch_assoc();
        if($row && isset($row['client_id'])){
            $client_id = trim((string)$row['client_id']);
        }
    }
    $stmt->close();
    return $client_id;
}

function ensure_appointments_client_id_column_supports_text($mysqli){
    static $checked = false;
    if($checked) return;
    $checked = true;

    $colRes = $mysqli->query("SHOW FULL COLUMNS FROM `appointments` LIKE 'client_id'");
    if(!$colRes || !$colRes->num_rows){
        return;
    }
    $col = $colRes->fetch_assoc();
    $colRes->free();
    if(!$col || !isset($col['Type'])){
        return;
    }
    $type = strtolower($col['Type']);
    if(strpos($type, 'char') === false && strpos($type, 'text') === false){
        if(!$mysqli->query("ALTER TABLE `appointments` MODIFY `client_id` VARCHAR(64) DEFAULT NULL")){
            error_log("Failed to alter appointments.client_id to VARCHAR: ".$mysqli->error);
        }
    }
}

$action = $_GET['action'] ?? 'list_medicines';

/////////////////////////
/// list_patients (returns patient_name,mobile,age,weight,last_visit)
/////////////////////////
if($action === 'list_patients'){
    $sql = "SELECT pl.patient_id, pl.patient_name, pl.mobile, pl.age, pl.weight, 
                   DATE_FORMAT(p_max.last_visit, '%Y-%m-%d') AS last_visit
            FROM patient_list pl
            LEFT JOIN (
              SELECT patient_id, MAX(created_at) AS last_visit
              FROM prescriptions
              GROUP BY patient_id
            ) p_max ON p_max.patient_id = pl.patient_id
            ORDER BY pl.id DESC
            LIMIT 1000";
    $res = $mysqli->query($sql);
    $out=[];
    if($res){
        while($r=$res->fetch_assoc()) $out[]=$r;
    }
    echo json_encode(['success'=>true,'patients'=>$out]); exit;
}

/////////////////////////
// list_medicines
/////////////////////////
if($action === 'list_medicines'){
    $res = $mysqli->query("SELECT id,name,company,form,strength FROM medicines ORDER BY name LIMIT 1000");
    $out=[];
    if($res){
        while($r=$res->fetch_assoc()) $out[]=$r;
    }
    echo json_encode(['success'=>true,'medicines'=>$out]); exit;
}

/////////////////////////
// list_blood_tests
/////////////////////////
if($action === 'list_blood_tests'){
    $res = $mysqli->query("SELECT id, name, price, created_at FROM blood_tests ORDER BY name LIMIT 1000");
    $out=[];
    if($res){
        while($r=$res->fetch_assoc()) $out[]=$r;
    }
    echo json_encode(['success'=>true,'blood_tests'=>$out]); exit;
}

/////////////////////////
// get_doctor_info: fetch doctor details by client_id where role='user'
/////////////////////////
if($action === 'get_doctor_info'){
    try{
        $client_id = isset($_GET['client_id']) ? trim($_GET['client_id']) : '';
        if($client_id === ''){
            echo json_encode(['success'=>false,'message'=>'client_id required']); exit;
        }
        
        // Log the attempt
        error_log("get_doctor_info: Looking for client_id: " . $client_id);
        
        // First check if users table exists and has data
        $checkTable = $mysqli->query("SHOW TABLES LIKE 'users'");
        if(!$checkTable || $checkTable->num_rows === 0){
            echo json_encode(['success'=>false,'message'=>'users table not found']); exit;
        }
        
        // Query users table with client_id and role='user'
        $stmt = $mysqli->prepare("SELECT name, email, mobile, role FROM users WHERE client_id = ? AND role = 'user' LIMIT 1");
        if(!$stmt){
            error_log("get_doctor_info: Prepare failed - " . $mysqli->error);
            echo json_encode(['success'=>false,'message'=>'Prepare failed: ' . $mysqli->error]); exit;
        }
        
        $stmt->bind_param('s', $client_id);
        if(!$stmt->execute()){
            error_log("get_doctor_info: Execute failed - " . $stmt->error);
            echo json_encode(['success'=>false,'message'=>'Execute failed: ' . $stmt->error]); exit;
        }
        
        $res = $stmt->get_result();
        
        if($res && $res->num_rows > 0){
            $doctor = $res->fetch_assoc();
            error_log("get_doctor_info: Found doctor - " . $doctor['name']);
            $stmt->close();
            echo json_encode(['success'=>true,'doctor'=>$doctor]); exit;
        } else {
            error_log("get_doctor_info: No doctor found with client_id: " . $client_id . " and role=user");
            $stmt->close();
            
            // Try without role filter to see if user exists
            $stmt2 = $mysqli->prepare("SELECT name, email, mobile, role FROM users WHERE client_id = ? LIMIT 1");
            if($stmt2){
                $stmt2->bind_param('s', $client_id);
                $stmt2->execute();
                $res2 = $stmt2->get_result();
                if($res2 && $res2->num_rows > 0){
                    $user = $res2->fetch_assoc();
                    error_log("get_doctor_info: Found user but role is: " . $user['role']);
                    echo json_encode(['success'=>true,'doctor'=>$user,'note'=>'Found user with different role']); exit;
                }
                $stmt2->close();
            }
            
            echo json_encode(['success'=>false,'message'=>'Doctor not found with role=user','client_id'=>$client_id]); exit;
        }
    }catch(Exception $e){
        error_log("get_doctor_info: Exception - " . $e->getMessage());
        echo json_encode(['success'=>false,'message'=>'Exception: ' . $e->getMessage()]); exit;
    }
}

/////////////////////////
// get_current_user: fetch current logged-in user details
/////////////////////////
if($action === 'get_current_user'){
    $username = isset($_GET['username']) ? trim($_GET['username']) : '';
    if($username === ''){
        echo json_encode(['success'=>false,'message'=>'username required']); exit;
    }
    
    $stmt = $mysqli->prepare("SELECT name, email, mobile, role, client_id FROM users WHERE username = ? LIMIT 1");
    if(!$stmt){
        echo json_encode(['success'=>false,'message'=>'Prepare failed']); exit;
    }
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if($res && $res->num_rows > 0){
        $user = $res->fetch_assoc();
        $stmt->close();
        echo json_encode(['success'=>true,'user'=>$user]); exit;
    } else {
        $stmt->close();
        echo json_encode(['success'=>false,'message'=>'User not found']); exit;
    }
}

/////////////////////////
// auto_save: saves partial prescription data as draft
/////////////////////////
if($action === 'auto_save'){
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if(!$data || !isset($data['patient_id'])) {
        echo json_encode(['success'=>false,'message'=>'Invalid payload']); exit;
    }
    
    $patient_id = trim((string)$data['patient_id']);
    $client_id = resolve_effective_client_id($mysqli, $patient_id, $data['client_id'] ?? '');
    if($client_id === ''){
        echo json_encode(['success'=>false,'message'=>'Client ID missing']); exit;
    }
    
    $draft_prescription_id = isset($data['draft_prescription_id']) ? intval($data['draft_prescription_id']) : 0;
    $symptoms = isset($data['symptoms']) ? trim($data['symptoms']) : '';
    $follow_up_date = isset($data['follow_up_date']) && $data['follow_up_date'] !== '' ? $data['follow_up_date'] : null;
    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
    $blood_test_ids = isset($data['blood_test_ids']) && is_array($data['blood_test_ids']) ? $data['blood_test_ids'] : [];
    $blood_test_names = isset($data['blood_test_names']) && is_array($data['blood_test_names']) ? $data['blood_test_names'] : [];
    $blood_test_name = !empty($blood_test_names) ? implode(', ', $blood_test_names) : null;
    
    $mysqli->begin_transaction();
    try{
        // If draft_prescription_id exists, check if it's valid
        if($draft_prescription_id > 0){
            $check = $mysqli->prepare("SELECT id FROM prescriptions WHERE id = ? AND patient_id = ?");
            $check->bind_param('is', $draft_prescription_id, $patient_id);
            $check->execute();
            $result = $check->get_result();
            if($result->num_rows === 0){
                $draft_prescription_id = 0; // Invalid, create new
            }
            $check->close();
        }
        
        // Create new draft prescription if needed
        if($draft_prescription_id === 0){
            $stmt = $mysqli->prepare("INSERT INTO prescriptions (patient_id, created_at) VALUES (?, NOW())");
            if(!$stmt) throw new Exception('Prepare failed: '.$mysqli->error);
            $stmt->bind_param('s', $patient_id);
            if(!$stmt->execute()) throw new Exception('Execute failed: '.$stmt->error);
            $draft_prescription_id = $mysqli->insert_id;
            $stmt->close();
        }
        
        // Delete existing items for this draft
        $del = $mysqli->prepare("DELETE FROM prescription_items WHERE prescription_id = ?");
        if($del){
            $del->bind_param('i', $draft_prescription_id);
            $del->execute();
            $del->close();
        }
        
        // Insert items if any exist
        if(count($items) > 0){
            $stmt = $mysqli->prepare("INSERT INTO prescription_items (prescription_id, symptoms, client_id, patient_id, medicine_name, type, duration, times_of_day, before_after, notes, recommended_blood_test, follow_up_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            if(!$stmt) throw new Exception('Prepare failed (items): '.$mysqli->error);
            
            foreach($items as $it){
                $m = isset($it['medicine_name']) ? $it['medicine_name'] : '';
                $t = isset($it['type']) ? $it['type'] : '';
                $d = isset($it['duration']) ? $it['duration'] : '';
                $times = isset($it['times_of_day']) ? $it['times_of_day'] : '';
                $before_after = isset($it['before_after']) ? $it['before_after'] : null;
                $notes = isset($it['notes']) ? $it['notes'] : '';
                $follow_date = $follow_up_date;
                $recommended = $blood_test_name ?: null;
                
                $stmt->bind_param('isssssssssss', $draft_prescription_id, $symptoms, $client_id, $patient_id, $m, $t, $d, $times, $before_after, $notes, $recommended, $follow_date);
                $stmt->execute();
            }
            $stmt->close();
        } else if(!empty($symptoms)){
            // Save symptoms only if no items yet
            $stmt = $mysqli->prepare("INSERT INTO prescription_items (prescription_id, symptoms, client_id, patient_id, medicine_name, type, duration, times_of_day, before_after, notes, recommended_blood_test, follow_up_date) VALUES (?,?,?,?,'','','','','','',?,?)");
            if($stmt){
                $recommended = $blood_test_name ?: null;
                $stmt->bind_param('isssss', $draft_prescription_id, $symptoms, $client_id, $patient_id, $recommended, $follow_up_date);
                $stmt->execute();
                $stmt->close();
            }
        }
        
        $mysqli->commit();
        echo json_encode(['success'=>true,'draft_prescription_id'=>$draft_prescription_id]);
        exit;
        
    } catch(Exception $ex){
        $mysqli->rollback();
        echo json_encode(['success'=>false,'message'=>'Auto-save failed: '.$ex->getMessage()]);
        exit;
    }
}

/*
 Save a new prescription:
 Request expected JSON:
 {
   patient_id, client_id, symptoms, follow_up_date,
   items: [ { medicine_name, type, duration, times_of_day, before_after, notes, follow_up_date(optional) }, ... ],
   blood_test_id, blood_test_name
 }
 Inserts:
  - prescriptions (patient_id, created_at)
  - prescription_items (prescription_id, symptoms, client_id, patient_id, medicine_name, type, duration, times_of_day, before_after, notes, recommended_blood_test, follow_up_date)
*/
if($action === 'save'){
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if(!$data || !isset($data['patient_id']) || !isset($data['items']) || !is_array($data['items'])) {
        echo json_encode(['success'=>false,'message'=>'Invalid payload']); exit;
    }
    $patient_id = trim((string)$data['patient_id']);
    $client_id = resolve_effective_client_id($mysqli, $patient_id, $data['client_id'] ?? '');
    if($client_id === ''){
        echo json_encode(['success'=>false,'message'=>'Client ID missing for this prescription']); exit;
    }
    $items = $data['items'];
    // Handle multiple blood tests
    $blood_test_ids = isset($data['blood_test_ids']) && is_array($data['blood_test_ids']) ? $data['blood_test_ids'] : (isset($data['blood_test_id']) ? [$data['blood_test_id']] : []);
    $blood_test_names = isset($data['blood_test_names']) && is_array($data['blood_test_names']) ? $data['blood_test_names'] : (isset($data['blood_test_name']) ? [$data['blood_test_name']] : []);
    // Join multiple blood test names with comma
    $blood_test_name = !empty($blood_test_names) ? implode(', ', $blood_test_names) : null;
    $blood_test_id = !empty($blood_test_ids) ? $blood_test_ids[0] : null; // Use first ID for backward compatibility
    $symptoms = isset($data['symptoms']) ? $data['symptoms'] : '';
    $follow_up_date_top = isset($data['follow_up_date']) && $data['follow_up_date'] !== '' ? $data['follow_up_date'] : null;
    $draft_prescription_id = isset($data['draft_prescription_id']) ? intval($data['draft_prescription_id']) : 0;

    // Basic server-side validations
    if(trim($symptoms) === ''){
        echo json_encode(['success'=>false,'message'=>'Symptoms is required']); exit;
    }
    if(count($items) === 0){
        echo json_encode(['success'=>false,'message'=>'No medicines provided']); exit;
    }

    // Begin transaction
    $mysqli->begin_transaction();
    try{
        // Check if we should reuse the draft prescription
        $pres_id = 0;
        error_log("========== SAVE ACTION ==========");
        error_log("save: Received draft_prescription_id from frontend: " . $draft_prescription_id);
        error_log("save: Patient ID: " . $patient_id);
        error_log("save: Number of items: " . count($items));
        
        if($draft_prescription_id > 0){
            error_log("save: Checking if draft ID " . $draft_prescription_id . " exists in DB...");
            $check = $mysqli->prepare("SELECT id FROM prescriptions WHERE id = ? AND patient_id = ?");
            $check->bind_param('is', $draft_prescription_id, $patient_id);
            $check->execute();
            $result = $check->get_result();
            if($result->num_rows > 0){
                // Draft exists, reuse it
                $pres_id = $draft_prescription_id;
                error_log("save: ✓ Found valid draft, REUSING prescription ID: " . $pres_id);
            } else {
                error_log("save: ✗ Draft ID " . $draft_prescription_id . " NOT found in DB or patient_id mismatch");
            }
            $check->close();
        } else {
            error_log("save: No draft_prescription_id provided (is 0 or null)");
        }
        
        // Create new prescription only if no valid draft exists
        if($pres_id === 0){
            error_log("save: Creating BRAND NEW prescription...");
            $stmt = $mysqli->prepare("INSERT INTO prescriptions (patient_id, created_at) VALUES (?, NOW())");
            if(!$stmt) throw new Exception('Prepare failed: '.$mysqli->error);
            $stmt->bind_param('s', $patient_id);
            if(!$stmt->execute()) throw new Exception('Execute failed (prescriptions): '.$stmt->error);
            $pres_id = $mysqli->insert_id;
            $stmt->close();
            error_log("save: ✓ Created BRAND NEW prescription ID: " . $pres_id);
        }
        error_log("save: Final prescription ID being used: " . $pres_id);
        error_log("================================");
        
        // Delete existing items for this prescription (in case of draft update)
        $del = $mysqli->prepare("DELETE FROM prescription_items WHERE prescription_id = ?");
        if($del){
            $del->bind_param('i', $pres_id);
            $del->execute();
            $del->close();
        }

        // prepare insert for items
        // Order: prescription_id, symptoms, client_id, patient_id, medicine_name, type, duration, times_of_day, before_after, notes, recommended_blood_test, follow_up_date
        $stmt = $mysqli->prepare("INSERT INTO prescription_items (prescription_id, symptoms, client_id, patient_id, medicine_name, type, duration, times_of_day, before_after, notes, recommended_blood_test, follow_up_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        if(!$stmt) throw new Exception('Prepare failed (items): '.$mysqli->error);

        foreach($items as $idx => $it){
            $m = isset($it['medicine_name']) ? $it['medicine_name'] : (isset($it['name']) ? $it['name'] : '');
            $t = isset($it['type']) ? $it['type'] : '';
            $d = isset($it['duration']) ? $it['duration'] : '';
            $times = isset($it['times_of_day']) ? $it['times_of_day'] : (isset($it['times']) ? $it['times'] : '');
            $before_after = isset($it['before_after']) ? $it['before_after'] : null;
            $notes = isset($it['notes']) ? $it['notes'] : '';
            // if item includes follow_up_date use it, otherwise use top-level follow_up_date
            $follow_date = null;
            if(isset($it['follow_up_date']) && $it['follow_up_date'] !== '') $follow_date = $it['follow_up_date'];
            else $follow_date = $follow_up_date_top;

            $recommended = $blood_test_name ?: null;

            // server-side rule: if times_of_day provided (non-empty), before_after is required
            if(trim($times) !== '' && (is_null($before_after) || trim($before_after) === '')){
                throw new Exception('Before/After selection is required when time of day is selected (item '.($idx+1).')');
            }

            // bind params: i pres_id, s symptoms, s client_id, s patient_id, s medicine_name, s type, s duration, s times, s before_after, s notes, s recommended, s follow
            if(!$stmt->bind_param('isssssssssss', $pres_id, $symptoms, $client_id, $patient_id, $m, $t, $d, $times, $before_after, $notes, $recommended, $follow_date)){
                throw new Exception('Bind failed: '.$stmt->error);
            }
            if(!$stmt->execute()){
                throw new Exception('Execute failed (item): '.$stmt->error);
            }
        }
        $stmt->close();

        // update prescriptions row with blood_test_id if provided and column exists
        if($blood_test_id !== null){
            $colRes = $mysqli->query("SHOW COLUMNS FROM `prescriptions` LIKE 'blood_test_id'");
            if($colRes && $colRes->num_rows){
                $up = $mysqli->prepare("UPDATE prescriptions SET blood_test_id = ? WHERE id = ?");
                if($up){
                    $up->bind_param('ii', $blood_test_id, $pres_id);
                    $up->execute();
                    $up->close();
                }
            }
        }

        // ===== Added: insert appointment rows =====
        ensure_appointments_client_id_column_supports_text($mysqli);
        
        // Fetch doctor's name from users table using client_id
        $doctorName = '';
        if(!empty($client_id)){
            $u_stmt = $mysqli->prepare("SELECT name FROM users WHERE client_id = ? LIMIT 1");
            if($u_stmt){
                $u_stmt->bind_param('s', $client_id);
                if($u_stmt->execute()){
                    $u_res = $u_stmt->get_result();
                    if($u_res){
                        $u_row = $u_res->fetch_assoc();
                        if($u_row && isset($u_row['name'])) $doctorName = $u_row['name'];
                    }
                } else {
                    error_log("users lookup execute failed: (" . $u_stmt->errno . ") " . $u_stmt->error);
                }
                $u_stmt->close();
            } else {
                error_log("users prepare failed: " . $mysqli->error);
            }
        }

        // 1. Create appointment for CURRENT prescription date (completed visit)
        // This represents the visit that just happened where the prescription was created
        $current_date = date('Y-m-d'); // Today's date
        $appt_stmt = $mysqli->prepare("INSERT INTO appointments (client_id, patient_id, doctor, date, time, notes, created_at) VALUES (?,?,?,?,?,?,NOW())");
        if($appt_stmt){
            $appt_time = date('H:i:s'); // Current time
            $notes_current = $symptoms . ' (Prescription created)';
            $appt_stmt->bind_param('ssssss', $client_id, $patient_id, $doctorName, $current_date, $appt_time, $notes_current);
            $appt_stmt->execute();
            if($appt_stmt->errno){
                error_log("Current appointment insert failed: (" . $appt_stmt->errno . ") " . $appt_stmt->error);
            }
            $appt_stmt->close();
        } else {
            error_log("Current appointment prepare failed: " . $mysqli->error);
        }

        // 2. Create appointment for FOLLOW-UP date (if provided) - future appointment
        if(!empty($follow_up_date_top)){
            $appt_stmt = $mysqli->prepare("INSERT INTO appointments (client_id, patient_id, doctor, date, time, notes, created_at) VALUES (?,?,?,?,?,?,NOW())");
            if($appt_stmt){
                $appt_time = null; // no time provided for follow-up
                $notes_followup = $symptoms . ' (Follow-up appointment)';
                $appt_stmt->bind_param('ssssss', $client_id, $patient_id, $doctorName, $follow_up_date_top, $appt_time, $notes_followup);
                $appt_stmt->execute();
                if($appt_stmt->errno){
                    error_log("Follow-up appointment insert failed: (" . $appt_stmt->errno . ") " . $appt_stmt->error);
                }
                $appt_stmt->close();
            } else {
                error_log("Follow-up appointment prepare failed: " . $mysqli->error);
            }
        }
        // ===== end appointment insert =====

        $mysqli->commit();
        echo json_encode(['success'=>true,'prescription_id'=>$pres_id]);
        exit;

    } catch(Exception $ex){
        $mysqli->rollback();
        echo json_encode(['success'=>false,'message'=>'Save failed: '.$ex->getMessage()]);
        exit;
    }
}

/*
 Fetch all prescription items for a patient.
 Returns items array where each item includes prescription_id and prescription created_at for context.
*/
if($action === 'get_prescriptions'){
    $patient_id = $_GET['patient_id'] ?? '';
    if(!$patient_id){ echo json_encode(['success'=>false,'message'=>'patient_id required']); exit; }

    // Check if prescriptions has blood_test_id column
    $hasBloodCol = false;
    $colRes = $mysqli->query("SHOW COLUMNS FROM `prescriptions` LIKE 'blood_test_id'");
    if($colRes && $colRes->num_rows) $hasBloodCol = true;

    if($hasBloodCol){
        $sql = "SELECT pi.id, pi.prescription_id, p.created_at, pi.client_id, pi.patient_id, pi.symptoms, pi.medicine_name, pi.type, pi.duration, pi.times_of_day, pi.before_after, pi.notes, pi.recommended_blood_test, pi.follow_up_date, bt.name AS blood_test_name
                FROM prescription_items pi
                LEFT JOIN prescriptions p ON p.id = pi.prescription_id
                LEFT JOIN blood_tests bt ON p.blood_test_id = bt.id
                WHERE pi.patient_id = ?
                ORDER BY p.created_at DESC, pi.id DESC";
    } else {
        $sql = "SELECT pi.id, pi.prescription_id, p.created_at, pi.client_id, pi.patient_id, pi.symptoms, pi.medicine_name, pi.type, pi.duration, pi.times_of_day, pi.before_after, pi.notes, pi.recommended_blood_test, pi.follow_up_date
                FROM prescription_items pi
                LEFT JOIN prescriptions p ON p.id = pi.prescription_id
                WHERE pi.patient_id = ?
                ORDER BY p.created_at DESC, pi.id DESC";
    }

    $stmt = $mysqli->prepare($sql);
    if(!$stmt){ echo json_encode(['success'=>false,'message'=>'Prepare failed']); exit; }
    $stmt->bind_param('s', $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    while($row = $res->fetch_assoc()){
        $item = [
            'id' => $row['id'],
            'prescription_id' => $row['prescription_id'],
            'created_at' => $row['created_at'],
            'client_id' => $row['client_id'],
            'patient_id' => $row['patient_id'],
            'symptoms' => $row['symptoms'],
            'medicine_name' => $row['medicine_name'],
            'type' => $row['type'],
            'duration' => $row['duration'],
            'times_of_day' => $row['times_of_day'],
            'before_after' => $row['before_after'],
            'notes' => $row['notes'],
            'recommended_blood_test' => $row['recommended_blood_test'],
            'follow_up_date' => $row['follow_up_date']
        ];
        if(isset($row['blood_test_name'])) $item['blood_test_name'] = $row['blood_test_name'];
        $items[] = $item;
    }
    $stmt->close();
    echo json_encode(['success'=>true,'items'=>$items]);
    exit;
}

/*
 Print prescription (render printable HTML for a given prescription_id)
*/
if($action === 'print_prescription'){
    $pres_id = isset($_GET['prescription_id']) ? intval($_GET['prescription_id']) : 0;
    if(!$pres_id){ echo "Invalid prescription id"; exit; }

    // fetch prescription basic info (patient_id, created_at, blood_test_id if exists)
    $hasBloodCol = false;
    $colRes = $mysqli->query("SHOW COLUMNS FROM `prescriptions` LIKE 'blood_test_id'");
    if($colRes && $colRes->num_rows) $hasBloodCol = true;

    if($hasBloodCol){
      $pstmt = $mysqli->prepare("SELECT id, patient_id, created_at, blood_test_id FROM prescriptions WHERE id = ?");
    } else {
      $pstmt = $mysqli->prepare("SELECT id, patient_id, created_at FROM prescriptions WHERE id = ?");
    }
    if(!$pstmt){ echo "Prepare failed"; exit; }
    $pstmt->bind_param('i', $pres_id);
    $pstmt->execute();
    $presRes = $pstmt->get_result();
    $pres = $presRes->fetch_assoc();
    $pstmt->close();
    if(!$pres){ echo "Prescription not found"; exit; }

    // fetch patient details
    $patient = ['patient_name'=>'','mobile'=>'','age'=>'','weight'=>''];
    $pst = $mysqli->prepare("SELECT patient_name,mobile,age,weight FROM patient_list WHERE patient_id = ? LIMIT 1");
    if($pst){
      $pst->bind_param('s', $pres['patient_id']);
      $pst->execute();
      $pr = $pst->get_result()->fetch_assoc();
      if($pr) $patient = $pr;
      $pst->close();
    }

    // fetch blood test name if available
    $blood_test_name = '';
    if($hasBloodCol && !empty($pres['blood_test_id'])){
      $btst = $mysqli->prepare("SELECT name FROM blood_tests WHERE id = ? LIMIT 1");
      if($btst){
        $btst->bind_param('i', $pres['blood_test_id']);
        $btst->execute();
        $brow = $btst->get_result()->fetch_assoc();
        if($brow) $blood_test_name = $brow['name'];
        $btst->close();
      }
    }

    // fetch items (including symptoms, before_after and recommended_blood_test)
    $stmt = $mysqli->prepare("SELECT symptoms, medicine_name, type, duration, times_of_day, before_after, notes, recommended_blood_test, follow_up_date, client_id FROM prescription_items WHERE prescription_id = ? ORDER BY id ASC");
    if(!$stmt){ echo "Prepare failed (items)"; exit; }
    $stmt->bind_param('i', $pres_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    while($r = $res->fetch_assoc()) $items[] = $r;
    $stmt->close();

    // render printable HTML
    ?>
    <!doctype html>
    <html>
    <head>
      <meta charset="utf-8"/>
      <title>Prescription #<?php echo htmlspecialchars($pres_id); ?></title>
      <style>
        body{ font-family: Arial, Helvetica, sans-serif; padding:20px; color:#111 }
        h1{ font-size:20px; margin-bottom:4px }
        .meta{ margin-bottom:14px; color:#444 }
        table{ width:100%; border-collapse:collapse; margin-top:10px }
        th, td{ text-align:left; padding:8px; border:1px solid #ddd; }
        th{ background:#f7f7f7 }
        .small{ font-size:13px; color:#666 }
      </style>
    </head>
    <body>
      <h1>Prescription #<?php echo htmlspecialchars($pres_id); ?></h1>
      <div class="meta">
        <div><strong>Patient ID:</strong> <?php echo htmlspecialchars($pres['patient_id']); ?></div>
        <div><strong>Patient name:</strong> <?php echo htmlspecialchars($patient['patient_name']); ?></div>
        <div><strong>Mobile:</strong> <?php echo htmlspecialchars($patient['mobile']); ?></div>
        <div><strong>Age / Weight:</strong> <?php echo htmlspecialchars($patient['age']).' / '.htmlspecialchars($patient['weight']); ?></div>
        <div><strong>Date:</strong> <?php echo htmlspecialchars($pres['created_at']); ?></div>
        <?php if($blood_test_name){ ?>
          <div><strong>Recommended blood test:</strong> <?php echo htmlspecialchars($blood_test_name); ?></div>
        <?php } ?>
      </div>

      <table>
        <thead>
          <tr><th>#</th><th>Symptoms</th><th>Medicine</th><th>Type</th><th>Duration</th><th>Time</th><th>Before/After</th><th>Notes</th><th>Recommended Test</th><th>Follow-up</th><th>Client ID</th></tr>
        </thead>
        <tbody>
        <?php $n=1; foreach($items as $it){ ?>
          <tr>
            <td><?php echo $n++; ?></td>
            <td><?php echo htmlspecialchars($it['symptoms']); ?></td>
            <td><?php echo htmlspecialchars($it['medicine_name']); ?></td>
            <td><?php echo htmlspecialchars($it['type']); ?></td>
            <td><?php echo htmlspecialchars($it['duration']); ?></td>
            <td><?php echo htmlspecialchars($it['times_of_day']); ?></td>
            <td><?php echo htmlspecialchars($it['before_after']); ?></td>
            <td><?php echo htmlspecialchars($it['notes']); ?></td>
            <td><?php echo htmlspecialchars($it['recommended_blood_test']); ?></td>
            <td><?php echo htmlspecialchars($it['follow_up_date']); ?></td>
            <td><?php echo htmlspecialchars($it['client_id']); ?></td>
          </tr>
        <?php } ?>
        </tbody>
      </table>

      <script>
        // Auto-print and close after print
        window.onload = function(){
          window.print();
        };
      </script>
    </body>
    </html>
    <?php
    exit;
}

echo json_encode(['success'=>false,'message'=>'invalid action']);
$mysqli->close();