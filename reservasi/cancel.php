<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.use_strict_mode', 1);
session_start();
require_once '../lib/functions.php';
require_once '../lib/auth.php';
requireAuth();
requireModuleAccess('reservasi');
require_once '../config/database.php';

// Validate booking ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    redirect('index.php');
}

// Get booking data
$stmt = mysqli_prepare($connection, "SELECT * FROM booking WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$booking = mysqli_fetch_assoc($result);

if (!$booking) {
    redirect('index.php');
}

// Only allow cancellation for confirmed bookings
if ($booking['status'] !== 'confirmed') {
    redirect('detail.php?id=' . $id);
}

// Get the DP amount that will be refunded
$dp_refund = (float)$booking['dp_bayar'];

// Update booking status to cancelled and reset DP
$update_stmt = mysqli_prepare($connection, "UPDATE booking SET status = 'cancelled', dp_bayar = 0, sisa_bayar = total_bayar WHERE id = ?");
mysqli_stmt_bind_param($update_stmt, "i", $id);

if (mysqli_stmt_execute($update_stmt)) {
    redirect('index.php?cancelled=1&refund=' . $dp_refund);
} else {
    redirect('detail.php?id=' . $id . '&error=cancel_failed');
}
?>
