<?php
session_start();
require_once '../lib/functions.php';
require_once '../lib/auth.php';
requireAuth();
requireModuleAccess('kamar');
require_once '../config/database.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('index.php');

$stmt = mysqli_prepare($connection, "SELECT * FROM `kamar` WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$data) redirect('index.php');

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
        $stmt = mysqli_prepare($connection, "UPDATE `kamar` SET `nomor_kamar` = ?, `id_tipe` = ?, `lantai` = ?, `status` = ?, `keterangan` = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "siissi", $nomor_kamar, $id_tipe, $lantai, $status, $keterangan, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            redirect("kamar/index.php");
        } else {
            $error = "Gagal memperbarui: " . mysqli_error($connection);
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
<h2>Edit Kamar</h2>
<?php if ($error): ?><?= showAlert($error, 'danger') ?><?php endif; ?>
<form method="POST">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <div class="row">
        <div class="col-md-4">
            <div class="mb-3">
                <label class="form-label">Nomor Kamar*</label>
                <input type="text" name="nomor_kamar" class="form-control" value="<?= htmlspecialchars($data['nomor_kamar']) ?>" required>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <label class="form-label">Tipe Kamar*</label>
                <?= dropdownFromTable('tipe_kamar', 'id', 'nama_tipe', $data['id_tipe'], 'id_tipe', '-- Pilih Tipe --', 'nama_tipe') ?>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <label class="form-label">Lantai</label>
                <input type="number" name="lantai" class="form-control" value="<?= $data['lantai'] ?>">
            </div>
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
            <option value="tersedia" <?= $data['status'] === 'tersedia' ? 'selected' : '' ?>>Tersedia</option>
            <option value="terisi" <?= $data['status'] === 'terisi' ? 'selected' : '' ?>>Terisi</option>
            <option value="maintenance" <?= $data['status'] === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
            <option value="reserved" <?= $data['status'] === 'reserved' ? 'selected' : '' ?>>Reserved</option>
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label">Keterangan</label>
        <textarea name="keterangan" class="form-control" rows="2"><?= htmlspecialchars($data['keterangan'] ?? '') ?></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Perbarui</button>
    <a href="index.php" class="btn btn-secondary">Batal</a>
</form>
<?php include '../views/'.$THEME.'/lower_block.php'; ?>
<?php include '../views/'.$THEME.'/footer.php'; ?>
