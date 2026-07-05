<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.use_strict_mode', 1);
session_start();
require_once '../lib/functions.php';
require_once '../lib/auth.php';
requireAuth();
requireModuleAccess('activity_logs');
require_once '../config/database.php';
require_once '../lib/activity_logger.php';

// Get filter parameters
$filters = [];
if (!empty($_GET['module'])) $filters['module'] = $_GET['module'];
if (!empty($_GET['action'])) $filters['action'] = $_GET['action'];
if (!empty($_GET['date_from'])) $filters['date_from'] = $_GET['date_from'];
if (!empty($_GET['date_to'])) $filters['date_to'] = $_GET['date_to'];
if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Get logs and total count
$logs = getActivityLogs($connection, $filters, $perPage, $offset);
$totalLogs = countActivityLogs($connection, $filters);
$totalPages = ceil($totalLogs / $perPage);

// Get unique modules and actions for filter dropdowns
$modules_query = mysqli_query($connection, "SELECT DISTINCT module FROM activity_logs ORDER BY module");
$actions_query = mysqli_query($connection, "SELECT DISTINCT action FROM activity_logs ORDER BY action");
?>
<?php include '../views/'.$THEME.'/header.php'; ?>
<?php include '../views/'.$THEME.'/sidebar.php'; ?>
<?php include '../views/'.$THEME.'/topnav.php'; ?>
<?php include '../views/'.$THEME.'/upper_block.php'; ?>

<style>
/* Activity Log Page - Premium Styling */
.activity-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}
.activity-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.75rem;
    font-weight: 700;
    color: #1A202C;
    margin: 0;
}
.activity-title i {
    color: #C5A059;
}

/* Filter Card */
.filter-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
}
.filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: flex-end;
}
.filter-group {
    flex: 1;
    min-width: 150px;
}
.filter-group label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #718096;
    margin-bottom: 6px;
}
.filter-group .form-control,
.filter-group .form-select {
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    font-size: 0.9rem;
}
.filter-group .form-control:focus,
.filter-group .form-select:focus {
    border-color: #C5A059;
    box-shadow: 0 0 0 3px rgba(197, 160, 89, 0.15);
}
.filter-buttons {
    display: flex;
    gap: 8px;
}
.btn-filter {
    background: #1A202C;
    color: #FFFFFF;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 500;
    font-size: 0.9rem;
}
.btn-filter:hover {
    background: #2D3748;
    color: #FFFFFF;
}
.btn-reset {
    background: #FFFFFF;
    color: #4A5568;
    border: 1px solid #E2E8F0;
    padding: 10px 16px;
    border-radius: 8px;
    font-weight: 500;
    font-size: 0.9rem;
    text-decoration: none;
}
.btn-reset:hover {
    background: #F7FAFC;
    color: #1A202C;
}

/* Stats Summary */
.stats-summary {
    display: flex;
    gap: 16px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.stat-badge {
    background: #F7FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 8px 16px;
    font-size: 0.85rem;
    color: #4A5568;
}
.stat-badge strong {
    color: #1A202C;
}

/* Activity Table */
.activity-table-container {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    overflow: hidden;
}
.activity-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}
.activity-table thead th {
    background: #F7FAFC;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #718096;
    padding: 14px 16px;
    text-align: left;
    border-bottom: 2px solid #E2E8F0;
}
.activity-table tbody td {
    padding: 14px 16px;
    border-bottom: 1px solid #F0F0F0;
    font-size: 0.9rem;
    color: #1A202C;
    vertical-align: middle;
}
.activity-table tbody tr:last-child td {
    border-bottom: none;
}
.activity-table tbody tr:hover {
    background: #FAFAFA;
}

/* Activity Row Styles */
.activity-time {
    font-size: 0.8rem;
    color: #718096;
    white-space: nowrap;
}
.activity-time .date {
    font-weight: 600;
    color: #4A5568;
    display: block;
}
.activity-user {
    display: flex;
    align-items: center;
    gap: 10px;
}
.activity-user .user-avatar {
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, #1A202C 0%, #2D3748 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #C5A059;
    font-size: 0.8rem;
    font-weight: 600;
}
.activity-user .user-name {
    font-weight: 500;
}
.activity-user .user-ip {
    font-size: 0.75rem;
    color: #A0AEC0;
}
.activity-action {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
}
.activity-module {
    display: inline-block;
    background: #EDF2F7;
    color: #4A5568;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 4px;
    text-transform: uppercase;
}
.activity-record {
    font-weight: 500;
    color: #1A202C;
}
.activity-record-id {
    font-size: 0.75rem;
    color: #A0AEC0;
}
.activity-changes {
    font-size: 0.8rem;
}
.activity-changes .old-value {
    color: #C53030;
    text-decoration: line-through;
}
.activity-changes .new-value {
    color: #38A169;
}

/* Pagination */
.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-top: 1px solid #E2E8F0;
    flex-wrap: wrap;
    gap: 12px;
}
.pagination-info {
    font-size: 0.85rem;
    color: #718096;
}
.pagination {
    margin: 0;
}
.pagination .page-link {
    border: 1px solid #E2E8F0;
    color: #4A5568;
    padding: 8px 14px;
    font-size: 0.85rem;
}
.pagination .page-item.active .page-link {
    background: #1A202C;
    border-color: #1A202C;
}
.pagination .page-link:hover {
    background: #F7FAFC;
    border-color: #CBD5E0;
    color: #1A202C;
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

/* Details Modal */
.details-json {
    background: #1A202C;
    color: #A0AEC0;
    padding: 16px;
    border-radius: 8px;
    font-family: 'Courier New', monospace;
    font-size: 0.8rem;
    max-height: 200px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-break: break-all;
}
.details-json .key {
    color: #C5A059;
}
</style>

<!-- Page Header -->
<div class="activity-header">
    <h2 class="activity-title"><i class="bi bi-journal-text me-2"></i>Activity Log</h2>
</div>

<!-- Filter Card -->
<div class="filter-card">
    <form method="GET" class="filter-row">
        <div class="filter-group">
            <label>Cari</label>
            <input type="text" name="search" class="form-control" placeholder="Cari user, aksi, info..." 
                   value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
        </div>
        <div class="filter-group" style="min-width: 130px;">
            <label>Modul</label>
            <select name="module" class="form-select">
                <option value="">Semua Modul</option>
                <?php while ($mod = mysqli_fetch_assoc($modules_query)): ?>
                <option value="<?= htmlspecialchars($mod['module']) ?>" 
                        <?= ($_GET['module'] ?? '') === $mod['module'] ? 'selected' : '' ?>>
                    <?= getModuleLabel($mod['module']) ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="filter-group" style="min-width: 130px;">
            <label>Aksi</label>
            <select name="action" class="form-select">
                <option value="">Semua Aksi</option>
                <?php while ($act = mysqli_fetch_assoc($actions_query)): ?>
                <option value="<?= htmlspecialchars($act['action']) ?>"
                        <?= ($_GET['action'] ?? '') === $act['action'] ? 'selected' : '' ?>>
                    <?= getActionLabel($act['action']) ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="filter-group" style="min-width: 140px;">
            <label>Dari Tanggal</label>
            <input type="date" name="date_from" class="form-control" 
                   value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>">
        </div>
        <div class="filter-group" style="min-width: 140px;">
            <label>Sampai Tanggal</label>
            <input type="date" name="date_to" class="form-control" 
                   value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>">
        </div>
        <div class="filter-buttons">
            <button type="submit" class="btn-filter"><i class="bi bi-funnel me-1"></i>Filter</button>
            <a href="activity_logs.php" class="btn-reset"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>
</div>

<!-- Stats Summary -->
<div class="stats-summary">
    <span class="stat-badge"><strong><?= number_format($totalLogs) ?></strong> total aktivitas</span>
    <?php if (!empty($filters)): ?>
    <span class="stat-badge">Filter aktif: <strong><?= count($filters) ?></strong></span>
    <?php endif; ?>
</div>

<!-- Activity Table -->
<div class="activity-table-container">
    <?php if (count($logs) > 0): ?>
    <table class="activity-table">
        <thead>
            <tr>
                <th>Waktu</th>
                <th>User</th>
                <th>Aksi</th>
                <th>Modul</th>
                <th>Keterangan</th>
                <th>Perubahan</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td>
                    <div class="activity-time">
                        <span class="date"><?= date('d M Y', strtotime($log['created_at'])) ?></span>
                        <?= date('H:i:s', strtotime($log['created_at'])) ?>
                    </div>
                </td>
                <td>
                    <div class="activity-user">
                        <div class="user-avatar">
                            <?= strtoupper(substr($log['username'] ?? 'S', 0, 1)) ?>
                        </div>
                        <div>
                            <div class="user-name"><?= htmlspecialchars($log['username'] ?? 'System') ?></div>
                            <div class="user-ip"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></div>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="activity-action badge <?= getActionBadgeClass($log['action']) ?>">
                        <i class="bi <?= getActionIcon($log['action']) ?>"></i>
                        <?= getActionLabel($log['action']) ?>
                    </span>
                </td>
                <td>
                    <span class="activity-module"><?= getModuleLabel($log['module']) ?></span>
                </td>
                <td>
                    <?php if ($log['record_info']): ?>
                    <div class="activity-record"><?= htmlspecialchars($log['record_info']) ?></div>
                    <?php endif; ?>
                    <?php if ($log['record_id']): ?>
                    <div class="activity-record-id">ID: #<?= $log['record_id'] ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($log['old_value'] || $log['new_value']): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" 
                            data-bs-toggle="modal" data-bs-target="#detailModal<?= $log['id'] ?>">
                        <i class="bi bi-eye"></i> Lihat
                    </button>
                    <?php else: ?>
                    <span class="text-muted">-</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination-container">
        <div class="pagination-info">
            Menampilkan <?= $offset + 1 ?> - <?= min($offset + $perPage, $totalLogs) ?> dari <?= number_format($totalLogs) ?> aktivitas
        </div>
        <nav>
            <ul class="pagination">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php 
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                for ($i = $startPage; $i <= $endPage; $i++): 
                ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
    
    <?php else: ?>
    <div class="empty-state">
        <i class="bi bi-journal-x d-block"></i>
        <h3>Tidak Ada Aktivitas</h3>
        <p><?= !empty($filters) ? 'Tidak ada aktivitas yang sesuai dengan filter.' : 'Belum ada aktivitas yang tercatat di sistem.' ?></p>
    </div>
    <?php endif; ?>
</div>

<!-- Detail Modals -->
<?php foreach ($logs as $log): ?>
<?php if ($log['old_value'] || $log['new_value']): ?>
<div class="modal fade" id="detailModal<?= $log['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi <?= getActionIcon($log['action']) ?> me-2"></i>
                    Detail Perubahan - <?= getActionLabel($log['action']) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-danger">
                            <i class="bi bi-arrow-left-circle me-1"></i>Nilai Lama
                        </label>
                        <div class="details-json">
                            <?php 
                            $oldVal = $log['old_value'];
                            $decoded = json_decode($oldVal, true);
                            if ($decoded !== null) {
                                echo '<pre>' . htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
                            } else {
                                echo htmlspecialchars($oldVal ?: '-');
                            }
                            ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-success">
                            <i class="bi bi-arrow-right-circle me-1"></i>Nilai Baru
                        </label>
                        <div class="details-json">
                            <?php 
                            $newVal = $log['new_value'];
                            $decoded = json_decode($newVal, true);
                            if ($decoded !== null) {
                                echo '<pre>' . htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
                            } else {
                                echo htmlspecialchars($newVal ?: '-');
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="row text-muted small">
                    <div class="col-md-4">
                        <strong>User:</strong> <?= htmlspecialchars($log['username'] ?? 'System') ?>
                    </div>
                    <div class="col-md-4">
                        <strong>IP:</strong> <?= htmlspecialchars($log['ip_address'] ?? '-') ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Waktu:</strong> <?= date('d M Y H:i:s', strtotime($log['created_at'])) ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endforeach; ?>

<?php include '../views/'.$THEME.'/lower_block.php'; ?>
<?php include '../views/'.$THEME.'/footer.php'; ?>
