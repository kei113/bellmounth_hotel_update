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

// Fetch order
$stmt = mysqli_prepare($connection, "
    SELECT ro.*, CONCAT(UPPER(u.role), ' - ', COALESCE(u.nama, u.username)) as ordered_by_name
    FROM room_orders ro
    LEFT JOIN users u ON ro.ordered_by = u.id
    WHERE ro.id = ?
");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$order) redirect('room_service/index.php');

// Fetch order details
$details = [];
$detail_query = mysqli_prepare($connection, "
    SELECT rod.*, fm.kategori, fm.gambar
    FROM room_order_details rod
    LEFT JOIN fnb_menu fm ON rod.menu_id = fm.id
    WHERE rod.order_id = ?
    ORDER BY rod.id ASC
");
mysqli_stmt_bind_param($detail_query, "i", $id);
mysqli_stmt_execute($detail_query);
$detail_result = mysqli_stmt_get_result($detail_query);
while ($row = mysqli_fetch_assoc($detail_result)) {
    $details[] = $row;
}
mysqli_stmt_close($detail_query);

// Handle status update (admin only for preparing/delivered)
$csrf_token = generateCSRFToken();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_status'])) {
    if (validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $new_status = $_POST['new_status'];
        $valid = ['preparing', 'delivered'];
        if (in_array($new_status, $valid) && $order['status'] !== 'cancelled') {
            $upd = mysqli_prepare($connection, "UPDATE room_orders SET status = ? WHERE id = ?");
            mysqli_stmt_bind_param($upd, "si", $new_status, $id);
            if (mysqli_stmt_execute($upd)) {
                logActivity($connection, 'update', 'room_service', $id, $order['no_order'],
                    ['status' => $order['status']], ['status' => $new_status]);
                redirect("room_service/detail.php?id=$id");
            }
            mysqli_stmt_close($upd);
        }
    }
}

$badge = match($order['status']) {
    'pending' => 'warning', 'preparing' => 'info',
    'delivered' => 'success', 'cancelled' => 'danger', default => 'secondary'
};
?>
<?php include '../views/'.$THEME.'/header.php'; ?>
<?php include '../views/'.$THEME.'/sidebar.php'; ?>
<?php include '../views/'.$THEME.'/topnav.php'; ?>
<?php include '../views/'.$THEME.'/upper_block.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-receipt me-2"></i>Detail Pesanan</h2>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

<div class="row">
    <!-- Order Info -->
    <div class="col-lg-5 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><?= htmlspecialchars($order['no_order']) ?></h5>
                    <span class="badge bg-<?= $badge ?> fs-6"><?= strtoupper($order['status']) ?></span>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr>
                        <td class="text-muted" width="140">Kamar</td>
                        <td><strong><span class="badge bg-dark"><?= htmlspecialchars($order['nomor_kamar']) ?></span></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Nama Tamu</td>
                        <td><strong><?= htmlspecialchars($order['nama_tamu']) ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Diinput Oleh</td>
                        <td><?= htmlspecialchars($order['ordered_by_name'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Waktu Pesan</td>
                        <td><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></td>
                    </tr>
                    <?php if ($order['catatan']): ?>
                    <tr>
                        <td class="text-muted">Catatan</td>
                        <td><em><?= htmlspecialchars($order['catatan']) ?></em></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="text-muted">Grand Total</td>
                        <td><strong class="text-primary fs-5">Rp <?= number_format($order['grand_total'], 0, ',', '.') ?></strong></td>
                    </tr>
                </table>
            </div>
            <?php if ($order['status'] !== 'delivered' && $order['status'] !== 'cancelled'): ?>
            <div class="card-footer bg-light">
                <form method="POST" class="d-flex gap-2">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <?php if ($order['status'] === 'pending'): ?>
                        <button name="new_status" value="preparing" class="btn btn-info btn-sm flex-fill">
                            <i class="bi bi-fire me-1"></i>Mulai Proses
                        </button>
                    <?php endif; ?>
                    <?php if ($order['status'] === 'preparing'): ?>
                        <button name="new_status" value="delivered" class="btn btn-success btn-sm flex-fill">
                            <i class="bi bi-check-circle me-1"></i>Sudah Dikirim
                        </button>
                    <?php endif; ?>
                    <?php if ($order['status'] === 'pending'): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#cancelOrderModal">
                            <i class="bi bi-x-lg me-1"></i>Batal
                        </button>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Cancel Order Modal -->
            <?php if ($order['status'] === 'pending'): ?>
            <div class="modal fade" id="cancelOrderModal" tabindex="-1">
                <div class="modal-dialog modal-sm">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Konfirmasi Pembatalan</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            Batalkan pesanan <strong><?= htmlspecialchars($order['no_order']) ?></strong>?
                            <br><small class="text-muted mt-1 d-block">Biaya Rp <?= number_format($order['grand_total'], 0, ',', '.') ?> akan dikembalikan ke folio tamu.</small>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                            <a href="cancel.php?id=<?= $order['id'] ?>" class="btn btn-danger btn-sm">Batalkan</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Order Items -->
    <div class="col-lg-7 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Item Pesanan (<?= count($details) ?> item)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Item</th>
                                <th class="text-center">Kategori</th>
                                <th class="text-end">Harga</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($details as $d): 
                                $cat_badge = match($d['kategori'] ?? '') {
                                    'makanan' => 'warning', 'minuman' => 'info',
                                    'snack' => 'secondary', 'dessert' => 'danger', default => 'dark'
                                };
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($d['nama_item']) ?></strong></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $cat_badge ?>"><?= ucfirst($d['kategori'] ?? '-') ?></span>
                                </td>
                                <td class="text-end">Rp <?= number_format($d['harga_satuan'], 0, ',', '.') ?></td>
                                <td class="text-center"><?= $d['qty'] ?></td>
                                <td class="text-end fw-semibold">Rp <?= number_format($d['subtotal'], 0, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-light">
                            <tr>
                                <td colspan="4" class="text-end fw-bold">Grand Total</td>
                                <td class="text-end fw-bold text-primary fs-5">Rp <?= number_format($order['grand_total'], 0, ',', '.') ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../views/'.$THEME.'/lower_block.php'; ?>
<?php include '../views/'.$THEME.'/footer.php'; ?>
