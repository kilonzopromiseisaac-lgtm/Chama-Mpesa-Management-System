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

$success = "";
$error   = "";

/* ===============================
   APPLY FOR LOAN
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $amount           = (float) $_POST['amount'];
    $repayment_cycle  = (int) $_POST['repayment_cycle'];
    $purpose          = trim($_POST['purpose'] ?? '');
    $interest_rate    = 10; // fixed 10%
    $status           = "pending";

    if ($amount < 1000) {
        $error = "Minimum loan amount is KES 1,000.";
    } elseif ($repayment_cycle <= 0) {
        $error = "Please select a valid repayment period.";
    } elseif ($purpose === '') {
        $error = "Please provide the purpose of the loan.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO loan_applications 
            (member_id, amount, purpose, interest_rate, repayment_cycle, status, application_date)
            VALUES (?, ?, ?, ?, ?, ?, CURDATE())
        ");
        $stmt->bind_param("idsiis", $member_id, $amount, $purpose, $interest_rate, $repayment_cycle, $status);

        if ($stmt->execute()) {
            $success = "Loan application submitted successfully! Admin will review it shortly.";
        } else {
            $error = "Failed to submit application. Please try again.";
        }
        $stmt->close();
    }
}

/* ===============================
   FETCH MEMBER LOAN APPLICATIONS
================================ */
$stmt = $conn->prepare("
    SELECT *
    FROM loan_applications
    WHERE member_id = ?
    ORDER BY id DESC
");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$loans = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Loans | Chama Management System</title>
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
            <a href="member_loan.php" class="block bg-blue-600 px-4 py-2 rounded font-semibold">Loans</a>
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

            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800">My Loans</h1>
                <p class="text-gray-500 mt-2">Apply for a loan or track your existing applications</p>
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

            <!-- LOAN APPLICATION FORM -->
            <div class="bg-white rounded-xl shadow-lg p-8 max-w-2xl mb-10">
                <h2 class="text-xl font-semibold text-gray-800 mb-6">Apply for a New Loan</h2>

                <form method="POST" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Loan Amount (KES)</label>
                        <input type="number" name="amount" min="1000" step="100" required
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="e.g. 15000">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Purpose of Loan</label>
                        <textarea name="purpose" rows="3" required
                                  class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                  placeholder="Briefly explain why you need the loan..."></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Repayment Period</label>
                        <select name="repayment_cycle" required
                                class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Select Period --</option>
                            <option value="3">3 Months</option>
                            <option value="6">6 Months</option>
                            <option value="9">9 Months</option>
                            <option value="12">12 Months</option>
                        </select>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <p class="text-sm text-blue-800">
                            <strong>Note:</strong> Interest rate is fixed at <strong>10%</strong>. 
                            Monthly installments will be calculated upon approval.
                        </p>
                    </div>

                    <button type="submit"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-4 rounded-lg transition shadow-lg">
                        Submit Loan Application
                    </button>
                </form>
            </div>

            <!-- LOAN APPLICATIONS HISTORY -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-8 py-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800">My Loan Applications</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Application ID</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Purpose</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Interest</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Term</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Total Payable</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Monthly</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Applied On</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if ($loans->num_rows > 0): ?>
                                <?php while ($loan = $loans->fetch_assoc()):
                                    $total   = $loan['amount'] + ($loan['amount'] * $loan['interest_rate'] / 100);
                                    $monthly = $loan['repayment_cycle'] > 0 ? round($total / $loan['repayment_cycle'], 2) : 0;
                                ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 font-medium">#<?= $loan['id'] ?></td>
                                    <td class="px-6 py-4 font-bold text-lg">KES <?= number_format($loan['amount']) ?></td>
                                    <td class="px-6 py-4 text-gray-700 max-w-xs truncate"><?= htmlspecialchars($loan['purpose']) ?></td>
                                    <td class="px-6 py-4"><?= $loan['interest_rate'] ?>%</td>
                                    <td class="px-6 py-4"><?= $loan['repayment_cycle'] ?> months</td>
                                    <td class="px-6 py-4 font-semibold">KES <?= number_format($total) ?></td>
                                    <td class="px-6 py-4">KES <?= number_format($monthly) ?></td>
                                    <td class="px-6 py-4">
                                        <span class="inline-block px-4 py-2 rounded-full text-xs font-semibold
                                            <?= $loan['status'] === 'approved' ? 'bg-green-100 text-green-700' :
                                                ($loan['status'] === 'rejected' ? 'bg-red-100 text-red-700' :
                                                'bg-yellow-100 text-yellow-700') ?>">
                                            <?= ucfirst($loan['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600">
                                        <?= date('d M Y', strtotime($loan['application_date'])) ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="px-6 py-16 text-center text-gray-500">
                                        <div class="text-6xl mb-4">💰</div>
                                        <p class="text-xl font-medium">No loan applications yet</p>
                                        <p class="mt-2">Apply for your first loan using the form above!</p>
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