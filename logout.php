<?php
session_start();
require_once 'config/database.php';
require_once 'lib/activity_logger.php';

// Log logout before destroying session
if (isset($_SESSION['user_id'])) {
    logActivity($connection, 'logout', 'auth', $_SESSION['user_id'], $_SESSION['username'] ?? 'Unknown', null, null);
}

session_destroy();
header("Location: login.php");
exit();
?>
