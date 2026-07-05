<?php
session_start();
require_once '../lib/functions.php';
require_once '../lib/auth.php';
requireAuth();
requireModuleAccess('kamar');
require_once '../config/database.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) die('Invalid CSRF token.');
    
    $nomor_kamar = trim($_POST['nomor_kamar'] ?? '');
    $id_tipe = (int)($_POST['id_tipe'] ?? 0);
    $lantai = (int)($_POST['lantai'] ?? 1);
    $status = $_POST['status'] ?? 'tersedia';
    $keterangan = trim($_POST['keterangan'] ?? '');
    
    if (empty($nomor_kamar) || $id_tipe <= 0) {
        $error = "Nomor Kamar dan Tipe Kamar wajib diisi.";
    }
    
    if (!$error) {
        $stmt = mysqli_prepare($connection, "INSERT INTO `kamar` (`nomor_kamar`, `id_tipe`, `lantai`, `status`, `keterangan`) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "siiss", $nomor_kamar, $id_tipe, $lantai, $status, $keterangan);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            redirect("kamar/index.php");
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
<h2>Tambah Kamar</h2>
<?php if ($error): ?><?= showAlert($error, 'danger') ?><?php endif; ?>
<form method="POST">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <div class="row">
        <div class="col-md-4">
            <div class="mb-3">
                <label class="form-label">Nomor Kamar*</label>
                <input type="text" name="nomor_kamar" class="form-control" required>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <label class="form-label">Tipe Kamar*</label>
                <?= dropdownFromTable('tipe_kamar', 'id', 'nama_tipe', '', 'id_tipe', '-- Pilih Tipe --', 'nama_tipe', "status = 'aktif'") ?>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <label class="form-label">Lantai</label>
                <input type="number" name="lantai" class="form-control" value="1">
            </div>
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
            <option value="tersedia">Tersedia</option>
            <option value="terisi">Terisi</option>
            <option value="maintenance">Maintenance</option>
            <option value="reserved">Reserved</option>
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label">Keterangan</label>
        <textarea name="keterangan" class="form-control" rows="2"></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="index.php" class="btn btn-secondary">Batal</a>
</form>
<?php include '../views/'.$THEME.'/lower_block.php'; ?>
<?php include '../views/'.$THEME.'/footer.php'; ?>
