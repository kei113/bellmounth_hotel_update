<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.use_strict_mode', 1);
session_start();
require_once '../lib/auth.php';
require_once '../lib/functions.php';
require_once '../config/database.php';

requireAuth();
if (getUserRole() !== 'admin') {
    redirect('../login.php');
}

$message = '';
$messageType = '';

if (isset($_POST['backup_now'])) {
    $result = backupDatabaseSimple('database_backup');
    if ($result['success']) {
        $message = "Berhasil membuat backup: " . $result['file'];
        $messageType = 'success';
    } else {
        $message = "Gagal membuat backup: " . $result['error'];
        $messageType = 'danger';
    }
}

if (isset($_POST['open_folder'])) {
    $fullPath = realpath('../backups');
    if ($fullPath && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        pclose(popen("start explorer " . escapeshellarg($fullPath), "r"));
        $message = "Folder backup dibuka di File Explorer.";
        $messageType = 'info';
    } else {
        $message = "Fitur ini hanya tersedia di sistem operasi Windows dengan folder yang valid.";
        $messageType = 'warning';
    }
}

// Get list of existing backups
$backupDir = '../backups';
$backups = [];
if (is_dir($backupDir)) {
    $files = scandir($backupDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && str_ends_with($file, '.sql')) {
            $backups[] = [
                'name' => $file,
                'size' => filesize($backupDir . '/' . $file),
                'date' => date('d M Y, H:i:s', filemtime($backupDir . '/' . $file))
            ];
        }
    }
    // Sort by date descending
    usort($backups, function($a, $b) use ($backupDir) {
        $mtimeA = file_exists($backupDir . '/' . $a['name']) ? filemtime($backupDir . '/' . $a['name']) : 0;
        $mtimeB = file_exists($backupDir . '/' . $b['name']) ? filemtime($backupDir . '/' . $b['name']) : 0;
        return $mtimeB - $mtimeA;
    });
}
?>

<?php include '../views/'.$THEME.'/header.php'; ?>
<?php include '../views/'.$THEME.'/sidebar.php'; ?>
<?php include '../views/'.$THEME.'/topnav.php'; ?>
<?php include '../views/'.$THEME.'/upper_block.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Backup Database</h2>
    <div class="text-muted"><?= date('l, d F Y') ?></div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-cloud-arrow-up me-2"></i>Buat Backup Baru</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">Klik tombol di bawah untuk membuat salinan database saat ini. File akan disimpan di folder <code>backups/</code>.</p>
                <form method="POST">
                    <button type="submit" name="backup_now" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-hdd-fill me-2"></i>Backup Sekarang
                    </button>
                    <button type="submit" name="open_folder" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-folder2-open me-2"></i>Buka Folder Backup
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-list-task me-2"></i>Daftar Backup Tersedia</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Nama File</th>
                                <th>Ukuran</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($backups) > 0): ?>
                                <?php foreach ($backups as $b): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars($b['name']) ?></code></td>
                                        <td><?= round($b['size'] / 1024, 2) ?> KB</td>
                                        <td><?= $b['date'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted">Belum ada file backup yang tersimpan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../views/'.$THEME.'/lower_block.php'; ?>
<?php include '../views/'.$THEME.'/footer.php'; ?>
