<?php
session_start();

/* ===============================
   DATABASE CONNECTION
================================ */
$conn = new mysqli("localhost", "root", "", "chama");
if ($conn->connect_error) {
    die("Database connection failed");
}

/* ===============================
   HANDLE LOGIN (AJAX)
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    header("Content-Type: application/json");

    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        echo json_encode([
            "success" => false,
            "message" => "Please fill in all fields"
        ]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT id, full_name, password, role
        FROM admins
        WHERE email = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid email or password"
        ]);
        exit;
    }

    $admin = $result->fetch_assoc();

    if (!password_verify($password, $admin['password'])) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid email or password"
        ]);
        exit;
    }

    session_regenerate_id(true);

    $_SESSION['admin_id']        = $admin['id'];
    $_SESSION['admin_name']      = $admin['full_name'];
    $_SESSION['admin_role']      = $admin['role'];
    $_SESSION['admin_logged_in'] = true;

    echo json_encode([
        "success" => true,
        "redirect" => "admin_dashboard.php"
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Portal Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- Tailwind -->
<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-gradient-to-br from-purple-50 via-white to-purple-50 flex items-center justify-center p-4">

<div class="w-full max-w-md">

    <!-- Login Card -->
    <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-8">

        <!-- Header -->
        <div class="text-center mb-8">
            <div class="flex justify-center mb-4">
                <div class="w-16 h-16 bg-gradient-to-br from-purple-600 to-purple-700 rounded-2xl flex items-center justify-center shadow-lg">
                    🛡️
                </div>
            </div>
            <h2 class="text-xl font-semibold text-gray-900 mb-2">Admin Portal Login</h2>
            <p class="text-gray-600">Sign in to manage your chama</p>
        </div>

        <!-- Login Form -->
        <form id="adminLoginForm" class="space-y-5">

            <!-- Email -->
            <div>
                <label class="block text-sm text-gray-700 mb-2">Email Address</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">📧</span>
                    <input id="email"
                           type="email"
                           class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 outline-none"
                           placeholder="admin@chama.com">
                </div>
            </div>

            <!-- Password -->
            <div>
                <label class="block text-sm text-gray-700 mb-2">Password</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">🔒</span>
                    <input id="password"
                           type="password"
                           class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 outline-none"
                           placeholder="••••••••">
                </div>
            </div>

            <!-- Error -->
            <div id="errorBox"
                 class="hidden bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg text-sm">
            </div>

            <!-- Button -->
            <button type="submit"
                    class="w-full bg-gradient-to-r from-purple-600 to-purple-700 text-white py-3 rounded-lg hover:from-purple-700 hover:to-purple-800 transition-all shadow-md hover:shadow-lg">
                Sign In to Admin Portal
            </button>
        </form>

    <!-- Footer -->
    <div class="text-center mt-6 text-sm text-gray-500">
        Chama Admin Portal – Secure Access
    </div>
</div>

<!-- JS -->
<script>
document.getElementById("adminLoginForm").addEventListener("submit", async function (e) {
    e.preventDefault();

    const email = document.getElementById("email").value.trim();
    const password = document.getElementById("password").value.trim();
    const errorBox = document.getElementById("errorBox");

    errorBox.classList.add("hidden");
    errorBox.textContent = "";

    if (!email || !password) {
        errorBox.textContent = "Please fill in all fields";
        errorBox.classList.remove("hidden");
        return;
    }

    const formData = new FormData();
    formData.append("email", email);
    formData.append("password", password);

    try {
        const response = await fetch("admin_login.php", {
            method: "POST",
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            window.location.href = result.redirect;
        } else {
            errorBox.textContent = result.message;
            errorBox.classList.remove("hidden");
        }
    } catch {
        errorBox.textContent = "Server error. Please try again.";
        errorBox.classList.remove("hidden");
    }
});
</script>

</body>
</html>
