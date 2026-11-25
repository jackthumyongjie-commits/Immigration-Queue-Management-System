<?php
require_once dirname(__DIR__) . '/includes/functions.php';
$user = require_login('public');
$pdo = db();

$appts = $pdo->prepare('SELECT a.*, s.name AS service_name FROM appointments a JOIN services s ON s.id=a.service_id WHERE a.user_id=? ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT 5');
$appts->execute([$user['id']]);
$appointments = $appts->fetchAll();

$tickets = $pdo->prepare('SELECT qt.*, s.name AS service_name FROM queue_tickets qt JOIN services s ON s.id=qt.service_id WHERE qt.user_id=? ORDER BY qt.created_at DESC LIMIT 5');
$tickets->execute([$user['id']]);
$queueTickets = $tickets->fetchAll();

$apps = $pdo->prepare('SELECT a.*, s.name AS service_name FROM applications a JOIN services s ON s.id=a.service_id WHERE a.user_id=? ORDER BY a.created_at DESC LIMIT 5');
$apps->execute([$user['id']]);
$applications = $apps->fetchAll();

$pageTitle = 'Public Dashboard';
require dirname(__DIR__) . '/includes/header.php';
require dirname(__DIR__) . '/includes/public_header.php';
?>
<section class="hero-strip">
  <div class="container">
    <h1 class="h4 mb-1">Welcome, <?= e($user['full_name']) ?></h1>
    <p class="mb-0 opacity-75">Manage appointments, check in, join the queue and track your tickets.</p>
  </div>
</section>
<div class="container pb-5">
  <div class="row g-3 mb-4">
    <div class="col-md-3"><a class="service-card d-block" href="<?= e(url('public/kiosk.php')) ?>"><div class="icon-wrap"><i class="fa-solid fa-tablet-screen-button"></i></div><h6>Self-Service Kiosk</h6></a></div>
    <div class="col-md-3"><a class="service-card d-block" href="<?= e(url('public/appointments.php')) ?>"><div class="icon-wrap"><i class="fa-solid fa-calendar-plus"></i></div><h6>Book Appointment</h6></a></div>
    <div class="col-md-3"><a class="service-card d-block" href="<?= e(url('public/office_checkin.php')) ?>"><div class="icon-wrap"><i class="fa-solid fa-clipboard-check"></i></div><h6>Office Check-in</h6></a></div>
    <div class="col-md-3"><a class="service-card d-block" href="<?= e(url('public/walk_in.php')) ?>"><div class="icon-wrap"><i class="fa-solid fa-ticket"></i></div><h6>Walk-in Queue</h6></a></div>
  </div>

  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card-panel"><div class="card-panel-header">My Appointments</div><div class="card-panel-body p-0">
        <ul class="list-group list-group-flush">
          <?php foreach ($appointments as $a): ?>
            <li class="list-group-item"><strong><?= e($a['reference_no']) ?></strong><div class="small"><?= e($a['service_name']) ?> · <?= format_date($a['appointment_date']) ?> <?= format_time($a['appointment_time']) ?></div><?= status_badge($a['status']) ?></li>
          <?php endforeach; ?>
          <?php if (!$appointments): ?><li class="list-group-item text-muted">No appointments yet.</li><?php endif; ?>
        </ul>
      </div></div>
    </div>
    <div class="col-lg-4">
      <div class="card-panel"><div class="card-panel-header">Queue History</div><div class="card-panel-body p-0">
        <ul class="list-group list-group-flush">
          <?php foreach ($queueTickets as $t): ?>
            <li class="list-group-item d-flex justify-content-between"><div><strong><?= e($t['queue_number']) ?></strong><div class="small"><?= e($t['service_name']) ?></div></div><div><?= status_badge($t['status']) ?><?php if (in_array($t['status'], ['waiting','serving'], true)): ?><div><a class="small" href="<?= e(url('public/live_tracker.php')) ?>?ref=<?= e(urlencode($t['ticket_ref'])) ?>">Track</a></div><?php endif; ?></div></li>
          <?php endforeach; ?>
          <?php if (!$queueTickets): ?><li class="list-group-item text-muted">No queue tickets yet.</li><?php endif; ?>
        </ul>
      </div></div>
    </div>
    <div class="col-lg-4">
      <div class="card-panel"><div class="card-panel-header">Applications</div><div class="card-panel-body p-0">
        <ul class="list-group list-group-flush">
          <?php foreach ($applications as $a): ?>
            <li class="list-group-item"><strong><?= e($a['reference_no']) ?></strong><div class="small"><?= e($a['service_name']) ?></div><?= status_badge($a['status']) ?></li>
          <?php endforeach; ?>
          <?php if (!$applications): ?><li class="list-group-item text-muted">No applications yet.</li><?php endif; ?>
        </ul>
        <div class="p-3"><a href="<?= e(url('public/applications.php')) ?>" class="btn btn-sm btn-outline-primary">Manage Applications</a></div>
      </div></div>
    </div>
  </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
