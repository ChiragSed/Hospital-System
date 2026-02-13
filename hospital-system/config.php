<?php
$host = "127.0.0.1";
$username = "root";
$password = "";
$database = "hospital_system";

/*
If your MySQL runs on port 3307 (common when you changed port),
set $port = 3307, else keep 3306.
*/
$port = 3306;

$conn = new mysqli($host, $username, $password, $database, $port);

if ($conn->connect_error) {
    die("Connection Failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>
