<?php
/**
 * Activity Logger Functions for Hotel Bellmounth
 * 
 * Provides functions to log and retrieve user activities
 */

/**
 * Log an activity to the database
 * 
 * @param mysqli $connection Database connection
 * @param string $action Action type (login, logout, create, update, delete, status_change, view, etc)
 * @param string $module Module name (booking, kamar, room_management, auth, etc)
 * @param int|null $recordId ID of the affected record
 * @param string|null $recordInfo Brief info about the record
 * @param mixed $oldValue Previous value (will be JSON encoded if array/object)
 * @param mixed $newValue New value (will be JSON encoded if array/object)
 * @return bool Success status
 */
function logActivity($connection, $action, $module, $recordId = null, $recordInfo = null, $oldValue = null, $newValue = null) {
    // Get current user info from session
    $userId = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? $_SESSION['name'] ?? 'System';
    
    // Get client info
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    
    // JSON encode complex values
    if (is_array($oldValue) || is_object($oldValue)) {
        $oldValue = json_encode($oldValue, JSON_UNESCAPED_UNICODE);
    }
    if (is_array($newValue) || is_object($newValue)) {
        $newValue = json_encode($newValue, JSON_UNESCAPED_UNICODE);
    }
    
    $stmt = mysqli_prepare($connection, "
        INSERT INTO `activity_logs` 
        (`user_id`, `username`, `action`, `module`, `record_id`, `record_info`, `old_value`, `new_value`, `ip_address`, `user_agent`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    if (!$stmt) {
        error_log("Activity log error: " . mysqli_error($connection));
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, "isssssssss", 
        $userId, $username, $action, $module, $recordId, $recordInfo, $oldValue, $newValue, $ipAddress, $userAgent
    );
    
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $result;
}

/**
 * Get activity logs with optional filters
 * 
 * @param mysqli $connection Database connection
 * @param array $filters Optional filters: module, action, user_id, date_from, date_to, search
 * @param int $limit Number of records to return
 * @param int $offset Starting offset for pagination
 * @return array Array of activity log records
 */
function getActivityLogs($connection, $filters = [], $limit = 50, $offset = 0) {
    $where = [];
    $params = [];
    $types = "";
    
    if (!empty($filters['module'])) {
        $where[] = "module = ?";
        $params[] = $filters['module'];
        $types .= "s";
    }
    
    if (!empty($filters['action'])) {
        $where[] = "action = ?";
        $params[] = $filters['action'];
        $types .= "s";
    }
    
    if (!empty($filters['user_id'])) {
        $where[] = "user_id = ?";
        $params[] = $filters['user_id'];
        $types .= "i";
    }
    
    if (!empty($filters['date_from'])) {
        $where[] = "DATE(created_at) >= ?";
        $params[] = $filters['date_from'];
        $types .= "s";
    }
    
    if (!empty($filters['date_to'])) {
        $where[] = "DATE(created_at) <= ?";
        $params[] = $filters['date_to'];
        $types .= "s";
    }
    
    if (!empty($filters['search'])) {
        $where[] = "(record_info LIKE ? OR username LIKE ? OR action LIKE ?)";
        $searchTerm = "%" . $filters['search'] . "%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= "sss";
    }
    
    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    $sql = "SELECT * FROM `activity_logs` $whereClause ORDER BY `created_at` DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = mysqli_prepare($connection, $sql);
    
    if (!$stmt) {
        error_log("Get activity logs error: " . mysqli_error($connection));
        return [];
    }
    
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $logs = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $logs[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $logs;
}

/**
 * Count total activity logs with filters
 */
function countActivityLogs($connection, $filters = []) {
    $where = [];
    $params = [];
    $types = "";
    
    if (!empty($filters['module'])) {
        $where[] = "module = ?";
        $params[] = $filters['module'];
        $types .= "s";
    }
    
    if (!empty($filters['action'])) {
        $where[] = "action = ?";
        $params[] = $filters['action'];
        $types .= "s";
    }
    
    if (!empty($filters['user_id'])) {
        $where[] = "user_id = ?";
        $params[] = $filters['user_id'];
        $types .= "i";
    }
    
    if (!empty($filters['date_from'])) {
        $where[] = "DATE(created_at) >= ?";
        $params[] = $filters['date_from'];
        $types .= "s";
    }
    
    if (!empty($filters['date_to'])) {
        $where[] = "DATE(created_at) <= ?";
        $params[] = $filters['date_to'];
        $types .= "s";
    }
    
    if (!empty($filters['search'])) {
        $where[] = "(record_info LIKE ? OR username LIKE ? OR action LIKE ?)";
        $searchTerm = "%" . $filters['search'] . "%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= "sss";
    }
    
    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    $sql = "SELECT COUNT(*) as total FROM `activity_logs` $whereClause";
    
    $stmt = mysqli_prepare($connection, $sql);
    
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    return (int)($row['total'] ?? 0);
}

/**
 * Get action label for display
 */
function getActionLabel($action) {
    $labels = [
        'login' => 'Login',
        'logout' => 'Logout',
        'create' => 'Tambah Data',
        'update' => 'Update Data',
        'delete' => 'Hapus Data',
        'status_change' => 'Ubah Status',
        'view' => 'Lihat Data',
        'checkout' => 'Check-Out',
        'checkin' => 'Check-In',
        'confirm' => 'Konfirmasi',
        'cancel' => 'Batal',
        'payment' => 'Pembayaran',
    ];
    return $labels[$action] ?? ucfirst(str_replace('_', ' ', $action));
}

/**
 * Get module label for display
 */
function getModuleLabel($module) {
    $labels = [
        'auth' => 'Autentikasi',
        'reservasi' => 'Reservasi',
        'kamar' => 'Kamar',
        'room_management' => 'Kelola Kamar',
        'tipe_kamar' => 'Tipe Kamar',
        'user' => 'User',
        'system' => 'Sistem',
    ];
    return $labels[$module] ?? ucfirst(str_replace('_', ' ', $module));
}

/**
 * Get action badge color
 */
function getActionBadgeClass($action) {
    $colors = [
        'login' => 'bg-success',
        'logout' => 'bg-secondary',
        'create' => 'bg-primary',
        'update' => 'bg-info',
        'delete' => 'bg-danger',
        'status_change' => 'bg-warning text-dark',
        'checkout' => 'bg-success',
        'checkin' => 'bg-primary',
        'confirm' => 'bg-info',
        'cancel' => 'bg-danger',
        'payment' => 'bg-success',
    ];
    return $colors[$action] ?? 'bg-secondary';
}

/**
 * Get action icon
 */
function getActionIcon($action) {
    $icons = [
        'login' => 'bi-box-arrow-in-right',
        'logout' => 'bi-box-arrow-right',
        'create' => 'bi-plus-circle',
        'update' => 'bi-pencil',
        'delete' => 'bi-trash',
        'status_change' => 'bi-arrow-repeat',
        'checkout' => 'bi-door-open',
        'checkin' => 'bi-door-closed',
        'confirm' => 'bi-check-circle',
        'cancel' => 'bi-x-circle',
        'payment' => 'bi-credit-card',
        'view' => 'bi-eye',
    ];
    return $icons[$action] ?? 'bi-activity';
}
?>
