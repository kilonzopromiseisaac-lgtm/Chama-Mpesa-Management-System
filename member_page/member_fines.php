<?php
session_start();
require_once "db_connection.php";

if (!isset($_SESSION['member_code']) && !isset($_SESSION['member_id'])) {
    header("Location: member_login.php");
    exit;
}

// Use the STRING Member ID (CHM-2025-XXXX) - this matches fines table
$member_code = $_SESSION['member_code'] ?? $_SESSION['member_id'] ?? '';
$member_name = $_SESSION['full_name'] ?? 'Member';

if ($member_code === '') {
    die("Error: Member ID not found in session.");
}

/* ===============================
   MARK AS PAID (AJAX)
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fine_id'])) {
    $fine_id = (int)$_POST['fine_id'];

    $stmt = $conn->prepare("UPDATE fines SET status = 'paid' WHERE id = ? AND member_id = ?");
    $stmt->bind_param("is", $fine_id, $member_code); // 's' for string
    $stmt->execute();

    echo json_encode(["success" => $stmt->affected_rows > 0]);
    $stmt->close();
    exit;
}

/* ===============================
   FETCH MEMBER'S FINES (using string member_id)
================================ */
$stmt = $conn->prepare("
    SELECT id, reason, amount, status, issued_date 
    FROM fines 
    WHERE member_id = ? 
    ORDER BY issued_date DESC
");
$stmt->bind_param("s", $member_code);
$stmt->execute();
$result = $stmt->get_result();
$fines = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$unpaid_count = 0;
$total_unpaid = 0;
foreach ($fines as $f) {
    if ($f['status'] === 'unpaid') {
        $unpaid_count++;
        $total_unpaid += $f['amount'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Fines</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">

    <!-- SIDEBAR -->
    <aside class="w-64 bg-gradient-to-b from-blue-700 to-blue-800 text-white p-6">
        <h2 class="text-xl font-bold mb-8">Chama Member</h2>
        <nav class="space-y-4 text-sm">
            <a href="member_dashboard.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Dashboard</a>
            <a href="member_profile.php" class="block hover:bg-blue-600 px-4 py-2 rounded">My Profile</a>
            <a href="member_contribution.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Contributions</a>
            <a href="member_loan.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Loans</a>
            <a href="member_fines.php" class="block bg-blue-600 px-4 py-2 rounded font-semibold">Fines</a>
            <a href="member_notifications.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Notifications</a>
            <a href="member_logout.php" class="block text-red-200 hover:bg-red-600 px-4 py-2 rounded">Logout</a>
        </nav>
        <div class="mt-auto pt-6 border-t border-blue-600">
            <p class="text-sm text-blue-200">Welcome, <?= htmlspecialchars($member_name) ?></p>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 p-8">
        <div class="max-w-5xl mx-auto">

            <h1 class="text-3xl font-bold text-gray-800 mb-2">My Fines</h1>
            <p class="text-gray-600 mb-8">View and settle your outstanding fines</p>

            <!-- Summary Alert -->
            <?php if ($unpaid_count > 0): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 px-6 py-4 rounded-lg mb-8">
                    <p class="font-bold text-lg">You have <?= $unpaid_count ?> unpaid fine<?= $unpaid_count > 1 ? 's' : '' ?></p>
                    <p class="font-semibold">Total owed: KES <?= number_format($total_unpaid, 2) ?></p>
                </div>
            <?php else: ?>
                <div class="bg-green-50 border-l-4 border-green-500 text-green-700 px-6 py-4 rounded-lg mb-8">
                    <p class="font-bold text-lg">All Clear! 🎉</p>
                    <p>No outstanding fines</p>
                </div>
            <?php endif; ?>

            <!-- FINES TABLE -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-8 py-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800">Fines History</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                                <th class="px-6 py-4 text-center text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-6 py-4 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-4 text-center text-xs font-medium text-gray-500 uppercase">Issued Date</th>
                                <th class="px-6 py-4 text-center text-xs font-medium text-gray-500 uppercase">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (!empty($fines)): ?>
                                <?php foreach ($fines as $fine): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4"><?= htmlspecialchars($fine['reason']) ?></td>
                                    <td class="px-6 py-4 text-center font-bold text-red-600">
                                        KES <?= number_format($fine['amount'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-block px-4 py-2 rounded-full text-xs font-medium
                                            <?= $fine['status'] === 'paid' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                            <?= ucfirst($fine['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center text-gray-600">
                                        <?= date('d M Y', strtotime($fine['issued_date'])) ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <?php if ($fine['status'] === 'unpaid'): ?>
                                            <button onclick="markAsPaid(<?= $fine['id'] ?>, this)"
                                                    class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-5 py-2 rounded-lg transition shadow">
                                                Mark as Paid
                                            </button>
                                        <?php else: ?>
                                            <span class="text-green-600 font-semibold">Paid ✓</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-16 text-center text-gray-500">
                                        <div class="text-6xl mb-4">😊</div>
                                        <p class="text-xl font-medium">No fines recorded</p>
                                        <p class="mt-2">Keep up the great discipline!</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script>
        function markAsPaid(fineId, btn) {
            if (!confirm("Confirm you have paid this fine?")) return;

            const formData = new FormData();
            formData.append("fine_id", fineId);

            fetch("", {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const row = btn.closest("tr");
                    row.querySelector("span.inline-block").textContent = "Paid";
                    row.querySelector("span.inline-block").className = "inline-block px-4 py-2 rounded-full text-xs font-medium bg-green-100 text-green-700";
                    btn.parentElement.innerHTML = '<span class="text-green-600 font-semibold">Paid ✓</span>';
                    location.reload(); // Update summary
                } else {
                    alert("Failed to update fine status.");
                }
            })
            .catch(() => alert("Network error."));
        }
    </script>
</body>
</html>