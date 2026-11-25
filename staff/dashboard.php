<?php
require_once dirname(__DIR__) . '/includes/functions.php';
$user = require_login('staff');
$staff = get_staff_profile((int)$user['id']);
if (!$staff) {
    flash('danger', 'Staff profile not found.');
    redirect('auth/logout.php');
}
$pdo = db();
$serviceList = staff_services((int)$staff['id']);
$serviceIds = array_column($serviceList, 'id');

$waiting = 0; $served = 0; $walkins = 0;
if ($serviceIds) {
    $in = implode(',', array_map('intval', $serviceIds));
    $waiting = (int)$pdo->query("SELECT COUNT(*) FROM queue_tickets WHERE queue_date=CURDATE() AND status='waiting' AND service_id IN ($in)")->fetchColumn();
    $served = (int)$pdo->query("SELECT COUNT(*) FROM queue_tickets WHERE queue_date=CURDATE() AND status='completed' AND staff_id=".(int)$staff['id'])->fetchColumn();
    $walkins = (int)$pdo->query("SELECT COUNT(*) FROM queue_tickets WHERE queue_date=CURDATE() AND ticket_type='walk_in' AND service_id IN ($in)")->fetchColumn();
}
$stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE assigned_staff_id=? AND status IN ('pending','under_review','additional_docs')");
$stmt->execute([$staff['id']]);
$pendingApps = (int)$stmt->fetchColumn();

$currentTicket = get_current_serving_ticket((int)$staff['id']);

$pageTitle = 'Staff Workstation';
$pageHeading = 'Staff Workstation Dashboard';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-shell">
<?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
<div class="main-content">
<?php require dirname(__DIR__) . '/includes/navbar.php'; ?>
<div class="page-body">
<?php $flash = get_flash(); if ($flash): ?><div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>

<div class="card-panel mb-3">
  <div class="card-panel-body">
    <div class="row align-items-center">
      <div class="col-md-8">
        <h4 class="mb-1">Welcome, <?= e($user['full_name']) ?></h4>
        <div class="text-muted">
          Staff ID: <strong><?= e($staff['staff_code']) ?></strong> ·
          Counter: <strong><?= e($staff['counter_number'] ?? 'Not Assigned') ?></strong> ·
          Service: <strong><?= e(implode(', ', array_column($serviceList, 'name')) ?: 'None') ?></strong>
        </div>
      </div>
      <div class="col-md-4 text-md-end mt-2 mt-md-0">
        Counter Status: <?= status_badge($staff['counter_status'] ?? 'offline') ?>
        <?= status_badge($staff['working_status']) ?>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <?php
  $cards = [
    ['Waiting in Queue', $waiting, 'fa-hourglass-half', '#F59E0B'],
    ['Served Today', $served, 'fa-circle-check', '#16A34A'],
    ['Total Walk-ins', $walkins, 'fa-person-walking', '#2563EB'],
    ['Pending Applications', $pendingApps, 'fa-folder-open', '#DC2626'],
  ];
  foreach ($cards as $c): ?>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="d-flex justify-content-between"><div class="stat-label"><?= e($c[0]) ?></div><div class="stat-icon" style="background:<?= e($c[3]) ?>"><i class="fa-solid <?= e($c[2]) ?>"></i></div></div><div class="stat-value"><?= (int)$c[1] ?></div></div></div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card-panel">
      <div class="card-panel-header">Currently Serving</div>
      <div class="card-panel-body text-center">
        <?php if ($currentTicket): ?>
          <div class="queue-number-display mb-2"><?= e($currentTicket['queue_number']) ?></div>
          <div class="fw-semibold"><?= e($currentTicket['visitor_name']) ?></div>
          <div class="text-muted mb-3"><?= e($currentTicket['service_name']) ?></div>
          <?= status_badge($currentTicket['status']) ?>
        <?php else: ?>
          <p class="text-muted mb-0">No visitor currently being served.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card-panel">
      <div class="card-panel-header">Quick Operations</div>
      <div class="card-panel-body">
        <div class="row g-2">
          <div class="col-md-6"><a class="btn btn-navy ops-btn w-100" href="<?= e(url('staff/counter_operations.php')) ?>"><i class="fa-solid fa-bell-concierge me-2"></i>Counter Operations</a></div>
          <div class="col-md-6"><a class="btn btn-primary ops-btn w-100" href="<?= e(url('staff/queue_list.php')) ?>"><i class="fa-solid fa-list-ol me-2"></i>Queue List</a></div>
          <div class="col-md-6"><a class="btn btn-success ops-btn w-100" href="<?= e(url('staff/applications.php')) ?>"><i class="fa-solid fa-folder-open me-2"></i>Process Applications</a></div>
          <div class="col-md-6"><a class="btn btn-outline-secondary ops-btn w-100" href="<?= e(url('display/queue_board.php')) ?>" target="_blank"><i class="fa-solid fa-tv me-2"></i>Open Queue Display</a></div>
        </div>
      </div>
    </div>
  </div>
</div>
</div></div></div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
