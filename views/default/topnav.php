<header class="top-navbar">
<div class="navbar-left">
<h1 class="page-title"><?= ucfirst($_SESSION['role'] ?? 'User') ?> Dashboard — <?= htmlspecialchars($_SESSION['nama'] ?? $_SESSION['username'] ?? 'User') ?></h1>
</div>
<div class="navbar-right">
<div class="user-dropdown">
<img src="https://picsum.photos/seed/user123/40/40.jpg" alt="User Avatar" class="user-avatar">
</div>
</header>
