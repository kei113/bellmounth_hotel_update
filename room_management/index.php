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
require_once '../lib/activity_logger.php';

// Automatically synchronize room statuses
syncRoomStatuses($connection);

// Handle AJAX status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update_status'])) {
    header('Content-Type: application/json');
    
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit();
    }
    
    $kamar_id = (int)($_POST['kamar_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? '';
    
    $valid_statuses = ['tersedia', 'kotor', 'maintenance', 'terisi', 'reserved'];
    if (!in_array($new_status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit();
    }
    
    // Get current room info for logging
    $room_info_stmt = mysqli_prepare($connection, "SELECT nomor_kamar, status FROM kamar WHERE id = ?");
    mysqli_stmt_bind_param($room_info_stmt, "i", $kamar_id);
    mysqli_stmt_execute($room_info_stmt);
    $room_info = mysqli_fetch_assoc(mysqli_stmt_get_result($room_info_stmt));
    mysqli_stmt_close($room_info_stmt);
    
    $old_status = $room_info['status'] ?? '';
    $nomor_kamar = $room_info['nomor_kamar'] ?? '';
    
    if ($old_status === 'terisi' || $old_status === 'reserved') {
        echo json_encode(['success' => false, 'message' => 'Status kamar yang terisi/reserved tidak dapat diubah secara manual.']);
        exit();
    }
    
    $stmt = mysqli_prepare($connection, "UPDATE `kamar` SET `status` = ? WHERE `id` = ?");
    mysqli_stmt_bind_param($stmt, "si", $new_status, $kamar_id);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        
        // Log the status change
        logActivity($connection, 'status_change', 'room_management', $kamar_id, 
            "Kamar $nomor_kamar", 
            ['status' => $old_status], 
            ['status' => $new_status]
        );
        
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit();
}

// Filter by status
$filter_status = $_GET['status'] ?? '';
$where_clause = "";
if ($filter_status && in_array($filter_status, ['tersedia', 'kotor', 'maintenance', 'terisi', 'reserved'])) {
    $where_clause = "WHERE k.status = '" . mysqli_real_escape_string($connection, $filter_status) . "'";
}

// Get all rooms with type info
$rooms = mysqli_query($connection, "
    SELECT k.*, t.nama_tipe, t.harga_per_malam, t.kapasitas, t.fasilitas
    FROM kamar k
    LEFT JOIN tipe_kamar t ON k.id_tipe = t.id
    $where_clause
    ORDER BY k.lantai ASC, k.nomor_kamar ASC
");

// Room counts by status
$room_counts = [];
$count_query = mysqli_query($connection, "SELECT status, COUNT(*) as count FROM kamar GROUP BY status");
while ($row = mysqli_fetch_assoc($count_query)) {
    $room_counts[$row['status']] = $row['count'];
}
$total_rooms = array_sum($room_counts);

// Status badge helper
function getStatusBadge($status) {
    switch($status) {
        case 'tersedia': return ['bg-success', 'Bersih', 'bi-check-circle-fill'];
        case 'kotor': return ['bg-danger', 'Kotor', 'bi-exclamation-circle-fill'];
        case 'maintenance': return ['bg-warning', 'Maintenance', 'bi-tools'];
        case 'terisi': return ['bg-primary', 'Terisi', 'bi-person-fill'];
        case 'reserved': return ['bg-info', 'Reserved', 'bi-bookmark-fill'];
        default: return ['bg-secondary', 'Unknown', 'bi-question-circle'];
    }
}

// Get room thumbnail based on type
function getRoomThumbnail($nama_tipe) {
    $tipe_lower = strtolower($nama_tipe ?? '');
    if (strpos($tipe_lower, 'presidential') !== false) return 'presidential_suite.jpg';
    if (strpos($tipe_lower, 'suite') !== false) return 'suite.jpg';
    if (strpos($tipe_lower, 'superior') !== false) return 'superior.jpg';
    if (strpos($tipe_lower, 'deluxe') !== false) return 'deluxe.jpg';
    return 'standard.jpg';
}

$csrfToken = generateCSRFToken();
?>
<?php include '../views/'.$THEME.'/header.php'; ?>
<?php include '../views/'.$THEME.'/sidebar.php'; ?>
<?php include '../views/'.$THEME.'/topnav.php'; ?>
<?php include '../views/'.$THEME.'/upper_block.php'; ?>

<style>
/* Room Management - Hotel Bellmounth Premium Theme */
.room-management-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}
.room-management-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.75rem;
    font-weight: 700;
    color: #1A202C;
    margin: 0;
}
.room-management-title span {
    color: #C5A059;
}

/* Stats Cards */
.stats-row {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}
@media (max-width: 992px) {
    .stats-row { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 576px) {
    .stats-row { grid-template-columns: repeat(2, 1fr); }
}
.stat-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 16px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    text-decoration: none;
}
.stat-card.active {
    border-color: #1A202C;
    border-width: 2px;
}
.stat-card .stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    margin-bottom: 8px;
}
.stat-card .stat-icon.total { background: #1A202C; color: #C5A059; }
.stat-card .stat-icon.tersedia { background: #C6F6D5; color: #22543D; }
.stat-card .stat-icon.kotor { background: #FED7D7; color: #822727; }
.stat-card .stat-icon.maintenance { background: #FEFCBF; color: #744210; }
.stat-card .stat-icon.terisi { background: #BEE3F8; color: #2A4365; }
.stat-card .stat-count {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1A202C;
}
.stat-card .stat-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #718096;
}

/* Filter Bar */
.filter-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 12px;
}
.filter-bar .filter-label {
    font-size: 0.85rem;
    color: #718096;
}
.filter-bar .filter-label strong {
    color: #1A202C;
}

/* Room Grid */
.room-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}
@media (max-width: 1200px) { .room-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 768px) { .room-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px) { .room-grid { grid-template-columns: 1fr; } }

/* Room Card */
.room-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.2s ease;
    position: relative;
}
.room-card:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    transform: translateY(-4px);
}
.room-card .room-image {
    width: 100%;
    height: 140px;
    object-fit: cover;
    background: #F7FAFC;
}
.room-card .room-status-badge {
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    padding: 4px 10px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
}
.room-card .room-body {
    padding: 16px;
}
.room-card .room-number {
    font-family: 'Playfair Display', serif;
    font-size: 1.25rem;
    font-weight: 700;
    color: #1A202C;
    margin-bottom: 4px;
}
.room-card .room-type-badge {
    display: inline-block;
    background: #1A202C;
    color: #FFFFFF;
    font-size: 0.6rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    padding: 3px 8px;
    border-radius: 4px;
    margin-bottom: 10px;
}
.room-card .room-info {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 16px;
    font-size: 0.8rem;
    color: #718096;
    margin-bottom: 12px;
}
.room-card .room-info i {
    color: #C5A059;
    margin-right: 4px;
}
.room-card .room-price {
    font-size: 1rem;
    font-weight: 600;
    color: #C5A059;
    margin-bottom: 12px;
}
.room-card .room-price small {
    font-weight: 400;
    color: #A0AEC0;
    font-size: 0.75rem;
}

/* Status Dropdown */
.status-dropdown {
    position: relative;
}
.status-dropdown .dropdown-toggle {
    width: 100%;
    background: #F7FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 0.8rem;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.2s ease;
}
.status-dropdown .dropdown-toggle:hover {
    background: #EDF2F7;
    border-color: #CBD5E0;
}
.status-dropdown .dropdown-toggle:disabled {
    background: #F5F5F7 !important;
    border-color: #E8E8ED !important;
    color: #86868B !important;
    cursor: not-allowed !important;
    opacity: 0.65;
}
.status-dropdown .dropdown-menu {
    min-width: 100%;
}
.status-dropdown .dropdown-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.85rem;
    padding: 8px 12px;
}
.status-dropdown .dropdown-item .badge {
    font-size: 0.7rem;
}

/* Action Buttons */
.room-actions {
    display: flex;
    gap: 8px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #F0F0F0;
}
.room-actions .btn-action {
    flex: 1;
    padding: 6px 8px;
    font-size: 0.75rem;
    font-weight: 500;
    border-radius: 6px;
    border: 1px solid #E2E8F0;
    background: #FFFFFF;
    color: #4A5568;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    text-decoration: none;
}
.room-actions .btn-action:hover {
    background: #F7FAFC;
    border-color: #CBD5E0;
    color: #1A202C;
}
.room-actions .btn-action.btn-delete:hover {
    background: #FED7D7;
    border-color: #FC8181;
    color: #C53030;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #718096;
}
.empty-state i {
    font-size: 4rem;
    color: #CBD5E0;
    margin-bottom: 16px;
}
.empty-state h3 {
    font-family: 'Playfair Display', serif;
    color: #1A202C;
    margin-bottom: 8px;
}

/* Add Button */
.btn-add-room {
    background: linear-gradient(135deg, #1A202C 0%, #2D3748 100%);
    color: #FFFFFF;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    text-decoration: none;
}
.btn-add-room:hover {
    box-shadow: 0 4px 12px rgba(26, 32, 44, 0.3);
    transform: translateY(-2px);
    color: #FFFFFF;
}
</style>

<!-- Page Header -->
<div class="room-management-header">
    <h2 class="room-management-title">Kelola <span>Kamar</span></h2>
    <a href="add.php" class="btn-add-room">
        <i class="bi bi-plus-circle"></i>Tambah Kamar
    </a>
</div>

<!-- Stats Row -->
<div class="stats-row">
    <a href="index.php" class="stat-card <?= !$filter_status ? 'active' : '' ?>">
        <div class="stat-icon total"><i class="bi bi-door-open-fill"></i></div>
        <div class="stat-count"><?= $total_rooms ?></div>
        <div class="stat-label">Total Kamar</div>
    </a>
    <a href="index.php?status=tersedia" class="stat-card <?= $filter_status === 'tersedia' ? 'active' : '' ?>">
        <div class="stat-icon tersedia"><i class="bi bi-check-circle-fill"></i></div>
        <div class="stat-count"><?= $room_counts['tersedia'] ?? 0 ?></div>
        <div class="stat-label">Bersih</div>
    </a>
    <a href="index.php?status=kotor" class="stat-card <?= $filter_status === 'kotor' ? 'active' : '' ?>">
        <div class="stat-icon kotor"><i class="bi bi-exclamation-circle-fill"></i></div>
        <div class="stat-count"><?= $room_counts['kotor'] ?? 0 ?></div>
        <div class="stat-label">Kotor</div>
    </a>
    <a href="index.php?status=maintenance" class="stat-card <?= $filter_status === 'maintenance' ? 'active' : '' ?>">
        <div class="stat-icon maintenance"><i class="bi bi-tools"></i></div>
        <div class="stat-count"><?= $room_counts['maintenance'] ?? 0 ?></div>
        <div class="stat-label">Maintenance</div>
    </a>
    <a href="index.php?status=terisi" class="stat-card <?= $filter_status === 'terisi' ? 'active' : '' ?>">
        <div class="stat-icon terisi"><i class="bi bi-person-fill"></i></div>
        <div class="stat-count"><?= $room_counts['terisi'] ?? 0 ?></div>
        <div class="stat-label">Terisi</div>
    </a>
</div>

<!-- Filter Bar -->
<?php if ($filter_status): ?>
<div class="filter-bar">
    <span class="filter-label">
        Menampilkan: <strong><?= ucfirst($filter_status) ?></strong> (<?= mysqli_num_rows($rooms) ?> kamar)
    </span>
    <a href="index.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-x-lg me-1"></i>Reset Filter
    </a>
</div>
<?php endif; ?>

<!-- Room Grid -->
<?php if (mysqli_num_rows($rooms) > 0): ?>
<div class="room-grid">
    <?php while ($room = mysqli_fetch_assoc($rooms)): 
        $status_info = getStatusBadge($room['status']);
        $thumb_file = getRoomThumbnail($room['nama_tipe']);
        $thumb_url = base_url() . 'assets/default/images/' . $thumb_file;
    ?>
    <div class="room-card" data-room-id="<?= $room['id'] ?>">
        <img src="<?= $thumb_url ?>" alt="<?= htmlspecialchars($room['nama_tipe']) ?>" class="room-image" 
             onerror="this.src='<?= base_url() ?>assets/default/images/standard.jpg'">
        
        <div class="room-body">
            <div class="d-flex align-items-center gap-2 mb-2">
                <div class="room-number mb-0"><?= htmlspecialchars($room['nomor_kamar']) ?></div>
                <span class="room-status-badge badge <?= $status_info[0] ?>">
                    <i class="bi <?= $status_info[2] ?>"></i><?= $status_info[1] ?>
                </span>
            </div>
            <span class="room-type-badge"><?= htmlspecialchars($room['nama_tipe'] ?? 'No Type') ?></span>
            
            <div class="room-info">
                <span><i class="bi bi-building"></i>Lantai <?= $room['lantai'] ?></span>
                <span><i class="bi bi-people-fill"></i><?= $room['kapasitas'] ?? 2 ?> orang</span>
            </div>
            
            <div class="room-price">
                Rp <?= number_format($room['harga_per_malam'] ?? 0, 0, ',', '.') ?>
                <small>/malam</small>
            </div>
            
            <!-- Status Quick Change -->
            <div class="status-dropdown dropdown">
                <?php 
                $is_occupied_or_reserved = ($room['status'] === 'terisi' || $room['status'] === 'reserved');
                ?>
                <button class="dropdown-toggle" type="button" <?= $is_occupied_or_reserved ? 'disabled' : 'data-bs-toggle="dropdown"' ?> aria-expanded="false">
                    <span><i class="bi <?= $status_info[2] ?> me-1"></i>Ubah Status</span>
                </button>
                <?php if (!$is_occupied_or_reserved): ?>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item status-change" href="#" data-status="tersedia" data-room-id="<?= $room['id'] ?>">
                        <span class="badge bg-success"><i class="bi bi-check-circle-fill"></i></span> Bersih
                    </a></li>
                    <li><a class="dropdown-item status-change" href="#" data-status="kotor" data-room-id="<?= $room['id'] ?>">
                        <span class="badge bg-danger"><i class="bi bi-exclamation-circle-fill"></i></span> Kotor
                    </a></li>
                    <li><a class="dropdown-item status-change" href="#" data-status="maintenance" data-room-id="<?= $room['id'] ?>">
                        <span class="badge bg-warning"><i class="bi bi-tools"></i></span> Maintenance
                    </a></li>
                </ul>
                <?php endif; ?>
            </div>
            
            <!-- Action Buttons -->
            <div class="room-actions">
                <a href="edit.php?id=<?= $room['id'] ?>" class="btn-action">
                    <i class="bi bi-pencil"></i>Edit
                </a>
                <button type="button" class="btn-action btn-delete" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $room['id'] ?>">
                    <i class="bi bi-trash"></i>Hapus
                </button>
            </div>
        </div>
    </div>
    
    <!-- Delete Modal for each room -->
    <div class="modal fade" id="deleteModal<?= $room['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-5">
                    <div class="mb-4">
                        <div style="width: 80px; height: 80px; background-color: #f8d7da; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;">
                            <i class="bi bi-trash" style="font-size: 40px; color: #842029;"></i>
                        </div>
                    </div>
                    <h4 class="text-danger fw-bold mb-3">Hapus Kamar?</h4>
                    <p class="mb-2">Apakah Anda yakin ingin menghapus kamar ini?</p>
                    <div class="alert alert-light border my-3">
                        <strong><?= htmlspecialchars($room['nomor_kamar']) ?></strong> - <?= htmlspecialchars($room['nama_tipe'] ?? 'No Type') ?>
                    </div>
                </div>
                <div class="modal-footer justify-content-center border-0 pt-0 gap-2">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Batal</button>
                    <a href="delete.php?id=<?= $room['id'] ?>" class="btn btn-danger px-4">Ya, Hapus</a>
                </div>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
</div>
<?php else: ?>
<div class="empty-state">
    <i class="bi bi-door-closed d-block"></i>
    <h3>Tidak Ada Kamar</h3>
    <p><?= $filter_status ? 'Tidak ada kamar dengan status "' . ucfirst($filter_status) . '"' : 'Belum ada kamar yang terdaftar.' ?></p>
    <?php if (!$filter_status): ?>
    <a href="add.php" class="btn-add-room mt-3">
        <i class="bi bi-plus-circle"></i>Tambah Kamar Pertama
    </a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include '../views/'.$THEME.'/lower_block.php'; ?>

<script>
// Status change via AJAX
document.querySelectorAll('.status-change').forEach(item => {
    item.addEventListener('click', function(e) {
        e.preventDefault();
        
        const roomId = this.dataset.roomId;
        const newStatus = this.dataset.status;
        const card = document.querySelector(`.room-card[data-room-id="${roomId}"]`);
        
        // Show loading state
        const dropdown = card.querySelector('.dropdown-toggle');
        const originalText = dropdown.innerHTML;
        dropdown.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';
        dropdown.disabled = true;
        
        // Send AJAX request
        fetch('index.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `ajax_update_status=1&kamar_id=${roomId}&new_status=${newStatus}&csrf_token=<?= $csrfToken ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload page to reflect changes
                location.reload();
            } else {
                alert('Error: ' + data.message);
                dropdown.innerHTML = originalText;
                dropdown.disabled = false;
            }
        })
        .catch(error => {
            alert('Network error. Please try again.');
            dropdown.innerHTML = originalText;
            dropdown.disabled = false;
        });
    });
});
</script>

<?php include '../views/'.$THEME.'/footer.php'; ?>
