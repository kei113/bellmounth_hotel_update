<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.use_strict_mode', 1);
session_start();
require_once '../lib/functions.php';
require_once '../lib/auth.php';
requireAuth();
requireModuleAccess('room_management');
require_once '../config/database.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('room_management/index.php');

// Check if room exists
$stmt = mysqli_prepare($connection, "SELECT id, nomor_kamar FROM kamar WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$room = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$room) redirect('room_management/index.php');

// Check if room is currently in use (has active reservasi)
$booking_check = mysqli_prepare($connection, "
    SELECT b.id, b.no_reservasi 
    FROM reservasi_detail bd 
    JOIN reservasi b ON bd.reservasi_id = b.id 
    WHERE bd.kamar_id = ? AND b.status NOT IN ('checkout', 'cancelled')
    LIMIT 1
");
mysqli_stmt_bind_param($booking_check, "i", $id);
mysqli_stmt_execute($booking_check);
$active_booking = mysqli_fetch_assoc(mysqli_stmt_get_result($booking_check));
mysqli_stmt_close($booking_check);

if ($active_booking) {
    // Room has active reservasi, cannot delete
    $_SESSION['error_message'] = "Kamar " . htmlspecialchars($room['nomor_kamar']) . " tidak dapat dihapus karena masih digunakan di reservasi #" . htmlspecialchars($active_booking['no_reservasi']);
    redirect('room_management/index.php');
}

// Delete room
$delete_stmt = mysqli_prepare($connection, "DELETE FROM kamar WHERE id = ?");
mysqli_stmt_bind_param($delete_stmt, "i", $id);

if (mysqli_stmt_execute($delete_stmt)) {
    $_SESSION['success_message'] = "Kamar " . htmlspecialchars($room['nomor_kamar']) . " berhasil dihapus.";
} else {
    $_SESSION['error_message'] = "Gagal menghapus kamar: " . mysqli_error($connection);
}
mysqli_stmt_close($delete_stmt);

redirect('room_management/index.php');
?>
