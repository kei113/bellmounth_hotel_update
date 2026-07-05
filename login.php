<?php
// Secure session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // 1 in production with HTTPS
ini_set('session.cookie_path', '/');
ini_set('session.use_strict_mode', 1);
session_start();
if (isset($_SESSION['user_id']) && empty($_SESSION['role'])) {
session_destroy();
}
// Include functions FIRST (so auth.php can use sanitize(), etc.)
require_once 'lib/functions.php';
require_once 'lib/auth.php';
// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
redirectBasedOnRole($_SESSION['role']);
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
// CSRF Check
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
die('Invalid request. CSRF token mismatch.');
}
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
if (empty($username) || empty($password)) {
$error = "Semua field harus diisi.";
} else {
$role = login($username, $password);
if ($role) {
redirectBasedOnRole($role);
} else {
$error = "Username atau password salah.";
}
}
}
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Hotel Bellmounth</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root {
    --primary-color: #1A202C; /* Deep Navy */
    --accent-color: #C5A059;  /* Elegant Gold */
    --text-color: #2D3748;
}
body {
    font-family: 'Inter', sans-serif;
    background-image: url('assets/default/images/hotel_bellmounth.jpg');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}
/* Dark Overlay */
body::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(15, 23, 42, 0.65); /* Dark Navy Overlay */
    z-index: 0;
}
.login-container {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 450px;
    padding: 20px;
}
.login-card {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 16px;
    padding: 3rem 2.5rem;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
    text-align: center;
}
.brand-title {
    font-family: 'Playfair Display', serif;
    font-weight: 700;
    font-size: 2.5rem;
    color: var(--primary-color);
    margin-bottom: 0.5rem;
    letter-spacing: -0.5px;
}
.brand-subtitle {
    font-family: 'Inter', sans-serif;
    font-size: 0.95rem;
    color: #4A5568;
    margin-bottom: 2.5rem;
    letter-spacing: 1px;
    text-transform: uppercase;
    font-weight: 500;
}
.form-floating > .form-control {
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding-left: 1rem;
    font-size: 0.95rem;
    background-color: rgba(255, 255, 255, 0.9);
}
.form-floating > .form-control:focus {
    box-shadow: 0 0 0 3px rgba(197, 160, 89, 0.2);
    border-color: var(--accent-color);
}
.form-floating > label {
    padding-left: 1rem;
    color: #718096;
}
.btn-login {
    background: linear-gradient(135deg, #C5A059 0%, #B08D46 100%);
    border: none;
    padding: 0.85rem;
    border-radius: 8px;
    color: white;
    font-family: 'Inter', sans-serif;
    font-weight: 600;
    letter-spacing: 0.5px;
    box-shadow: 0 4px 10px rgba(197, 160, 89, 0.3);
    transition: all 0.3s ease;
}
.btn-login:hover {
    background: linear-gradient(135deg, #B08D46 0%, #9A7B3E 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(197, 160, 89, 0.4);
    color: white;
}
.divider {
    height: 1px;
    background: #E2E8F0;
    margin: 2rem 0;
    position: relative;
}
.divider span {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(255, 255, 255, 0.9);
    padding: 0 10px;
    color: #A0AEC0;
    font-size: 0.8rem;
    text-transform: uppercase;
}
.copyright {
    color: rgba(255, 255, 255, 0.6);
    font-family: 'Playfair Display', serif;
    font-size: 0.85rem;
    margin-top: 20px;
    font-style: italic;
}
</style>
</head>
<body>
<div class="login-container">
    <div class="login-card">
        <div class="mb-2">
            <i class="bi bi-buildings-fill" style="font-size: 2.5rem; color: var(--accent-color);"></i>
        </div>
        <h1 class="brand-title">Hotel Bellmounth</h1>
        <p class="brand-subtitle">Staff Administration</p>

        <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center p-2 mb-4 text-start font-monospace small" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-6"></i>
            <div><?= $error ?></div>
        </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
            
            <div class="form-floating mb-3">
                <input type="text" name="username" class="form-control" id="floatingInput" placeholder="Username" required>
                <label for="floatingInput">Username</label>
            </div>
            
            <div class="form-floating mb-4">
                <input type="password" name="password" class="form-control" id="floatingPassword" placeholder="Password" required>
                <label for="floatingPassword">Password</label>
            </div>

            <button type="submit" class="btn btn-login w-100">
                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In Secured
            </button>
        </form>
    </div>
    <div class="text-center copyright">
        &copy; <?= date('Y') ?> Hotel Bellmounth. Excellence in Hospitality.
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
