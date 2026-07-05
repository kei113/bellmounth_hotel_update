<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // atur nilai menjadi 1 jika di publish ke real public server
ini_set('session.use_strict_mode', 1);
session_start();
require_once '../lib/auth.php';
require_once '../lib/functions.php';
require_once '../config/database.php';
requireAuth();
if (getUserRole() !== 'admin') {
    redirect('../login.php');
}

// Automatically synchronize room statuses
syncRoomStatuses($connection);

// Calculate Dashboard Stats
// 1. Pendapatan Kamar Bulan Ini (Room Revenue - from checked-out reservations)
$current_month = date('m');
$current_year = date('Y');

// Room revenue: sum of reservasi_detail subtotals for checkout reservations this month
$query_room_income = mysqli_query($connection, "
    SELECT COALESCE(SUM(rd.subtotal), 0) as total 
    FROM reservasi_detail rd 
    JOIN reservasi r ON rd.reservasi_id = r.id 
    WHERE r.status = 'checkout' 
    AND MONTH(r.tanggal_checkout) = $current_month 
    AND YEAR(r.tanggal_checkout) = $current_year
");
$room_income = (float)(mysqli_fetch_assoc($query_room_income)['total'] ?? 0);

// F&B Revenue: sum of delivered room_orders this month
$query_fnb_income = mysqli_query($connection, "
    SELECT COALESCE(SUM(grand_total), 0) as total 
    FROM room_orders 
    WHERE status = 'delivered' 
    AND MONTH(created_at) = $current_month 
    AND YEAR(created_at) = $current_year
");
$fnb_income = (float)(mysqli_fetch_assoc($query_fnb_income)['total'] ?? 0);

$total_income = $room_income + $fnb_income;

// F&B Orders today
$today = date('Y-m-d');
$query_fnb_today = mysqli_query($connection, "
    SELECT COUNT(*) as cnt, COALESCE(SUM(grand_total), 0) as total 
    FROM room_orders 
    WHERE status IN ('pending','preparing','delivered') 
    AND DATE(created_at) = '$today'
");
$fnb_today = mysqli_fetch_assoc($query_fnb_today);
$fnb_orders_today = (int)($fnb_today['cnt'] ?? 0);
$fnb_revenue_today = (float)($fnb_today['total'] ?? 0);

// Top 3 selling menu items this month
$query_top_items = mysqli_query($connection, "
    SELECT rod.nama_item, SUM(rod.qty) as total_qty, SUM(rod.subtotal) as total_sales
    FROM room_order_details rod
    JOIN room_orders ro ON rod.order_id = ro.id
    WHERE ro.status = 'delivered'
    AND MONTH(ro.created_at) = $current_month 
    AND YEAR(ro.created_at) = $current_year
    GROUP BY rod.menu_id, rod.nama_item
    ORDER BY total_qty DESC
    LIMIT 3
");
$top_items = [];
while ($row = mysqli_fetch_assoc($query_top_items)) {
    $top_items[] = $row;
}

// 2. Tamu Aktif (Checkin / Confirmed)
$query_active = mysqli_query($connection, "SELECT COUNT(*) as count FROM reservasi WHERE status IN ('checkin', 'confirmed')");
$active_guests = mysqli_fetch_assoc($query_active)['count'] ?? 0;

// 3. Kamar Terisi & Occupancy Rate
$query_occupied = mysqli_query($connection, "SELECT COUNT(DISTINCT kamar_id) as count FROM reservasi_detail bd JOIN reservasi b ON bd.reservasi_id = b.id WHERE b.status IN ('checkin', 'confirmed')");
$occupied_rooms = mysqli_fetch_assoc($query_occupied)['count'] ?? 0;

// Total Rooms
$query_total_rooms = mysqli_query($connection, "SELECT COUNT(*) as count FROM kamar");
$total_rooms = mysqli_fetch_assoc($query_total_rooms)['count'] ?? 1;
$occupancy_rate = ($total_rooms > 0) ? round(($occupied_rooms / $total_rooms) * 100) : 0;

// 4. Booking Pending
$query_pending = mysqli_query($connection, "SELECT COUNT(*) as count FROM reservasi WHERE status = 'pending'");
$pending_bookings = mysqli_fetch_assoc($query_pending)['count'] ?? 0;

// 5. Today's Operations - Check-In Today
$query_checkin_today = mysqli_query($connection, "
    SELECT b.id, b.nama_tamu, GROUP_CONCAT(k.nomor_kamar SEPARATOR ', ') as rooms 
    FROM reservasi b 
    LEFT JOIN reservasi_detail bd ON b.id = bd.reservasi_id 
    LEFT JOIN kamar k ON bd.kamar_id = k.id 
    WHERE b.tanggal_checkin = '$today' AND b.status IN ('confirmed', 'pending')
    GROUP BY b.id
");
$checkin_today = [];
while ($row = mysqli_fetch_assoc($query_checkin_today)) {
    $checkin_today[] = $row;
}

// 6. Today's Operations - Check-Out Today
$query_checkout_today = mysqli_query($connection, "
    SELECT b.id, b.nama_tamu, GROUP_CONCAT(k.nomor_kamar SEPARATOR ', ') as rooms 
    FROM reservasi b 
    LEFT JOIN reservasi_detail bd ON b.id = bd.reservasi_id 
    LEFT JOIN kamar k ON bd.kamar_id = k.id 
    WHERE b.tanggal_checkout = '$today' AND b.status = 'checkin'
    GROUP BY b.id
");
$checkout_today = [];
while ($row = mysqli_fetch_assoc($query_checkout_today)) {
    $checkout_today[] = $row;
}

// Indonesian month name
$month_names = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$current_month_name = $month_names[(int)$current_month] . ' ' . $current_year;

?>
<?php include '../views/'.$THEME.'/header.php'; ?>
<?php include '../views/'.$THEME.'/sidebar.php'; ?>
<?php include '../views/'.$THEME.'/topnav.php'; ?>
<?php include '../views/'.$THEME.'/upper_block.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold text-primary" style="font-size: 1.75rem; letter-spacing: -0.5px;">Dashboard Admin</h2>
    <div class="text-muted small fw-semibold"><i class="bi bi-clock me-1"></i><?= date('l, d F Y') ?></div>
</div>

<div class="row">
    <!-- Total Pendapatan Bulan Ini -->
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="dashboard-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-muted fw-semibold" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Total Pendapatan</span>
                <div class="card-icon success">
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1" style="font-size: 1.85rem; letter-spacing: -0.5px; color: var(--primary-color);">Rp <?= number_format($total_income, 0, ',', '.') ?></h3>
            <small class="text-muted"><i class="bi bi-calendar3 me-1"></i><?= $current_month_name ?></small>
        </div>
    </div>

    <!-- Tamu Aktif -->
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="dashboard-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-muted fw-semibold" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Tamu Aktif</span>
                <div class="card-icon primary">
                    <i class="bi bi-people-fill"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1" style="font-size: 1.85rem; letter-spacing: -0.5px; color: var(--primary-color);"><?= $active_guests ?></h3>
            <small class="text-muted">Tamu Check-In / Confirmed</small>
        </div>
    </div>

    <!-- Kamar Terisi with Occupancy Progress Bar -->
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="dashboard-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-muted fw-semibold" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Kamar Terisi</span>
                <div class="card-icon primary" style="background-color: rgba(59, 130, 246, 0.08); color: var(--info-color);">
                    <i class="bi bi-door-closed-fill"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1" style="font-size: 1.85rem; letter-spacing: -0.5px; color: var(--primary-color);"><?= $occupied_rooms ?> <small class="fs-6 text-muted">/ <?= $total_rooms ?></small></h3>
            <div class="progress mb-2" style="height: 6px;">
                <div class="progress-bar" role="progressbar" style="width: <?= $occupancy_rate ?>%; background: var(--accent-gradient) !important;" aria-valuenow="<?= $occupancy_rate ?>" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <small class="fw-bold" style="color: var(--accent-color); font-size: 0.75rem;">Okupansi: <?= $occupancy_rate ?>%</small>
        </div>
    </div>

    <!-- Booking Pending -->
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="dashboard-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-muted fw-semibold" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Reservasi Pending</span>
                <div class="card-icon warning">
                    <i class="bi bi-hourglass-split"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1" style="font-size: 1.85rem; letter-spacing: -0.5px; color: var(--primary-color);"><?= $pending_bookings ?></h3>
            <small class="text-warning fw-semibold" style="font-size: 0.75rem;"><i class="bi bi-exclamation-circle-fill me-1"></i>Perlu Konfirmasi</small>
        </div>
    </div>
</div>

<!-- Revenue Breakdown Section -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header py-3 bg-white d-flex align-items-center justify-content-between">
                <h5 class="mb-0 fw-bold" style="font-size: 1.1rem; color: var(--primary-color);"><i class="bi bi-graph-up-arrow me-2 text-warning" style="color: var(--accent-color) !important;"></i>Rincian Pendapatan — <?= $current_month_name ?></h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Left: Stats and Doughnut Chart -->
                    <div class="col-lg-8">
                        <div class="row h-100">
                            <!-- Room & F&B Stats -->
                            <div class="col-md-6 d-flex flex-column justify-content-between gap-3 mb-3 mb-md-0">
                                <!-- Room Revenue -->
                                <div class="rounded-4 p-4 flex-grow-1 d-flex flex-column justify-content-center" style="border: 1px solid rgba(226, 232, 240, 0.7); background-color: #FAFBFD;">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="card-icon primary me-2" style="width: 32px; height: 32px; font-size: 1rem; border-radius: 8px;">
                                            <i class="bi bi-building"></i>
                                        </div>
                                        <span class="fw-bold text-muted uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Pendapatan Kamar</span>
                                    </div>
                                    <h4 class="fw-bold text-primary mb-1" style="font-size: 1.5rem; letter-spacing: -0.5px;">Rp <?= number_format($room_income, 0, ',', '.') ?></h4>
                                    <small class="text-muted small">Dari reservasi checkout bulan ini</small>
                                </div>
                                <!-- F&B Revenue -->
                                <div class="rounded-4 p-4 flex-grow-1 d-flex flex-column justify-content-center" style="border: 1px solid rgba(226, 232, 240, 0.7); background-color: #FAFBFD;">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="card-icon warning me-2" style="width: 32px; height: 32px; font-size: 1rem; border-radius: 8px;">
                                            <i class="bi bi-cup-hot-fill"></i>
                                        </div>
                                        <span class="fw-bold text-muted uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Pendapatan F&B</span>
                                    </div>
                                    <h4 class="fw-bold text-warning mb-1" style="font-size: 1.5rem; letter-spacing: -0.5px; color: var(--accent-color) !important;">Rp <?= number_format($fnb_income, 0, ',', '.') ?></h4>
                                    <small class="text-muted small">Dari room service delivered bulan ini</small>
                                    <?php if ($fnb_orders_today > 0): ?>
                                    <div class="mt-2">
                                        <span class="badge bg-success" style="font-size: 0.65rem;"><i class="bi bi-arrow-up me-1"></i>Hari ini: <?= $fnb_orders_today ?> pesanan</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <!-- Doughnut Chart -->
                            <div class="col-md-6 d-flex align-items-center justify-content-center py-3">
                                <div style="width: 100%; max-width: 200px; height: 200px; position: relative;">
                                    <canvas id="revenueChart"></canvas>
                                    <!-- Center label -->
                                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; pointer-events: none;">
                                        <span class="text-muted d-block" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700;">Total</span>
                                        <span class="fw-bold text-dark" style="font-size: 0.95rem; letter-spacing: -0.3px;">Rp <?= number_format($total_income / 1000, 0) ?>K</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Right: Menu Terlaris -->
                    <div class="col-lg-4 mt-4 mt-lg-0">
                        <div class="rounded-4 p-4 h-100 d-flex flex-column justify-content-between" style="border: 1px solid rgba(226, 232, 240, 0.7); background-color: #FAFBFD;">
                            <div class="d-flex align-items-center mb-3">
                                <div class="card-icon success me-2" style="width: 32px; height: 32px; font-size: 1rem; border-radius: 8px;">
                                    <i class="bi bi-trophy-fill"></i>
                                </div>
                                <span class="fw-bold text-muted uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Menu Terlaris</span>
                            </div>
                            <?php if (!empty($top_items)): ?>
                            <div class="flex-grow-1 d-flex flex-column justify-content-center">
                                <ul class="list-unstyled mb-0">
                                    <?php foreach ($top_items as $i => $ti): 
                                        $rankBadge = match($i) {
                                            0 => 'background: var(--accent-gradient); color: white;',
                                            1 => 'background: #64748B; color: white;',
                                            default => 'background: #94A3B8; color: white;'
                                        };
                                    ?>
                                    <li class="d-flex justify-content-between align-items-center py-2.5 <?= $i < count($top_items) - 1 ? 'border-bottom' : '' ?>" style="border-bottom: 1px solid rgba(226, 232, 240, 0.5) !important;">
                                        <span class="d-flex align-items-center">
                                            <span class="badge rounded-circle d-inline-flex align-items-center justify-content-center p-0 me-2" style="width: 20px; height: 20px; font-size: 0.65rem; <?= $rankBadge ?>"><?= $i + 1 ?></span>
                                            <small class="fw-semibold text-dark"><?= htmlspecialchars($ti['nama_item']) ?></small>
                                        </span>
                                        <span class="badge bg-light text-dark fw-bold" style="font-size: 0.65rem; padding: 0.25rem 0.5rem;"><?= $ti['total_qty'] ?> porsi</span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php else: ?>
                            <div class="flex-grow-1 d-flex align-items-center justify-content-center py-4">
                                <p class="text-muted mb-0 small fst-italic">Belum ada data penjualan F&B</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Operasional Hari Ini Section -->
<div class="row mt-2">
    <div class="col-12">
        <div class="card shadow-sm mb-4">
            <div class="card-header py-3 bg-white">
                <h5 class="mb-0 fw-bold" style="font-size: 1.1rem; color: var(--primary-color);"><i class="bi bi-calendar-check me-2 text-warning" style="color: var(--accent-color) !important;"></i>Operasional Hari Ini</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Check-In Today -->
                    <div class="col-md-6 mb-3 mb-md-0">
                        <div class="rounded-4 p-4 h-100" style="border: 1px solid rgba(226, 232, 240, 0.7); background-color: #FAFBFD;">
                            <h6 class="text-primary fw-bold mb-3" style="font-size: 0.9rem; color: var(--primary-color) !important;"><i class="bi bi-box-arrow-in-right me-2 text-success"></i>Check-In Hari Ini</h6>
                            <?php if (count($checkin_today) > 0): ?>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($checkin_today as $ci): ?>
                                <li class="d-flex justify-content-between align-items-center py-2.5 border-bottom" style="border-bottom: 1px solid rgba(226, 232, 240, 0.5) !important;">
                                    <span class="fw-semibold text-dark small"><?= htmlspecialchars($ci['nama_tamu']) ?></span>
                                    <span class="badge bg-light text-dark fw-bold" style="font-size: 0.65rem;"><?= htmlspecialchars($ci['rooms'] ?: 'No room') ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?>
                            <p class="text-muted mb-0 small fst-italic"><i class="bi bi-info-circle me-1"></i>Tidak ada jadwal check-in hari ini</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Check-Out Today -->
                    <div class="col-md-6">
                        <div class="rounded-4 p-4 h-100" style="border: 1px solid rgba(226, 232, 240, 0.7); background-color: #FAFBFD;">
                            <h6 class="text-success fw-bold mb-3" style="font-size: 0.9rem; color: var(--success-color) !important;"><i class="bi bi-box-arrow-right me-2 text-danger"></i>Check-Out Hari Ini</h6>
                            <?php if (count($checkout_today) > 0): ?>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($checkout_today as $co): ?>
                                <li class="d-flex justify-content-between align-items-center py-2.5 border-bottom" style="border-bottom: 1px solid rgba(226, 232, 240, 0.5) !important;">
                                    <span class="fw-semibold text-dark small"><?= htmlspecialchars($co['nama_tamu']) ?></span>
                                    <span class="badge bg-light text-dark fw-bold" style="font-size: 0.65rem;"><?= htmlspecialchars($co['rooms'] ?: 'No room') ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?>
                            <p class="text-muted mb-0 small fst-italic"><i class="bi bi-info-circle me-1"></i>Tidak ada jadwal check-out hari ini</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold" style="font-size: 1.1rem; color: var(--primary-color);">Reservasi Terbaru</h5>
                <a href="../reservasi/index.php" class="btn btn-sm btn-outline-primary" style="font-size: 0.75rem; padding: 0.35rem 0.85rem;">Lihat Semua</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>No Reservasi</th>
                                <th>Nama Tamu</th>
                                <th>Check-In</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $latest = mysqli_query($connection, "SELECT * FROM reservasi ORDER BY id DESC LIMIT 5");
                            if (mysqli_num_rows($latest) > 0):
                                while($row = mysqli_fetch_assoc($latest)):
                                    $badge = match($row['status']) {
                                        'checkout' => 'secondary',
                                        'checkin' => 'success',
                                        'confirmed' => 'primary',
                                        'cancelled' => 'danger',
                                        default => 'warning'
                                    };
                            ?>
                            <tr>
                                <td class="fw-semibold text-dark small"><?= htmlspecialchars($row['no_reservasi']) ?></td>
                                <td class="fw-semibold text-dark small"><?= htmlspecialchars($row['nama_tamu']) ?></td>
                                <td class="text-muted small"><?= date('d M Y', strtotime($row['tanggal_checkin'])) ?></td>
                                <td><span class="badge bg-<?= $badge ?>"><?= strtoupper($row['status']) ?></span></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="4" class="text-center py-3 text-muted small fst-italic">Belum ada data reservasi</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('revenueChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Pendapatan Kamar', 'Pendapatan F&B'],
                datasets: [{
                    data: [<?= $room_income ?>, <?= $fnb_income ?>],
                    backgroundColor: [
                        '#BFA370', // Minimalist Champagne Gold for Room Revenue
                        '#1D1D1F'  // Space Gray for F&B Revenue
                    ],
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        padding: 10,
                        bodyFont: {
                            family: 'Plus Jakarta Sans',
                            size: 12
                        },
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed !== null) {
                                    label += new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(context.parsed);
                                }
                                return label;
                            }
                        }
                    }
                },
                cutout: '72%'
            }
        });
    }
});
</script>

<?php include '../views/'.$THEME.'/lower_block.php'; ?>
<?php include '../views/'.$THEME.'/footer.php'; ?>

