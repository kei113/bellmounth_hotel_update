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

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) die('Invalid CSRF token.');
    
    $nomor_kamar = trim($_POST['nomor_kamar'] ?? '');
    $id_tipe = (int)($_POST['id_tipe'] ?? 0);
    $lantai = (int)($_POST['lantai'] ?? 1);
    $status = $_POST['status'] ?? 'tersedia';
    $keterangan = trim($_POST['keterangan'] ?? '');
    
    // Validate required fields
    if (empty($nomor_kamar)) {
        $error = "Nomor Kamar wajib diisi.";
    } elseif ($id_tipe <= 0) {
        $error = "Tipe Kamar wajib dipilih.";
    }
    
    // Check for duplicate room number
    if (!$error) {
        $check_stmt = mysqli_prepare($connection, "SELECT id FROM kamar WHERE nomor_kamar = ?");
        mysqli_stmt_bind_param($check_stmt, "s", $nomor_kamar);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $error = "Nomor kamar '$nomor_kamar' sudah terdaftar.";
        }
        mysqli_stmt_close($check_stmt);
    }
    
    if (!$error) {
        $stmt = mysqli_prepare($connection, "INSERT INTO `kamar` (`nomor_kamar`, `id_tipe`, `lantai`, `status`, `keterangan`) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "siiss", $nomor_kamar, $id_tipe, $lantai, $status, $keterangan);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            redirect("room_management/index.php");
        } else {
            $error = "Gagal menyimpan: " . mysqli_error($connection);
        }
        mysqli_stmt_close($stmt);
    }
}

// Get room types for dropdown
$tipe_list = mysqli_query($connection, "SELECT id, nama_tipe, harga_per_malam, kapasitas FROM tipe_kamar WHERE status = 'aktif' ORDER BY nama_tipe ASC");

$csrfToken = generateCSRFToken();
?>
<?php include '../views/'.$THEME.'/header.php'; ?>
<?php include '../views/'.$THEME.'/sidebar.php'; ?>
<?php include '../views/'.$THEME.'/topnav.php'; ?>
<?php include '../views/'.$THEME.'/upper_block.php'; ?>

<style>
/* Add Room Form - Premium Styling */
.form-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
    overflow: hidden;
    max-width: 700px;
}
.form-card-header {
    background: linear-gradient(135deg, #1A202C 0%, #2D3748 100%);
    padding: 20px 24px;
    color: #FFFFFF;
}
.form-card-header h2 {
    font-family: 'Playfair Display', serif;
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
}
.form-card-header small {
    color: #C5A059;
    font-size: 0.85rem;
}
.form-card-body {
    padding: 24px;
}

/* Form Fields */
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #718096;
    margin-bottom: 8px;
}
.form-group label .required {
    color: #C53030;
}
.form-group .form-control,
.form-group .form-select {
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 12px 16px;
    font-size: 0.95rem;
    transition: all 0.2s ease;
}
.form-group .form-control:focus,
.form-group .form-select:focus {
    border-color: #C5A059;
    box-shadow: 0 0 0 3px rgba(197, 160, 89, 0.15);
}

/* Status Options */
.status-options {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}
@media (max-width: 576px) {
    .status-options { grid-template-columns: 1fr; }
}
.status-option {
    position: relative;
}
.status-option input[type="radio"] {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
}
.status-option label {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #F7FAFC;
    border: 2px solid #E2E8F0;
    border-radius: 10px;
    padding: 14px 16px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-transform: none;
    letter-spacing: 0;
    font-weight: 500;
}
.status-option input[type="radio"]:checked + label {
    border-color: #1A202C;
    background: #FFFFFF;
}
.status-option .status-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}
.status-option .status-icon.tersedia { background: #C6F6D5; color: #22543D; }
.status-option .status-icon.kotor { background: #FED7D7; color: #822727; }
.status-option .status-icon.maintenance { background: #FEFCBF; color: #744210; }
.status-option .status-icon.terisi { background: #BEE3F8; color: #2A4365; }
.status-option .status-text {
    font-size: 0.95rem;
    color: #1A202C;
}
.status-option .status-desc {
    font-size: 0.75rem;
    color: #718096;
}

/* Room Type Cards */
.type-options {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}
@media (max-width: 576px) {
    .type-options { grid-template-columns: 1fr; }
}
.type-option {
    position: relative;
}
.type-option input[type="radio"] {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
}
.type-option label {
    display: block;
    background: #F7FAFC;
    border: 2px solid #E2E8F0;
    border-radius: 10px;
    padding: 14px 16px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-transform: none;
    letter-spacing: 0;
}
.type-option input[type="radio"]:checked + label {
    border-color: #C5A059;
    background: linear-gradient(135deg, #FFFDF7 0%, #FFF9E6 100%);
}
.type-option .type-name {
    font-weight: 600;
    color: #1A202C;
    font-size: 0.95rem;
    margin-bottom: 4px;
}
.type-option .type-info {
    font-size: 0.8rem;
    color: #718096;
}
.type-option .type-price {
    font-weight: 600;
    color: #C5A059;
    font-size: 0.9rem;
    margin-top: 6px;
}

/* Form Footer */
.form-footer {
    display: flex;
    gap: 12px;
    padding-top: 20px;
    border-top: 1px solid #E2E8F0;
}
.btn-submit {
    background: linear-gradient(135deg, #1A202C 0%, #2D3748 100%);
    color: #FFFFFF;
    border: none;
    padding: 12px 28px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.95rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}
.btn-submit:hover {
    box-shadow: 0 4px 12px rgba(26, 32, 44, 0.3);
    transform: translateY(-2px);
    color: #FFFFFF;
}
.btn-cancel {
    background: #FFFFFF;
    color: #4A5568;
    border: 1px solid #E2E8F0;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 500;
    font-size: 0.95rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    text-decoration: none;
}
.btn-cancel:hover {
    background: #F7FAFC;
    border-color: #CBD5E0;
    color: #1A202C;
}
</style>

<div class="form-card">
    <div class="form-card-header">
        <h2><i class="bi bi-plus-circle me-2"></i>Tambah Kamar Baru</h2>
        <small>Isi data kamar baru untuk ditambahkan ke sistem</small>
    </div>
    
    <div class="form-card-body">
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Nomor Kamar <span class="required">*</span></label>
                        <input type="text" name="nomor_kamar" class="form-control" 
                               placeholder="Contoh: 101, 201A" required
                               value="<?= htmlspecialchars($_POST['nomor_kamar'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Lantai</label>
                        <input type="number" name="lantai" class="form-control" 
                               min="1" max="99" placeholder="1"
                               value="<?= htmlspecialchars($_POST['lantai'] ?? '1') ?>">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Tipe Kamar <span class="required">*</span></label>
                <div class="type-options">
                    <?php while ($tipe = mysqli_fetch_assoc($tipe_list)): ?>
                    <div class="type-option">
                        <input type="radio" name="id_tipe" id="tipe_<?= $tipe['id'] ?>" 
                               value="<?= $tipe['id'] ?>" 
                               <?= (isset($_POST['id_tipe']) && $_POST['id_tipe'] == $tipe['id']) ? 'checked' : '' ?>>
                        <label for="tipe_<?= $tipe['id'] ?>">
                            <div class="type-name"><?= htmlspecialchars($tipe['nama_tipe']) ?></div>
                            <div class="type-info">
                                <i class="bi bi-people-fill me-1"></i><?= $tipe['kapasitas'] ?> orang
                            </div>
                            <div class="type-price">
                                Rp <?= number_format($tipe['harga_per_malam'], 0, ',', '.') ?>/malam
                            </div>
                        </label>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label>Status Kamar <small class="text-muted fw-normal">(Status "Terisi" diatur otomatis oleh sistem booking)</small></label>
                <div class="status-options">
                    <div class="status-option">
                        <input type="radio" name="status" id="status_tersedia" value="tersedia" 
                               <?= (!isset($_POST['status']) || $_POST['status'] === 'tersedia') ? 'checked' : '' ?>>
                        <label for="status_tersedia">
                            <div class="status-icon tersedia"><i class="bi bi-check-circle-fill"></i></div>
                            <div>
                                <div class="status-text">Bersih / Tersedia</div>
                                <div class="status-desc">Siap untuk ditempati</div>
                            </div>
                        </label>
                    </div>
                    <div class="status-option">
                        <input type="radio" name="status" id="status_kotor" value="kotor"
                               <?= (isset($_POST['status']) && $_POST['status'] === 'kotor') ? 'checked' : '' ?>>
                        <label for="status_kotor">
                            <div class="status-icon kotor"><i class="bi bi-exclamation-circle-fill"></i></div>
                            <div>
                                <div class="status-text">Kotor</div>
                                <div class="status-desc">Perlu dibersihkan</div>
                            </div>
                        </label>
                    </div>
                    <div class="status-option">
                        <input type="radio" name="status" id="status_maintenance" value="maintenance"
                               <?= (isset($_POST['status']) && $_POST['status'] === 'maintenance') ? 'checked' : '' ?>>
                        <label for="status_maintenance">
                            <div class="status-icon maintenance"><i class="bi bi-tools"></i></div>
                            <div>
                                <div class="status-text">Maintenance</div>
                                <div class="status-desc">Dalam perbaikan</div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Keterangan</label>
                <textarea name="keterangan" class="form-control" rows="3" 
                          placeholder="Catatan tambahan tentang kamar (opsional)"><?= htmlspecialchars($_POST['keterangan'] ?? '') ?></textarea>
            </div>
            
            <div class="form-footer">
                <button type="submit" class="btn-submit">
                    <i class="bi bi-check-lg"></i>Simpan Kamar
                </button>
                <a href="index.php" class="btn-cancel">
                    <i class="bi bi-x-lg"></i>Batal
                </a>
            </div>
        </form>
    </div>
</div>

<?php include '../views/'.$THEME.'/lower_block.php'; ?>
<?php include '../views/'.$THEME.'/footer.php'; ?>
