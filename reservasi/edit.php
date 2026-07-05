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

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('index.php');

$stmt = mysqli_prepare($connection, "SELECT * FROM `reservasi` WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$booking) redirect('index.php');

// Only allow edit if status is pending (no kamar added yet)
if ($booking['status'] !== 'pending' || $booking['total_bayar'] > 0) {
    redirect("reservasi/detail.php?id=$id");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) die('Invalid CSRF token.');
    
    $no_reservasi = trim($_POST['no_reservasi'] ?? '');
    $tanggal_checkin_input = trim($_POST['tanggal_checkin'] ?? '');
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
    $foto_identitas = $booking['foto_identitas'];
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
                    // Delete old file if exists
                    if (!empty($booking['foto_identitas']) && file_exists($uploadFileDir . $booking['foto_identitas'])) {
                        unlink($uploadFileDir . $booking['foto_identitas']);
                    }
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

    if (empty($no_reservasi) || empty($tanggal_checkin) || empty($tanggal_checkout) || empty($nama_tamu)) {
        $error = "No Reservasi, Tanggal Checkin, Tanggal Checkout, dan Nama Tamu wajib diisi.";
    }
    
    // Validate checkout date must be after checkin date
    if (!$error && !empty($tanggal_checkin) && !empty($tanggal_checkout)) {
        if (strtotime($tanggal_checkout) <= strtotime($tanggal_checkin)) {
            $error = "Tanggal Check-Out harus lebih dari tanggal Check-In.";
        }
    }
    
    if (!$error) {
        $stmt = mysqli_prepare($connection, "UPDATE `reservasi` SET `no_reservasi` = ?, `tanggal_checkin` = ?, `tanggal_checkout` = ?, `nama_tamu` = ?, `no_identitas` = ?, `foto_identitas` = ?, `no_telepon` = ?, `email` = ?, `alamat` = ?, `jumlah_tamu` = ?, `catatan` = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ssssssssisii", $no_reservasi, $tanggal_checkin, $tanggal_checkout, $nama_tamu, $no_identitas, $foto_identitas, $no_telepon, $email, $alamat, $jumlah_tamu, $catatan, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            redirect("reservasi/detail.php?id=$id");
            exit();
        } else {
            $error = "Gagal memperbarui booking: " . mysqli_error($connection);
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
<h2>Edit Booking</h2>
<?php if ($error): ?>
<?= showAlert($error, 'danger') ?>
<?php endif; ?>
<form method="POST" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <div class="row">
        <div class="col-md-4">
            <div class="mb-3">
                <label class="form-label">No Booking*</label>
                <input type="text" name="no_reservasi" class="form-control" value="<?= htmlspecialchars($booking['no_reservasi']) ?>" required>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <label class="form-label">Tanggal Check-In*</label>
                <input type="text" name="tanggal_checkin" id="tanggal_checkin" class="form-control datepicker" value="<?= date('d/m/Y', strtotime($booking['tanggal_checkin'])) ?>" placeholder="DD/MM/YYYY" required>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <label class="form-label">Tanggal Check-Out*</label>
                <input type="text" name="tanggal_checkout" id="tanggal_checkout" class="form-control datepicker" value="<?= date('d/m/Y', strtotime($booking['tanggal_checkout'])) ?>" placeholder="DD/MM/YYYY" required>
                <div class="invalid-feedback">Tanggal Check-Out harus lebih dari Check-In</div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label class="form-label">Nama Tamu*</label>
                <input type="text" name="nama_tamu" class="form-control" value="<?= htmlspecialchars($booking['nama_tamu']) ?>" required>
            </div>
        </div>
        <div class="col-md-3">
            <div class="mb-3">
                <label class="form-label">No. Identitas (KTP/SIM)</label>
                <input type="text" name="no_identitas" class="form-control" value="<?= htmlspecialchars($booking['no_identitas'] ?? '') ?>">
            </div>
        </div>
        <div class="col-md-3">
            <div class="mb-3">
                <label class="form-label">Foto KTP/SIM <small class="text-muted">(opsional)</small></label>
                <input type="file" name="foto_identitas" class="form-control" accept="image/*">
                <?php if (!empty($booking['foto_identitas'])): ?>
                    <div class="mt-2">
                        <small class="text-muted d-block mb-1">Foto saat ini:</small>
                        <img src="../assets/uploads/identitas/<?= htmlspecialchars($booking['foto_identitas']) ?>" alt="KTP/SIM" class="img-thumbnail" style="max-height: 100px;">
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-3">
            <div class="mb-3">
                <label class="form-label">Jumlah Tamu</label>
                <input type="number" name="jumlah_tamu" class="form-control" value="<?= $booking['jumlah_tamu'] ?>" min="1">
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label class="form-label">No. Telepon</label>
                <input type="text" name="no_telepon" class="form-control" value="<?= htmlspecialchars($booking['no_telepon'] ?? '') ?>">
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($booking['email'] ?? '') ?>">
            </div>
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label">Alamat</label>
        <textarea name="alamat" class="form-control" rows="2"><?= htmlspecialchars($booking['alamat'] ?? '') ?></textarea>
    </div>
    <div class="mb-3">
        <label class="form-label">Catatan</label>
        <textarea name="catatan" class="form-control" rows="2"><?= htmlspecialchars($booking['catatan'] ?? '') ?></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Perbarui</button>
    <a href="detail.php?id=<?= $id ?>" class="btn btn-secondary">Batal</a>
</form>

<script>
(function() {
    // Initialize Flatpickr with Indonesian locale
    const checkinPicker = flatpickr('#tanggal_checkin', {
        locale: 'id',
        dateFormat: 'd/m/Y',
        altInput: true,
        altFormat: 'd F Y',
        onChange: function(selectedDates, dateStr) {
            if (selectedDates.length > 0) {
                const minCheckout = new Date(selectedDates[0]);
                minCheckout.setDate(minCheckout.getDate() + 1);
                checkoutPicker.set('minDate', minCheckout);
                
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
