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
   HANDLE SEND NOTIFICATION
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $type    = trim($_POST['type'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $target  = $_POST['target'] ?? 'all'; // 'all' or specific member_id string

    if ($type === '' || $message === '') {
        $error = "Notification type and message are required.";
    } else {
        if ($target === 'all') {
            // Get all member numeric IDs
            $members_result = $conn->query("SELECT id FROM members");
            $inserted = 0;

            $stmt = $conn->prepare("
                INSERT INTO notifications (member_id, type, message, created_at)
                VALUES (?, ?, ?, NOW())
            ");

            while ($row = $members_result->fetch_assoc()) {
                $stmt->bind_param("iss", $row['id'], $type, $message);
                if ($stmt->execute()) $inserted++;
            }
            $stmt->close();

            $success = "Notification sent to all $inserted members.";
        } else {
            // Send to specific member (target is member_id string like CHM-2025-XXXX)
            $check = $conn->prepare("SELECT id FROM members WHERE member_id = ?");
            $check->bind_param("s", $target);
            $check->execute();
            $member = $check->get_result()->fetch_assoc();
            $check->close();

            if (!$member) {
                $error = "Member not found.";
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO notifications (member_id, type, message, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->bind_param("iss", $member['id'], $type, $message);

                if ($stmt->execute()) {
                    $success = "Notification sent successfully.";
                } else {
                    $error = "Failed to send notification.";
                }
                $stmt->close();
            }
        }
    }
}

/* ===============================
   FETCH MEMBERS FOR DROPDOWN
================================ */
$members = $conn->query("SELECT member_id, full_name FROM members ORDER BY full_name");

/* ===============================
   RECENT SENT NOTIFICATIONS
================================ */
$recent = $conn->query("
    SELECT n.type, n.message, n.created_at, m.full_name, m.member_id
    FROM notifications n
    LEFT JOIN members m ON n.member_id = m.id
    ORDER BY n.created_at DESC
    LIMIT 15
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Notifications</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">

    <!-- SIDEBAR - Blue Theme -->
    <aside class="w-64 bg-gradient-to-b from-blue-700 to-blue-800 text-white p-6">
        <h2 class="text-xl font-bold mb-8">Chama Admin</h2>
        <nav class="space-y-4 text-sm">
            <a href="admin_dashboard.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Dashboard</a>
            <a href="admin_member.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Members</a>
            <a href="admin_add_member.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Add Member</a>
            <a href="admin_contribution.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Contributions</a>
            <a href="admin_loan.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Loan Approvals</a>
            <a href="admin_fines.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Fines</a>
            <a href="admin_notification.php" class="block bg-blue-600 px-4 py-2 rounded font-semibold">Notifications</a>
            <a href="admin_logout.php" class="block text-red-200 hover:bg-red-600 px-4 py-2 rounded">Logout</a>
        </nav>
        <div class="mt-auto pt-6 border-t border-blue-600">
            <p class="text-sm text-blue-200">Welcome, <?= htmlspecialchars($admin_name) ?></p>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 p-8">
        <div class="max-w-5xl mx-auto">

            <h1 class="text-3xl font-bold text-gray-800 mb-8">Send Notifications</h1>

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

            <!-- SEND FORM -->
            <div class="bg-white rounded-xl shadow-lg p-8 mb-10">
                <h2 class="text-xl font-semibold text-gray-800 mb-6">Compose New Notification</h2>
                <form method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Send To</label>
                            <select name="target" required class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500">
                                <option value="all">All Members</option>
                                <?php mysqli_data_seek($members, 0); // Reset pointer ?>
                                <?php while($m = $members->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($m['member_id']) ?>">
                                        <?= htmlspecialchars($m['full_name']) ?> (<?= $m['member_id'] ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notification Type</label>
                            <select name="type" required class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500">
                                <option value="general">General</option>
                                <option value="contribution_reminder">Contribution Reminder</option>
                                <option value="loan_approved">Loan Approved</option>
                                <option value="loan_rejected">Loan Rejected</option>
                                <option value="fine">Fine Issued</option>
                                <option value="meeting">Meeting Reminder</option>
                                <option value="important">Important Update</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Message</label>
                        <textarea name="message" rows="6" required placeholder="Write your message here..."
                                  class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>

                    <div>
                        <button type="submit"
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-lg transition shadow-lg text-lg">
                            Send Notification
                        </button>
                    </div>
                </form>
            </div>

            <!-- RECENTLY SENT -->
            <?php if ($recent->num_rows > 0): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-8 py-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800">Recently Sent Notifications</h2>
                </div>
                <div class="divide-y divide-gray-200">
                    <?php while ($n = $recent->fetch_assoc()): ?>
                    <div class="p-6 hover:bg-gray-50 transition">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-medium text-gray-800"><?= htmlspecialchars($n['message']) ?></p>
                                <p class="text-sm text-gray-600 mt-1">
                                    Type: <span class="font-medium"><?= ucfirst(str_replace('_', ' ', $n['type'])) ?></span>
                                    <?php if ($n['full_name']): ?>
                                        • To: <?= htmlspecialchars($n['full_name']) ?> (<?= $n['member_id'] ?>)
                                    <?php else: ?>
                                        • To: All Members
                                    <?php endif; ?>
                                </p>
                            </div>
                            <p class="text-xs text-gray-500">
                                <?= date("d M Y H:i", strtotime($n['created_at'])) ?>
                            </p>
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