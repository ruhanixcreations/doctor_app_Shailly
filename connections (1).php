<?php
$servername = "localhost";
$username = "ruhanixl_doctorApp";
$password = "@aashi12345678@";
$database = "ruhanixl_doctorApp";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>
