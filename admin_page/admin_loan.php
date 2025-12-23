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

$success = $error = "";

/* ===============================
   HANDLE APPROVE / REJECT
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $application_id = (int)$_POST['application_id'];
    $action = $_POST['action'] ?? '';
    $comment = trim($_POST['admin_comment'] ?? '');

    if ($action === 'approve') {

        $stmt = $conn->prepare("
            SELECT * FROM loan_applications
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->bind_param("i", $application_id);
        $stmt->execute();
        $application = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($application) {
            $balance = $application['amount'];

            $insert = $conn->prepare("
                INSERT INTO loans (
                    member_id, loan_application_id, amount, balance,
                    interest_rate, repayment_cycle, next_repayment_date,
                    status, date
                ) VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 1 MONTH), 'active', CURDATE())
            ");
            $insert->bind_param(
                "iidddi",
                $application['member_id'],
                $application['id'],
                $application['amount'],
                $balance,
                $application['interest_rate'],
                $application['repayment_cycle']
            );

            if ($insert->execute()) {
                $update = $conn->prepare("
                    UPDATE loan_applications
                    SET status='approved', approved_by=?, approved_date=CURDATE()
                    WHERE id=?
                ");
                $update->bind_param("ii", $_SESSION['admin_id'], $application_id);
                $update->execute();
                $update->close();
                $success = "Loan application approved and active loan created.";
            } else {
                $error = "Failed to create loan record.";
            }
            $insert->close();
        } else {
            $error = "Application not found or already processed.";
        }

    } elseif ($action === 'reject') {
        $update = $conn->prepare("
            UPDATE loan_applications
            SET status='rejected', admin_comment=?, approved_by=?, approved_date=CURDATE()
            WHERE id=? AND status='pending'
        ");
        $update->bind_param("sii", $comment, $_SESSION['admin_id'], $application_id);
        if ($update->execute() && $update->affected_rows > 0) {
            $success = "Loan application rejected.";
        } else {
            $error = "Failed to reject application or already processed.";
        }
        $update->close();
    }
}

/* ===============================
   FETCH PENDING APPLICATIONS
================================ */
$applications = $conn->query("
    SELECT 
        la.id,
        la.amount,
        la.purpose,
        la.application_date,
        la.interest_rate,
        la.repayment_cycle,
        m.full_name
    FROM loan_applications la
    JOIN members m ON la.member_id = m.id
    WHERE la.status = 'pending'
    ORDER BY la.application_date DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Loan Approvals</title>
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
            <a href="admin_contribution.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Contributions</a>
            <a href="admin_loan.php" class="block bg-blue-600 px-4 py-2 rounded font-semibold">Loan Approvals</a>
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
        <div class="max-w-6xl mx-auto">

            <h1 class="text-3xl font-bold text-gray-800 mb-2">Pending Loan Applications</h1>
            <p class="text-gray-600 mb-8">Review and approve or reject member loan requests</p>

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

            <!-- APPLICATIONS TABLE -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Member</th>
                                <th class="px-6 py-4 text-center text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Purpose</th>
                                <th class="px-6 py-4 text-center text-xs font-medium text-gray-500 uppercase">Interest</th>
                                <th class="px-6 py-4 text-center text-xs font-medium text-gray-500 uppercase">Term</th>
                                <th class="px-6 py-4 text-center text-xs font-medium text-gray-500 uppercase">Applied On</th>
                                <th class="px-6 py-4 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if ($applications->num_rows > 0): ?>
                                <?php while ($row = $applications->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 font-medium text-gray-900">
                                        <?= htmlspecialchars($row['full_name']) ?>
                                    </td>
                                    <td class="px-6 py-4 text-center font-bold text-lg">
                                        KES <?= number_format($row['amount']) ?>
                                    </td>
                                    <td class="px-6 py-4 text-gray-700 max-w-xs truncate">
                                        <?= htmlspecialchars($row['purpose']) ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <?= $row['interest_rate'] ?>%
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <?= $row['repayment_cycle'] ?> months
                                    </td>
                                    <td class="px-6 py-4 text-center text-gray-600">
                                        <?= date("d M Y", strtotime($row['application_date'])) ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex justify-center gap-3">
                                            <!-- Approve -->
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="application_id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit"
                                                        class="bg-green-600 hover:bg-green-700 text-white font-medium px-5 py-2 rounded-lg transition shadow">
                                                    Approve
                                                </button>
                                            </form>

                                            <!-- Reject Modal Trigger -->
                                            <button onclick="openRejectModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['full_name']), ENT_QUOTES) ?>')"
                                                    class="bg-red-600 hover:bg-red-700 text-white font-medium px-5 py-2 rounded-lg transition shadow">
                                                Reject
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-16 text-center text-gray-500">
                                        <div class="text-6xl mb-4">🎉</div>
                                        <p class="text-xl font-medium">No pending loan applications</p>
                                        <p class="mt-2">All applications are processed!</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Reject Modal -->
    <div id="rejectModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl p-8 max-w-md w-full">
            <h3 class="text-xl font-bold text-gray-800 mb-4">Reject Loan Application</h3>
            <p class="text-gray-600 mb-6">Member: <span id="rejectMemberName" class="font-semibold"></span></p>
            <form method="POST">
                <input type="hidden" name="application_id" id="rejectAppId">
                <input type="hidden" name="action" value="reject">
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Reason for Rejection (Optional)</label>
                    <textarea name="admin_comment" rows="4"
                              class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500"
                              placeholder="Enter reason..."></textarea>
                </div>
                <div class="flex justify-end gap-4">
                    <button type="button" onclick="closeRejectModal()"
                            class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="bg-red-600 hover:bg-red-700 text-white font-medium px-6 py-2 rounded-lg transition shadow">
                        Confirm Reject
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openRejectModal(appId, memberName) {
            document.getElementById('rejectAppId').value = appId;
            document.getElementById('rejectMemberName').textContent = memberName;
            document.getElementById('rejectModal').classList.remove('hidden');
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').classList.add('hidden');
        }

        document.getElementById('rejectModal').addEventListener('click', function(e) {
            if (e.target === this) closeRejectModal();
        });
    </script>
</body>
</html>