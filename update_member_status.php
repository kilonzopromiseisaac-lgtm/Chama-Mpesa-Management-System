<?php
session_start();
require_once "db_connection.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$member_id = $_GET['member_id'] ?? '';
$action    = $_GET['action'] ?? '';

if ($member_id === '' || !in_array($action, ['activate', 'suspend'])) {
    header("Location: admin_member.php");
    exit;
}

$newStatus = ($action === 'activate') ? 'active' : 'suspended';

$stmt = $conn->prepare("
    UPDATE members 
    SET status = ? 
    WHERE member_id = ?
");
$stmt->bind_param("ss", $newStatus, $member_id);
$stmt->execute();

header("Location: admin_member.php");
exit;
