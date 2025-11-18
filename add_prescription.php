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
    $blood_test_id = isset($data['blood_test_id']) ? $data['blood_test_id'] : null;
    $blood_test_name = isset($data['blood_test_name']) ? $data['blood_test_name'] : null;
    $symptoms = isset($data['symptoms']) ? $data['symptoms'] : '';
    $follow_up_date_top = isset($data['follow_up_date']) && $data['follow_up_date'] !== '' ? $data['follow_up_date'] : null;

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
        $stmt = $mysqli->prepare("INSERT INTO prescriptions (patient_id, created_at) VALUES (?, NOW())");
        if(!$stmt) throw new Exception('Prepare failed: '.$mysqli->error);
        $stmt->bind_param('s', $patient_id);
        if(!$stmt->execute()) throw new Exception('Execute failed (prescriptions): '.$stmt->error);
        $pres_id = $mysqli->insert_id;
        $stmt->close();

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

        // ===== Added: insert an appointment row (if follow-up date is provided) =====
        // Mapping:
        //  - use top-level follow_up_date as appointment.date
        //  - use symptoms as appointment.notes
        //  - find doctor name from users table by client_id and store it in appointments.doctor
        //  - leave time NULL / empty
        if(!empty($follow_up_date_top)){
            ensure_appointments_client_id_column_supports_text($mysqli);
            // attempt to fetch doctor's name from users table using client_id
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

            $appt_stmt = $mysqli->prepare("INSERT INTO appointments (client_id, patient_id, doctor, date, time, notes, created_at) VALUES (?,?,?,?,?,?,NOW())");
            if($appt_stmt){
                $appt_time = null;          // no time provided (will be inserted as empty/null)
                $notes_appt = $symptoms;
                // bind as strings; null/empty time is acceptable
                $appt_stmt->bind_param('ssssss', $client_id, $patient_id, $doctorName, $follow_up_date_top, $appt_time, $notes_appt);
                $appt_stmt->execute();
                if($appt_stmt->errno){
                    error_log("appointments insert failed: (" . $appt_stmt->errno . ") " . $appt_stmt->error);
                }
                $appt_stmt->close();
            } else {
                error_log("appointments prepare failed: " . $mysqli->error);
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
