<?php
session_start();

// Unset all session variables
$_SESSION = [];

// Destroy session
session_destroy();

// Redirect to admin login
header("Location: admin_login.php");
exit;
