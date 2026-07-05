<?php
ini_set('session.cookie_httponly', 1);
session_start();
require_once '../lib/functions.php';
require_once '../lib/auth.php';
requireAuth();
requireModuleAccess('tipe_kamar');
require_once '../config/database.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) die('Invalid CSRF token.');
    
    $nama_tipe = trim($_POST['nama_tipe'] ?? '');
    $harga_per_malam = (float)($_POST['harga_per_malam'] ?? 0);
    $kapasitas = (int)($_POST['kapasitas'] ?? 2);
    $fasilitas = trim($_POST['fasilitas'] ?? '');
    $status = $_POST['status'] ?? 'aktif';
    
    if (empty($nama_tipe)) {
        $error = "Nama Tipe wajib diisi.";
    }
    
    if (!$error) {
        $stmt = mysqli_prepare($connection, "INSERT INTO `tipe_kamar` (`nama_tipe`, `harga_per_malam`, `kapasitas`, `fasilitas`, `status`) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sdiss", $nama_tipe, $harga_per_malam, $kapasitas, $fasilitas, $status);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            redirect("tipe_kamar/index.php");
            exit();
        } else {
            $error = "Gagal menyimpan: " . mysqli_error($connection);
        }
        mysqli_stmt_close($stmt);
    }
}
$csrfToken = generateCSRFToken();
?>
<?php include '../views/'.$THEME.'/header.php'; ?>
<?php include '../views/'.$THEME.'/sidebar.php'; ?>
<?php include '../views/'.$THEME.'/topnav.php'; ?>
<?php include '../views/'.$THEME.'/upper_block.php'; ?>
<h2>Tambah Tipe Kamar</h2>
<?php if ($error): ?><?= showAlert($error, 'danger') ?><?php endif; ?>
<form method="POST">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <div class="mb-3">
        <label class="form-label">Nama Tipe*</label>
        <input type="text" name="nama_tipe" class="form-control" required>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label class="form-label">Harga Per Malam</label>
                <input type="number" name="harga_per_malam" class="form-control" value="0">
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label class="form-label">Kapasitas</label>
                <input type="number" name="kapasitas" class="form-control" value="2">
            </div>
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label">Fasilitas</label>
        <textarea name="fasilitas" class="form-control" rows="3"></textarea>
    </div>
    <div class="mb-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
            <option value="aktif">Aktif</option>
            <option value="nonaktif">Nonaktif</option>
        </select>
    </div>
    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="index.php" class="btn btn-secondary">Batal</a>
</form>
<?php include '../views/'.$THEME.'/lower_block.php'; ?>
<?php include '../views/'.$THEME.'/footer.php'; ?>
