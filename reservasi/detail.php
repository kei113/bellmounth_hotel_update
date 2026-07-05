<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.use_strict_mode', 1);
session_start();
require_once '../lib/functions.php';
require_once '../lib/auth.php';
requireAuth();
requireModuleAccess('reservasi');
require_once '../config/database.php';

// Automatically synchronize room statuses
syncRoomStatuses($connection);

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('reservasi/index.php');

$stmt = mysqli_prepare($connection, "SELECT * FROM `reservasi` WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$booking) redirect('reservasi/index.php');

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token.');
    }
    
    // Update DP Bayar - ADD to existing DP
    if (isset($_POST['update_dp'])) {
        $tambah_dp_raw = $_POST['tambah_dp'] ?? '0';
        $tambah_dp = (float)str_replace('.', '', $tambah_dp_raw);
        $new_dp = $booking['dp_bayar'] + $tambah_dp; // Add to existing DP
        
        // Cap at total_bayar
        if ($new_dp > $booking['total_bayar']) {
            $new_dp = $booking['total_bayar'];
        }
        
        $sisa = $booking['total_bayar'] - $new_dp;
        if ($sisa < 0) $sisa = 0;
        
        // Do NOT auto-set checkout - require manual confirmation
        $updateStmt = mysqli_prepare($connection, "UPDATE `reservasi` SET `dp_bayar` = ?, `sisa_bayar` = ? WHERE `id` = ?");
        mysqli_stmt_bind_param($updateStmt, "ddi", $new_dp, $sisa, $id);
        mysqli_stmt_execute($updateStmt);
        mysqli_stmt_close($updateStmt);
        
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }
    
    // Set Checkout manual
    if (isset($_POST['set_checkout'])) {
        // Check if sisa_bayar > 0
        if ($booking['sisa_bayar'] > 0) {
            // Cannot checkout - redirect with error
            header("Location: " . $_SERVER['REQUEST_URI'] . "&error=sisa_bayar");
            exit();
        }
        
        if ($booking['status'] !== 'checkout') {
            // Load enterprise functions if available
            $enterprise_file = __DIR__ . '/../lib/enterprise.php';
            if (file_exists($enterprise_file)) {
                require_once $enterprise_file;
                
                // Log the checkout action to audit trail
                logActivity($connection, 'reservasi.checkout', 'reservasi', $id,
                    ['status' => $booking['status']],
                    ['status' => 'checkout'],
                    $booking['no_reservasi'],
                    'reservasi'
                );
                
                // Auto-mark all rooms as dirty
                markBookingRoomsDirty($connection, $id);
            }
            
            $updateStmt = mysqli_prepare($connection, "UPDATE `reservasi` SET `status` = 'checkout' WHERE `id` = ?");
            mysqli_stmt_bind_param($updateStmt, "i", $id);
            mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }

    
    // Set Checkin
    if (isset($_POST['set_checkin'])) {
        if ($booking['status'] === 'confirmed' || $booking['status'] === 'pending') {
            // Check if DP is at least 50%
            $min_dp = $booking['total_bayar'] * 0.5;
            if ($booking['dp_bayar'] < $min_dp) {
                // Must pay at least 50% DP
                 header("Location: " . $_SERVER['REQUEST_URI'] . "&error=min_dp");
                 exit();
            }

            $updateStmt = mysqli_prepare($connection, "UPDATE `reservasi` SET `status` = 'checkin' WHERE `id` = ?");
            mysqli_stmt_bind_param($updateStmt, "i", $id);
            mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }
    
    // Set Confirmed
    if (isset($_POST['set_confirmed'])) {
        if ($booking['status'] === 'pending') {
            $updateStmt = mysqli_prepare($connection, "UPDATE `reservasi` SET `status` = 'confirmed' WHERE `id` = ?");
            mysqli_stmt_bind_param($updateStmt, "i", $id);
            mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }
}

// Refresh booking data
$stmt = mysqli_prepare($connection, "SELECT * FROM `reservasi` WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$details = mysqli_query($connection, "SELECT bd.*, k.nomor_kamar, t.nama_tipe FROM `reservasi_detail` bd LEFT JOIN kamar k ON bd.kamar_id = k.id LEFT JOIN tipe_kamar t ON k.id_tipe = t.id WHERE `reservasi_id` = $id");

// Status badge color
function getStatusBadge($status) {
    switch($status) {
        case 'checkout': return 'success';
        case 'checkin': return 'primary';
        case 'confirmed': return 'info';
        case 'cancelled': return 'danger';
        default: return 'warning';
    }
}
?>
<?php include '../views/'.$THEME.'/header.php'; ?>
<?php include '../views/'.$THEME.'/sidebar.php'; ?>
<?php include '../views/'.$THEME.'/topnav.php'; ?>
<?php include '../views/'.$THEME.'/upper_block.php'; ?>

<!-- Booking Detail Reskin Styles -->
<style>
/* Detail Page Typography */
.detail-title {
    font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", sans-serif;
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-bottom: 24px;
}
.detail-title span {
    color: var(--accent-hover);
}

/* Info Card */
.info-card {
    background: #FFFFFF;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    box-shadow: var(--shadow-sm);
    padding: 24px;
    margin-bottom: 24px;
}
.info-row {
    margin-bottom: 12px;
}
.info-row:last-child {
    margin-bottom: 0;
}
.info-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--secondary-color);
    margin-bottom: 2px;
}
.info-value {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--primary-color);
}
.info-value.booking-id {
    font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", monospace;
    font-size: 0.85rem;
    color: var(--secondary-color);
}

/* Financial Section */
.financial-section {
    background: var(--primary-light);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
}
.financial-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
    margin-bottom: 20px;
}
@media (max-width: 768px) {
    .financial-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
}
.financial-item {
    text-align: center;
}
.financial-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--secondary-color);
    margin-bottom: 4px;
}
.financial-value {
    font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", sans-serif;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary-color);
}
.financial-value.hero {
    font-size: 2rem;
}
.financial-value.text-danger {
    color: var(--danger-color) !important;
}
.financial-value.text-success {
    color: var(--success-color) !important;
}
.financial-value.text-primary {
    color: var(--primary-color) !important;
}

/* Action Bar */
.action-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    padding-top: 16px;
    border-top: 1px solid var(--border-color);
}
.input-group-dp {
    display: flex;
    align-items: center;
    background: #FFFFFF;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    overflow: hidden;
}
.input-group-dp label {
    padding: 8px 12px;
    background: var(--primary-light);
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--secondary-color);
    white-space: nowrap;
    margin: 0;
}
.input-group-dp input {
    border: none;
    padding: 8px 12px;
    width: 140px;
    font-size: 0.9rem;
    color: var(--primary-color);
    background: #FFFFFF;
}
.input-group-dp input:focus {
    outline: none;
}
.input-group-dp button {
    background: var(--primary-color);
    color: #FFFFFF;
    border: none;
    padding: 8px 16px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: background 0.2s ease;
}
.input-group-dp button:hover {
    background: #333336;
}
.sisa-hint {
    font-size: 0.75rem;
    color: var(--secondary-color);
    margin-left: 8px;
}

/* Room Table */
.room-table-container {
    background: #FFFFFF;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 24px;
}
.room-table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color);
}
.room-table-header h3 {
    font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", sans-serif;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--primary-color);
    margin: 0;
}
.table-styled {
    margin: 0;
    border-collapse: collapse;
    width: 100%;
}
.table-styled thead th {
    background: var(--primary-light);
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--secondary-color);
    padding: 12px 16px;
    border-bottom: 2px solid var(--border-color);
}
.table-styled tbody td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border-light);
    font-size: 0.9rem;
    color: var(--primary-color);
}
.table-styled tbody tr:last-child td {
    border-bottom: none;
}
.table-styled tbody tr:hover {
    background: var(--primary-light);
}
.table-styled .room-number {
    font-weight: 600;
    color: var(--primary-color);
}
.table-styled .room-type {
    display: inline-block;
    background: rgba(191, 163, 112, 0.05);
    border: 1px solid rgba(191, 163, 112, 0.15);
    color: var(--accent-hover);
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    padding: 3px 8px;
    border-radius: 4px;
}
.table-styled .currency {
    font-weight: 500;
    color: var(--secondary-color);
}
.table-styled .currency.subtotal {
    font-weight: 600;
    color: var(--accent-hover);
}

/* Ghost Delete Button */
.btn-delete-ghost {
    background: transparent;
    border: none;
    color: var(--danger-color);
    font-size: 1rem;
    padding: 6px 10px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
}
.btn-delete-ghost:hover {
    background: rgba(255, 59, 48, 0.05);
    color: #b22921;
}

/* Empty State */
.empty-room-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--secondary-color);
}
.empty-room-state i {
    font-size: 2.5rem;
    color: var(--border-color);
    margin-bottom: 12px;
}

/* Back Button */
.btn-back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--primary-light);
    color: var(--primary-color);
    border: 1px solid var(--border-color);
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.25, 0.8, 0.25, 1);
}
.btn-back:hover {
    background: #E8E8ED;
    color: var(--primary-color);
}
</style>

<!-- Page Title -->
<h2 class="detail-title">Detail Reservasi <span>#<?= $booking['id'] ?></span></h2>

<!-- Guest & Booking Info Card -->
<div class="info-card">
    <div class="row">
        <!-- Left: Guest Info -->
        <div class="col-md-6">
            <div class="info-row">
                <div class="info-label">No Reservasi</div>
                <div class="info-value booking-id"><?= htmlspecialchars($booking['no_reservasi']) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Nama Tamu</div>
                <div class="info-value"><?= htmlspecialchars($booking['nama_tamu']) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">No. Identitas</div>
                <div class="info-value"><?= htmlspecialchars($booking['no_identitas'] ?? '-') ?></div>
            </div>
            <?php if (!empty($booking['foto_identitas'])): ?>
            <div class="info-row">
                <div class="info-label">Foto Identitas</div>
                <div class="mt-2">
                    <a href="../assets/uploads/identitas/<?= htmlspecialchars($booking['foto_identitas']) ?>" target="_blank" title="Klik untuk memperbesar">
                        <img src="../assets/uploads/identitas/<?= htmlspecialchars($booking['foto_identitas']) ?>" alt="KTP/SIM" class="img-thumbnail" style="max-height: 120px; transition: transform 0.2s;">
                    </a>
                    <style>
                        .img-thumbnail:hover {
                            transform: scale(1.05);
                        }
                    </style>
                </div>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <div class="info-label">No. Telepon</div>
                <div class="info-value"><?= htmlspecialchars($booking['no_telepon'] ?? '-') ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Email</div>
                <div class="info-value"><?= htmlspecialchars($booking['email'] ?? '-') ?></div>
            </div>
        </div>
        
        <!-- Right: Booking Info -->
        <div class="col-md-6">
            <div class="info-row">
                <div class="info-label">Check-In</div>
                <div class="info-value"><?= date('d M Y', strtotime($booking['tanggal_checkin'])) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Check-Out</div>
                <div class="info-value"><?= date('d M Y', strtotime($booking['tanggal_checkout'])) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Jumlah Tamu</div>
                <div class="info-value"><?= $booking['jumlah_tamu'] ?> orang</div>
            </div>
            <div class="info-row">
                <div class="info-label">Status</div>
                <div class="info-value">
                    <span class="badge bg-<?= getStatusBadge($booking['status']) ?>">
                        <?= $booking['status'] === 'checkout' ? 'LUNAS' : strtoupper($booking['status']) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Financial Section -->
<div class="financial-section">
    <div class="financial-grid">
        <div class="financial-item">
            <div class="financial-label">Total Bayar</div>
            <div class="financial-value hero text-primary">Rp <?= number_format((float)$booking['total_bayar'], 0, ',', '.') ?></div>
        </div>
        <div class="financial-item">
            <div class="financial-label">DP Dibayar</div>
            <div class="financial-value">Rp <?= number_format((float)$booking['dp_bayar'], 0, ',', '.') ?></div>
        </div>
        <div class="financial-item">
            <div class="financial-label">Sisa Bayar</div>
            <div class="financial-value hero <?= $booking['sisa_bayar'] > 0 ? 'text-danger' : 'text-success' ?>">Rp <?= number_format((float)$booking['sisa_bayar'], 0, ',', '.') ?></div>
        </div>
    </div>
    
    <?php if ($booking['status'] !== 'checkout' && $booking['status'] !== 'cancelled'): ?>
    <div class="action-bar">
        <?php if ($booking['status'] === 'pending' && $booking['total_bayar'] > 0): ?>
        <form method="POST" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
            <input type="hidden" name="set_confirmed" value="1">
            <button type="submit" class="btn btn-info"><i class="bi bi-check-circle me-1"></i>Konfirmasi Reservasi</button>
        </form>
        <?php endif; ?>
        
        <?php if ($booking['status'] === 'confirmed'): ?>
        <?php 
            $min_dp = $booking['total_bayar'] * 0.5;
            if ($booking['dp_bayar'] < $min_dp):
        ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#checkinWarningModal">
            <i class="bi bi-box-arrow-in-right me-1"></i>Check-In
        </button>
        <?php else: ?>
        <form method="POST" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
            <input type="hidden" name="set_checkin" value="1">
            <button type="submit" class="btn btn-primary"><i class="bi bi-box-arrow-in-right me-1"></i>Check-In</button>
        </form>
        <?php endif; ?>
        <?php endif; ?>
        
        <?php if ($booking['status'] === 'checkin'): ?>
        <?php if ($booking['sisa_bayar'] > 0): ?>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#warningModal">
            <i class="bi bi-box-arrow-right me-1"></i>Check-Out / LUNAS
        </button>
        <?php else: ?>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#checkoutConfirmModal">
            <i class="bi bi-box-arrow-right me-1"></i>Check-Out / LUNAS
        </button>
        <?php endif; ?>
        <?php endif; ?>
        
        <?php if ($booking['total_bayar'] > 0 && $booking['sisa_bayar'] > 0): ?>
        <form method="POST" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
            <input type="hidden" name="update_dp" value="1">
            <div class="input-group-dp">
                <label>+ Tambah DP</label>
                <input type="text" name="tambah_dp" id="input_tambah_dp" placeholder="Nominal" inputmode="numeric" autocomplete="off">
                <button type="submit"><i class="bi bi-credit-card me-1"></i>Bayar</button>
            </div>
        </form>
        <span class="sisa-hint">Sisa: Rp <?= number_format((float)$booking['sisa_bayar'], 0, ',', '.') ?></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Room List Section -->
<div class="room-table-container">
    <div class="room-table-header">
        <h3>Daftar Kamar</h3>
        <?php if ($booking['status'] === 'pending'): ?>
        <a href="detailadd.php?reservasi_id=<?= $id ?>" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Tambah Kamar</a>
        <?php endif; ?>
    </div>
    
    <?php if (mysqli_num_rows($details) > 0): ?>
    <table class="table-styled">
        <thead>
            <tr>
                <th>No. Kamar</th>
                <th>Tipe</th>
                <th>Jumlah Malam</th>
                <th class="text-end">Harga/Malam</th>
                <th class="text-end">Subtotal</th>
                <?php if ($booking['status'] === 'pending'): ?><th class="text-center">Aksi</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php $detail_index = 0; ?>
            <?php mysqli_data_seek($details, 0); ?>
            <?php while ($detail = mysqli_fetch_assoc($details)): ?>
            <tr>
                <td><span class="room-number"><?= htmlspecialchars($detail['nomor_kamar'] ?? 'Kamar #'.$detail['kamar_id']) ?></span></td>
                <td><span class="room-type"><?= htmlspecialchars($detail['nama_tipe'] ?? '-') ?></span></td>
                <td><?= $detail['jumlah_malam'] ?> malam</td>
                <td class="text-end currency">Rp <?= number_format((float)$detail['harga'], 0, ',', '.') ?></td>
                <td class="text-end currency subtotal">Rp <?= number_format((float)$detail['subtotal'], 0, ',', '.') ?></td>
                <?php if ($booking['status'] === 'pending'): ?>
                <td class="text-center">
                    <button type="button" class="btn-delete-ghost" data-bs-toggle="modal" data-bs-target="#deleteKamarModal<?= $detail_index ?>" title="Hapus Kamar">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
                <?php endif; ?>
            </tr>
            <?php $detail_index++; ?>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="empty-room-state">
        <i class="bi bi-door-open d-block"></i>
        <p>Belum ada kamar dipilih.</p>
        <?php if ($booking['status'] === 'pending'): ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <a href="index.php" class="btn-back">
        <i class="bi bi-arrow-left"></i>Kembali ke Daftar
    </a>
    <?php if (in_array($booking['status'], ['confirmed', 'checkin'])): ?>
    <a href="services.php?id=<?= $id ?>" class="btn btn-dark">
        <i class="bi bi-plus-circle me-1"></i>Tambah Service
    </a>
    <?php endif; ?>
</div>

<?php include '../views/'.$THEME.'/lower_block.php'; ?>

<!-- Custom Warning Modal -->
<?php if ($booking['status'] === 'checkin' && $booking['sisa_bayar'] > 0): ?>
<div class="modal fade" id="warningModal" tabindex="-1" aria-labelledby="warningModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <div class="mb-4">
                    <div style="width: 80px; height: 80px; background-color: #fff3cd; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;">
                        <span style="font-size: 40px; color: #856404; font-weight: bold;">!</span>
                    </div>
                </div>
                <h4 class="text-danger fw-bold mb-3">Tidak Bisa Check-Out!</h4>
                <p class="mb-2">Masih ada <strong>SISA BAYAR</strong>:</p>
                <h3 class="text-danger fw-bold mb-4">Rp <?= number_format((float)$booking['sisa_bayar'], 0, ',', '.') ?></h3>
                <p class="text-muted">Silakan update DP terlebih dahulu agar sisa bayar menjadi 0.</p>
            </div>
            <div class="modal-footer justify-content-center border-0 pt-0">
                <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">Mengerti</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Custom Warning Modal for Check-In (DP < 50%) -->
<?php if ($booking['status'] === 'confirmed' && $booking['dp_bayar'] < ($booking['total_bayar'] * 0.5)): ?>
<div class="modal fade" id="checkinWarningModal" tabindex="-1" aria-labelledby="checkinWarningModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <div class="mb-4">
                    <div style="width: 80px; height: 80px; background-color: #fff3cd; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;">
                        <span style="font-size: 40px; color: #856404; font-weight: bold;">!</span>
                    </div>
                </div>
                <h4 class="text-danger fw-bold mb-3">Tidak Bisa Check-In!</h4>
                <p class="mb-2">Pembayaran DP Kurang dari <strong>50%</strong></p>
                <?php $min_dp_50 = $booking['total_bayar'] * 0.5; $kekurangan = $min_dp_50 - $booking['dp_bayar']; ?>
                <div class="my-3">
                    <div class="d-flex justify-content-between px-5">
                        <small class="text-muted">Total:</small>
                        <span class="fw-bold">Rp <?= number_format((float)$booking['total_bayar'], 0, ',', '.') ?></span>
                    </div>
                    <div class="d-flex justify-content-between px-5">
                        <small class="text-muted">Min. DP (50%):</small>
                        <span class="fw-bold">Rp <?= number_format((float)$min_dp_50, 0, ',', '.') ?></span>
                    </div>
                    <div class="d-flex justify-content-between px-5">
                        <small class="text-muted">DP Saat Ini:</small>
                        <span class="text-danger fw-bold">Rp <?= number_format((float)$booking['dp_bayar'], 0, ',', '.') ?></span>
                    </div>
                    <hr class="mx-5 my-2">
                    <div class="d-flex justify-content-between px-5">
                        <small class="text-muted">Kekurangan:</small>
                        <span class="text-success fw-bold fs-5">Rp <?= number_format((float)$kekurangan, 0, ',', '.') ?></span>
                    </div>
                </div>
                <p class="text-muted mt-3">Silakan minta pembayaran DP sebesar <strong class="text-success">Rp <?= number_format((float)$kekurangan, 0, ',', '.') ?></strong> untuk melanjutkan Check-In.</p>
            </div>
            <div class="modal-footer justify-content-center border-0 pt-0">
                <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">Mengerti</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Custom Check-Out Confirmation Modal -->
<?php if ($booking['status'] === 'checkin' && $booking['sisa_bayar'] == 0): ?>
<div class="modal fade" id="checkoutConfirmModal" tabindex="-1" aria-labelledby="checkoutConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <div class="mb-4">
                    <div style="width: 80px; height: 80px; background-color: #d1e7dd; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;">
                        <span style="font-size: 40px; color: #0f5132; font-weight: bold;">?</span>
                    </div>
                </div>
                <h4 class="text-success fw-bold mb-3">Konfirmasi Check-Out</h4>
                <p class="mb-4">Apakah Anda yakin ingin melakukan Check-Out untuk tamu ini?</p>
                <div class="alert alert-light border">
                    <small class="text-muted d-block text-start mb-1">Status Pembayaran:</small>
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-check-circle-fill text-success fs-5"></i>
                        <span class="fw-bold text-dark">LUNAS</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-center border-0 pt-0 gap-2">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Batal</button>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
                    <input type="hidden" name="set_checkout" value="1">
                    <button type="submit" class="btn btn-success px-4">Ya, Check-Out</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Delete Kamar Confirmation Modals -->
<?php if ($booking['status'] === 'pending'): ?>
<?php 
mysqli_data_seek($details, 0);
$modal_index = 0;
while ($detail = mysqli_fetch_assoc($details)): 
?>
<div class="modal fade" id="deleteKamarModal<?= $modal_index ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <div class="mb-4">
                    <div style="width: 80px; height: 80px; background-color: #f8d7da; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;">
                        <span style="font-size: 40px; color: #842029; font-weight: bold;">?</span>
                    </div>
                </div>
                <h4 class="text-danger fw-bold mb-3">Hapus Kamar?</h4>
                <p class="mb-2">Apakah Anda yakin ingin menghapus kamar ini dari reservasi?</p>
                <div class="alert alert-light border my-3">
                    <strong><?= htmlspecialchars($detail['nomor_kamar'] ?? 'Kamar #'.$detail['kamar_id']) ?></strong> - <?= htmlspecialchars($detail['nama_tipe'] ?? '-') ?><br>
                    <small class="text-muted">Subtotal: Rp <?= number_format((float)$detail['subtotal'], 0, ',', '.') ?></small>
                </div>
            </div>
            <div class="modal-footer justify-content-center border-0 pt-0 gap-2">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Batal</button>
                <a href="detaildelete.php?id=<?= $detail['id'] ?>&reservasi_id=<?= $id ?>" class="btn btn-danger px-4">Ya, Hapus</a>
            </div>
        </div>
    </div>
</div>
<?php 
$modal_index++;
endwhile; 
?>
<?php endif; ?>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputDp = document.getElementById('input_tambah_dp');
    if (inputDp) {
        inputDp.addEventListener('input', function(e) {
            let cursorPosition = this.selectionStart;
            let oldLength = this.value.length;
            
            // Clean non-numeric characters
            let value = this.value.replace(/[^0-9]/g, '');
            
            if (value) {
                // Format with dots
                this.value = new Intl.NumberFormat('id-ID').format(value);
            } else {
                this.value = '';
            }
            
            // Re-adjust cursor position
            let newLength = this.value.length;
            cursorPosition = cursorPosition + (newLength - oldLength);
            this.setSelectionRange(cursorPosition, cursorPosition);
        });
    }
});
</script>

<?php include '../views/'.$THEME.'/footer.php'; ?>
