<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$servername = "sql204.infinityfree.com";
$username = "if0_42022884";
$password = "PWp1pzwdti3jW"; // Your XAMPP password if any
$dbname = "if0_42022884_pasarkraft_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
