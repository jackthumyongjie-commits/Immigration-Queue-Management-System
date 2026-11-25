<?php
require_once dirname(__DIR__) . '/includes/functions.php';
$user = require_login('admin');
$stats = admin_dashboard_stats();

$pdo = db();
$statusDist = $pdo->prepare("SELECT status, COUNT(*) c FROM queue_tickets WHERE queue_date = CURDATE() GROUP BY status");
$statusDist->execute();
$statusRows = $statusDist->fetchAll();

$volume = $pdo->query("SELECT s.name, COUNT(qt.id) c FROM services s
  LEFT JOIN queue_tickets qt ON qt.service_id = s.id AND qt.queue_date = CURDATE()
  GROUP BY s.id ORDER BY s.id")->fetchAll();

$counters = $pdo->query("SELECT c.*, u.full_name AS staff_name,
  (SELECT COUNT(*) FROM queue_tickets qt WHERE qt.counter_id = c.id AND qt.queue_date = CURDATE() AND qt.status='waiting') AS waiting_count,
  (SELECT qt2.queue_number FROM queue_tickets qt2 WHERE qt2.counter_id = c.id AND qt2.status='serving' AND qt2.queue_date=CURDATE() LIMIT 1) AS serving_number
  FROM counters c
  LEFT JOIN staff_counter_assignments sca ON sca.counter_id = c.id AND sca.is_current = 1
  LEFT JOIN staff st ON st.id = sca.staff_id
  LEFT JOIN users u ON u.id = st.user_id
  WHERE c.is_active = 1 ORDER BY c.counter_number")->fetchAll();

$recentQueue = $pdo->query("SELECT qt.*, s.name AS service_name FROM queue_tickets qt JOIN services s ON s.id = qt.service_id ORDER BY qt.created_at DESC LIMIT 8")->fetchAll();
$recentApps = $pdo->query("SELECT a.*, s.name AS service_name FROM applications a JOIN services s ON s.id = a.service_id ORDER BY a.created_at DESC LIMIT 6")->fetchAll();

$pageTitle = 'Admin Dashboard';
$pageHeading = 'Admin Dashboard';
$pageSubtitle = 'Live immigration operations overview';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-shell">
  <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
  <div class="main-content">
    <?php require dirname(__DIR__) . '/includes/navbar.php'; ?>
    <div class="page-body">
      <?php $flash = get_flash(); if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show"><?= e($flash['message']) ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
      <?php endif; ?>

      <div class="row g-3 mb-4">
        <?php
        $cards = [
          ['Total Queue Tickets', $stats['total_tickets'], 'fa-ticket', '#123B70'],
          ['Currently Waiting', $stats['waiting'], 'fa-hourglass-half', '#F59E0B'],
          ['Currently Serving', $stats['serving'], 'fa-user-clock', '#2563EB'],
          ['Completed Tickets', $stats['completed'], 'fa-circle-check', '#16A34A'],
          ['Skipped Tickets', $stats['skipped'], 'fa-forward', '#6B7280'],
          ['Pending Applications', $stats['pending_applications'], 'fa-folder-open', '#DC2626'],
          ['Registered Users', $stats['total_users'], 'fa-users', '#0EA5E9'],
          ['Active Staff', $stats['active_staff'], 'fa-user-tie', '#7C3AED'],
        ];
        foreach ($cards as $c): ?>
        <div class="col-6 col-md-3">
          <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
              <div class="stat-label"><?= e($c[0]) ?></div>
              <div class="stat-icon" style="background:<?= e($c[3]) ?>"><i class="fa-solid <?= e($c[2]) ?>"></i></div>
            </div>
            <div class="stat-value"><?= (int)$c[1] ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-lg-4">
          <div class="card-panel h-100">
            <div class="card-panel-header">Today's Queue Status</div>
            <div class="card-panel-body"><canvas id="statusChart" height="220"></canvas></div>
          </div>
        </div>
        <div class="col-lg-8">
          <div class="card-panel h-100">
            <div class="card-panel-header">Today's Service Ticket Volume</div>
            <div class="card-panel-body"><canvas id="volumeChart" height="110"></canvas></div>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-lg-5">
          <div class="card-panel">
            <div class="card-panel-header">Service Counter Live Status</div>
            <div class="card-panel-body p-0">
              <div class="table-responsive">
                <table class="table mb-0">
                  <thead><tr><th>Counter</th><th>Status</th><th>Staff</th><th>Serving</th><th>Waiting</th></tr></thead>
                  <tbody>
                  <?php foreach ($counters as $c): ?>
                    <tr>
                      <td><strong><?= e($c['counter_number']) ?></strong><br><small class="text-muted"><?= e($c['counter_name']) ?></small></td>
                      <td><?= status_badge($c['status']) ?></td>
                      <td><?= e($c['staff_name'] ?? '-') ?></td>
                      <td><?= e($c['serving_number'] ?? '-') ?></td>
                      <td><?= (int)$c['waiting_count'] ?></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="card-panel">
            <div class="card-panel-header">Recent Queue Activities</div>
            <div class="card-panel-body p-0">
              <ul class="list-group list-group-flush">
                <?php foreach ($recentQueue as $q): ?>
                <li class="list-group-item d-flex justify-content-between align-items-start">
                  <div>
                    <strong><?= e($q['queue_number']) ?></strong> · <?= e($q['visitor_name']) ?>
                    <div class="small text-muted"><?= e($q['service_name']) ?> · <?= format_datetime($q['created_at']) ?></div>
                  </div>
                  <?= status_badge($q['status']) ?>
                </li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        </div>
        <div class="col-lg-3">
          <div class="card-panel">
            <div class="card-panel-header">Recent Applications</div>
            <div class="card-panel-body p-0">
              <ul class="list-group list-group-flush">
                <?php foreach ($recentApps as $a): ?>
                <li class="list-group-item">
                  <div class="fw-semibold"><?= e($a['reference_no']) ?></div>
                  <div class="small"><?= e($a['applicant_name']) ?></div>
                  <div class="d-flex justify-content-between align-items-center mt-1">
                    <small class="text-muted"><?= e($a['service_name']) ?></small>
                    <?= status_badge($a['status']) ?>
                  </div>
                </li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
$statusLabels = array_column($statusRows, 'status');
$statusData = array_map('intval', array_column($statusRows, 'c'));
$volLabels = array_column($volume, 'name');
$volData = array_map('intval', array_column($volume, 'c'));
$extraJs = '<script>
const statusChart = new Chart(document.getElementById("statusChart"), {
  type: "doughnut",
  data: {
    labels: ' . json_encode(array_map(fn($s) => ucwords(str_replace('_',' ',$s)), $statusLabels)) . ',
    datasets: [{ data: ' . json_encode($statusData) . ', backgroundColor: ["#F59E0B","#2563EB","#16A34A","#6B7280","#DC2626","#111827"] }]
  },
  options: { plugins: { legend: { position: "bottom" } } }
});
new Chart(document.getElementById("volumeChart"), {
  type: "bar",
  data: {
    labels: ' . json_encode($volLabels) . ',
    datasets: [{ label: "Tickets", data: ' . json_encode($volData) . ', backgroundColor: "#2563EB" }]
  },
  options: { scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }, plugins: { legend: { display: false } } }
});
</script>';
require dirname(__DIR__) . '/includes/footer.php';
?>
