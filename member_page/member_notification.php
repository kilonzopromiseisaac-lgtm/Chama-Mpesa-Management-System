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

$member_numeric_id = (int) $_SESSION['member_id']; // numeric id
$member_name = $_SESSION['full_name'] ?? 'Member';

/* ===============================
   HANDLE ACTIONS
================================ */
if (isset($_GET['mark_read'])) {
    $nid = (int)$_GET['mark_read'];
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND member_id = ?");
    $stmt->bind_param("ii", $nid, $member_numeric_id);
    $stmt->execute();
    $stmt->close();
    header("Location: member_notifications.php");
    exit;
}

if (isset($_GET['delete'])) {
    $nid = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND member_id = ?");
    $stmt->bind_param("ii", $nid, $member_numeric_id);
    $stmt->execute();
    $stmt->close();
    header("Location: member_notifications.php");
    exit;
}

/* ===============================
   FETCH NOTIFICATIONS
================================ */
$stmt = $conn->prepare("
    SELECT id, type, message, is_read, created_at
    FROM notifications 
    WHERE member_id = ? 
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $member_numeric_id);
$stmt->execute();
$result = $stmt->get_result();
$notifications = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ===============================
   UNREAD COUNT
================================ */
$unread_count = 0;
foreach ($notifications as $n) {
    if (!$n['is_read']) $unread_count++;
}

/* ===============================
   RELATIVE TIME HELPER
================================ */
function formatRelativeTime($datetime) {
    $date = new DateTime($datetime);
    $now = new DateTime();
    $diff = $now->diff($date);

    if ($diff->days == 0) {
        if ($diff->h == 0 && $diff->i < 1) return "Just now";
        if ($diff->h == 0) return $diff->i . " min" . ($diff->i > 1 ? "s" : "") . " ago";
        return $diff->h . " hour" . ($diff->h > 1 ? "s" : "") . " ago";
    } elseif ($diff->days == 1) {
        return "Yesterday";
    } elseif ($diff->days < 7) {
        return $diff->days . " days ago";
    } else {
        return $date->format('M d, Y');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Notifications</title>
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
            <a href="member_fines.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Fines</a>
            <a href="member_notifications.php" class="block bg-blue-600 px-4 py-2 rounded font-semibold relative">
                Notifications
                <?php if ($unread_count > 0): ?>
                    <span class="absolute top-1 right-2 bg-red-500 text-white text-xs rounded-full h-6 w-6 flex items-center justify-center animate-pulse">
                        <?= $unread_count ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="member_logout.php" class="block text-red-200 hover:bg-red-600 px-4 py-2 rounded">Logout</a>
        </nav>
        <div class="mt-auto pt-6 border-t border-blue-600">
            <p class="text-sm text-blue-200">Welcome, <?= htmlspecialchars($member_name) ?></p>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 p-8">
        <div class="max-w-4xl mx-auto">

            <h1 class="text-3xl font-bold text-gray-800 mb-2">My Notifications</h1>
            <p class="text-gray-600 mb-8">Stay updated with important alerts from your chama</p>

            <?php if (empty($notifications)): ?>
                <div class="bg-white rounded-xl shadow-lg p-16 text-center">
                    <div class="text-6xl mb-6">🔔</div>
                    <p class="text-xl font-medium text-gray-600">No notifications yet</p>
                    <p class="text-gray-500 mt-2">You'll see updates about contributions, loans, fines, and more here</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($notifications as $notif): ?>
                        <?php
                        $icon = match($notif['type']) {
                            'fine' => '⚖️',
                            'loan_approved' => '✅',
                            'loan_rejected' => '❌',
                            'contribution_reminder' => '💰',
                            'general', default => '📢'
                        };
                        $bg = $notif['is_read'] ? 'bg-gray-50' : 'bg-blue-50 border-l-4 border-blue-500';
                        ?>
                        <div class="bg-white rounded-xl shadow-md p-6 <?= $bg ?> hover:shadow-lg transition">
                            <div class="flex items-start justify-between">
                                <div class="flex items-start gap-4">
                                    <div class="text-3xl"><?= $icon ?></div>
                                    <div>
                                        <p class="font-semibold text-gray-900">
                                            <?= ucwords(str_replace('_', ' ', $notif['type'])) ?>
                                        </p>
                                        <p class="text-gray-700 mt-1"><?= htmlspecialchars($notif['message']) ?></p>
                                        <p class="text-xs text-gray-500 mt-3">
                                            <?= formatRelativeTime($notif['created_at']) ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex flex-col gap-2 text-sm">
                                    <?php if (!$notif['is_read']): ?>
                                        <a href="?mark_read=<?= $notif['id'] ?>"
                                           class="text-blue-600 hover:underline">Mark as read</a>
                                    <?php endif; ?>
                                    <a href="?delete=<?= $notif['id'] ?>"
                                       onclick="return confirm('Delete this notification?')"
                                       class="text-red-600 hover:underline">Delete</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </main>
</body>
</html>