<?php
require_once __DIR__ . '/includes/functions.php';

$user = current_user();
if ($user) {
    redirect(role_home($user['role']));
}

$pageTitle = 'Home';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/public_header.php';
?>
<section class="hero-strip">
  <div class="container">
    <div class="small text-uppercase fw-semibold mb-2" style="letter-spacing:.12em;opacity:.8">Official Portal</div>
    <h1 class="h2 mb-2 fw-bold">Immigration Queue Management System</h1>
    <p class="mb-0" style="max-width:640px;opacity:.85">Book appointments, check in at the office, join the walk-in queue and track your ticket live.</p>
  </div>
</section>

<div class="container pb-5">
  <div class="row g-4">
    <div class="col-md-4">
      <div class="service-card">
        <div class="icon-wrap" style="background:linear-gradient(145deg,#F0FDFA,#CCFBF1);color:#0F766E"><i class="fa-solid fa-user-shield"></i></div>
        <h5>Admin Portal</h5>
        <p class="text-muted">Manage counters, services, staff, queue tickets, applications and reports.</p>
        <a href="<?= e(url('auth/login.php')) ?>" class="btn btn-navy btn-sm">Admin Login</a>
      </div>
    </div>
    <div class="col-md-4">
      <div class="service-card">
        <div class="icon-wrap" style="background:linear-gradient(145deg,#EFF6FF,#DBEAFE);color:#1D4ED8"><i class="fa-solid fa-id-badge"></i></div>
        <h5>Staff Workstation</h5>
        <p class="text-muted">Call next visitors, recall numbers, complete or skip tickets at your counter.</p>
        <a href="<?= e(url('auth/login.php')) ?>" class="btn btn-primary btn-sm">Staff Login</a>
      </div>
    </div>
    <div class="col-md-4">
      <div class="service-card">
        <div class="icon-wrap" style="background:linear-gradient(145deg,#ECFDF5,#D1FAE5);color:#15803D"><i class="fa-solid fa-users"></i></div>
        <h5>Public Services</h5>
        <p class="text-muted">Self-service kiosk, online booking, office check-in and live queue tracker.</p>
        <a href="<?= e(url('auth/register.php')) ?>" class="btn btn-success btn-sm">Register</a>
        <a href="<?= e(url('public/kiosk.php')) ?>" class="btn btn-outline-primary btn-sm">Open Kiosk</a>
      </div>
    </div>
  </div>

  <div class="row g-4 mt-2">
    <div class="col-md-6">
      <div class="card-panel">
        <div class="card-panel-header">Quick Links</div>
        <div class="card-panel-body d-grid gap-2">
          <a class="btn btn-outline-primary" href="<?= e(url('public/walk_in.php')) ?>"><i class="fa-solid fa-ticket me-2"></i>Walk-in Queue Registration</a>
          <a class="btn btn-outline-primary" href="<?= e(url('public/office_checkin.php')) ?>"><i class="fa-solid fa-clipboard-check me-2"></i>Office Check-in</a>
          <a class="btn btn-outline-primary" href="<?= e(url('display/queue_board.php')) ?>" target="_blank"><i class="fa-solid fa-tv me-2"></i>Public Queue Display</a>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card-panel">
        <div class="card-panel-header">Office Hours</div>
        <div class="card-panel-body">
          <p class="mb-2"><i class="fa-regular fa-clock me-2 text-primary"></i>Monday – Friday: 08:00 AM – 5:00 PM</p>
          <p class="mb-2"><i class="fa-solid fa-location-dot me-2 text-danger"></i>Immigration Department HQ</p>
          <p class="mb-0 text-muted">Please arrive 10 minutes before your appointment time and bring original identification documents.</p>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
