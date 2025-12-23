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
   HANDLE ADD MEMBER
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $full_name = trim($_POST['full_name']);
    $email     = trim($_POST['email']);
    $phone     = trim($_POST['phone']);

    if ($full_name === '' || $email === '' || $phone === '') {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {

        // Check duplicate email
        $check = $conn->prepare("SELECT id FROM members WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "This email is already registered.";
        } else {

            // Generate unique Member ID: CHM-YYYY-XXXX
            do {
                $member_id = "CHM-" . date("Y") . "-" . sprintf("%04d", rand(0, 9999));
                $id_check = $conn->prepare("SELECT id FROM members WHERE member_id = ?");
                $id_check->bind_param("s", $member_id);
                $id_check->execute();
                $id_check->store_result();
                $exists = $id_check->num_rows > 0;
                $id_check->close();
            } while ($exists);

            // Generate secure temporary password
            $temp_password_plain = substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$"), 0, 10);
            $password_hash = password_hash($temp_password_plain, PASSWORD_DEFAULT);

            // Insert new member
            $stmt = $conn->prepare("
                INSERT INTO members 
                (member_id, full_name, email, phone, password, status, must_change_password, join_date)
                VALUES (?, ?, ?, ?, ?, 'active', 1, CURDATE())
            ");
            $stmt->bind_param("sssss", $member_id, $full_name, $email, $phone, $password_hash);

            if ($stmt->execute()) {
                $success = "
                    <strong>Member added successfully!</strong><br><br>
                    <strong>Member ID:</strong> $member_id<br>
                    <strong>Full Name:</strong> " . htmlspecialchars($full_name) . "<br>
                    <strong>Email:</strong> $email<br>
                    <strong>Phone:</strong> $phone<br><br>
                    <strong>Temporary Password:</strong> <code class='bg-gray-200 px-3 py-1 rounded'>$temp_password_plain</code><br><br>
                    <small class='text-gray-600'>Member must change password on first login.</small>
                ";
            } else {
                $error = "Failed to add member. Please try again.";
            }
            $stmt->close();
        }
        $check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Add Member</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">

    <!-- SIDEBAR - Blue Theme -->
    <aside class="w-64 bg-gradient-to-b from-blue-700 to-blue-800 text-white p-6">
        <h2 class="text-xl font-bold mb-8">Chama Admin</h2>
        <nav class="space-y-4 text-sm">
            <a href="admin_dashboard.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Dashboard</a>
            <a href="admin_member.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Members</a>
            <a href="admin_add_member.php" class="block bg-blue-600 px-4 py-2 rounded font-semibold">Add Member</a>
            <a href="admin_contribution.php" class="block hover:bg-blue-600 px-4 py-2 rounded">Contributions</a>
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
        <div class="max-w-4xl mx-auto">

            <h1 class="text-3xl font-bold text-gray-800 mb-8">Add New Member</h1>

            <!-- Success / Error Alerts -->
            <?php if ($success): ?>
                <div class="bg-green-50 border-l-4 border-green-500 text-green-700 px-6 py-6 rounded-lg mb-8">
                    <div class="font-semibold text-lg mb-2">Success!</div>
                    <div class="text-sm leading-relaxed"><?= $success ?></div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 px-6 py-4 rounded-lg mb-8">
                    <p class="font-semibold"><?= htmlspecialchars($error) ?></p>
                </div>
            <?php endif; ?>

            <!-- ADD MEMBER FORM -->
            <div class="bg-white rounded-xl shadow-lg p-8">
                <form method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                            <input type="text" name="full_name" required placeholder="e.g. John Doe"
                                   class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                            <input type="email" name="email" required placeholder="john@example.com"
                                   class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                            <input type="text" name="phone" required placeholder="e.g. 0712345678"
                                   class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div class="md:col-span-2">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <p class="text-sm text-blue-800">
                                    <strong>Note:</strong> A secure temporary password will be auto-generated.<br>
                                    The member will be required to change it on first login.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit"
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-lg transition shadow-lg text-lg">
                            Add Member
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </main>
</body>
</html>