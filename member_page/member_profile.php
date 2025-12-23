<?php
session_start();
require_once "db_connection.php";

/* ===============================
   MEMBER AUTH CHECK
================================ */
if (!isset($_SESSION['member_id'])) {
    header("Location: member_login.php");
    exit;
}

$member_id = $_SESSION['member_id'];

/* ===============================
   FETCH MEMBER DETAILS
================================ */
$stmt = $conn->prepare("
    SELECT 
        member_id,
        full_name,
        email,
        phone,
        join_date,
        status,
        role,
        created_at
    FROM members
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Member record not found.");
}

$member = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Member Profile</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen flex">

<!-- SIDEBAR -->
<aside class="w-64 bg-gradient-to-b from-blue-700 to-blue-800 text-white p-6">
    <h2 class="text-xl font-bold mb-8">Chama Member</h2>

    <nav class="space-y-4 text-sm">
        <a href="member_dashboard.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Dashboard</a>
        <a href="member_profile.php" class="block bg-blue-600 px-4 py-2 rounded">My Profile</a>
        <a href="member_contribution.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Contributions</a>
        <a href="member_loan.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Loans</a>
        <a href="member_fines.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Fines</a>
        <a href="change_password.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Change Password</a>
        <a href="member_logout.php" class="block text-red-200 hover:bg-red-600 px-4 py-2 rounded">Logout</a>
    </nav>
</aside>

<!-- MAIN -->
<main class="flex-1 p-8">

    <!-- HEADER -->
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800">My Profile</h1>
        <p class="text-gray-500">View your personal and membership details</p>
    </div>

    <!-- PROFILE CARD -->
    <div class="bg-white rounded-xl shadow p-8 max-w-3xl">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <div>
                <label class="text-sm text-gray-500">Full Name</label>
                <p class="font-medium text-gray-800"><?= htmlspecialchars($member['full_name']) ?></p>
            </div>

            <div>
                <label class="text-sm text-gray-500">Member ID</label>
                <p class="font-medium text-gray-800"><?= htmlspecialchars($member['member_id']) ?></p>
            </div>

            <div>
                <label class="text-sm text-gray-500">Email</label>
                <p class="font-medium text-gray-800"><?= htmlspecialchars($member['email']) ?></p>
            </div>

            <div>
                <label class="text-sm text-gray-500">Phone</label>
                <p class="font-medium text-gray-800"><?= htmlspecialchars($member['phone'] ?? '-') ?></p>
            </div>

            <div>
                <label class="text-sm text-gray-500">Join Date</label>
                <p class="font-medium text-gray-800">
                    <?= $member['join_date'] ? date("d M Y", strtotime($member['join_date'])) : '-' ?>
                </p>
            </div>

            <div>
                <label class="text-sm text-gray-500">Account Status</label>
                <span class="inline-block px-3 py-1 rounded text-xs font-semibold
                    <?= $member['status'] === 'active'
                        ? 'bg-green-100 text-green-700'
                        : 'bg-red-100 text-red-700' ?>">
                    <?= ucfirst($member['status']) ?>
                </span>
            </div>

            <div>
                <label class="text-sm text-gray-500">Role</label>
                <p class="font-medium text-gray-800"><?= ucfirst($member['role']) ?></p>
            </div>

            <div>
                <label class="text-sm text-gray-500">Account Created</label>
                <p class="font-medium text-gray-800">
                    <?= date("d M Y, H:i", strtotime($member['created_at'])) ?>
                </p>
            </div>

        </div>

        <!-- INFO NOTE -->
        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-700">
            If any of your details are incorrect, please contact the chama administrator.
        </div>

    </div>

</main>

</body>
</html>
