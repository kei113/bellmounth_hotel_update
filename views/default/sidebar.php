<?php 
$menuConfig = loadMenuConfig();

// Detect current page for active state
$currentPath = $_SERVER['REQUEST_URI'] ?? '';
$isAdmin = strpos($currentPath, '/admin/index') !== false;
$isRoomManagement = strpos($currentPath, '/room_management') !== false;
$isBooking = strpos($currentPath, '/reservasi') !== false;
$isFnbMenu = strpos($currentPath, '/fnb_menu') !== false;
$isRoomService = strpos($currentPath, '/room_service') !== false;
?>
<aside class="sidebar" id="sidebar">
<div class="sidebar-header">
<a href="<?= base_url() ?>" class="sidebar-logo">
<i class="bi bi-buildings-fill"></i>
<span>Hotel Bellmounth</span>
</a>
</div>
<nav class="sidebar-menu">
<?php if ($_SESSION['role'] === 'admin'): ?>
<div class="sidebar-item">
<a href="<?= base_url('admin/index.php') ?>" class="sidebar-link <?= $isAdmin ? 'active' : '' ?>">
<i class="bi bi-speedometer2"></i>
<span>Dashboard</span>
</a>
</div>
<div class="sidebar-item">
<a href="<?= base_url('room_management/index.php') ?>" class="sidebar-link <?= $isRoomManagement ? 'active' : '' ?>">
<i class="bi bi-door-open"></i>
<span>Kelola Kamar</span>
</a>
</div>
<?php endif; ?>
<div class="sidebar-item">
<a href="<?= base_url('reservasi/index.php') ?>" class="sidebar-link <?= $isBooking ? 'active' : '' ?>">
<i class="bi bi-calendar-check"></i>
<span>Reservasi</span>
</a>
</div>
<?php if ($_SESSION['role'] === 'admin'): ?>
<div class="sidebar-item">
<a href="<?= base_url('fnb_menu/index.php') ?>" class="sidebar-link <?= $isFnbMenu ? 'active' : '' ?>">
<i class="bi bi-cup-hot-fill"></i>
<span>Menu F&B</span>
</a>
</div>
<?php endif; ?>
<div class="sidebar-item">
<a href="<?= base_url('room_service/index.php') ?>" class="sidebar-link <?= $isRoomService ? 'active' : '' ?>">
<i class="bi bi-cart3"></i>
<span>Room Service</span>
</a>
</div>
<div class="sidebar-item">
<a href="<?= base_url('admin/activity_logs.php') ?>" class="sidebar-link <?= $isActivityLogs ? 'active' : '' ?>">
<i class="bi bi-journal-text"></i>
<span>Activity Log</span>
        </a>
    </div>
    <?php if ($_SESSION['role'] === 'admin'): ?>
    <div class="sidebar-item">
        <a href="<?= base_url('admin/backup.php') ?>" class="sidebar-link <?= $isBackup ? 'active' : '' ?>">
            <i class="bi bi-hdd-fill"></i>
            <span>Backup Database</span>
        </a>
    </div>
    <?php endif; ?>
<div class="sidebar-item">
<a href="<?= base_url('logout.php') ?>" class="sidebar-link">
<i class="bi bi-box-arrow-right"></i>
<span>Logout</span>
</a>
</div>
</nav>
</aside>
