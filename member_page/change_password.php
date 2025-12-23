<?php
session_start();
require_once "db_connection.php";

/* ==========================
   ACCESS CONTROL
========================== */
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: member_login.php");
    exit;
}

if (!isset($_SESSION['force_password_change'])) {
    header("Location: member_dashboard.php");
    exit;
}

$error = "";
$success = "";

/* ==========================
   HANDLE FORM SUBMIT
========================== */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $new_password     = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if ($new_password === '' || $confirm_password === '') {
        $error = "All fields are required.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {

        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("
            UPDATE members
            SET password = ?, must_change_password = 0
            WHERE id = ?
        ");
        $stmt->bind_param("si", $password_hash, $_SESSION['member_id']);

        if ($stmt->execute()) {
            unset($_SESSION['force_password_change']);
            header("Location: member_dashboard.php");
            exit;
        } else {
            $error = "Failed to update password. Try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Change Password</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-gray-100 flex items-center justify-center">

<div class="bg-white shadow-xl rounded-xl w-full max-w-md p-8">

    <h2 class="text-2xl font-semibold text-center mb-4">
        Change Your Password
    </h2>

    <p class="text-sm text-gray-600 text-center mb-6">
        This is your first login. Please set a new password.
    </p>

    <?php if ($error): ?>
        <div class="bg-red-50 text-red-600 px-4 py-3 rounded mb-4">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">

        <div>
            <label class="block text-sm mb-1">New Password</label>
            <input type="password" name="new_password"
                   class="w-full border rounded px-4 py-2"
                   required>
        </div>

        <div>
            <label class="block text-sm mb-1">Confirm Password</label>
            <input type="password" name="confirm_password"
                   class="w-full border rounded px-4 py-2"
                   required>
        </div>

        <button type="submit"
                class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700">
            Update Password
        </button>

    </form>

</div>

</body>
</html>
