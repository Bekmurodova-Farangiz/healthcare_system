<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "healthcare_v2";

$conn = mysqli_connect($servername, $username, $password, $database);

if (!$conn) {
    die("Connection failed");
}

mysqli_set_charset($conn, "utf8mb4");
?>
