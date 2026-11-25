<?php
$user = current_user();
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$flash = get_flash();
?>
<header class="public-header no-print">
  <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
    <a href="<?= e(url('index.php')) ?>" class="brand">
      <span class="me-2 d-inline-flex align-items-center justify-content-center rounded-3" style="width:36px;height:36px;background:rgba(56,189,248,.16);color:#7DD3FC">
        <i class="fa-solid fa-building-columns"></i>
      </span>
      Immigration Department
    </a>
    <nav class="public-nav">
      <?php if ($user && $user['role'] === 'public'): ?>
        <a class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="<?= e(url('public/dashboard.php')) ?>">Dashboard</a>
        <a class="<?= $currentPage === 'kiosk.php' ? 'active' : '' ?>" href="<?= e(url('public/kiosk.php')) ?>">Self-Service Kiosk</a>
        <a class="<?= $currentPage === 'appointments.php' ? 'active' : '' ?>" href="<?= e(url('public/appointments.php')) ?>">Appointments</a>
        <a class="<?= $currentPage === 'office_checkin.php' ? 'active' : '' ?>" href="<?= e(url('public/office_checkin.php')) ?>">Check-in</a>
        <a class="<?= $currentPage === 'walk_in.php' ? 'active' : '' ?>" href="<?= e(url('public/walk_in.php')) ?>">Walk-in</a>
        <a class="<?= $currentPage === 'applications.php' ? 'active' : '' ?>" href="<?= e(url('public/applications.php')) ?>">Applications</a>
        <a class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>" href="<?= e(url('public/profile.php')) ?>">Profile</a>
        <a href="<?= e(url('auth/logout.php')) ?>">Logout</a>
      <?php else: ?>
        <a href="<?= e(url('public/kiosk.php')) ?>">Kiosk</a>
        <a href="<?= e(url('display/queue_board.php')) ?>">Queue Display</a>
        <a href="<?= e(url('auth/login.php')) ?>">Login</a>
        <a href="<?= e(url('auth/register.php')) ?>">Register</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<?php if ($flash): ?>
<div class="container mt-3 no-print">
  <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show">
    <?= e($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
</div>
<?php endif; ?>
