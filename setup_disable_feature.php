<?php
// Setup script to add is_disabled column and create user_sessions table
// Run this once to set up the database for the disable/enable feature

$DB_HOST='localhost'; 
$DB_USER='ruhanixl_doctorApp'; 
$DB_PASS='@aashi12345678@'; 
$DB_NAME='ruhanixl_doctorApp';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if($mysqli->connect_errno){
    die("Database connection failed: " . $mysqli->connect_error);
}

echo "Setting up disable/enable feature...\n";

// Add is_disabled column to users table if it doesn't exist
$checkColumn = $mysqli->query("SHOW COLUMNS FROM users LIKE 'is_disabled'");
if($checkColumn->num_rows == 0) {
    $mysqli->query("ALTER TABLE users ADD COLUMN is_disabled TINYINT(1) DEFAULT 0 AFTER role");
    echo "✓ Added is_disabled column to users table\n";
} else {
    echo "✓ is_disabled column already exists\n";
}

// Create user_sessions table to track active sessions
$createSessionsTable = "CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_session (session_id),
    KEY idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if($mysqli->query($createSessionsTable)) {
    echo "✓ user_sessions table created/verified\n";
} else {
    echo "Error creating user_sessions table: " . $mysqli->error . "\n";
}

echo "\nSetup complete! You can now use the disable/enable feature.\n";
echo "Remember to delete this file after running it once.\n";

$mysqli->close();
?>
