<?php
$servername = "localhost"; // Usually localhost for XAMPP
$username = "root";        // Default XAMPP username
$password = "";            // Default XAMPP password is empty
$dbname = "chama"; // Replace with your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>