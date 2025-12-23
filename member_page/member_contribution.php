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

// Use the STRING member_id (e.g., MEM-2024-001) - matches your contributions table
$member_id = $_SESSION['member_code'] ?? $_SESSION['member_id'] ?? ''; // Adjust if stored differently
$member_name = $_SESSION['full_name'] ?? 'Member';

if ($member_id === '') {
    die("Member ID not found in session.");
}

$monthly_amount = 2000; // ← CHANGE THIS to your actual monthly contribution amount

$success = "";
$error = "";

/* ===============================
   HANDLE M-PESA PAYMENT INITIATION (STK Push)
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_contribution'])) {

    // In a real system, call Daraja API here to trigger STK Push
    // For now, we'll simulate success and record it (you'll replace with real API later)

    $amount = $monthly_amount;
    $method = "Mpesa";
    $reference = "SIM-" . time(); // Simulate M-Pesa code; replace with real callback

    $stmt = $conn->prepare("
        INSERT INTO contributions 
        (member_id, amount, payment_method, mpesa_reference, contribution_date)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("sdss", $member_id, $amount, $method, $reference);

    if ($stmt->execute()) {
        // Update chama wallet
        $wallet = $conn->prepare("
            UPDATE chama_settings 
            SET total_contributions = total_contributions + ?, 
                total_balance = total_balance + ? 
            WHERE id = 1
        ");
        $wallet->bind_param("dd", $amount, $amount);
        $wallet->execute();

        $success = "Monthly contribution of KES " . number_format($monthly_amount) . " paid successfully via M-Pesa!";
    } else {
        $error = "Payment failed. Try again.";
    }
}

/* ===============================
   FETCH MEMBER'S CONTRIBUTIONS
================================ */
$contributions = $conn->query("
    SELECT amount, payment_method, mpesa_reference, contribution_date
    FROM contributions
    WHERE member_id = '$member_id'
    ORDER BY contribution_date DESC
");

/* ===============================
   CALCULATE TOTALS & CURRENT MONTH STATUS
================================ */
$total_contributed = 0;
$paid_this_month = false;
$current_month = date('Y-m');

while ($row = $contributions->fetch_assoc()) {
    $total_contributed += $row['amount'];
    if (date('Y-m', strtotime($row['contribution_date'])) === $current_month) {
        $paid_this_month = true;
    }
}
// Reset pointer for display
$contributions->data_seek(0);

$is_overdue = !$paid_this_month && (date('j') > 10); // Due on 10th
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Contributions | Chama System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">

    <!-- SIDEBAR -->
    <aside class="w-64 bg-gradient-to-b from-blue-700 to-blue-800 text-white p-6">
        <h2 class="text-xl font-bold mb-8">Chama Member</h2>
        <nav class="space-y-4 text-sm">
            <a href="member_dashboard.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Dashboard</a>
            <a href="member_profile.php" class="block hover:bg-blue-600 px-4 py-2 rounded">My Profile</a>
            <a href="member_contribution.php" class="block bg-blue-600 px-4 py-2 rounded font-semibold">Contributions</a>
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

            <h1 class="text-3xl font-bold text-gray-800 mb-2">My Contributions</h1>
            <p class="text-gray-600 mb-8">Track your monthly payments and history</p>

            <!-- ALERTS -->
            <?php if ($success): ?>
                <div class="bg-green-50 border-l-4 border-green-500 text-green-700 px-6 py-4 rounded mb-8">
                    <p class="font-semibold"><?= $success ?></p>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 px-6 py-4 rounded mb-8">
                    <p class="font-semibold"><?= $error ?></p>
                </div>
            <?php endif; ?>

            <!-- CURRENT STATUS CARD -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <p class="text-sm text-gray-600">Monthly Required</p>
                    <p class="text-3xl font-bold text-blue-600 mt-2">KES <?= number_format($monthly_amount) ?></p>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <p class="text-sm text-gray-600">Total Contributed</p>
                    <p class="text-3xl font-bold text-green-600 mt-2">KES <?= number_format($total_contributed) ?></p>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <p class="text-sm text-gray-600">This Month (<?= date('F Y') ?>)</p>
                    <?php if ($paid_this_month): ?>
                        <p class="text-2xl font-bold text-green-600 mt-2">Paid ✓</p>
                    <?php elseif ($is_overdue): ?>
                        <p class="text-2xl font-bold text-red-600 mt-2">Overdue!</p>
                    <?php else: ?>
                        <p class="text-2xl font-bold text-orange-600 mt-2">Pending</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PAY BUTTON -->
            <?php if (!$paid_this_month): ?>
            <div class="bg-white rounded-xl shadow-lg p-8 max-w-md mb-10">
                <h2 class="text-xl font-semibold text-gray-800 mb-6">Pay This Month's Contribution</h2>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <p class="text-sm text-blue-800">
                        <strong>Amount:</strong> KES <?= number_format($monthly_amount) ?><br>
                        <strong>Method:</strong> M-Pesa (STK Push will be sent to your phone)
                    </p>
                </div>
                <form method="POST">
                    <button name="pay_contribution" value="1"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-lg transition shadow-lg text-lg">
                        Pay KES <?= number_format($monthly_amount) ?> via M-Pesa
                    </button>
                </form>
                <p class="text-xs text-gray-500 text-center mt-4">
                    After payment, confirmation will update automatically
                </p>
            </div>
            <?php endif; ?>

            <!-- CONTRIBUTION HISTORY -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-8 py-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800">Contribution History</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if ($contributions->num_rows > 0): ?>
                                <?php while ($c = $contributions->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 text-gray-700">
                                        <?= date("d M Y", strtotime($c['contribution_date'])) ?>
                                    </td>
                                    <td class="px-6 py-4 font-bold text-green-600">
                                        KES <?= number_format($c['amount'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?= htmlspecialchars($c['payment_method']) ?>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600">
                                        <?= htmlspecialchars($c['mpesa_reference'] ?? '-') ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-16 text-center text-gray-500">
                                        <div class="text-6xl mb-4">📭</div>
                                        <p class="text-xl">No contributions recorded yet</p>
                                        <p class="mt-2">Start by paying this month's contribution above</p>
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