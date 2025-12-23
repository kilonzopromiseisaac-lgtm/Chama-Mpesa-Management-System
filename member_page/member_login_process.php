<?php
session_start();
require_once "db_connection.php";

header('Content-Type: application/json');

// 1. Check request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// 2. Read inputs
$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Email and password required'
    ]);
    exit;
}

// 3. Check DB connection
if (!$conn) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

// 4. Fetch member
$stmt = $conn->prepare("SELECT id, email, password FROM members WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    echo json_encode([
        'success' => false,
        'message' => 'Email not found'
    ]);
    exit;
}

$member = $result->fetch_assoc();
error_log("INPUT PASSWORD: " . $password);
error_log("DB HASH: " . $member['password']);


// 5. Verify password
if (!password_verify($password, $member['password'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Password does not match'
    ]);
    exit;
}

// 6. Success → set session
$_SESSION['member_id'] = $member['id'];

echo json_encode([
    'success' => true,
    'redirect' => 'member_dashboard.php'
]);
exit;
