<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
ini_set('session.use_strict_mode', 1);
session_start();
require_once __DIR__ . '/lib/auth.php';
if (!isset($_SESSION['user_id'])) {
header('Location: login.php');
exit();
}
redirectBasedOnRole($_SESSION['role']);
?>
