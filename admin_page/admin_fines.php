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

$success = $error = "";

/* ===============================
   HANDLE ASSIGN FINE
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $member_code = trim($_POST['member_code'] ?? '');
    $reason      = trim($_POST['reason'] ?? '');
    $amount      = (float) ($_POST['amount'] ?? 0);

    if ($member_code === '' || $reason === '' || $amount <= 0) {
        $error = "All fields are required.";
    } else {
        $check = $conn->prepare("SELECT full_name, status FROM members WHERE member_id = ?");
        $check->bind_param("s", $member_code);
        $check->execute();
        $member = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$member) {
            $error = "Member not found.";
        } elseif ($member['status'] !== 'active') {
            $error = "Cannot fine suspended member.";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO fines (member_id, reason, amount, status, issued_date)
                VALUES (?, ?, ?, 'unpaid', NOW())
            ");
            $stmt->bind_param("ssd", $member_code, $reason, $amount);

            if ($stmt->execute()) {
                $success = "Fine of KES " . number_format($amount) . " assigned to " . htmlspecialchars($member['full_name']);
            } else {
                $error = "Failed to assign fine.";
            }
            $stmt->close();
        }
    }
}

/* ===============================
   FETCH MEMBERS FOR DROPDOWN
================================ */
$members = $conn->query("SELECT member_id, full_name FROM members WHERE status = 'active' ORDER BY full_name");

/* ===============================
   SEARCH & FILTER FINES
================================ */
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? 'all';

$where = "";
$params = [];
$types = "";

if ($search !== '') {
    $where .= " AND (m.full_name LIKE ? OR m.member_id LIKE ? OR f.reason LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like]);
    $types .= "sss";
}

if ($status_filter !== 'all') {
    $where .= " AND f.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql = "
    SELECT f.id, f.reason, f.amount, f.status, f.issued_date, m.full_name, m.member_id
    FROM fines f
    JOIN members m ON f.member_id = m.member_id
    WHERE 1=1 $where
    ORDER BY f.issued_date DESC
";

$stmt = $conn->prepare($sql);
if ($types !== "") {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$fines = $stmt->get_result();
$stmt->close();

/* ===============================
   TOTAL UNPAID FINES
================================ */
$total_unpaid = $conn->query("SELECT COALESCE(SUM(amount), 0) FROM fines WHERE status = 'unpaid'")->fetch_array()[0] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Fines Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">

    <!-- SIDEBAR -->
    <aside class="w-64 bg-gradient-to-b from-blue-700 to-blue-800 text-white p-6">
        <h2 class="text-xl font-bold mb-8">Chama Admin</h2>
        <nav class="space-y-4 text-sm">
            <a href="admin_dashboard.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Dashboard</a>
            <a href="admin_member.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Members</a>
            <a href="admin_add_member.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Add Member</a>
            <a href="admin_contribution.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Contributions</a>
            <a href="admin_loan.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Loan Approvals</a>
            <a href="admin_fines.php" class="block bg-blue-600 px-4 py-2 rounded font-semibold">Fines</a>
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

            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800">Fines Management</h1>
                <div class="text-right">
                    <p class="text-sm text-gray-600">Total Unpaid Fines</p>
                    <p class="text-3xl font-bold text-red-600">KES <?= number_format($total_unpaid, 2) ?></p>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="bg-green-50 border-l-4 border-green-500 text-green-700 px-6 py-4 rounded-lg mb-8">
                    <p class="font-semibold"><?= htmlspecialchars($success) ?></p>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 px-6 py-4 rounded-lg mb-8">
                    <p class="font-semibold"><?= htmlspecialchars($error) ?></p>
                </div>
            <?php endif; ?>

            <!-- ASSIGN FINE FORM -->
            <div class="bg-white rounded-xl shadow-lg p-8 mb-10 max-w-3xl">
                <h2 class="text-xl font-semibold text-gray-800 mb-6">Assign New Fine</h2>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Member</label>
                        <select name="member_code" required class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Choose Member --</option>
                            <?php mysqli_data_seek($members, 0); ?>
                            <?php while ($m = $members->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($m['member_id']) ?>">
                                    <?= htmlspecialchars($m['full_name']) ?> (<?= $m['member_id'] ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Reason</label>
                        <select name="reason" required class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Select Reason --</option>
                            <option value="Late contribution">Late contribution</option>
                            <option value="Missed meeting">Missed meeting</option>
                            <option value="Loan default">Loan default</option>
                            <option value="Absence without apology">Absence without apology</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Amount (KES)</label>
                        <input type="number" name="amount" min="1" step="0.01" required placeholder="e.g. 500"
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit"
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-lg transition shadow-lg text-lg">
                            Assign Fine
                        </button>
                    </div>
                </form>
            </div>

            <!-- SEARCH & FILTER -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Search by member name, ID, or reason..."
                           class="border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500">
                    <select name="status" class="border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="unpaid" <?= $status_filter === 'unpaid' ? 'selected' : '' ?>>Unpaid Only</option>
                        <option value="paid" <?= $status_filter === 'paid' ? 'selected' : '' ?>>Paid Only</option>
                    </select>
                    <div class="flex gap-3">
                        <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-6 py-3 rounded-lg transition shadow">
                            Apply Filter
                        </button>
                        <?php if ($search !== '' || $status_filter !== 'all'): ?>
                            <a href="admin_fines.php"
                               class="bg-gray-300 hover:bg-gray-400 text-gray-700 font-medium px-6 py-3 rounded-lg transition">
                                Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- FINE HISTORY TABLE -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-8 py-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800">Fines History (<?= $fines->num_rows ?> records)</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Member</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Issued Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if ($fines->num_rows > 0): ?>
                                <?php while ($f = $fines->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 font-medium">
                                        <?= htmlspecialchars($f['full_name']) ?> <span class="text-gray-500">(<?= $f['member_id'] ?>)</span>
                                    </td>
                                    <td class="px-6 py-4"><?= htmlspecialchars($f['reason']) ?></td>
                                    <td class="px-6 py-4 font-bold text-red-600">KES <?= number_format($f['amount'], 2) ?></td>
                                    <td class="px-6 py-4">
                                        <span class="inline-block px-3 py-1 rounded-full text-xs font-medium
                                            <?= $f['status'] === 'paid' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                            <?= ucfirst($f['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600"><?= date("d M Y", strtotime($f['issued_date'])) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-16 text-center text-gray-500">
                                        <div class="text-6xl mb-4">⚖️</div>
                                        <p class="text-xl font-medium">No fines found</p>
                                        <p class="mt-2">All members are compliant!</p>
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