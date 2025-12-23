<?php
session_start();
require_once "db_connection.php";

/* ===============================
   ADMIN AUTH CHECK
================================ */
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

/* ===============================
   SEARCH
================================ */
$search = trim($_GET['search'] ?? '');

$where = "";
$params = [];
$types = "";

if ($search !== '') {
    $where = "WHERE full_name LIKE ? OR email LIKE ? OR member_id LIKE ?";
    $like = "%$search%";
    $params = [$like, $like, $like];
    $types = "sss";
}

$sql = "
    SELECT member_id, full_name, email, phone, status, created_at
    FROM members
    $where
    ORDER BY created_at DESC
";

$stmt = $conn->prepare($sql);
if ($types !== "") {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$members = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Members</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">

    <!-- SIDEBAR - Blue Theme -->
    <aside class="w-64 bg-gradient-to-b from-blue-700 to-blue-800 text-white p-6">
        <h2 class="text-xl font-bold mb-8">Chama Admin</h2>
        <nav class="space-y-4 text-sm">
            <a href="admin_dashboard.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Dashboard</a>
            <a href="admin_member.php" class="block bg-blue-600 px-4 py-2 rounded font-semibold">Members</a>
            <a href="admin_add_member.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Add Member</a>
            <a href="admin_contribution.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Contributions</a>
            <a href="admin_loan.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Loan Approvals</a>
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

            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800">Members Management</h1>
                <a href="admin_add_member.php"
                   class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-6 py-3 rounded-lg transition shadow">
                    ➕ Add New Member
                </a>
            </div>

            <!-- SEARCH BAR -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <form method="GET" class="flex flex-col sm:flex-row gap-4">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Search by name, email, or Member ID..."
                           class="flex-1 border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-8 py-3 rounded-lg transition shadow">
                        Search
                    </button>
                    <?php if ($search !== ''): ?>
                        <a href="admin_member.php"
                           class="text-blue-600 hover:underline text-center sm:text-left">
                            Clear search
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- MEMBERS TABLE -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-8 py-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800">All Members (<?= $members->num_rows ?>)</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Member ID</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Full Name</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Joined</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if ($members->num_rows > 0): ?>
                                <?php while ($m = $members->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 font-medium text-blue-600"><?= htmlspecialchars($m['member_id']) ?></td>
                                    <td class="px-6 py-4 font-medium"><?= htmlspecialchars($m['full_name']) ?></td>
                                    <td class="px-6 py-4 text-gray-700"><?= htmlspecialchars($m['email']) ?></td>
                                    <td class="px-6 py-4 text-gray-700"><?= htmlspecialchars($m['phone'] ?? '-') ?></td>
                                    <td class="px-6 py-4">
                                        <span class="inline-block px-3 py-1 rounded-full text-xs font-medium
                                            <?= $m['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                            <?= ucfirst($m['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600">
                                        <?= date("d M Y", strtotime($m['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($m['status'] === 'active'): ?>
                                            <a href="update_member_status.php?member_id=<?= urlencode($m['member_id']) ?>&action=suspend"
                                               onclick="return confirm('Suspend this member? They will not be able to log in or contribute.')"
                                               class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded text-xs transition shadow">
                                                Suspend
                                            </a>
                                        <?php else: ?>
                                            <a href="update_member_status.php?member_id=<?= urlencode($m['member_id']) ?>&action=activate"
                                               onclick="return confirm('Activate this member?')"
                                               class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-xs transition shadow">
                                                Activate
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-16 text-center text-gray-500">
                                        <div class="text-6xl mb-4">👥</div>
                                        <p class="text-xl font-medium">No members found</p>
                                        <?php if ($search !== ''): ?>
                                            <p class="mt-2">Try adjusting your search terms</p>
                                        <?php else: ?>
                                            <p class="mt-2">Start by adding your first member!</p>
                                        <?php endif; ?>
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