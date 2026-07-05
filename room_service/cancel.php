<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.use_strict_mode', 1);
session_start();
require_once '../lib/auth.php';
require_once '../lib/functions.php';
require_once '../config/database.php';
requireAuth();
requireModuleAccess('room_service');

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('room_service/index.php');

// ===== BEGIN TRANSACTION =====
mysqli_begin_transaction($connection);
try {
    // Lock and fetch the order
    $stmt = mysqli_prepare($connection, 
        "SELECT * FROM room_orders WHERE id = ? AND status = 'pending' FOR UPDATE");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$order) {
        throw new Exception("Pesanan tidak ditemukan atau sudah tidak bisa dibatalkan.");
    }

    // STEP 1: Restore stock for each item
    $details_q = mysqli_prepare($connection, 
        "SELECT rod.menu_id, rod.qty, fm.stok 
         FROM room_order_details rod 
         JOIN fnb_menu fm ON rod.menu_id = fm.id 
         WHERE rod.order_id = ?");
    mysqli_stmt_bind_param($details_q, "i", $id);
    mysqli_stmt_execute($details_q);
    $details_result = mysqli_stmt_get_result($details_q);
    
    while ($detail = mysqli_fetch_assoc($details_result)) {
        // Only restore if item has tracked stock
        if ($detail['stok'] !== null) {
            $restore_stmt = mysqli_prepare($connection,
                "UPDATE fnb_menu SET stok = stok + ? WHERE id = ?");
            mysqli_stmt_bind_param($restore_stmt, "ii", $detail['qty'], $detail['menu_id']);
            mysqli_stmt_execute($restore_stmt);
            mysqli_stmt_close($restore_stmt);
        }
    }
    mysqli_stmt_close($details_q);

    // STEP 2: Subtract from guest folio
    $folio_stmt = mysqli_prepare($connection,
        "UPDATE reservasi SET total_bayar = total_bayar - ?, sisa_bayar = sisa_bayar - ? 
         WHERE id = ? AND total_bayar >= ?");
    $grand_total = $order['grand_total'];
    $reservasi_id = $order['reservasi_id'];
    mysqli_stmt_bind_param($folio_stmt, "ddid", $grand_total, $grand_total, $reservasi_id, $grand_total);
    mysqli_stmt_execute($folio_stmt);
    mysqli_stmt_close($folio_stmt);

    // Ensure sisa_bayar doesn't go negative
    mysqli_query($connection, "UPDATE reservasi SET sisa_bayar = GREATEST(sisa_bayar, 0) WHERE id = $reservasi_id");

    // STEP 3: Mark order as cancelled
    $cancel_stmt = mysqli_prepare($connection, 
        "UPDATE room_orders SET status = 'cancelled' WHERE id = ?");
    mysqli_stmt_bind_param($cancel_stmt, "i", $id);
    mysqli_stmt_execute($cancel_stmt);
    mysqli_stmt_close($cancel_stmt);

    // STEP 4: Log activity
    logActivity($connection, 'cancel', 'room_service', $id, 
        $order['no_order'] . ' - Dibatalkan',
        ['status' => 'pending', 'grand_total' => $grand_total],
        ['status' => 'cancelled']);

    mysqli_commit($connection);
    redirect('room_service/index.php?success=' . urlencode("Pesanan {$order['no_order']} dibatalkan. Biaya Rp " . number_format($grand_total, 0, ',', '.') . " dikembalikan ke folio tamu."));

} catch (Exception $e) {
    mysqli_rollback($connection);
    redirect('room_service/index.php?error=' . urlencode($e->getMessage()));
}
