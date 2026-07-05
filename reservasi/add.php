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
require_once '../lib/activity_logger.php';

$error = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token.');
    }
    $no_reservasi = trim($_POST['no_reservasi'] ?? '');
    $tanggal_checkin_input = trim($_POST['tanggal_checkin'] ?? date('d/m/Y'));
    $tanggal_checkout_input = trim($_POST['tanggal_checkout'] ?? '');
    
    // Convert DD/MM/YYYY to Y-m-d for database
    $tanggal_checkin = '';
    $tanggal_checkout = '';
    if (!empty($tanggal_checkin_input)) {
        $date = DateTime::createFromFormat('d/m/Y', $tanggal_checkin_input);
        if ($date) $tanggal_checkin = $date->format('Y-m-d');
    }
    if (!empty($tanggal_checkout_input)) {
        $date = DateTime::createFromFormat('d/m/Y', $tanggal_checkout_input);
        if ($date) $tanggal_checkout = $date->format('Y-m-d');
    }
    
    $nama_tamu = trim($_POST['nama_tamu'] ?? '');
    $no_identitas = trim($_POST['no_identitas'] ?? '');
    $no_telepon = trim($_POST['no_telepon'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $jumlah_tamu = (int)($_POST['jumlah_tamu'] ?? 1);
    $catatan = trim($_POST['catatan'] ?? '');
    
    // Handle File Upload for Foto Identitas
    $foto_identitas = '';
    if (isset($_FILES['foto_identitas']) && $_FILES['foto_identitas']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['foto_identitas']['tmp_name'];
        $fileName = $_FILES['foto_identitas']['name'];
        $fileSize = $_FILES['foto_identitas']['size'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        $allowedfileExtensions = array('jpg', 'jpeg', 'png');
        if (in_array($fileExtension, $allowedfileExtensions)) {
            if ($fileSize < 2 * 1024 * 1024) { // 2MB
                $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                $uploadFileDir = '../assets/uploads/identitas/';
                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0755, true);
                }
                $dest_path = $uploadFileDir . $newFileName;

                if(move_uploaded_file($fileTmpPath, $dest_path)) {
                    $foto_identitas = $newFileName;
                } else {
                    $error = 'Terjadi kesalahan saat menyimpan file.';
                }
            } else {
                $error = 'Ukuran file terlalu besar. Maksimal 2MB.';
            }
        } else {
            $error = 'Format file tidak didukung. Gunakan JPG, JPEG, atau PNG.';
        }
    }

    // Validate all required fields
    if (empty($no_reservasi)) $errors[] = "No Reservasi";
    if (empty($tanggal_checkin)) $errors[] = "Tanggal Check-In";
    if (empty($tanggal_checkout)) $errors[] = "Tanggal Check-Out";
    if (empty($nama_tamu)) $errors[] = "Nama Tamu";
    if (empty($no_identitas)) $errors[] = "No. Identitas (KTP/SIM)";
    if (empty($no_telepon)) $errors[] = "No. Telepon";
    if (empty($email)) $errors[] = "Email";
    if (empty($alamat)) $errors[] = "Alamat";
    if ($jumlah_tamu < 1) $errors[] = "Jumlah Tamu";
    
    // Validate checkout date must be after checkin date
    if (!empty($tanggal_checkin) && !empty($tanggal_checkout)) {
        if (strtotime($tanggal_checkout) <= strtotime($tanggal_checkin)) {
            $error = "Tanggal Check-Out harus lebih dari tanggal Check-In.";
        }
    }
    
    if (!empty($errors) && !$error) {
        $error = "Field berikut wajib diisi: " . implode(", ", $errors);
    }
    
    if (!$error) {
        $stmt = mysqli_prepare($connection, "INSERT INTO `reservasi` (`no_reservasi`, `tanggal_checkin`, `tanggal_checkout`, `nama_tamu`, `no_identitas`, `foto_identitas`, `no_telepon`, `email`, `alamat`, `jumlah_tamu`, `catatan`, `total_bayar`, `dp_bayar`, `sisa_bayar`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 'pending')");
        mysqli_stmt_bind_param($stmt, "sssssssssis", $no_reservasi, $tanggal_checkin, $tanggal_checkout, $nama_tamu, $no_identitas, $foto_identitas, $no_telepon, $email, $alamat, $jumlah_tamu, $catatan);
        
        if (mysqli_stmt_execute($stmt)) {
            $new_id = mysqli_insert_id($connection);
            mysqli_stmt_close($stmt);
            
            // Log booking creation
            logActivity($connection, 'create', 'reservasi', $new_id, "$no_reservasi - $nama_tamu", null, [
                'nama_tamu' => $nama_tamu,
                'checkin' => $tanggal_checkin,
                'checkout' => $tanggal_checkout,
                'jumlah_tamu' => $jumlah_tamu,
                'status' => 'pending'
            ]);
            
            header("Location: detail.php?id=$new_id");
            exit();
        } else {
            $error = "Gagal menyimpan reservasi: " . mysqli_error($connection);
        }
        mysqli_stmt_close($stmt);
    }
}

// Generate no reservasi otomatis
$today = date('Ymd');
$prefix = "BLMH-$today-";
$result = mysqli_query($connection, "SELECT MAX(CAST(SUBSTRING(no_reservasi, 15) AS UNSIGNED)) as max_num FROM reservasi WHERE no_reservasi LIKE '$prefix%'");
$row = mysqli_fetch_assoc($result);
$next_num = ($row['max_num'] ?? 0) + 1;
$auto_booking = $prefix . str_pad($next_num, 4, '0', STR_PAD_LEFT);

$csrfToken = generateCSRFToken();
?>
<?php include '../views/'.$THEME.'/header.php'; ?>
<?php include '../views/'.$THEME.'/sidebar.php'; ?>
<?php include '../views/'.$THEME.'/topnav.php'; ?>
<?php include '../views/'.$THEME.'/upper_block.php'; ?>
<h2>Tambah Reservasi Baru</h2>
<?php if ($error): ?>
<?= showAlert($error, 'danger') ?>
<?php endif; ?>
<form method="POST" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <div class="row">
        <div class="col-md-4">
            <div class="mb-3">
                <label class="form-label">No Reservasi*</label>
                <input type="text" name="no_reservasi" class="form-control" value="<?= htmlspecialchars($_POST['no_reservasi'] ?? $auto_booking) ?>" required>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <label class="form-label">Tanggal Check-In*</label>
                <input type="text" name="tanggal_checkin" id="tanggal_checkin" class="form-control datepicker" value="<?= htmlspecialchars($_POST['tanggal_checkin'] ?? date('d/m/Y')) ?>" placeholder="DD/MM/YYYY" required>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <label class="form-label">Tanggal Check-Out*</label>
                <input type="text" name="tanggal_checkout" id="tanggal_checkout" class="form-control datepicker" value="<?= htmlspecialchars($_POST['tanggal_checkout'] ?? date('d/m/Y', strtotime('+1 day'))) ?>" placeholder="DD/MM/YYYY" required>
                <div class="invalid-feedback" id="checkout-error">Tanggal Check-Out harus lebih dari Check-In</div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label class="form-label">Nama Tamu*</label>
                <input type="text" name="nama_tamu" class="form-control" value="<?= htmlspecialchars($_POST['nama_tamu'] ?? '') ?>" required>
            </div>
        </div>
        <div class="col-md-3">
            <div class="mb-3">
                <label class="form-label">No. Identitas (KTP/SIM)*</label>
                <input type="text" name="no_identitas" class="form-control" value="<?= htmlspecialchars($_POST['no_identitas'] ?? '') ?>" required>
            </div>
        </div>
        <div class="col-md-3">
            <div class="mb-3">
                <label class="form-label">Foto KTP/SIM <small class="text-muted">(opsional)</small></label>
                <input type="file" name="foto_identitas" class="form-control" accept="image/*">
            </div>
        </div>
        <div class="col-md-3">
            <div class="mb-3">
                <label class="form-label">Jumlah Tamu*</label>
                <input type="number" name="jumlah_tamu" class="form-control" value="<?= htmlspecialchars($_POST['jumlah_tamu'] ?? '1') ?>" min="1" required>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label class="form-label">No. Telepon*</label>
                <input type="text" name="no_telepon" class="form-control" value="<?= htmlspecialchars($_POST['no_telepon'] ?? '') ?>" required>
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label class="form-label">Email*</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label">Alamat*</label>
        <textarea name="alamat" class="form-control" rows="2" required><?= htmlspecialchars($_POST['alamat'] ?? '') ?></textarea>
    </div>
    <div class="mb-3">
        <label class="form-label">Catatan <small class="text-muted">(opsional)</small></label>
        <textarea name="catatan" class="form-control" rows="2"><?= htmlspecialchars($_POST['catatan'] ?? '') ?></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Simpan & Pilih Kamar</button>
    <a href="index.php" class="btn btn-secondary">Batal</a>
</form>

<script>
(function() {
    // Initialize Flatpickr with Indonesian locale
    const checkinPicker = flatpickr('#tanggal_checkin', {
        locale: 'id',
        dateFormat: 'd/m/Y',
        altInput: true,
        altFormat: 'd F Y',
        minDate: 'today',
        onChange: function(selectedDates, dateStr) {
            if (selectedDates.length > 0) {
                // Set minimum checkout date to day after checkin
                const minCheckout = new Date(selectedDates[0]);
                minCheckout.setDate(minCheckout.getDate() + 1);
                checkoutPicker.set('minDate', minCheckout);
                
                // Auto-adjust checkout if now invalid
                const currentCheckout = checkoutPicker.selectedDates[0];
                if (currentCheckout && currentCheckout <= selectedDates[0]) {
                    checkoutPicker.setDate(minCheckout);
                }
            }
            validateDates();
        }
    });
    
    const checkoutPicker = flatpickr('#tanggal_checkout', {
        locale: 'id',
        dateFormat: 'd/m/Y',
        altInput: true,
        altFormat: 'd F Y',
        minDate: new Date().fp_incr(1), // Tomorrow
        onChange: function() {
            validateDates();
        }
    });
    
    function validateDates() {
        const checkinDate = checkinPicker.selectedDates[0];
        const checkoutDate = checkoutPicker.selectedDates[0];
        const checkoutInput = document.getElementById('tanggal_checkout');
        
        if (checkinDate && checkoutDate) {
            if (checkoutDate <= checkinDate) {
                checkoutInput.classList.add('is-invalid');
                return false;
            } else {
                checkoutInput.classList.remove('is-invalid');
                return true;
            }
        }
        return true;
    }
    
    // Form submit validation
    document.querySelector('form').addEventListener('submit', function(e) {
        if (!validateDates()) {
            e.preventDefault();
            document.getElementById('tanggal_checkout').focus();
        }
    });
})();
</script>

<?php include '../views/'.$THEME.'/lower_block.php'; ?>
<?php include '../views/'.$THEME.'/footer.php'; ?>
