<?php
require_once dirname(__DIR__) . '/includes/functions.php';
$user = require_login('admin');
$pdo = db();
$date = (string)get('date', today());

$stmt = $pdo->prepare("SELECT
  COUNT(*) AS total_visitors,
  SUM(ticket_type='appointment') AS appointments,
  SUM(ticket_type='walk_in') AS walk_ins,
  SUM(status='completed') AS completed,
  SUM(status='waiting') AS waiting,
  SUM(status='skipped') AS skipped,
  AVG(CASE WHEN status='completed' AND started_at IS NOT NULL AND called_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, created_at, called_at) END) AS avg_wait,
  AVG(CASE WHEN status='completed' AND completed_at IS NOT NULL AND started_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, started_at, completed_at) END) AS avg_service
  FROM queue_tickets WHERE queue_date = ?");
$stmt->execute([$date]);
$report = $stmt->fetch() ?: [];

$popularity = $pdo->prepare("SELECT s.name, COUNT(qt.id) AS total,
  SUM(qt.status='completed') AS completed
  FROM services s
  LEFT JOIN queue_tickets qt ON qt.service_id = s.id AND qt.queue_date = ?
  GROUP BY s.id ORDER BY total DESC");
$popularity->execute([$date]);
$servicesPop = $popularity->fetchAll();

$apptCount = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date = ?");
$apptCount->execute([$date]);
$totalAppts = (int)$apptCount->fetchColumn();

$pageTitle = 'Daily Reports';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-shell">
<?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
<div class="main-content">
<?php require dirname(__DIR__) . '/includes/navbar.php'; ?>
<div class="page-body">
  <div class="card-panel no-print">
    <div class="card-panel-header d-flex justify-content-between">
      <span>Daily Reports</span>
      <button class="btn btn-sm btn-outline-primary" onclick="window.print()"><i class="fa-solid fa-print me-1"></i>Print Report</button>
    </div>
    <div class="card-panel-body">
      <form class="row g-2 align-items-end" method="get">
        <div class="col-md-3"><label class="form-label">Report Date</label><input type="date" name="date" class="form-control" value="<?= e($date) ?>"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Generate</button></div>
      </form>
    </div>
  </div>

  <div class="printable">
    <div class="mb-3">
      <h4 class="page-title">Immigration Daily Operations Report</h4>
      <div class="text-muted">Date: <?= format_date($date) ?> · Generated: <?= format_datetime(now()) ?></div>
    </div>

    <div class="row g-3 mb-3">
      <?php
      $cards = [
        ['Total Visitors', (int)($report['total_visitors'] ?? 0), 'fa-users'],
        ['Appointments (Day)', $totalAppts, 'fa-calendar-check'],
        ['Walk-in Visitors', (int)($report['walk_ins'] ?? 0), 'fa-person-walking'],
        ['Completed Services', (int)($report['completed'] ?? 0), 'fa-circle-check'],
        ['Waiting Visitors', (int)($report['waiting'] ?? 0), 'fa-hourglass'],
        ['Skipped Tickets', (int)($report['skipped'] ?? 0), 'fa-forward'],
        ['Avg Waiting Time', round((float)($report['avg_wait'] ?? 0), 1) . ' min', 'fa-clock'],
        ['Avg Service Duration', round((float)($report['avg_service'] ?? 0), 1) . ' min', 'fa-stopwatch'],
      ];
      foreach ($cards as $c): ?>
      <div class="col-6 col-md-3">
        <div class="stat-card">
          <div class="stat-label"><i class="fa-solid <?= e($c[2]) ?> me-1"></i><?= e($c[0]) ?></div>
          <div class="stat-value"><?= e((string)$c[1]) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="card-panel">
      <div class="card-panel-header">Service Popularity</div>
      <div class="card-panel-body">
        <div class="row">
          <div class="col-md-7 table-responsive">
            <table class="table">
              <thead><tr><th>Service</th><th>Tickets</th><th>Completed</th></tr></thead>
              <tbody>
              <?php foreach ($servicesPop as $s): ?>
                <tr><td><?= e($s['name']) ?></td><td><?= (int)$s['total'] ?></td><td><?= (int)$s['completed'] ?></td></tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="col-md-5"><canvas id="popChart" height="200"></canvas></div>
        </div>
      </div>
    </div>
  </div>
</div></div></div>
<?php
$extraJs = '<script>new Chart(document.getElementById("popChart"),{type:"pie",data:{labels:'.json_encode(array_column($servicesPop,'name')).',datasets:[{data:'.json_encode(array_map('intval',array_column($servicesPop,'total'))).',backgroundColor:["#2563EB","#16A34A","#F59E0B","#DC2626","#7C3AED"]}]},options:{plugins:{legend:{position:"bottom"}}}});</script>';
require dirname(__DIR__) . '/includes/footer.php';
?>
