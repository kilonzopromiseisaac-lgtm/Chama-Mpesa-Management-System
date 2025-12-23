<?php
require_once "db_connection.php";

// CHANGE THESE DETAILS
$adminName = "System Admin";
$adminEmail = "admin@chama.com";
$plainPassword = "Admin@123"; // temporary password

$hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

// Insert admin
$stmt = $conn->prepare("
    INSERT INTO admins (full_name, email, password, role)
    VALUES (?, ?, ?, 'super_admin')
");
$stmt->bind_param("sss", $adminName, $adminEmail, $hashedPassword);

if ($stmt->execute()) {
    echo "Admin created successfully.<br>";
    echo "Email: $adminEmail <br>";
    echo "Password: $plainPassword <br>";
} else {
    echo "Error: " . $stmt->error;
}
