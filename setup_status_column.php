<?php
// setup_status_column.php - Run this once to add status column to users table
header('Content-Type: application/json; charset=utf-8');

$DB_HOST='localhost'; 
$DB_USER='ruhanixl_doctorApp'; 
$DB_PASS='@aashi12345678@'; 
$DB_NAME='ruhanixl_doctorApp';

$mysqli = new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
if($mysqli->connect_errno){
    echo json_encode(['success'=>false,'message'=>'DB connection failed: ' . $mysqli->connect_error]);
    exit;
}

// Check if status column exists
$result = $mysqli->query("SHOW COLUMNS FROM users LIKE 'status'");
if($result->num_rows === 0){
    // Column doesn't exist, add it
    $alterQuery = "ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'active' AFTER role";
    if($mysqli->query($alterQuery)){
        echo json_encode(['success'=>true,'message'=>'Status column added successfully']);
    } else {
        echo json_encode(['success'=>false,'message'=>'Failed to add status column: ' . $mysqli->error]);
    }
} else {
    // Column exists, update NULL values to 'active'
    $updateQuery = "UPDATE users SET status = 'active' WHERE status IS NULL OR status = ''";
    if($mysqli->query($updateQuery)){
        $affected = $mysqli->affected_rows;
        echo json_encode(['success'=>true,'message'=>"Status column already exists. Updated $affected NULL/empty values to 'active'"]);
    } else {
        echo json_encode(['success'=>false,'message'=>'Column exists but update failed: ' . $mysqli->error]);
    }
}

$mysqli->close();
?>
