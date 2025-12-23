<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Member Portal Login</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-blue-50 flex items-center justify-center p-4">

<div class="w-full max-w-md">

    <!-- Login Card -->
    <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-8">

        <!-- Header -->
        <div class="text-center mb-8">
            <div class="flex justify-center mb-4">
                <div class="w-16 h-16 bg-gradient-to-br from-blue-600 to-blue-700 rounded-2xl flex items-center justify-center shadow-lg">
                    <i data-lucide="users" class="w-10 h-10 text-white"></i>
                </div>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Member Portal Login</h2>
            <p class="text-gray-600">Sign in to access your account</p>
        </div>

        <!-- Login Form -->
        <form id="loginForm" class="space-y-5">

            <!-- Email -->
            <div>
                <label class="block text-sm text-gray-700 mb-2">Email Address</label>
                <div class="relative">
                    <i data-lucide="mail" class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                    <input type="email" name="email" required
                           class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all"
                          >
                </div>
            </div>

            <!-- Password -->
            <div>
                <label class="block text-sm text-gray-700 mb-2">Password</label>
                <div class="relative">
                    <i data-lucide="lock" class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                    <input type="password" name="password" required
                           class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all"
                           placeholder="••••••••">
                </div>
            </div>

            <!-- Error Message -->
            <div id="errorBox"
                 class="hidden bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg text-sm">
            </div>

            <!-- Submit -->
            <button type="submit"
                    class="w-full bg-gradient-to-r from-blue-600 to-blue-700 text-white py-3 rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all shadow-md hover:shadow-lg">
                Sign In to Member Portal
            </button>
        </form>

        <!-- Divider -->
        <div class="relative my-6">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-gray-200"></div>
    </div>
</div>

<!-- JS -->
<script>
lucide.createIcons();

document.getElementById('loginForm').addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(this);
    const errorBox = document.getElementById('errorBox');

    fetch('member_login_process.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.href = data.redirect;
        } else {
            errorBox.textContent = data.message;
            errorBox.classList.remove('hidden');
        }
    })
    .catch(() => {
        errorBox.textContent = 'Something went wrong. Try again.';
        errorBox.classList.remove('hidden');
    });
});
</script>

</body>
</html>
