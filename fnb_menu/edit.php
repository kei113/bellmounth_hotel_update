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
$error = '';
$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('fnb_menu/index.php');

// Fetch current item
$stmt = mysqli_prepare($connection, "SELECT * FROM `fnb_menu` WHERE `id` = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$item = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$item) redirect('fnb_menu/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF tidak valid.";
    } else {
        $nama_item = sanitize($_POST['nama_item'] ?? '');
        $deskripsi = sanitize($_POST['deskripsi'] ?? '');
        $harga = (float)str_replace('.', '', $_POST['harga'] ?? '0');
        $kategori = $_POST['kategori'] ?? 'makanan';
        $stok = ($_POST['stok_type'] === 'unlimited') ? null : max(0, (int)($_POST['stok'] ?? 0));
        $is_available = isset($_POST['is_available']) ? 1 : 0;

        $valid_categories = ['makanan', 'minuman', 'snack', 'dessert'];
        if (!in_array($kategori, $valid_categories)) $kategori = 'makanan';

        if (empty($nama_item)) {
            $error = "Nama item wajib diisi.";
        } elseif ($harga <= 0) {
            $error = "Harga harus lebih dari 0.";
        } else {
            $gambar = $item['gambar']; // Keep existing

            // Handle new image upload
            if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === 0) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
                $max_size = 2 * 1024 * 1024;

                if (!in_array($_FILES['gambar']['type'], $allowed_types)) {
                    $error = "Format gambar harus JPG, PNG, atau WebP.";
                } elseif ($_FILES['gambar']['size'] > $max_size) {
                    $error = "Ukuran gambar maksimal 2MB.";
                } else {
                    $upload_dir = '../assets/uploads/fnb/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    
                    // Delete old image
                    if ($item['gambar'] && file_exists($upload_dir . $item['gambar'])) {
                        unlink($upload_dir . $item['gambar']);
                    }
                    
                    $ext = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
                    $gambar = 'FNB_' . uniqid() . '.' . $ext;
                    move_uploaded_file($_FILES['gambar']['tmp_name'], $upload_dir . $gambar);
                }
            }

            if (empty($error)) {
                $stmt = mysqli_prepare($connection, 
                    "UPDATE `fnb_menu` SET `nama_item`=?, `deskripsi`=?, `harga`=?, `kategori`=?, `gambar`=?, `stok`=?, `is_available`=? WHERE `id`=?");
                mysqli_stmt_bind_param($stmt, "ssdssiii", $nama_item, $deskripsi, $harga, $kategori, $gambar, $stok, $is_available, $id);
                
                if (mysqli_stmt_execute($stmt)) {
                    logActivity($connection, 'update', 'fnb_menu', $id, $nama_item,
                        ['harga' => $item['harga'], 'stok' => $item['stok']],
                        ['harga' => $harga, 'stok' => $stok]);
                    redirect('fnb_menu/index.php');
                } else {
                    $error = "Gagal memperbarui: " . mysqli_error($connection);
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}
?>
<?php include '../views/'.$THEME.'/header.php'; ?>
<?php include '../views/'.$THEME.'/sidebar.php'; ?>
<?php include '../views/'.$THEME.'/topnav.php'; ?>
<?php include '../views/'.$THEME.'/upper_block.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-pencil-square me-2"></i>Edit Item Menu</h2>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            
            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Item <span class="text-danger">*</span></label>
                        <input type="text" name="nama_item" class="form-control" required
                               value="<?= htmlspecialchars($item['nama_item']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Deskripsi</label>
                        <textarea name="deskripsi" class="form-control" rows="3"><?= htmlspecialchars($item['deskripsi'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Gambar</label>
                        <?php if ($item['gambar']): ?>
                            <div class="mb-2">
                                <img src="<?= base_url('assets/uploads/fnb/' . $item['gambar']) ?>" 
                                     alt="Preview" class="img-thumbnail" style="max-height: 120px;">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="gambar" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <small class="text-muted">Kosongkan jika tidak ingin mengganti</small>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Harga (Rp) <span class="text-danger">*</span></label>
                        <input type="text" name="harga" class="form-control format-ribuan" required
                               value="<?= number_format($item['harga'], 0, '', '.') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kategori</label>
                        <select name="kategori" class="form-control">
                            <?php foreach (['makanan','minuman','snack','dessert'] as $cat): ?>
                            <option value="<?= $cat ?>" <?= $item['kategori'] === $cat ? 'selected' : '' ?>><?= ucfirst($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Stok</label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="stok_type" id="stok_unlimited" value="unlimited" 
                                   <?= $item['stok'] === null ? 'checked' : '' ?> onchange="toggleStok()">
                            <label class="form-check-label" for="stok_unlimited">Unlimited</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="stok_type" id="stok_limited" value="limited" 
                                   <?= $item['stok'] !== null ? 'checked' : '' ?> onchange="toggleStok()">
                            <label class="form-check-label" for="stok_limited">Terbatas:</label>
                        </div>
                        <input type="number" name="stok" id="stok_input" class="form-control" min="0" 
                               value="<?= $item['stok'] ?? 0 ?>" <?= $item['stok'] === null ? 'disabled' : '' ?>>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_available" id="is_available" 
                           <?= $item['is_available'] ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="is_available">Item Tersedia</label>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan Perubahan</button>
                <a href="index.php" class="btn btn-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>

<script>
function toggleStok() {
    const limited = document.getElementById('stok_limited').checked;
    document.getElementById('stok_input').disabled = !limited;
}
document.querySelectorAll('.format-ribuan').forEach(input => {
    input.addEventListener('input', function() {
        let val = this.value.replace(/\D/g, '');
        this.value = val.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    });
});
</script>

<?php include '../views/'.$THEME.'/lower_block.php'; ?>
<?php include '../views/'.$THEME.'/footer.php'; ?>
