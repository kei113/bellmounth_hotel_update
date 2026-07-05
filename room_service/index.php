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

$csrf_token = generateCSRFToken();

// Filter
$filter_status = $_GET['status'] ?? '';
$valid_statuses = ['pending', 'preparing', 'delivered', 'cancelled'];

$where = "";
if ($filter_status && in_array($filter_status, $valid_statuses)) {
    $where = " WHERE ro.status = '" . mysqli_real_escape_string($connection, $filter_status) . "'";
}

$result = mysqli_query($connection, "
    SELECT ro.*, CONCAT(UPPER(u.role), ' - ', COALESCE(u.nama, u.username)) as ordered_by_name
    FROM room_orders ro
    LEFT JOIN users u ON ro.ordered_by = u.id
    $where
    ORDER BY ro.id DESC
    LIMIT 50
");
?>
<?php include '../views/'.$THEME.'/header.php'; ?>
<?php include '../views/'.$THEME.'/sidebar.php'; ?>
<?php include '../views/'.$THEME.'/topnav.php'; ?>
<?php include '../views/'.$THEME.'/upper_block.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-cart3 me-2"></i>Room Service</h2>
    <a href="create.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> Buat Pesanan Baru
    </a>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= htmlspecialchars($_GET['success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars($_GET['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Status Filter -->
<ul class="nav nav-pills mb-4">
    <li class="nav-item">
        <a class="nav-link <?= !$filter_status ? 'active' : '' ?>" href="index.php">Semua</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $filter_status === 'pending' ? 'active' : '' ?>" href="?status=pending">
            <i class="bi bi-clock me-1"></i>Pending
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $filter_status === 'preparing' ? 'active' : '' ?>" href="?status=preparing">
            <i class="bi bi-fire me-1"></i>Preparing
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $filter_status === 'delivered' ? 'active' : '' ?>" href="?status=delivered">
            <i class="bi bi-check-circle me-1"></i>Delivered
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $filter_status === 'cancelled' ? 'active' : '' ?>" href="?status=cancelled">
            <i class="bi bi-x-circle me-1"></i>Cancelled
        </a>
    </li>
</ul>

<!-- Orders Table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>No. Order</th>
                        <th>Kamar</th>
                        <th>Nama Tamu</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Status</th>
                        <th>Diinput Oleh</th>
                        <th>Waktu</th>
                        <th class="text-center" width="120">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php while($row = mysqli_fetch_assoc($result)): 
                        $badge = match($row['status']) {
                            'pending' => 'warning',
                            'preparing' => 'info',
                            'delivered' => 'success',
                            'cancelled' => 'danger',
                            default => 'secondary'
                        };
                        $icon = match($row['status']) {
                            'pending' => 'bi-clock',
                            'preparing' => 'bi-fire',
                            'delivered' => 'bi-check-circle-fill',
                            'cancelled' => 'bi-x-circle-fill',
                            default => 'bi-question'
                        };
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['no_order']) ?></strong></td>
                        <td><span class="badge bg-dark"><?= htmlspecialchars($row['nomor_kamar']) ?></span></td>
                        <td><?= htmlspecialchars($row['nama_tamu']) ?></td>
                        <td class="text-end fw-semibold">Rp <?= number_format($row['grand_total'], 0, ',', '.') ?></td>
                        <td class="text-center">
                            <span class="badge bg-<?= $badge ?>">
                                <i class="<?= $icon ?> me-1"></i><?= strtoupper($row['status']) ?>
                            </span>
                        </td>
                        <td><small class="text-muted"><?= htmlspecialchars($row['ordered_by_name'] ?? '-') ?></small></td>
                        <td><small><?= date('d/m H:i', strtotime($row['created_at'])) ?></small></td>
                        <td class="text-center">
                            <a href="detail.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary" title="Detail">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if ($row['status'] === 'pending'): ?>
                            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal<?= $row['id'] ?>" title="Batalkan">
                                <i class="bi bi-x-lg"></i>
                            </button>
                            
                            <!-- Cancel Modal -->
                            <div class="modal fade" id="cancelModal<?= $row['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-sm">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Konfirmasi Pembatalan</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body text-start">
                                            Batalkan pesanan <strong><?= htmlspecialchars($row['no_order']) ?></strong>?
                                            <br><small class="text-muted mt-1 d-block">Biaya Rp <?= number_format($row['grand_total'], 0, ',', '.') ?> akan dikembalikan ke folio tamu.</small>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                                            <a href="cancel.php?id=<?= $row['id'] ?>" class="btn btn-danger btn-sm">Batalkan</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">Belum ada pesanan room service</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../views/'.$THEME.'/lower_block.php'; ?>
<?php include '../views/'.$THEME.'/footer.php'; ?>
