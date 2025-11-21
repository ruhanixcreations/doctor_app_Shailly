<?php
// blood_tests.php
header('Content-Type: application/json; charset=utf-8');
$DB_HOST='localhost'; $DB_USER='ruhanixl_doctorApp'; $DB_PASS='@aashi12345678@'; $DB_NAME='ruhanixl_doctorApp';
$mysqli = new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
if($mysqli->connect_errno){ echo json_encode(['success'=>false]); exit; }
$action = $_GET['action'] ?? 'list';
if($action === 'list'){
    $res = $mysqli->query("SELECT id,name,price FROM blood_tests ORDER BY name");
    $a=[]; while($r=$res->fetch_assoc()) $a[]=$r;
    echo json_encode(['success'=>true,'tests'=>$a]); exit;
}
if($action === 'add'){
    $raw = file_get_contents('php://input'); $d = json_decode($raw,true);
    $name = trim($d['name'] ?? ''); if(!$name){ echo json_encode(['success'=>false]); exit; }
    $price = trim($d['price'] ?? '');
    $stmt = $mysqli->prepare("INSERT INTO blood_tests (name,price) VALUES (?,?)"); $stmt->bind_param('ss',$name,$price); $stmt->execute();
    echo json_encode(['success'=>true,'id'=>$mysqli->insert_id]); exit;
}
if($action === 'update'){
    $raw = file_get_contents('php://input'); $d = json_decode($raw,true);
    $id = intval($d['id'] ?? 0); if(!$id){ echo json_encode(['success'=>false,'message'=>'invalid id']); exit; }
    $name = trim($d['name'] ?? ''); if(!$name){ echo json_encode(['success'=>false,'message'=>'name required']); exit; }
    $price = trim($d['price'] ?? '');
    $stmt = $mysqli->prepare("UPDATE blood_tests SET name=?, price=? WHERE id=?"); $stmt->bind_param('ssi',$name,$price,$id); $stmt->execute();
    echo json_encode(['success'=>true]); exit;
}
if($action === 'delete'){
    $id = intval($_GET['id'] ?? 0); if(!$id){ echo json_encode(['success'=>false]); exit; }
    $stmt = $mysqli->prepare("DELETE FROM blood_tests WHERE id=?"); $stmt->bind_param('i',$id); $stmt->execute();
    echo json_encode(['success'=>true]); exit;
}
echo json_encode(['success'=>false,'message'=>'invalid']);
