<?php
// all_medicine.php
header('Content-Type: application/json; charset=utf-8');

// Database credentials
$DB_HOST='localhost';
$DB_USER='ruhanixl_doctorApp';
$DB_PASS='@aashi12345678@';
$DB_NAME='ruhanixl_doctorApp';

$mysqli = new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
if($mysqli->connect_errno){
    echo json_encode(['success'=>false,'message'=>'DB connection failed']);
    exit;
}

// Action
$action = $_GET['action'] ?? 'list';

if($action === 'list'){
    $res = $mysqli->query("SELECT id,name,company,form,strength,unit FROM medicines ORDER BY name");
    $a=[];
    while($r=$res->fetch_assoc()) $a[]=$r;
    echo json_encode(['success'=>true,'medicines'=>$a]);
    exit;
}

if($action === 'add'){
    $raw = file_get_contents('php://input');
    $data = json_decode($raw,true);

    $name = trim($data['name'] ?? '');
    if(!$name){ echo json_encode(['success'=>false,'message'=>'Name required']); exit; }

    $company = trim($data['company'] ?? '');
    $form = trim($data['form'] ?? '');
    $strength = trim($data['strength'] ?? '');
    $unit = trim($data['unit'] ?? '');

    $stmt = $mysqli->prepare("INSERT INTO medicines (name, company, form, strength, unit) VALUES (?,?,?,?,?)");
    $stmt->bind_param('sssss', $name, $company, $form, $strength, $unit);

    if(!$stmt->execute()){
        echo json_encode(['success'=>false,'message'=>$stmt->error]);
        exit;
    }

    echo json_encode(['success'=>true,'id'=>$mysqli->insert_id]);
    exit;
}

if($action === 'update'){
    $raw = file_get_contents('php://input');
    $data = json_decode($raw,true);

    $id = intval($data['id'] ?? 0);
    if(!$id){ echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit; }

    $name = trim($data['name'] ?? '');
    if(!$name){ echo json_encode(['success'=>false,'message'=>'Name required']); exit; }

    $company = trim($data['company'] ?? '');
    $form = trim($data['form'] ?? '');
    $strength = trim($data['strength'] ?? '');
    $unit = trim($data['unit'] ?? '');

    $stmt = $mysqli->prepare("UPDATE medicines SET name=?, company=?, form=?, strength=?, unit=? WHERE id=?");
    $stmt->bind_param('sssssi', $name, $company, $form, $strength, $unit, $id);

    if(!$stmt->execute()){
        echo json_encode(['success'=>false,'message'=>$stmt->error]);
        exit;
    }

    echo json_encode(['success'=>true]);
    exit;
}

if($action === 'delete'){
    $id = intval($_GET['id'] ?? 0);
    if(!$id){ echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit; }

    $stmt = $mysqli->prepare("DELETE FROM medicines WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    echo json_encode(['success'=>true]);
    exit;
}

echo json_encode(['success'=>false,'message'=>'Invalid action']);
exit;
?>
