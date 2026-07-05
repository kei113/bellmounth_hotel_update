<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.use_strict_mode', 1);
session_start();
require_once '../lib/auth.php';
require_once '../lib/functions.php';
require_once '../config/database.php';
requireAuth();
requireModuleAccess('fnb_menu');

$csrf_token = generateCSRFToken();

// Handle Delete via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF tidak valid.";
    } else {
        $delete_id = (int)$_POST['delete_id'];
        
        // Check if item is used in any active orders
        $check_stmt = mysqli_prepare($connection, 
            "SELECT COUNT(*) as cnt FROM room_order_details rod 
             JOIN room_orders ro ON rod.order_id = ro.id 
             WHERE rod.menu_id = ? AND ro.status NOT IN ('delivered', 'cancelled')");
        mysqli_stmt_bind_param($check_stmt, "i", $delete_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));
        mysqli_stmt_close($check_stmt);
        
        if ($check_result['cnt'] > 0) {
            $error = "Item ini tidak dapat dihapus karena sedang digunakan dalam pesanan aktif.";
        } else {
            $stmt = mysqli_prepare($connection, "DELETE FROM `fnb_menu` WHERE `id` = ?");
            mysqli_stmt_bind_param($stmt, "i", $delete_id);
            if (mysqli_stmt_execute($stmt)) {
                logActivity($connection, 'delete', 'fnb_menu', $delete_id, 'Menu item deleted');
                $success = "Item menu berhasil dihapus.";
            } else {
                $error = "Gagal menghapus item: " . mysqli_error($connection);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Fetch all menu items
$filter_kategori = $_GET['kategori'] ?? '';
$valid_categories = ['makanan', 'minuman', 'snack', 'dessert'];
$where = "";
if ($filter_kategori && in_array($filter_kategori, $valid_categories)) {
    $where = " WHERE kategori = '" . mysqli_real_escape_string($connection, $filter_kategori) . "'";
}
$result = mysqli_query($connection, "SELECT * FROM fnb_menu $where ORDER BY kategori ASC, nama_item ASC");
?>
<?php include '../views/'.$THEME.'/header.php'; ?>
<?php include '../views/'.$THEME.'/sidebar.php'; ?>
<?php include '../views/'.$THEME.'/topnav.php'; ?>
<?php include '../views/'.$THEME.'/upper_block.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-cup-hot-fill me-2"></i>Menu F&B</h2>
    <a href="add.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> Tambah Item
    </a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Filter Tabs -->
<ul class="nav nav-pills mb-4">
    <li class="nav-item">
        <a class="nav-link <?= !$filter_kategori ? 'active' : '' ?>" href="index.php">Semua</a>
    </li>
    <?php foreach ($valid_categories as $cat): ?>
    <li class="nav-item">
        <a class="nav-link <?= $filter_kategori === $cat ? 'active' : '' ?>" href="?kategori=<?= $cat ?>">
            <?= ucfirst($cat) ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<!-- Menu Table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th width="50">#</th>
                        <th>Nama Item</th>
                        <th>Kategori</th>
                        <th class="text-end">Harga</th>
                        <th class="text-center">Stok</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" width="150">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php $no = 1; while($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td>
                            <strong><?= htmlspecialchars($row['nama_item']) ?></strong>
                            <?php if ($row['deskripsi']): ?>
                            <br><small class="text-muted"><?= htmlspecialchars(substr($row['deskripsi'], 0, 80)) ?><?= strlen($row['deskripsi']) > 80 ? '...' : '' ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $cat_badge = match($row['kategori']) {
                                'makanan' => 'warning',
                                'minuman' => 'info',
                                'snack' => 'secondary',
                                'dessert' => 'danger',
                                default => 'dark'
                            };
                            ?>
                            <span class="badge bg-<?= $cat_badge ?>"><?= ucfirst($row['kategori']) ?></span>
                        </td>
                        <td class="text-end fw-semibold">Rp <?= number_format($row['harga'], 0, ',', '.') ?></td>
                        <td class="text-center">
                            <?php if ($row['stok'] === null): ?>
                                <span class="text-muted">∞</span>
                            <?php elseif ($row['stok'] <= 0): ?>
                                <span class="text-danger fw-bold">Habis</span>
                            <?php elseif ($row['stok'] <= 5): ?>
                                <span class="text-warning fw-bold"><?= $row['stok'] ?></span>
                            <?php else: ?>
                                <span><?= $row['stok'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($row['is_available']): ?>
                                <span class="badge bg-success">Tersedia</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $row['id'] ?>" title="Hapus">
                                <i class="bi bi-trash"></i>
                            </button>
                            
                            <!-- Delete Modal -->
                            <div class="modal fade" id="deleteModal<?= $row['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-sm">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Konfirmasi Hapus</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body text-start">
                                            Hapus <strong><?= htmlspecialchars($row['nama_item']) ?></strong>?
                                        </div>
                                        <div class="modal-footer">
                                            <form method="POST">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                                                <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada item menu</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../views/'.$THEME.'/lower_block.php'; ?>
<?php include '../views/'.$THEME.'/footer.php'; ?>
