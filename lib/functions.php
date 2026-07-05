<?php
function sanitize($data) {
return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
function redirect($url) {
// If URL is relative, prepend BASE_URL
if (!preg_match('~^(https?://|//)~', $url) && !str_starts_with($url, '/')) {
$url = BASE_URL . '/' . ltrim($url, '/');
}
header("Location: " . $url);
exit();
}
function showAlert($message, $type = 'danger') {
$safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
echo "<div class='alert alert-$type alert-dismissible fade show' role='alert'>
$safeMessage
<button type='button' class='btn-close' data-bs-dismiss='alert'></button>
</div>";
}
function isLoggedIn() {
return isset($_SESSION['user_id']);
}
function getUserRole() {
return $_SESSION['role'] ?? null;
}
//=== NEW: CSRF Functions===
function generateCSRFToken() {
if (empty($_SESSION['csrf_token'])) {
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
return $_SESSION['csrf_token'];
}
function validateCSRFToken($token) {
return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}
function validatePassword($password, $enabled = true) {
// If validation is disabled, always return valid
if (!$enabled) {
return []; // Always valid when disabled
}
$errors = [];
if (strlen($password) < 8) {
$errors[] = "Password must be at least 8 characters.";
}
if (!preg_match('/[A-Z]/', $password)) {
$errors[] = "Password must contain at least one uppercase letter.";
}
if (!preg_match('/[a-z]/', $password)) {
$errors[] = "Password must contain at least one lowercase letter.";
}
if (!preg_match('/[0-9]/', $password)) {
$errors[] = "Password must contain at least one number.";
}
return $errors; // empty array = valid
}
function userCanAccess($allowedRoles = ['admin']) {
if (!isset($_SESSION['user_id'])) {
return false;
}
$userRole = $_SESSION['role'] ?? '';
return in_array($userRole, $allowedRoles);
}
/**
* Show access denied page with error message
* @param array $allowedRoles List of roles allowed to access the module
*/
function showAccessDenied($allowedRoles = ['admin']) {
$roleLabels = getRoleLabels(); // Now loaded from menu.json via config functions
$allowedLabels = array_map(fn($r) => $roleLabels[$r] ?? $r, $allowedRoles);
$allowedText = implode(' atau ', $allowedLabels);
include __DIR__ . '/../views/header.php';
include __DIR__ . '/../views/topnav.php';
?>
<div class="container-fluid">
<div class="row">
<?php include __DIR__ . '/../views/sidebar.php'; ?>
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
<div class="alert alert-danger">
<h4>ðŸ’’ Akses Ditolak</h4>
<p>
Halaman ini hanya dapat diakses oleh: <strong><?= htmlspecialchars($allowedText) ?></strong>.
</p>
<p>
Anda login sebagai <strong><?= htmlspecialchars(getRoleLabel($_SESSION['role'] ?? 'user')) ?></strong>.
</p>
<a href="../<?= htmlspecialchars($_SESSION['role'] ?? 'login') ?>/index.php"
class="btn btn-primary">
Kembali ke Dashboard
</a>
</div>
</main>
</div>
</div>
<?php
include __DIR__ . '/../views/footer.php';
exit();
}
function requireRoleAccess($allowedRoles = ['admin', 'dosen'], $redirectUrl = null) {
if (!userCanAccess($allowedRoles)) {
if ($redirectUrl) {
redirect($redirectUrl);
} else {
showAccessDenied($allowedRoles);
}
}
}
function loadMenuConfig() {
$configFile = __DIR__ . '/../config/menu.json';
if (file_exists($configFile)) {
$jsonContent = file_get_contents($configFile);
return json_decode($jsonContent, true) ?: [];
}
return [];
}
function getRoleLabel($role) {
$menuConfig = loadMenuConfig();
return $menuConfig['roles'][$role]['label'] ?? $role;
}
/**
* Get all role labels
*/
function getRoleLabels() {
$menuConfig = loadMenuConfig();
$labels = [];
foreach ($menuConfig['roles'] as $role => $config) {
$labels[$role] = $config['label'];
}
return $labels;
}
function getAllowedRolesForModule($moduleName) {
$menuConfig = loadMenuConfig();
return $menuConfig['modules'][$moduleName]['allowed_roles'] ?? ['admin']; // default to admin if not found
}
/**
* Check if current user can access a specific module
*/
function userCanAccessModule($moduleName) {
if (!isset($_SESSION['user_id'])) {
return false;
}
$userRole = $_SESSION['role'] ?? '';
$allowedRoles = getAllowedRolesForModule($moduleName);
return in_array($userRole, $allowedRoles);
}
/**
* Require role access for a specific module
*/
function requireModuleAccess($moduleName, $redirectUrl = null) {
$allowedRoles = getAllowedRolesForModule($moduleName);
if (!userCanAccessModule($moduleName)) {
if ($redirectUrl) {
redirect($redirectUrl);
} else {
showAccessDenied($allowedRoles);
}
}
}
function base_url($path = '') {
$url = BASE_URL . '/' . $path;
return $url;
}
// helpers/form_helper.php
function dropdownFromTable($table, $value_field = 'id', $label_field = 'name',
$selected = '', $name = '', $placeholder = '-- Pilih --',
$order_by = '', $where = '') {
// Use global connection from config/database.php
global $connection;
// Validate/sanitize identifiers (basic protection)
// In real apps, whitelist allowed tables/columns!
$value_field = str_replace('`', '', $value_field);
$label_field = str_replace('`', '', $label_field);
$table       = str_replace('`', '', $table);
if ($order_by) {
$order_by = str_replace('`', '', $order_by);
}
// Build query
$sql = "SELECT `$value_field`, `$label_field` FROM `$table`";
if ($where) {
// âš ï¸ WARNING: $where must be trusted or pre-sanitized!
$sql .= " WHERE $where";
}
$sql .= $order_by
? " ORDER BY `$order_by`"
: " ORDER BY `$label_field` ASC";
$result = mysqli_query($connection, $sql);
$html = '<select name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" class="form-control">';
$html .= '<option value="">' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '</option>';
if ($result && mysqli_num_rows($result) > 0) {
while ($row = mysqli_fetch_assoc($result)) {
$value = htmlspecialchars($row[$value_field], ENT_QUOTES, 'UTF-8');
$label = htmlspecialchars($row[$label_field], ENT_QUOTES, 'UTF-8');
$selected_attr = ($row[$value_field] == $selected) ? 'selected' : '';
$html .= "<option value=\"$value\" $selected_attr>$label</option>";
}
} else {
$html .= '<option value="">-- Tidak ada data --</option>';
}
$html .= '</select>';
return $html;
}
// helpers/db_helper.php (or add to existing helper file)
function getFieldValue($table, $field, $where_field, $where_value) {
global $connection;
// Basic sanitization: remove backticks to avoid injection in identifiers
$table = str_replace('`', '', $table);
$field = str_replace('`', '', $field);
$where_field = str_replace('`', '', $where_field);
// Use prepared statement via mysqli to prevent SQL injection
$sql = "SELECT `$field` FROM `$table` WHERE `$where_field` = ? LIMIT 1";
$stmt = mysqli_prepare($connection, $sql);
if (!$stmt) {
error_log("SQL prepare error: " . mysqli_error($connection));
return null;
}
// Determine the type for bind_param (assume string unless numeric)
$type = is_int($where_value) || is_float($where_value) ? 'd' : 's';
mysqli_stmt_bind_param($stmt, $type, $where_value);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_row($result);
mysqli_stmt_close($stmt);
return $row ? $row[0] : null;
}
// helpers/db_helper.php
/**
* Sum a field from a detail table and update the master total automatically.
*
* @param mysqli $connection       Database connection
* @param string $detail_table     Detail table name (e.g., 'penjualan_detail')
* @param string $sum_field        Field to sum in detail table (e.g., 'subtotal')
* @param string $detail_fk_field  Foreign key in detail table (e.g., 'penjualan_id')
* @param string $master_table     Master table name (e.g., 'penjualan')
* @param string $master_pk_field  Primary key in master table (e.g., 'id')
* @param string $master_total_field Field in master to update (e.g., 'total_bayar')
* @param mixed  $master_id        The master record ID
* @return bool                    True on success, false on failure
*/
/**
 * Sync total_bayar and sisa_bayar for a reservation
 * @param mysqli $connection
 * @param int $reservasi_id
 * @return bool
 */
function syncReservasiTotals($connection, $reservasi_id) {
    $reservasi_id = (int)$reservasi_id;
    
    // 1. Calculate sum from details
    $sql_sum = "SELECT COALESCE(SUM(`subtotal`), 0) AS total FROM `reservasi_detail` WHERE `reservasi_id` = ?";
    $stmt_sum = mysqli_prepare($connection, $sql_sum);
    mysqli_stmt_bind_param($stmt_sum, "i", $reservasi_id);
    mysqli_stmt_execute($stmt_sum);
    $result_sum = mysqli_stmt_get_result($stmt_sum);
    $row_sum = mysqli_fetch_assoc($result_sum);
    $total_bayar = (float)($row_sum['total'] ?? 0.0);
    mysqli_stmt_close($stmt_sum);
    
    // 2. Get current DP
    $sql_dp = "SELECT `dp_bayar` FROM `reservasi` WHERE `id` = ?";
    $stmt_dp = mysqli_prepare($connection, $sql_dp);
    mysqli_stmt_bind_param($stmt_dp, "i", $reservasi_id);
    mysqli_stmt_execute($stmt_dp);
    $result_dp = mysqli_stmt_get_result($stmt_dp);
    $row_dp = mysqli_fetch_assoc($result_dp);
    $dp_bayar = (float)($row_dp['dp_bayar'] ?? 0.0);
    mysqli_stmt_close($stmt_dp);
    
    // 3. Calculate sisa
    $sisa_bayar = $total_bayar - $dp_bayar;
    if ($sisa_bayar < 0) $sisa_bayar = 0;
    
    // 4. Update master
    $sql_update = "UPDATE `reservasi` SET `total_bayar` = ?, `sisa_bayar` = ? WHERE `id` = ?";
    $stmt_update = mysqli_prepare($connection, $sql_update);
    mysqli_stmt_bind_param($stmt_update, "ddi", $total_bayar, $sisa_bayar, $reservasi_id);
    $success = mysqli_stmt_execute($stmt_update);
    mysqli_stmt_close($stmt_update);
    
    return $success;
}

function updateMasterTotalFromDetail(
$connection,
$detail_table,
$sum_field,
$detail_fk_field,
$master_table,
$master_pk_field,
$master_total_field,
$master_id
) {
// Sanitize identifiers (remove backticks to prevent injection in names)
$detail_table      = str_replace('`', '', $detail_table);
$sum_field         = str_replace('`', '', $sum_field);
$detail_fk_field   = str_replace('`', '', $detail_fk_field);
$master_table      = str_replace('`', '', $master_table);
$master_pk_field   = str_replace('`', '', $master_pk_field);
$master_total_field = str_replace('`', '', $master_total_field);
// Step 1: Calculate the sum from detail table
$sql_sum = "SELECT COALESCE(SUM(`$sum_field`), 0) AS total
FROM `$detail_table`
WHERE `$detail_fk_field` = ?";
$stmt_sum = mysqli_prepare($connection, $sql_sum);
if (!$stmt_sum) {
error_log("updateMasterTotalFromDetail (SUM) prepare error: " . mysqli_error($connection));
return false;
}
$type = is_int($master_id) || is_float($master_id) ? 'd' : 's';
mysqli_stmt_bind_param($stmt_sum, $type, $master_id);
mysqli_stmt_execute($stmt_sum);
$result = mysqli_stmt_get_result($stmt_sum);
$row = mysqli_fetch_assoc($result);
$total = (float)($row['total'] ?? 0.0);
mysqli_stmt_close($stmt_sum);
// Step 2: Update master table
$sql_update = "UPDATE `$master_table`
SET `$master_total_field` = ?
WHERE `$master_pk_field` = ?";
$stmt_update = mysqli_prepare($connection, $sql_update);
if (!$stmt_update) {
error_log("updateMasterTotalFromDetail (UPDATE) prepare error: " . mysqli_error($connection));
return false;
}
mysqli_stmt_bind_param($stmt_update, "d" . $type, $total, $master_id);
$success = mysqli_stmt_execute($stmt_update);
mysqli_stmt_close($stmt_update);
return $success;
}

/**
 * Simple database backup function
 */
function backupDatabaseSimple($backupName, $backupDir = '../backups') {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT;

    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    $filename = $backupName . '_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backupDir . '/' . $filename;

    // Detect mysqldump path (especially for XAMPP on Windows)
    $mysqlDumpPath = 'mysqldump';
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $possiblePaths = [
            'C:\xampp\mysql\bin\mysqldump.exe',
            'D:\xampp\mysql\bin\mysqldump.exe',
            'E:\xampp\mysql\bin\mysqldump.exe',
        ];
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $mysqlDumpPath = '"' . $path . '"';
                break;
            }
        }
    }

    // Handle empty password correctly for mysqldump
    $passwordArg = $DB_PASS !== '' ? "--password=" . escapeshellarg($DB_PASS) : "";

    $command = sprintf(
        '%s --host=%s --port=%s --user=%s %s %s > %s 2>&1',
        $mysqlDumpPath,
        escapeshellarg($DB_HOST),
        escapeshellarg($DB_PORT),
        escapeshellarg($DB_USER),
        $passwordArg,
        escapeshellarg($DB_NAME),
        escapeshellarg($filepath)
    );

    exec($command, $output, $returnCode);

    // After execution, if returnCode is not 0, the output might be in the file due to 2>&1
    if ($returnCode !== 0) {
        $errorMsg = file_exists($filepath) ? file_get_contents($filepath) : "Unknown error";
        if (file_exists($filepath)) unlink($filepath); // Delete failed backup file
        return [
            'success' => false,
            'error' => $errorMsg ?: "Command failed with return code: $returnCode"
        ];
    }

    if (file_exists($filepath) && filesize($filepath) > 0) {
        return [
            'success' => true,
            'file' => $filename,
            'path' => $filepath,
            'size' => filesize($filepath)
        ];
    }

    return [
        'success' => false,
        'error' => "Backup file is empty or not created."
    ];
}

/**
 * Automatically synchronize room statuses based on active check-in reservations
 * @param mysqli $connection
 */
function syncRoomStatuses($connection) {
    // 1. Get all kamar_ids that are currently occupied (reservasi status = 'checkin')
    $checkin_query = mysqli_query($connection, "
        SELECT DISTINCT bd.kamar_id 
        FROM reservasi_detail bd 
        JOIN reservasi r ON bd.reservasi_id = r.id 
        WHERE r.status = 'checkin'
    ");
    $occupied_ids = [];
    if ($checkin_query) {
        while ($row = mysqli_fetch_assoc($checkin_query)) {
            $occupied_ids[] = (int)$row['kamar_id'];
        }
    }
    
    // 2. Update rooms that should be 'terisi'
    if (!empty($occupied_ids)) {
        $ids_placeholder = implode(',', $occupied_ids);
        // Set all these rooms to 'terisi' if they aren't already
        mysqli_query($connection, "UPDATE kamar SET status = 'terisi' WHERE id IN ($ids_placeholder) AND status != 'terisi'");
        
        // 3. Any room that is currently marked as 'terisi' but is NOT in the occupied list should be marked as 'kotor'
        mysqli_query($connection, "UPDATE kamar SET status = 'kotor' WHERE status = 'terisi' AND id NOT IN ($ids_placeholder)");
    } else {
        // If no rooms are occupied, any room marked as 'terisi' should be marked as 'kotor'
        mysqli_query($connection, "UPDATE kamar SET status = 'kotor' WHERE status = 'terisi'");
    }
}
?>
