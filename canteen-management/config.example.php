<?php
// config.example.php
// Copy this file, rename the copy to config.php, and fill in YOUR
// own local database name/credentials. config.php itself is not
// tracked by Git - everyone's stays local to their own machine.

$host = "localhost";
$dbname = "your_database_name_here";
$username = "root";
$password = "";

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>