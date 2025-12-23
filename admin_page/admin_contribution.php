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

$success = "";
$error   = "";

/* ===============================
   HANDLE RECORD NEW CONTRIBUTION
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_contribution'])) {

    $member_code = trim($_POST['member_code'] ?? '');
    $amount      = (float) ($_POST['amount'] ?? 0);
    $method      = trim($_POST['payment_method'] ?? '');
    $reference   = trim($_POST['mpesa_reference'] ?? '') ?: null;

    if ($member_code === '' || $amount <= 0 || $method === '') {
        $error = "Member ID, amount, and payment method are required.";
    } else {
        $check = $conn->prepare("SELECT full_name, status FROM members WHERE member_id = ?");
        $check->bind_param("s", $member_code);
        $check->execute();
        $member = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$member) {
            $error = "Member with ID '$member_code' not found.";
        } elseif ($member['status'] !== 'active') {
            $error = "Suspended members cannot make contributions.";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO contributions 
                (member_id, amount, payment_method, mpesa_reference, contribution_date)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("sdss", $member_code, $amount, $method, $reference);

            if ($stmt->execute()) {
                // Secure wallet update using prepared statement
                $wallet = $conn->prepare("
                    UPDATE chama_settings 
                    SET total_contributions = total_contributions + ?,
                        total_balance = total_balance + ?
                    WHERE id = 1
                ");
                $wallet->bind_param("dd", $amount, $amount);
                $wallet->execute();
                $wallet->close();

                $success = "KES " . number_format($amount) . " contribution recorded for " . htmlspecialchars($member['full_name']);
            } else {
                $error = "Failed to save contribution.";
            }
            $stmt->close();
        }
    }
}

/* ===============================
   SEARCH & FETCH CONTRIBUTIONS
================================ */
$search = trim($_GET['search'] ?? '');
$where = "";
$params = [];
$types = "";

if ($search !== "") {
    $where = "WHERE m.member_id LIKE ? OR m.full_name LIKE ?";
    $like = "%$search%";
    $params = [$like, $like];
    $types = "ss";
}

$sql = "
    SELECT 
        c.id, c.amount, c.payment_method, c.mpesa_reference, 
        c.contribution_date, m.full_name, m.member_id
    FROM contributions c
    JOIN members m ON c.member_id = m.member_id
    $where
    ORDER BY c.contribution_date DESC
";

$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$contributions = $stmt->get_result();

/* ===============================
   TOTAL CONTRIBUTIONS
================================ */
$total = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM contributions")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Contributions</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">

    <!-- SIDEBAR - Cleaned (No Active Loans / Reports) -->
    <aside class="w-64 bg-gradient-to-b from-blue-700 to-blue-800 text-white p-6">
        <h2 class="text-xl font-bold mb-8">Chama Admin</h2>
        <nav class="space-y-4 text-sm">
            <a href="admin_dashboard.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Dashboard</a>
            <a href="admin_member.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Members</a>
            <a href="admin_add_member.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Add Member</a>
            <a href="admin_contribution.php" class="block bg-blue-600 px-4 py-2 rounded font-semibold">Contributions</a>
            <a href="admin_loan.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Loan Approvals</a>
            <a href="admin_fines.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Fines</a>
            <a href="admin_notification.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Notifications</a>
            <a href="../admin_logout.php" class="block text-red-200 hover:bg-red-600 px-4 py-2 rounded">Logout</a>
        </nav>
        <div class="mt-auto pt-6 border-t border-blue-600">
            <p class="text-sm text-blue-200">Welcome, <?= htmlspecialchars($admin_name) ?></p>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 p-8">
        <div class="max-w-7xl mx-auto">

            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800">Contributions Management</h1>
                <div class="text-right">
                    <p class="text-sm text-gray-600">Total Collected</p>
                    <p class="text-3xl font-bold text-green-600">KES <?= number_format($total, 2) ?></p>
                </div>
            </div>

            <!-- Success / Error Alerts -->
            <?php if ($success): ?>
                <div class="bg-green-50 border-l-4 border-green-500 text-green-700 px-6 py-4 rounded mb-8">
                    <p class="font-semibold"><?= htmlspecialchars($success) ?></p>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 px-6 py-4 rounded mb-8">
                    <p class="font-semibold"><?= htmlspecialchars($error) ?></p>
                </div>
            <?php endif; ?>

            <!-- RECORD CONTRIBUTION FORM -->
            <div class="bg-white rounded-xl shadow-lg p-8 mb-10 max-w-3xl">
                <h2 class="text-xl font-semibold text-gray-800 mb-6">Record New Contribution</h2>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <input type="hidden" name="record_contribution" value="1">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Member ID</label>
                        <input type="text" name="member_code" placeholder="e.g. MEM-2024-001" required
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Amount (KES)</label>
                        <input type="number" name="amount" min="1" step="0.01" required
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method</label>
                        <select name="payment_method" required class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Select --</option>
                            <option value="Mpesa">M-Pesa</option>
                            <option value="Cash">Cash</option>
                            <option value="Bank">Bank Transfer</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">M-Pesa Reference (optional)</label>
                        <input type="text" name="mpesa_reference" placeholder="e.g. ABC123XYZ"
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit"
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-lg transition shadow-lg text-lg">
                            Record Contribution
                        </button>
                    </div>
                </form>
            </div>

            <!-- SEARCH BAR -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8 max-w-md">
                <form method="GET">
                    <div class="flex gap-3">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Search by Member ID or Name..." 
                               class="flex-1 border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition">
                            Search
                        </button>
                    </div>
                    <?php if ($search !== ''): ?>
                        <a href="admin_contribution.php" class="text-sm text-blue-600 hover:underline mt-3 inline-block">Clear search</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- CONTRIBUTIONS TABLE -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-8 py-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800">All Contributions History</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Member</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Member ID</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Ref</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if ($contributions->num_rows > 0): ?>
                                <?php $i = 1; while ($row = $contributions->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4"><?= $i++ ?></td>
                                    <td class="px-6 py-4 font-medium"><?= htmlspecialchars($row['full_name']) ?></td>
                                    <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($row['member_id']) ?></td>
                                    <td class="px-6 py-4 font-bold text-green-600">KES <?= number_format($row['amount'], 2) ?></td>
                                    <td class="px-6 py-4"><?= htmlspecialchars($row['payment_method']) ?></td>
                                    <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($row['mpesa_reference'] ?? '-') ?></td>
                                    <td class="px-6 py-4 text-gray-600"><?= date("d M Y", strtotime($row['contribution_date'])) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-16 text-center text-gray-500">
                                        <div class="text-6xl mb-4">No contributions found</div>
                                        <p class="text-xl">Try adjusting your search</p>
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