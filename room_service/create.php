<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.use_strict_mode', 1);
session_start();
require_once '../lib/auth.php';
require_once '../lib/functions.php';
require_once '../config/database.php';
requireAuth();
requireModuleAccess('room_service');

$csrf_token = generateCSRFToken();
$error = '';

// =========================================================
// 1. Fetch occupied rooms (only rooms with status 'checkin')
// =========================================================
$occupied_rooms = [];
$occ_query = mysqli_query($connection, "
    SELECT r.id as reservasi_id, r.nama_tamu, r.no_reservasi,
           k.id as kamar_id, k.nomor_kamar, t.nama_tipe
    FROM reservasi r
    JOIN reservasi_detail rd ON r.id = rd.reservasi_id
    JOIN kamar k ON rd.kamar_id = k.id
    JOIN tipe_kamar t ON k.id_tipe = t.id
    WHERE r.status = 'checkin' AND k.status = 'terisi'
    ORDER BY k.nomor_kamar ASC
");
while ($row = mysqli_fetch_assoc($occ_query)) {
    $occupied_rooms[] = $row;
}

// =========================================================
// 2. Fetch available menu items
// =========================================================
$menu_items = [];
$menu_query = mysqli_query($connection, "
    SELECT * FROM fnb_menu 
    WHERE is_available = 1 AND (stok IS NULL OR stok > 0)
    ORDER BY kategori ASC, nama_item ASC
");
while ($row = mysqli_fetch_assoc($menu_query)) {
    $menu_items[] = $row;
}

// Group menu by category for display
$menu_by_category = [];
foreach ($menu_items as $item) {
    $menu_by_category[$item['kategori']][] = $item;
}

// =========================================================
// 3. Handle form submission (with DB Transaction)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF tidak valid.";
    } else {
        $selected_room = $_POST['selected_room'] ?? ''; // format: reservasi_id|kamar_id
        $catatan = sanitize($_POST['catatan'] ?? '');
        $items = json_decode($_POST['cart_items'] ?? '[]', true);

        // Validate room selection
        $room_parts = explode('|', $selected_room);
        if (count($room_parts) !== 2) {
            $error = "Silakan pilih kamar yang valid.";
        }

        // Validate cart
        if (empty($error) && (empty($items) || !is_array($items))) {
            $error = "Keranjang pesanan kosong. Tambahkan minimal 1 item.";
        }

        if (empty($error)) {
            $reservasi_id = (int)$room_parts[0];
            $kamar_id = (int)$room_parts[1];

            // ===== BEGIN TRANSACTION =====
            mysqli_begin_transaction($connection);
            try {
                // STEP 1: Validate reservation is still checked-in (row lock)
                $check_stmt = mysqli_prepare($connection, 
                    "SELECT id, nama_tamu, status FROM reservasi WHERE id = ? AND status = 'checkin' FOR UPDATE");
                mysqli_stmt_bind_param($check_stmt, "i", $reservasi_id);
                mysqli_stmt_execute($check_stmt);
                $reservasi = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));
                mysqli_stmt_close($check_stmt);

                if (!$reservasi) {
                    throw new Exception("Reservasi tidak valid atau tamu sudah checkout.");
                }

                // STEP 2: Get room info
                $room_stmt = mysqli_prepare($connection, "SELECT nomor_kamar FROM kamar WHERE id = ?");
                mysqli_stmt_bind_param($room_stmt, "i", $kamar_id);
                mysqli_stmt_execute($room_stmt);
                $room_info = mysqli_fetch_assoc(mysqli_stmt_get_result($room_stmt));
                mysqli_stmt_close($room_stmt);

                if (!$room_info) throw new Exception("Kamar tidak ditemukan.");

                // STEP 3: Generate order number
                $today = date('Ymd');
                $prefix = "RS-$today-";
                $last_q = mysqli_query($connection, 
                    "SELECT no_order FROM room_orders WHERE no_order LIKE '$prefix%' ORDER BY id DESC LIMIT 1");
                $last_row = mysqli_fetch_assoc($last_q);
                if ($last_row) {
                    $last_num = (int)substr($last_row['no_order'], -4);
                    $new_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
                } else {
                    $new_num = '0001';
                }
                $no_order = $prefix . $new_num;

                // STEP 4: Validate & calculate each item, lock stock rows
                $grand_total = 0;
                $validated_items = [];

                foreach ($items as $cart_item) {
                    $menu_id = (int)($cart_item['id'] ?? 0);
                    $qty = max(1, (int)($cart_item['qty'] ?? 1));

                    // Lock the menu row to prevent concurrent stock issues
                    $menu_stmt = mysqli_prepare($connection,
                        "SELECT id, nama_item, harga, stok, is_available FROM fnb_menu WHERE id = ? FOR UPDATE");
                    mysqli_stmt_bind_param($menu_stmt, "i", $menu_id);
                    mysqli_stmt_execute($menu_stmt);
                    $menu = mysqli_fetch_assoc(mysqli_stmt_get_result($menu_stmt));
                    mysqli_stmt_close($menu_stmt);

                    if (!$menu || !$menu['is_available']) {
                        throw new Exception("Item '{$cart_item['nama']}' tidak tersedia lagi.");
                    }

                    // Check stock
                    if ($menu['stok'] !== null && $menu['stok'] < $qty) {
                        throw new Exception("Stok '{$menu['nama_item']}' tidak cukup. Tersisa: {$menu['stok']}");
                    }

                    $subtotal = $menu['harga'] * $qty;
                    $grand_total += $subtotal;

                    $validated_items[] = [
                        'menu_id' => $menu['id'],
                        'nama_item' => $menu['nama_item'],
                        'harga_satuan' => $menu['harga'],
                        'qty' => $qty,
                        'subtotal' => $subtotal,
                        'has_stock' => ($menu['stok'] !== null)
                    ];
                }

                // STEP 5: Insert order header
                $order_stmt = mysqli_prepare($connection,
                    "INSERT INTO room_orders (no_order, reservasi_id, kamar_id, nama_tamu, nomor_kamar, grand_total, catatan, status, ordered_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
                $user_id = $_SESSION['user_id'];
                $nama_tamu = $reservasi['nama_tamu'];
                $nomor_kamar = $room_info['nomor_kamar'];
                mysqli_stmt_bind_param($order_stmt, "siissdsi", 
                    $no_order, $reservasi_id, $kamar_id, $nama_tamu, $nomor_kamar, $grand_total, $catatan, $user_id);
                
                if (!mysqli_stmt_execute($order_stmt)) {
                    throw new Exception("Gagal membuat pesanan: " . mysqli_error($connection));
                }
                $order_id = mysqli_insert_id($connection);
                mysqli_stmt_close($order_stmt);

                // STEP 6: Insert order details & deduct stock
                foreach ($validated_items as $vi) {
                    $detail_stmt = mysqli_prepare($connection,
                        "INSERT INTO room_order_details (order_id, menu_id, nama_item, harga_satuan, qty, subtotal)
                         VALUES (?, ?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($detail_stmt, "iisdid", 
                        $order_id, $vi['menu_id'], $vi['nama_item'], $vi['harga_satuan'], $vi['qty'], $vi['subtotal']);
                    mysqli_stmt_execute($detail_stmt);
                    mysqli_stmt_close($detail_stmt);

                    // Deduct stock if tracked
                    if ($vi['has_stock']) {
                        $stock_stmt = mysqli_prepare($connection,
                            "UPDATE fnb_menu SET stok = stok - ? WHERE id = ? AND stok >= ?");
                        mysqli_stmt_bind_param($stock_stmt, "iii", $vi['qty'], $vi['menu_id'], $vi['qty']);
                        if (!mysqli_stmt_execute($stock_stmt) || mysqli_affected_rows($connection) === 0) {
                            mysqli_stmt_close($stock_stmt);
                            throw new Exception("Gagal mengurangi stok '{$vi['nama_item']}'. Kemungkinan stok habis.");
                        }
                        mysqli_stmt_close($stock_stmt);
                    }
                }

                // STEP 7: Update guest folio (add to reservasi.total_bayar and sisa_bayar)
                $folio_stmt = mysqli_prepare($connection,
                    "UPDATE reservasi SET total_bayar = total_bayar + ?, sisa_bayar = sisa_bayar + ? WHERE id = ?");
                mysqli_stmt_bind_param($folio_stmt, "ddi", $grand_total, $grand_total, $reservasi_id);
                if (!mysqli_stmt_execute($folio_stmt)) {
                    throw new Exception("Gagal memperbarui folio tamu.");
                }
                mysqli_stmt_close($folio_stmt);

                // STEP 8: Log activity
                logActivity($connection, 'create', 'room_service', $order_id, 
                    "$no_order - Kamar $nomor_kamar ($nama_tamu)", 
                    null, 
                    ['grand_total' => $grand_total, 'items' => count($validated_items)]);

                // ===== COMMIT =====
                mysqli_commit($connection);
                redirect('room_service/index.php?success=' . urlencode("Pesanan $no_order berhasil dibuat!"));

            } catch (Exception $e) {
                mysqli_rollback($connection);
                $error = $e->getMessage();
            }
        }
    }
}
?>
<?php include '../views/'.$THEME.'/header.php'; ?>
<?php include '../views/'.$THEME.'/sidebar.php'; ?>
<?php include '../views/'.$THEME.'/topnav.php'; ?>
<?php include '../views/'.$THEME.'/upper_block.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-cart-plus me-2"></i>Buat Pesanan Room Service</h2>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (empty($occupied_rooms)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Tidak ada kamar terisi saat ini.</strong> Pesanan room service hanya dapat dibuat untuk tamu yang sedang check-in.
    </div>
<?php else: ?>

<form method="POST" id="orderForm">
    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
    <input type="hidden" name="cart_items" id="cartItemsInput" value="[]">

    <div class="row">
        <!-- LEFT: Room Selection + Menu -->
        <div class="col-lg-8">
            <!-- Room Selection -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-door-open me-2"></i>1. Pilih Kamar Tamu</h5>
                </div>
                <div class="card-body">
                    <select name="selected_room" id="roomSelect" class="form-select form-select-lg" required>
                        <option value="">-- Pilih Kamar (Terisi) --</option>
                        <?php foreach ($occupied_rooms as $room): ?>
                        <option value="<?= $room['reservasi_id'] ?>|<?= $room['kamar_id'] ?>">
                            Kamar <?= htmlspecialchars($room['nomor_kamar']) ?> (<?= htmlspecialchars($room['nama_tipe']) ?>) — <?= htmlspecialchars($room['nama_tamu']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Menu Selection -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-cup-hot me-2"></i>2. Pilih Menu</h5>
                </div>
                <div class="card-body">
                    <!-- Category Tabs -->
                    <ul class="nav nav-pills mb-3" id="menuTabs">
                        <li class="nav-item">
                            <a class="nav-link active" href="#" data-category="all">Semua</a>
                        </li>
                        <?php foreach (array_keys($menu_by_category) as $cat): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-category="<?= $cat ?>"><?= ucfirst($cat) ?></a>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <!-- Menu Grid -->
                    <div class="row g-3" id="menuGrid">
                        <?php foreach ($menu_items as $item): ?>
                        <div class="col-md-6 col-lg-4 menu-card-wrapper" data-category="<?= $item['kategori'] ?>">
                            <div class="card h-100 menu-card" 
                                 data-id="<?= $item['id'] ?>"
                                 data-nama="<?= htmlspecialchars($item['nama_item']) ?>"
                                 data-harga="<?= $item['harga'] ?>"
                                 data-stok="<?= $item['stok'] ?? 'unlimited' ?>"
                                 style="cursor: pointer; transition: all 0.2s;">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?= htmlspecialchars($item['nama_item']) ?></h6>
                                            <?php 
                                            $cat_color = match($item['kategori']) {
                                                'makanan' => 'warning', 'minuman' => 'info',
                                                'snack' => 'secondary', 'dessert' => 'danger', default => 'dark'
                                            };
                                            ?>
                                            <span class="badge bg-<?= $cat_color ?> mb-1" style="font-size:0.7em"><?= ucfirst($item['kategori']) ?></span>
                                        </div>
                                        <span class="fw-bold text-primary">Rp <?= number_format($item['harga'], 0, ',', '.') ?></span>
                                    </div>
                                    <?php if ($item['deskripsi']): ?>
                                    <small class="text-muted"><?= htmlspecialchars(substr($item['deskripsi'], 0, 60)) ?></small>
                                    <?php endif; ?>
                                    <?php if ($item['stok'] !== null): ?>
                                    <div class="mt-1">
                                        <small class="text-<?= $item['stok'] <= 5 ? 'danger' : 'muted' ?>">
                                            Stok: <?= $item['stok'] ?>
                                        </small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: Cart / Order Summary -->
        <div class="col-lg-4">
            <div class="card shadow-sm sticky-top" style="top: 1rem;">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-cart3 me-2"></i>3. Keranjang Pesanan</h5>
                </div>
                <div class="card-body" id="cartBody">
                    <div id="emptyCart" class="text-center py-4 text-muted">
                        <i class="bi bi-cart-x" style="font-size: 2rem;"></i>
                        <p class="mt-2">Klik item menu untuk menambahkan</p>
                    </div>
                    <div id="cartItems" class="d-none"></div>
                </div>
                <div class="card-footer bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <strong class="fs-5">Total:</strong>
                        <strong class="fs-5 text-primary" id="grandTotal">Rp 0</strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Catatan Khusus</label>
                        <textarea name="catatan" class="form-control form-control-sm" rows="2" 
                                  placeholder="Misal: tanpa sambal, extra nasi..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 btn-lg" id="submitBtn" disabled>
                        <i class="bi bi-send me-2"></i>Kirim Pesanan
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
// =============================================
// Cart State Management
// =============================================
let cart = [];

function formatRupiah(num) {
    return 'Rp ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function renderCart() {
    const cartItems = document.getElementById('cartItems');
    const emptyCart = document.getElementById('emptyCart');
    const grandTotalEl = document.getElementById('grandTotal');
    const submitBtn = document.getElementById('submitBtn');
    const cartInput = document.getElementById('cartItemsInput');

    if (cart.length === 0) {
        emptyCart.classList.remove('d-none');
        cartItems.classList.add('d-none');
        grandTotalEl.textContent = 'Rp 0';
        submitBtn.disabled = true;
        cartInput.value = '[]';
        return;
    }

    emptyCart.classList.add('d-none');
    cartItems.classList.remove('d-none');

    let html = '';
    let grandTotal = 0;

    cart.forEach((item, index) => {
        const subtotal = item.harga * item.qty;
        grandTotal += subtotal;
        html += `
            <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                <div style="flex:1">
                    <div class="fw-semibold small">${item.nama}</div>
                    <div class="text-muted" style="font-size:0.75em">${formatRupiah(item.harga)} × ${item.qty}</div>
                </div>
                <div class="d-flex align-items-center gap-1">
                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1" onclick="changeQty(${index}, -1)">
                        <i class="bi bi-dash"></i>
                    </button>
                    <span class="fw-bold mx-1" style="min-width:20px;text-align:center">${item.qty}</span>
                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1" onclick="changeQty(${index}, 1)">
                        <i class="bi bi-plus"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1 ms-1" onclick="removeItem(${index})">
                        <i class="bi bi-trash3"></i>
                    </button>
                </div>
            </div>
        `;
    });

    cartItems.innerHTML = html;
    grandTotalEl.textContent = formatRupiah(grandTotal);
    submitBtn.disabled = !document.getElementById('roomSelect').value;
    cartInput.value = JSON.stringify(cart);
}

function addToCart(id, nama, harga, stok) {
    const existing = cart.find(item => item.id === id);
    if (existing) {
        if (stok !== 'unlimited' && existing.qty >= parseInt(stok)) {
            alert('Stok tidak mencukupi untuk item ini.');
            return;
        }
        existing.qty++;
    } else {
        cart.push({ id, nama, harga: parseFloat(harga), qty: 1, stok });
    }
    renderCart();
}

function changeQty(index, delta) {
    const item = cart[index];
    const newQty = item.qty + delta;
    if (newQty <= 0) {
        removeItem(index);
        return;
    }
    if (item.stok !== 'unlimited' && newQty > parseInt(item.stok)) {
        alert('Stok tidak mencukupi.');
        return;
    }
    item.qty = newQty;
    renderCart();
}

function removeItem(index) {
    cart.splice(index, 1);
    renderCart();
}

// =============================================
// Menu Card Click Handlers
// =============================================
document.querySelectorAll('.menu-card').forEach(card => {
    card.addEventListener('click', function() {
        const id = parseInt(this.dataset.id);
        const nama = this.dataset.nama;
        const harga = this.dataset.harga;
        const stok = this.dataset.stok;
        addToCart(id, nama, harga, stok);
        
        // Visual feedback
        this.style.transform = 'scale(0.95)';
        this.style.boxShadow = '0 0 0 2px var(--bs-primary)';
        setTimeout(() => {
            this.style.transform = '';
            this.style.boxShadow = '';
        }, 200);
    });
});

// =============================================
// Category Filter
// =============================================
document.querySelectorAll('#menuTabs .nav-link').forEach(tab => {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('#menuTabs .nav-link').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
        const cat = this.dataset.category;
        document.querySelectorAll('.menu-card-wrapper').forEach(card => {
            card.style.display = (cat === 'all' || card.dataset.category === cat) ? '' : 'none';
        });
    });
});

// =============================================
// Room selection enables submit
// =============================================
document.getElementById('roomSelect').addEventListener('change', renderCart);

// =============================================
// Form submit validation
// =============================================
document.getElementById('orderForm').addEventListener('submit', function(e) {
    if (cart.length === 0) {
        e.preventDefault();
        alert('Keranjang pesanan masih kosong.');
        return;
    }
    if (!document.getElementById('roomSelect').value) {
        e.preventDefault();
        alert('Silakan pilih kamar terlebih dahulu.');
        return;
    }
    document.getElementById('cartItemsInput').value = JSON.stringify(cart);
});
</script>

<?php endif; ?>

<?php include '../views/'.$THEME.'/lower_block.php'; ?>
<?php include '../views/'.$THEME.'/footer.php'; ?>
