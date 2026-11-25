<?php $user = $user ?? current_user(); ?>
<header class="topbar">
  <div class="d-flex align-items-center gap-3">
    <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle" type="button">
      <i class="fa-solid fa-bars"></i>
    </button>
    <div>
      <div class="fw-semibold text-navy"><?= e($pageHeading ?? $pageTitle ?? 'Dashboard') ?></div>
      <small class="text-muted"><?= e($pageSubtitle ?? date('l, d F Y')) ?></small>
    </div>
  </div>
  <div class="d-flex align-items-center gap-3">
    <span class="badge bg-light text-dark border">
      <i class="fa-regular fa-clock me-1"></i><?= date('h:i A') ?>
    </span>
    <div class="dropdown">
      <button class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown">
        <i class="fa-solid fa-user-circle me-1"></i><?= e($user['full_name'] ?? 'User') ?>
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><span class="dropdown-item-text small text-muted text-capitalize"><?= e($user['role'] ?? '') ?> Account</span></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="<?= e(url('auth/logout.php')) ?>"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a></li>
      </ul>
    </div>
  </div>
</header>
