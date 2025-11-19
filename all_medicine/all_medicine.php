<?php
// all_medicine.php
header('Content-Type: application/json; charset=utf-8');
$DB_HOST='localhost'; $DB_USER='ruhanixl_doctorApp'; $DB_PASS='@aashi12345678@'; $DB_NAME='ruhanixl_doctorApp';
$mysqli = new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
if($mysqli->connect_errno){ echo json_encode(['success'=>false]); exit; }
$action = $_GET['action'] ?? 'list';
if($action === 'list'){
    $res = $mysqli->query("SELECT id,name,company,form,strength FROM medicines ORDER BY name");
    $a=[];
    while($r=$res->fetch_assoc()) $a[]=$r;
    echo json_encode(['success'=>true,'medicines'=>$a]); exit;
}
if($action === 'add'){
    $raw = file_get_contents('php://input'); $data = json_decode($raw,true);
    $name = trim($data['name'] ?? '');
    if(!$name) { echo json_encode(['success'=>false,'message'=>'name required']); exit; }
    $company = trim($data['company'] ?? '');
    $form = trim($data['form'] ?? '');
    $strength = trim($data['strength'] ?? '');
    $stmt = $mysqli->prepare("INSERT INTO medicines (name,company,form,strength) VALUES (?,?,?,?)");
    $stmt->bind_param('ssss',$name,$company,$form,$strength);
    if(!$stmt->execute()){ echo json_encode(['success'=>false,'message'=>$stmt->error]); exit;}
    echo json_encode(['success'=>true,'id'=>$mysqli->insert_id]); exit;
}
if($action === 'delete'){
    $id = intval($_GET['id'] ?? 0);
    if(!$id){ echo json_encode(['success'=>false]); exit; }
    $stmt = $mysqli->prepare("DELETE FROM medicines WHERE id=?");
    $stmt->bind_param('i',$id); $stmt->execute();
    echo json_encode(['success'=>true]); exit;
}
echo json_encode(['success'=>false,'message'=>'invalid']);
