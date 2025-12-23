<?php
session_start();
require_once "db_connection.php";

if (!isset($_SESSION['admin_id'])) {
    exit("Unauthorized");
}

$app_id = $_POST['application_id'] ?? null;
if (!$app_id) exit("Invalid request");

/* FETCH APPLICATION */
$stmt = $conn->prepare("SELECT * FROM loan_applications WHERE id=? AND status='pending'");
$stmt->bind_param("i", $app_id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();

if (!$app) exit("Invalid application");

/* CALCULATE BALANCE */
$total_interest = ($app['amount'] * $app['interest_rate']) / 100;
$balance = $app['amount'] + $total_interest;

/* NEXT REPAYMENT */
$nextDate = $app['repayment_cycle'] === 'weekly'
    ? date('Y-m-d', strtotime('+7 days'))
    : date('Y-m-d', strtotime('+1 month'));

/* INSERT INTO LOANS */
$insert = $conn->prepare("
    INSERT INTO loans
    (member_id, loan_application_id, amount, balance, interest_rate, repayment_cycle, next_repayment_date, status, date)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'active', CURDATE())
");

$insert->bind_param(
    "iidddss",
    $app['member_id'],
    $app['id'],
    $app['amount'],
    $balance,
    $app['interest_rate'],
    $app['repayment_cycle'],
    $nextDate
);
$insert->execute();

/* UPDATE APPLICATION STATUS */
$update = $conn->prepare("UPDATE loan_applications SET status='approved' WHERE id=?");
$update->bind_param("i", $app_id);
$update->execute();

/* NOTIFICATION */
$msg = "Your loan of KES " . number_format($app['amount']) . " has been approved.";
$notify = $conn->prepare("
    INSERT INTO notifications (member_id, type, message)
    VALUES (?, 'loan', ?)
");
$notify->bind_param("is", $app['member_id'], $msg);
$notify->execute();

header("Location: admin_loans.php");
