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

$member_id   = (int) $_SESSION['member_id']; // numeric id
$member_name = $_SESSION['full_name'] ?? 'Member';

/* ===============================
   1. CONTRIBUTIONS SUMMARY
   - Safest possible: sum all contribution amounts for this member
================================ */
$total_contributed = 0;
$contrib_result = $conn->query("
    SELECT COALESCE(SUM(amount), 0) AS total_paid
    FROM contributions 
    WHERE member_id = $member_id
");

if ($contrib_result) {
    $row = $contrib_result->fetch_assoc();
    $total_contributed = $row['total_paid'];
}

/* ===============================
   2. LOAN STATUS & BALANCE
================================ */
$outstanding_balance = 0;
$next_repayment = null;
$loan_status_display = "No Active Loan";

$loan_result = $conn->query("
    SELECT balance, next_repayment_date
    FROM loans 
    WHERE member_id = $member_id AND status = 'active'
    ORDER BY date DESC LIMIT 1
");

if ($loan_result && $loan_result->num_rows > 0) {
    $loan = $loan_result->fetch_assoc();
    $outstanding_balance = $loan['balance'];
    $next_repayment = $loan['next_repayment_date'];
    $loan_status_display = "Active Loan";
} else {
    // Check latest application
    $app_result = $conn->query("
        SELECT status FROM loan_applications 
        WHERE member_id = $member_id 
        ORDER BY application_date DESC LIMIT 1
    ");
    if ($app_result && $app_result->num_rows > 0) {
        $app = $app_result->fetch_assoc();
        $loan_status_display = ucfirst($app['status']) . " Application";
    }
}

/* ===============================
   3. FINES OWED
================================ */
$unpaid_fines_count = 0;
$total_fines_owed = 0;

$fines_result = $conn->query("
    SELECT COUNT(*) AS count, COALESCE(SUM(amount), 0) AS total
    FROM fines 
    WHERE member_id = $member_id AND status = 'unpaid'
");

if ($fines_result) {
    $fines = $fines_result->fetch_assoc();
    $unpaid_fines_count = $fines['count'];
    $total_fines_owed = $fines['total'];
}

/* ===============================
   4. NOTIFICATIONS
================================ */
$notifications = $conn->query("
    SELECT message, created_at 
    FROM notifications 
    WHERE member_id = $member_id 
    ORDER BY created_at DESC 
    LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Chama Member</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">

    <!-- SIDEBAR -->
    <aside class="w-64 bg-gradient-to-b from-blue-700 to-blue-800 text-white p-6">
        <h2 class="text-xl font-bold mb-8">Chama Member</h2>
        <nav class="space-y-4 text-sm">
            <a href="member_dashboard.php" class="block bg-blue-600 px-4 py-2 rounded font-semibold">Dashboard</a>
            <a href="member_profile.php" class="block hover:bg-blue-600 px-4 py-2 rounded">My Profile</a>
            <a href="member_contribution.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Contributions</a>
            <a href="member_loan.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Loans</a>
            <a href="member_fines.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Fines</a>
            <a href="member_logout.php" class="block text-red-200 hover:bg-red-600 px-4 py-2 rounded">Logout</a>
        </nav>
        <div class="mt-auto pt-6 border-t border-blue-600">
            <p class="text-sm text-blue-200">Welcome, <?= htmlspecialchars($member_name) ?></p>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 p-8">
        <div class="max-w-6xl mx-auto">

            <h1 class="text-3xl font-bold text-gray-800 mb-2">Welcome back, <?= htmlspecialchars($member_name) ?>!</h1>
            <p class="text-gray-600 mb-8">Your personal chama overview</p>

            <!-- STATS CARDS -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">

                <!-- Contributions -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Total Contributions</h3>
                        <span class="text-3xl">💰</span>
                    </div>
                    <p class="text-3xl font-bold text-blue-600">KES <?= number_format($total_contributed) ?></p>
                    <p class="text-sm text-gray-600 mt-2">All time contributions</p>
                    <a href="member_contribution.php" class="text-blue-600 text-sm hover:underline mt-4 inline-block">View details →</a>
                </div>

                <!-- Loan Status -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Loan Status</h3>
                        <span class="text-3xl">📊</span>
                    </div>
                    <?php if ($outstanding_balance > 0): ?>
                        <p class="text-3xl font-bold text-orange-600">KES <?= number_format($outstanding_balance) ?></p>
                        <p class="text-sm text-gray-600 mt-2">Outstanding balance</p>
                        <?php if ($next_repayment): ?>
                            <p class="text-sm mt-3 text-gray-700">Next due: <?= date('d M Y', strtotime($next_repayment)) ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-xl font-semibold text-gray-600"><?= $loan_status_display ?></p>
                    <?php endif; ?>
                    <a href="member_loan.php" class="text-blue-600 text-sm hover:underline mt-4 inline-block">View loans →</a>
                </div>

                <!-- Fines -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Fines</h3>
                        <span class="text-3xl">⚖️</span>
                    </div>
                    <?php if ($unpaid_fines_count > 0): ?>
                        <p class="text-3xl font-bold text-red-600">KES <?= number_format($total_fines_owed) ?></p>
                        <p class="text-sm text-gray-600 mt-2"><?= $unpaid_fines_count ?> unpaid fine<?= $unpaid_fines_count > 1 ? 's' : '' ?></p>
                        <a href="member_fines.php" class="text-blue-600 text-sm hover:underline mt-4 inline-block">Settle now →</a>
                    <?php else: ?>
                        <p class="text-3xl font-bold text-green-600">All Clear! 🎉</p>
                        <p class="text-sm text-gray-600 mt-2">No outstanding fines</p>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <a href="member_contribution.php" class="block bg-blue-50 hover:bg-blue-100 text-blue-700 font-medium px-4 py-3 rounded-lg text-center transition">Pay Contribution</a>
                        <a href="member_loan.php" class="block bg-blue-50 hover:bg-blue-100 text-blue-700 font-medium px-4 py-3 rounded-lg text-center transition">Apply for Loan</a>
                        <a href="member_fines.php" class="block bg-blue-50 hover:bg-blue-100 text-blue-700 font-medium px-4 py-3 rounded-lg text-center transition">View Fines</a>
                    </div>
                </div>
            </div>

            <!-- Recent Notifications -->
            <?php if ($notifications && $notifications->num_rows > 0): ?>
            <div class="bg-white rounded-xl shadow-lg p-8">
                <h2 class="text-xl font-semibold text-gray-800 mb-6">Recent Notifications</h2>
                <div class="space-y-4">
                    <?php while ($notif = $notifications->fetch_assoc()): ?>
                    <div class="flex items-start gap-4 pb-4 border-b border-gray-200 last:border-0">
                        <div class="text-2xl">🔔</div>
                        <div>
                            <p class="text-gray-800"><?= htmlspecialchars($notif['message']) ?></p>
                            <p class="text-xs text-gray-500 mt-1"><?= date('d M Y \a\t H:i', strtotime($notif['created_at'])) ?></p>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </main>
</body>
</html>