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

// Fetch room data
$stmt = mysqli_prepare($connection, "SELECT k.*, t.nama_tipe, t.harga_per_malam FROM kamar k LEFT JOIN tipe_kamar t ON k.id_tipe = t.id WHERE k.id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$room = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$room) redirect('room_management/index.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) die('Invalid CSRF token.');
    
    $nomor_kamar = trim($_POST['nomor_kamar'] ?? '');
    $id_tipe = (int)($_POST['id_tipe'] ?? 0);
    $lantai = (int)($_POST['lantai'] ?? 1);
    
    // If room is terisi or reserved, preserve the existing status to prevent manual overwrite
    $status = $room['status'];
    if ($room['status'] !== 'terisi' && $room['status'] !== 'reserved') {
        $status = $_POST['status'] ?? 'tersedia';
    }
    
    $keterangan = trim($_POST['keterangan'] ?? '');
    
    // Validate required fields
    if (empty($nomor_kamar)) {
        $error = "Nomor Kamar wajib diisi.";
    } elseif ($id_tipe <= 0) {
        $error = "Tipe Kamar wajib dipilih.";
    }
    
    // Check for duplicate room number (exclude current room)
    if (!$error) {
        $check_stmt = mysqli_prepare($connection, "SELECT id FROM kamar WHERE nomor_kamar = ? AND id != ?");
        mysqli_stmt_bind_param($check_stmt, "si", $nomor_kamar, $id);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $error = "Nomor kamar '$nomor_kamar' sudah digunakan oleh kamar lain.";
        }
        mysqli_stmt_close($check_stmt);
    }
    
    if (!$error) {
        $stmt = mysqli_prepare($connection, "UPDATE `kamar` SET `nomor_kamar` = ?, `id_tipe` = ?, `lantai` = ?, `status` = ?, `keterangan` = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "siissi", $nomor_kamar, $id_tipe, $lantai, $status, $keterangan, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            redirect("room_management/index.php");
        } else {
            $error = "Gagal memperbarui: " . mysqli_error($connection);
        }
        mysqli_stmt_close($stmt);
    }
}

// Get room types for dropdown
$tipe_list = mysqli_query($connection, "SELECT id, nama_tipe, harga_per_malam, kapasitas FROM tipe_kamar WHERE status = 'aktif' ORDER BY nama_tipe ASC");

// Get room thumbnail based on type
function getRoomThumbnail($nama_tipe) {
    $tipe_lower = strtolower($nama_tipe ?? '');
    if (strpos($tipe_lower, 'presidential') !== false) return 'presidential_suite.jpg';
    if (strpos($tipe_lower, 'suite') !== false) return 'suite.jpg';
    if (strpos($tipe_lower, 'superior') !== false) return 'superior.jpg';
    if (strpos($tipe_lower, 'deluxe') !== false) return 'deluxe.jpg';
    return 'standard.jpg';
}

$thumb_file = getRoomThumbnail($room['nama_tipe']);
$thumb_url = base_url() . 'assets/default/images/' . $thumb_file;

$csrfToken = generateCSRFToken();
?>
<?php include '../views/'.$THEME.'/header.php'; ?>
<?php include '../views/'.$THEME.'/sidebar.php'; ?>
<?php include '../views/'.$THEME.'/topnav.php'; ?>
<?php include '../views/'.$THEME.'/upper_block.php'; ?>

<style>
/* Edit Room Form - Premium Styling */
.form-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
    overflow: hidden;
    max-width: 800px;
}
.form-card-header {
    background: linear-gradient(135deg, #1A202C 0%, #2D3748 100%);
    padding: 20px 24px;
    color: #FFFFFF;
    display: flex;
    align-items: center;
    gap: 20px;
}
.form-card-header .room-preview-thumb {
    width: 80px;
    height: 60px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid rgba(255,255,255,0.2);
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
.form-card-header .current-status {
    margin-left: auto;
}
.form-card-body {
    padding: 24px;
}

/* Current Room Info */
.current-room-info {
    background: linear-gradient(135deg, #F7FAFC 0%, #EDF2F7 100%);
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 24px;
    display: flex;
    flex-wrap: wrap;
    gap: 24px;
}
.current-room-info .info-item {
    flex: 1;
    min-width: 120px;
}
.current-room-info .info-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #718096;
    margin-bottom: 4px;
}
.current-room-info .info-value {
    font-size: 1rem;
    font-weight: 600;
    color: #1A202C;
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
        <img src="<?= $thumb_url ?>" alt="<?= htmlspecialchars($room['nama_tipe']) ?>" class="room-preview-thumb"
             onerror="this.src='<?= base_url() ?>assets/default/images/standard.jpg'">
        <div>
            <h2><i class="bi bi-pencil me-2"></i>Edit Kamar <?= htmlspecialchars($room['nomor_kamar']) ?></h2>
            <small><?= htmlspecialchars($room['nama_tipe'] ?? 'No Type') ?></small>
        </div>
        <div class="current-status">
            <?php 
            $status_badges = [
                'tersedia' => ['bg-success', 'Bersih'],
                'kotor' => ['bg-danger', 'Kotor'],
                'maintenance' => ['bg-warning text-dark', 'Maintenance'],
                'terisi' => ['bg-primary', 'Terisi'],
            ];
            $badge = $status_badges[$room['status']] ?? ['bg-secondary', 'Unknown'];
            ?>
            <span class="badge <?= $badge[0] ?> px-3 py-2"><?= $badge[1] ?></span>
        </div>
    </div>
    
    <div class="form-card-body">
        <!-- Current Room Info Summary -->
        <div class="current-room-info">
            <div class="info-item">
                <div class="info-label">Lantai</div>
                <div class="info-value"><?= $room['lantai'] ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Harga/Malam</div>
                <div class="info-value">Rp <?= number_format($room['harga_per_malam'] ?? 0, 0, ',', '.') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">ID Kamar</div>
                <div class="info-value">#<?= $room['id'] ?></div>
            </div>
        </div>
        
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
                               value="<?= htmlspecialchars($_POST['nomor_kamar'] ?? $room['nomor_kamar']) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Lantai</label>
                        <input type="number" name="lantai" class="form-control" 
                               min="1" max="99" placeholder="1"
                               value="<?= htmlspecialchars($_POST['lantai'] ?? $room['lantai']) ?>">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Tipe Kamar <span class="required">*</span></label>
                <div class="type-options">
                    <?php while ($tipe = mysqli_fetch_assoc($tipe_list)): 
                        $selected_tipe = $_POST['id_tipe'] ?? $room['id_tipe'];
                    ?>
                    <div class="type-option">
                        <input type="radio" name="id_tipe" id="tipe_<?= $tipe['id'] ?>" 
                               value="<?= $tipe['id'] ?>" 
                               <?= ($selected_tipe == $tipe['id']) ? 'checked' : '' ?>>
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
                    <?php 
                    $current_status = $_POST['status'] ?? $room['status']; 
                    $is_occupied_or_reserved = ($room['status'] === 'terisi' || $room['status'] === 'reserved');
                    ?>
                    <div class="status-option">
                        <input type="radio" name="status" id="status_tersedia" value="tersedia" 
                               <?= ($current_status === 'tersedia') ? 'checked' : '' ?> <?= $is_occupied_or_reserved ? 'disabled' : '' ?>>
                        <label for="status_tersedia" <?= $is_occupied_or_reserved ? 'style="opacity: 0.5; cursor: not-allowed;"' : '' ?>>
                            <div class="status-icon tersedia"><i class="bi bi-check-circle-fill"></i></div>
                            <div>
                                <div class="status-text">Bersih / Tersedia</div>
                                <div class="status-desc">Siap untuk ditempati</div>
                            </div>
                        </label>
                    </div>
                    <div class="status-option">
                        <input type="radio" name="status" id="status_kotor" value="kotor"
                               <?= ($current_status === 'kotor') ? 'checked' : '' ?> <?= $is_occupied_or_reserved ? 'disabled' : '' ?>>
                        <label for="status_kotor" <?= $is_occupied_or_reserved ? 'style="opacity: 0.5; cursor: not-allowed;"' : '' ?>>
                            <div class="status-icon kotor"><i class="bi bi-exclamation-circle-fill"></i></div>
                            <div>
                                <div class="status-text">Kotor</div>
                                <div class="status-desc">Perlu dibersihkan</div>
                            </div>
                        </label>
                    </div>
                    <div class="status-option">
                        <input type="radio" name="status" id="status_maintenance" value="maintenance"
                               <?= ($current_status === 'maintenance') ? 'checked' : '' ?> <?= $is_occupied_or_reserved ? 'disabled' : '' ?>>
                        <label for="status_maintenance" <?= $is_occupied_or_reserved ? 'style="opacity: 0.5; cursor: not-allowed;"' : '' ?>>
                            <div class="status-icon maintenance"><i class="bi bi-tools"></i></div>
                            <div>
                                <div class="status-text">Maintenance</div>
                                <div class="status-desc">Dalam perbaikan</div>
                            </div>
                        </label>
                    </div>
                    <?php if ($is_occupied_or_reserved): ?>
                    <div class="status-option">
                        <input type="radio" name="status" id="status_occupied" value="<?= $current_status ?>" checked disabled>
                        <label for="status_occupied" style="opacity: 0.6; cursor: not-allowed;">
                            <div class="status-icon <?= $current_status === 'terisi' ? 'terisi' : 'reserved' ?>">
                                <i class="bi <?= $current_status === 'terisi' ? 'bi-person-fill' : 'bi-bookmark-fill' ?>"></i>
                            </div>
                            <div>
                                <div class="status-text"><?= $current_status === 'terisi' ? 'Terisi' : 'Reserved' ?></div>
                                <div class="status-desc">Diatur oleh sistem booking</div>
                            </div>
                        </label>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label>Keterangan</label>
                <textarea name="keterangan" class="form-control" rows="3" 
                          placeholder="Catatan tambahan tentang kamar (opsional)"><?= htmlspecialchars($_POST['keterangan'] ?? $room['keterangan'] ?? '') ?></textarea>
            </div>
            
            <div class="form-footer">
                <button type="submit" class="btn-submit">
                    <i class="bi bi-check-lg"></i>Simpan Perubahan
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
