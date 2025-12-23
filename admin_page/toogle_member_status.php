<?php
session_start();
require_once "db_connection.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../admin_login.php");
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

if ($id <= 0) {
    header("Location: admin_member.php");
    exit;
}

if ($action === 'suspend') {
    $conn->query("UPDATE members SET status='suspended' WHERE id=$id");
}

if ($action === 'activate') {
    $conn->query("UPDATE members SET status='active' WHERE id=$id");
}

header("Location: admin_member.php");
exit;
