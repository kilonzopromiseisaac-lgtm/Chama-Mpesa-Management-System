<?php
require_once "admin_auth.php";

/* ===============================
   DATABASE CONNECTION
================================ */
$conn = new mysqli("localhost", "root", "", "chama");
if ($conn->connect_error) {
    die("Database connection failed");
}

/* ===============================
   HANDLE CREATE MEMBER (AJAX)
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    header("Content-Type: application/json");

    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');

    if ($full_name === '' || $email === '') {
        echo json_encode([
            "success" => false,
            "message" => "All fields are required"
        ]);
        exit;
    }

    // Check duplicate email
    $check = $conn->prepare("SELECT id FROM members WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode([
            "success" => false,
            "message" => "Email already exists"
        ]);
        exit;
    }

    // Generate member ID
    $member_id = "CHM-" . strtoupper(bin2hex(random_bytes(3)));

    // Generate temporary password
    $temp_password = substr(bin2hex(random_bytes(4)), 0, 8);
    $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

    // Insert member
    $stmt = $conn->prepare("
        INSERT INTO members 
        (member_id, full_name, email, password, must_change_password)
        VALUES (?, ?, ?, ?, 1)
    ");
    $stmt->bind_param(
        "ssss",
        $member_id,
        $full_name,
        $email,
        $hashed_password
    );

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "member_id" => $member_id,
            "temp_password" => $temp_password
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Failed to create member"
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Create Member</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen p-8">

<div class="max-w-lg mx-auto bg-white rounded-xl shadow-lg p-6">

    <h2 class="text-xl font-semibold mb-4">Create New Member</h2>

    <form id="memberForm" class="space-y-4">

        <div>
            <label class="block text-sm text-gray-700 mb-1">Full Name</label>
            <input id="full_name"
                   class="w-full px-4 py-2 border rounded-lg"
                   placeholder="John Doe">
        </div>

        <div>
            <label class="block text-sm text-gray-700 mb-1">Email</label>
            <input id="email"
                   type="email"
                   class="w-full px-4 py-2 border rounded-lg"
                   placeholder="member@email.com">
        </div>

        <div id="errorBox"
             class="hidden bg-red-50 border border-red-200 text-red-600 px-4 py-2 rounded text-sm">
        </div>

        <button type="submit"
                class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700">
            Create Member
        </button>
    </form>

    <!-- Success -->
    <div id="successBox"
         class="hidden mt-4 bg-green-50 border border-green-200 text-green-700 p-4 rounded">
        <p><strong>Member Created Successfully!</strong></p>
        <p>Member ID: <span id="mid"></span></p>
        <p>Temporary Password: <span id="pwd"></span></p>
        <p class="text-xs mt-2">Member must change password on first login.</p>
    </div>

</div>

<script>
document.getElementById("memberForm").addEventListener("submit", async function (e) {
    e.preventDefault();

    const fullName = document.getElementById("full_name").value.trim();
    const email = document.getElementById("email").value.trim();
    const errorBox = document.getElementById("errorBox");
    const successBox = document.getElementById("successBox");

    errorBox.classList.add("hidden");
    successBox.classList.add("hidden");

    if (!fullName || !email) {
        errorBox.textContent = "All fields are required";
        errorBox.classList.remove("hidden");
        return;
    }

    const formData = new FormData();
    formData.append("full_name", fullName);
    formData.append("email", email);

    const response = await fetch("admin_create_member.php", {
        method: "POST",
        body: formData
    });

    const result = await response.json();

    if (result.success) {
        document.getElementById("mid").textContent = result.member_id;
        document.getElementById("pwd").textContent = result.temp_password;
        successBox.classList.remove("hidden");
        document.getElementById("memberForm").reset();
    } else {
        errorBox.textContent = result.message;
        errorBox.classList.remove("hidden");
    }
});
</script>

</body>
</html>
