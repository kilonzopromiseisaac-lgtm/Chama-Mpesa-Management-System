<?php
session_start();
require_once "db_connection.php";

/* ===============================
   ADMIN AUTH CHECK
================================ */
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../admin_login.php");
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

/* ===============================
   DASHBOARD STATS
================================ */
$totalMembers = $conn->query("SELECT COUNT(*) AS total FROM members")->fetch_assoc()['total'] ?? 0;
$activeMembers = $conn->query("SELECT COUNT(*) AS total FROM members WHERE status = 'active'")->fetch_assoc()['total'] ?? 0;
$totalContributions = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM contributions")->fetch_assoc()['total'] ?? 0;
$pendingLoans = $conn->query("SELECT COUNT(*) AS total FROM loan_applications WHERE status = 'pending'")->fetch_assoc()['total'] ?? 0;
$outstandingBalance = $conn->query("SELECT COALESCE(SUM(balance), 0) AS total FROM loans WHERE status = 'active'")->fetch_assoc()['total'] ?? 0;
$totalUnpaidFines = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM fines WHERE status = 'unpaid'")->fetch_assoc()['total'] ?? 0;

/* ===============================
   RECENT TRANSACTIONS (Safe & Fixed)
================================ */
$recentTransactions = $conn->query("
    (SELECT 'contribution' AS type, c.contribution_date AS date, c.amount, CONCAT('Contribution by ', m.full_name) AS description
     FROM contributions c 
     JOIN members m ON c.member_id = m.member_id
     ORDER BY c.contribution_date DESC LIMIT 5)
    UNION ALL
    (SELECT 'loan' AS type, l.date AS date, l.amount, CONCAT('Loan disbursed to ', m.full_name) AS description
     FROM loans l 
     JOIN members m ON l.member_id = m.id
     WHERE l.status = 'active'
     ORDER BY l.date DESC LIMIT 3)
    UNION ALL
    (SELECT 'fine' AS type, f.issued_date AS date, f.amount, CONCAT('Fine: ', f.reason, ' (', m.full_name, ')') AS description
     FROM fines f 
     JOIN members m ON f.member_id = m.id 
     WHERE f.status = 'unpaid'
     ORDER BY f.issued_date DESC LIMIT 2)
    ORDER BY date DESC
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Chama System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">

    <!-- SIDEBAR - Blue Theme (Reports link removed) -->
    <aside class="w-64 bg-gradient-to-b from-blue-700 to-blue-800 text-white p-6">
        <h2 class="text-xl font-bold mb-8">Chama Admin</h2>
        <nav class="space-y-4 text-sm">
            <a href="admin_dashboard.php" class="block bg-blue-600 px-4 py-2 rounded font-semibold">Dashboard</a>
            <a href="admin_member.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Members</a>
            <a href="admin_add_member.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Add Member</a>
            <a href="admin_loan.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Loan Approvals</a>
            <a href="admin_contribution.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Contributions</a>
            <a href="admin_fines.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Fines</a>
            <a href="admin_notification.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Notifications</a>
            <a href="admin_logout.php" class="block text-red-200 hover:bg-red-600 px-4 py-2 rounded">Logout</a>
        </nav>
        <div class="mt-auto pt-6 border-t border-blue-600">
            <p class="text-sm text-blue-200">Welcome, <?= htmlspecialchars($admin_name) ?></p>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 p-8">
        <div class="max-w-7xl mx-auto">

            <h1 class="text-3xl font-bold text-gray-800 mb-2">Admin Dashboard</h1>
            <p class="text-gray-600 mb-8">Chama overview as of <?= date('F j, Y') ?></p>

            <!-- STATS CARDS (Active Loans card removed) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6 mb-10">

                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <div class="text-4xl mb-2">👥</div>
                    <p class="text-sm text-gray-600">Total Members</p>
                    <p class="text-3xl font-bold text-blue-600 mt-2"><?= $totalMembers ?></p>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <div class="text-4xl mb-2">✅</div>
                    <p class="text-sm text-gray-600">Active Members</p>
                    <p class="text-3xl font-bold text-green-600 mt-2"><?= $activeMembers ?></p>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <div class="text-4xl mb-2">💰</div>
                    <p class="text-sm text-gray-600">Total Contributions</p>
                    <p class="text-3xl font-bold text-purple-600 mt-2">KES <?= number_format($totalContributions, 2) ?></p>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <div class="text-4xl mb-2">⏳</div>
                    <p class="text-sm text-gray-600">Pending Loans</p>
                    <p class="text-3xl font-bold text-orange-600 mt-2"><?= $pendingLoans ?></p>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <div class="text-4xl mb-2">⚖️</div>
                    <p class="text-sm text-gray-600">Unpaid Fines</p>
                    <p class="text-3xl font-bold text-red-600 mt-2">KES <?= number_format($totalUnpaidFines, 2) ?></p>
                </div>

            </div>

            <!-- QUICK ACTIONS -->
            <div class="bg-white rounded-xl shadow-lg p-8 mb-10">
                <h3 class="text-xl font-semibold text-gray-800 mb-6">Quick Actions</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <a href="admin_add_member.php" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-4 rounded-lg text-center transition shadow">
                        ➕ Add Member
                    </a>
                    <a href="admin_member.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-4 rounded-lg text-center transition shadow">
                        👥 Manage Members
                    </a>
                    <a href="admin_loan.php" class="bg-orange-600 hover:bg-orange-700 text-white font-medium py-4 rounded-lg text-center transition shadow">
                        ⏳ Review Loans
                    </a>
                    <a href="admin_contribution.php" class="bg-green-600 hover:bg-green-700 text-white font-medium py-4 rounded-lg text-center transition shadow">
                        💵 Record Contribution
                    </a>
                </div>
            </div>

            <!-- RECENT TRANSACTIONS -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-8 py-6 border-b border-gray-200">
                    <h3 class="text-xl font-semibold text-gray-800">Recent Transactions</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if ($recentTransactions && $recentTransactions->num_rows > 0): ?>
                                <?php while ($tx = $recentTransactions->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 text-gray-600"><?= date('d M Y', strtotime($tx['date'])) ?></td>
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1 rounded-full text-xs font-medium
                                            <?= $tx['type'] === 'contribution' ? 'bg-green-100 text-green-700' :
                                                ($tx['type'] === 'loan' ? 'bg-blue-100 text-blue-700' :
                                                'bg-red-100 text-red-700') ?>">
                                            <?= ucfirst($tx['type']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-800"><?= htmlspecialchars($tx['description']) ?></td>
                                    <td class="px-6 py-4 font-semibold">KES <?= number_format($tx['amount'], 2) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-16 text-center text-gray-500">
                                        <div class="text-6xl mb-4">📭</div>
                                        <p class="text-xl">No recent transactions</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</body>
</html>