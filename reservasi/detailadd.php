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

$master_id = (int)($_GET['reservasi_id'] ?? 0);
if (!$master_id) redirect('index.php');

// Get booking info for calculating nights
$booking = mysqli_fetch_assoc(mysqli_query($connection, "SELECT * FROM reservasi WHERE id = $master_id"));
if (!$booking) redirect('index.php');

// Calculate jumlah malam automatically from checkin/checkout dates
$checkin = new DateTime($booking['tanggal_checkin']);
$checkout = new DateTime($booking['tanggal_checkout']);
$jumlah_malam_auto = $checkout->diff($checkin)->days;
if ($jumlah_malam_auto < 1) $jumlah_malam_auto = 1;

// Get jumlah tamu from booking
$jumlah_tamu = (int)$booking['jumlah_tamu'];

// Room capacity based on type
function getKapasitasByTipe($nama_tipe) {
    $tipe = strtolower($nama_tipe);
    if (strpos($tipe, 'presidential') !== false) return 5;
    if (strpos($tipe, 'suite') !== false) return 5;
    if (strpos($tipe, 'superior') !== false) return 4;
    if (strpos($tipe, 'deluxe') !== false) return 3;
    if (strpos($tipe, 'standard') !== false) return 2;
    return 2; // default
}

// Get list of kamar yang sedang dibooking (status bukan checkout/cancelled)
$booked_kamar_ids = [];
$booked_query = mysqli_query($connection, "
    SELECT DISTINCT bd.kamar_id 
    FROM reservasi_detail bd 
    JOIN reservasi b ON bd.reservasi_id = b.id 
    WHERE b.status NOT IN ('checkout', 'cancelled') 
    AND bd.reservasi_id != $master_id
");
while ($row = mysqli_fetch_assoc($booked_query)) {
    $booked_kamar_ids[] = $row['kamar_id'];
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) die('Invalid CSRF token.');
    
    $kamar_id = (int)($_POST['kamar_id'] ?? 0);
    $qty = $jumlah_malam_auto; // Use auto-calculated nights
    
    if ($kamar_id <= 0) {
        $error = "Pilih Kamar terlebih dahulu.";
    } else {
        // Check if kamar is already booked by another booking
        if (in_array($kamar_id, $booked_kamar_ids)) {
            $error = "Kamar ini sedang dalam reservasi tamu lain.";
        } else {
            // Get harga from tipe_kamar via kamar
            $kamar_info = mysqli_fetch_assoc(mysqli_query($connection, "SELECT t.harga_per_malam, t.nama_tipe FROM kamar k JOIN tipe_kamar t ON k.id_tipe = t.id WHERE k.id = $kamar_id"));
            $harga = (float)($kamar_info['harga_per_malam'] ?? 0);
            $count_val = $qty * $harga;
            
            // Check capacity
            $kapasitas = getKapasitasByTipe($kamar_info['nama_tipe'] ?? '');
            if ($jumlah_tamu > $kapasitas) {
                $error = "Kamar ini hanya bisa menampung $kapasitas orang, sedangkan jumlah tamu $jumlah_tamu orang.";
            }
        }
    }
    
    if (!$error) {
        // Check for duplicate in same booking
        $check_stmt = mysqli_prepare($connection, "SELECT id FROM `reservasi_detail` WHERE `reservasi_id` = ? AND `kamar_id` = ?");
        mysqli_stmt_bind_param($check_stmt, "ii", $master_id, $kamar_id);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $error = "Kamar ini sudah ditambahkan ke reservasi ini.";
        }
        mysqli_stmt_close($check_stmt);
        
        if (!$error) {
            // Insert booking detail
            $stmt = mysqli_prepare($connection, "INSERT INTO `reservasi_detail` (`kamar_id`, `jumlah_malam`, `harga`, `subtotal`, `reservasi_id`) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iiddi", $kamar_id, $qty, $harga, $count_val, $master_id);
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);

                syncReservasiTotals($connection, $master_id);
                
                redirect(dirname($_SERVER['SCRIPT_NAME']) . "/detail.php?id=$master_id");
                exit();
            } else {
                $error = "Gagal menyimpan: " . mysqli_error($connection);
            }
        }
    }
}

// Get available rooms with their info
$kamar_list = mysqli_query($connection, "
    SELECT k.id, k.nomor_kamar, k.lantai, k.status as room_status, t.nama_tipe, t.harga_per_malam 
    FROM kamar k 
    LEFT JOIN tipe_kamar t ON k.id_tipe = t.id 
    ORDER BY k.nomor_kamar ASC
");

$csrfToken = generateCSRFToken();
?>
<?php include '../views/'.$THEME.'/header.php'; ?>
<?php include '../views/'.$THEME.'/sidebar.php'; ?>
<?php include '../views/'.$THEME.'/topnav.php'; ?>
<?php include '../views/'.$THEME.'/upper_block.php'; ?>

<h2>Tambah Kamar ke Reservasi</h2>

<!-- Compact Info Grid - Booking Context -->
<div class="booking-info-card">
    <div class="info-grid">
        <div class="info-item">
            <div class="info-icon"><i class="bi bi-calendar-check"></i></div>
            <div class="info-content">
                <span class="info-label">Check-In</span>
                <span class="info-value"><?= date('d M Y', strtotime($booking['tanggal_checkin'])) ?></span>
            </div>
        </div>
        <div class="info-item">
            <div class="info-icon"><i class="bi bi-calendar-x"></i></div>
            <div class="info-content">
                <span class="info-label">Check-Out</span>
                <span class="info-value"><?= date('d M Y', strtotime($booking['tanggal_checkout'])) ?></span>
            </div>
        </div>
        <div class="info-item">
            <div class="info-icon"><i class="bi bi-moon-stars"></i></div>
            <div class="info-content">
                <span class="info-label">Durasi</span>
                <span class="info-value duration-badge"><?= $jumlah_malam_auto ?> malam</span>
            </div>
        </div>
        <div class="info-item">
            <div class="info-icon"><i class="bi bi-people"></i></div>
            <div class="info-content">
                <span class="info-label">Tamu</span>
                <span class="info-value"><?= $jumlah_tamu ?> orang</span>
            </div>
        </div>
    </div>
</div>

<?php if ($error): ?>
<?= showAlert($error, 'danger') ?>
<?php endif; ?>

<style>
/* Room Selection Modal - Hotel Bellmounth Theme */
/* SIMPLIFIED CSS - No transforms to avoid flickering */
.room-card {
    border: 2px solid #E2E8F0;
    border-radius: 12px;
    padding: 15px;
    cursor: pointer;
    background: #FFFFFF;
    height: 100%;
    position: relative;
}
.room-card:hover:not(.room-disabled) {
    border-color: #C5A059;
    box-shadow: 0 4px 12px rgba(197, 160, 89, 0.2);
}
.room-card.room-selected {
    border-color: #1A202C;
    border-width: 3px;
    background: #F8F9FA;
}
.room-card.room-disabled {
    background: #F0F0F0;
    cursor: not-allowed;
    opacity: 0.5;
}
.room-card .room-thumb {
    width: 100%;
    height: 80px;
    object-fit: cover;
    border-radius: 6px;
    margin-bottom: 10px;
}
.room-card .room-number {
    font-family: 'Playfair Display', serif;
    font-size: 1.1rem;
    font-weight: 700;
    color: #1A202C;
}
.room-card .room-type-badge {
    display: inline-block;
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    padding: 3px 8px;
    border-radius: 3px;
    background: #1A202C;
    color: #FFFFFF;
    margin: 5px 0;
}
.room-card .room-price {
    font-size: 0.9rem;
    font-weight: 600;
    color: #C5A059;
    margin: 4px 0;
}
.room-card .room-details {
    font-size: 0.75rem;
    color: #718096;
}
.room-card .room-status-badge {
    font-size: 0.6rem;
    font-weight: 600;
    text-transform: uppercase;
    padding: 3px 6px;
    border-radius: 3px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
}

/* Room Preview - Empty State */
.room-preview {
    border: 2px dashed #CBD5E0;
    border-radius: 12px;
    padding: 30px 20px;
    background: #F7FAFC;
    min-height: 160px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
}
.room-preview .empty-state-icon {
    font-size: 2.5rem;
    color: #A0AEC0;
    margin-bottom: 12px;
}
.room-preview .empty-state-text {
    color: #718096;
    font-size: 0.95rem;
    margin-bottom: 16px;
}
.room-preview.has-selection {
    border-style: solid;
    border-color: #1A202C;
    background: #FFFFFF;
}
.room-preview .preview-thumb {
    width: 80px;
    height: 55px;
    object-fit: cover;
    border-radius: 6px;
    margin-bottom: 8px;
}
.room-preview .preview-name {
    font-family: 'Playfair Display', serif;
    font-size: 1.1rem;
    font-weight: 700;
    color: #1A202C;
}
.room-preview .preview-type {
    font-size: 0.8rem;
    color: #718096;
}
.room-preview .preview-price {
    font-size: 0.9rem;
    font-weight: 600;
    color: #C5A059;
}
.room-preview .preview-details {
    font-size: 0.75rem;
    color: #A0AEC0;
}

/* Compact Info Card - Booking Context */
.booking-info-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}
.booking-info-card .info-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}
@media (max-width: 768px) {
    .booking-info-card .info-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
.booking-info-card .info-item {
    display: flex;
    align-items: center;
    gap: 12px;
}
.booking-info-card .info-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #1A202C 0%, #2D3748 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #C5A059;
    font-size: 1.1rem;
}
.booking-info-card .info-content {
    display: flex;
    flex-direction: column;
}
.booking-info-card .info-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #A0AEC0;
}
.booking-info-card .info-value {
    font-size: 0.9rem;
    font-weight: 600;
    color: #1A202C;
}
.booking-info-card .duration-badge {
    background: linear-gradient(135deg, #C5A059 0%, #D4AF37 100%);
    color: #FFFFFF;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 0.8rem;
}

/* Footer Buttons */
.form-footer {
    display: flex;
    gap: 12px;
    margin-top: 24px;
}
.btn-ghost {
    background: transparent;
    border: 1px solid #CBD5E0;
    color: #4A5568;
    padding: 10px 24px;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.2s ease;
}
.btn-ghost:hover {
    background: #F7FAFC;
    border-color: #A0AEC0;
    color: #1A202C;
}
.btn-submit {
    background: linear-gradient(135deg, #1A202C 0%, #2D3748 100%);
    border: none;
    color: #FFFFFF;
    padding: 10px 28px;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.2s ease;
}
.btn-submit:hover:not(:disabled) {
    box-shadow: 0 4px 12px rgba(26, 32, 44, 0.3);
    transform: translateY(-1px);
}
.btn-submit:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Modal */
#roomSelectionModal .modal-header {
    background: #1A202C;
    color: #FFFFFF;
}
#roomSelectionModal .modal-body {
    background: #F7FAFC;
}
#roomSelectionModal .booking-info {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 16px;
}
#roomSelectionModal .info-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    color: #A0AEC0;
}
#roomSelectionModal .info-value {
    font-size: 0.9rem;
    font-weight: 600;
    color: #1A202C;
}
</style>

<form method="POST" id="roomForm">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
<input type="hidden" name="reservasi_id" value="<?= $master_id ?>">
<input type="hidden" name="kamar_id" id="selectedKamarId" value="" required>

<div class="mb-4">
    <label class="form-label">Pilih Kamar*</label>
    
    <!-- Room Preview - Empty/Selected State -->
    <div class="room-preview mb-3" id="roomPreview">
        <i class="bi bi-door-closed empty-state-icon"></i>
        <span class="empty-state-text">Belum ada kamar dipilih</span>
        <button type="button" class="btn btn-primary" id="openModalBtn">
            <i class="bi bi-search me-2"></i>Pilih Kamar
        </button>
    </div>
</div>

<!-- Footer Buttons -->
<div class="form-footer">
    <button type="submit" class="btn-submit" id="submitBtn" disabled>
        <i class="bi bi-plus-circle me-2"></i>Tambah Kamar
    </button>
    <a href="detail.php?id=<?= $master_id ?>" class="btn-ghost">
        <i class="bi bi-x-lg me-1"></i>Batal
    </a>
</div>
</form>

<!-- Room Selection Modal -->
<div class="modal fade" id="roomSelectionModal" tabindex="-1" aria-labelledby="roomSelectionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="roomSelectionModalLabel">
                    <i class="bi bi-door-open me-2"></i>Pilih Kamar
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Booking Info -->
                <div class="booking-info">
                    <div class="row">
                        <div class="col-6 col-md-3 mb-2 mb-md-0">
                            <div class="info-label">Check-In</div>
                            <div class="info-value"><?= date('d M Y', strtotime($booking['tanggal_checkin'])) ?></div>
                        </div>
                        <div class="col-6 col-md-3 mb-2 mb-md-0">
                            <div class="info-label">Check-Out</div>
                            <div class="info-value"><?= date('d M Y', strtotime($booking['tanggal_checkout'])) ?></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="info-label">Durasi</div>
                            <div class="info-value"><?= $jumlah_malam_auto ?> malam</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="info-label">Jumlah Tamu</div>
                            <div class="info-value"><?= $jumlah_tamu ?> orang</div>
                        </div>
                    </div>
                </div>

                <!-- Room Grid -->
                <div class="row g-3">
                    <?php 
                    mysqli_data_seek($kamar_list, 0);
                    while ($kamar = mysqli_fetch_assoc($kamar_list)): 
                        $is_booked = in_array($kamar['id'], $booked_kamar_ids);
                        $kapasitas = getKapasitasByTipe($kamar['nama_tipe']);
                        $is_over_capacity = ($jumlah_tamu > $kapasitas);
                        $room_status = $kamar['room_status'] ?? 'tersedia';
                        $is_dirty = ($room_status === 'kotor');
                        $is_maintenance = ($room_status === 'maintenance');
                        $is_occupied = ($room_status === 'terisi');
                        $is_disabled = $is_booked || $is_over_capacity || $is_dirty || $is_maintenance || $is_occupied;
                        $harga_formatted = number_format($kamar['harga_per_malam'], 0, ',', '.');
                        
                        // Thumbnail image based on room type
                        $tipe_lower = strtolower($kamar['nama_tipe']);
                        if (strpos($tipe_lower, 'presidential') !== false) {
                            $thumb_file = 'presidential_suite.jpg';
                        } elseif (strpos($tipe_lower, 'suite') !== false) {
                            $thumb_file = 'suite.jpg';
                        } elseif (strpos($tipe_lower, 'superior') !== false) {
                            $thumb_file = 'superior.jpg';
                        } elseif (strpos($tipe_lower, 'deluxe') !== false) {
                            $thumb_file = 'deluxe.jpg';
                        } else {
                            $thumb_file = 'standard.jpg';
                        }
                        $thumb_url = base_url() . 'assets/default/images/' . $thumb_file;
                    ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="room-card <?= $is_disabled ? 'room-disabled' : '' ?>" 
                             data-kamar-id="<?= $kamar['id'] ?>"
                             data-kamar-nomor="<?= htmlspecialchars($kamar['nomor_kamar']) ?>"
                             data-kamar-tipe="<?= htmlspecialchars($kamar['nama_tipe']) ?>"
                             data-kamar-harga="<?= $harga_formatted ?>"
                             data-kamar-thumb="<?= $thumb_url ?>"
                             data-kamar-kapasitas="<?= $kapasitas ?>"
                             data-kamar-lantai="<?= $kamar['lantai'] ?>"
                             data-disabled="<?= $is_disabled ? 'true' : 'false' ?>">
                            
                            <div class="text-center">
                                <img src="<?= $thumb_url ?>" alt="<?= htmlspecialchars($kamar['nama_tipe']) ?>" class="room-thumb">
                                <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
                                    <div class="room-number mb-0"><?= htmlspecialchars($kamar['nomor_kamar']) ?></div>
                                    <?php if ($is_booked): ?>
                                    <span class="room-status-badge badge bg-danger m-0">Tidak Tersedia</span>
                                    <?php elseif ($is_dirty): ?>
                                    <span class="room-status-badge badge bg-danger m-0">Kotor</span>
                                    <?php elseif ($is_maintenance): ?>
                                    <span class="room-status-badge badge bg-warning m-0">Maintenance</span>
                                    <?php elseif ($is_occupied): ?>
                                    <span class="room-status-badge badge bg-primary m-0">Terisi</span>
                                    <?php elseif ($is_over_capacity): ?>
                                    <span class="room-status-badge badge bg-secondary m-0">Kapasitas Kurang</span>
                                    <?php elseif ($room_status === 'tersedia'): ?>
                                    <span class="room-status-badge badge bg-success m-0">Bersih</span>
                                    <?php endif; ?>
                                </div>
                                <span class="room-type-badge"><?= htmlspecialchars($kamar['nama_tipe']) ?></span>
                                <div class="room-price">Rp <?= $harga_formatted ?>/malam</div>
                                <div class="room-details">
                                    <span><i class="bi bi-people-fill me-1"></i><?= $kapasitas ?></span>
                                    <span><i class="bi bi-building me-1"></i>Lt. <?= $kamar['lantai'] ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<?php include '../views/'.$THEME.'/lower_block.php'; ?>
<?php include '../views/'.$THEME.'/footer.php'; ?>

<script>
(function() {
    // Wait for Bootstrap to be available
    const modalEl = document.getElementById('roomSelectionModal');
    const selectedKamarId = document.getElementById('selectedKamarId');
    const roomPreview = document.getElementById('roomPreview');
    const submitBtn = document.getElementById('submitBtn');
    const openModalBtn = document.getElementById('openModalBtn');
    
    // Create single modal instance
    let modalInstance = null;
    
    function getModal() {
        if (!modalInstance) {
            modalInstance = new bootstrap.Modal(modalEl);
        }
        return modalInstance;
    }
    
    // Open modal button
    openModalBtn.addEventListener('click', function() {
        getModal().show();
    });
    
    // Room card selection
    document.querySelectorAll('.room-card:not(.room-disabled)').forEach(card => {
        card.addEventListener('click', function() {
            if (this.dataset.disabled === 'true') return;
            
            // Remove selection from all cards
            document.querySelectorAll('.room-card').forEach(c => c.classList.remove('room-selected'));
            
            // Select this card
            this.classList.add('room-selected');
            
            // Update hidden input
            selectedKamarId.value = this.dataset.kamarId;
            
            // Store card data for the change button
            const cardData = this.dataset;
            
            // Update preview with selected room card
            roomPreview.innerHTML = `
                <div class="selected-room-card">
                    <img src="${cardData.kamarThumb}" alt="${cardData.kamarTipe}" class="preview-thumb">
                    <div class="preview-name">${cardData.kamarNomor}</div>
                    <div class="preview-type">${cardData.kamarTipe}</div>
                    <div class="preview-price">Rp ${cardData.kamarHarga}/malam</div>
                    <div class="preview-details"><i class="bi bi-people-fill me-1"></i>Max ${cardData.kamarKapasitas} orang • <i class="bi bi-building"></i> Lantai ${cardData.kamarLantai}</div>
                    <button type="button" class="btn btn-outline-secondary btn-sm mt-3" id="changeRoomBtn">
                        <i class="bi bi-arrow-repeat me-1"></i>Ganti Kamar
                    </button>
                </div>
            `;
            roomPreview.classList.add('has-selection');
            
            // Add click handler for change button
            document.getElementById('changeRoomBtn').addEventListener('click', function() {
                getModal().show();
            });
            
            // Enable submit button
            submitBtn.disabled = false;
            
            // Close modal
            getModal().hide();
        });
    });
    
    // Form validation
    document.getElementById('roomForm').addEventListener('submit', function(e) {
        if (!selectedKamarId.value) {
            e.preventDefault();
            alert('Silakan pilih kamar terlebih dahulu!');
            getModal().show();
        }
    });
})();
</script>

