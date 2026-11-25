<?php
require_once dirname(__DIR__) . '/includes/functions.php';
$user = require_login('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    try {
        if ($action === 'reseed') {
            $names = ['Demo Visitor A', 'Demo Visitor B', 'Walk-in Customer', 'Enquiry Guest', 'Passport Applicant', 'Visa Applicant'];
            $services = $pdo->query('SELECT id FROM services WHERE is_active=1')->fetchAll(PDO::FETCH_COLUMN);
            $created = 0;
            foreach ($services as $i => $sid) {
                create_queue_ticket([
                    'visitor_name' => $names[$i % count($names)],
                    'visitor_ic' => 'D' . str_pad((string)random_int(1, 999999), 6, '0', STR_PAD_LEFT),
                    'visitor_phone' => '01' . random_int(10000000, 99999999),
                    'service_id' => (int)$sid,
                    'ticket_type' => 'walk_in',
                    'performed_by' => (int)$user['id'],
                    'notes' => 'Re-seeded demo queue',
                ]);
                $created++;
            }
            $counter = $pdo->query("SELECT c.id, sca.staff_id FROM counters c JOIN staff_counter_assignments sca ON sca.counter_id=c.id AND sca.is_current=1 WHERE c.is_active=1 LIMIT 1")->fetch();
            if ($counter) {
                $stmt = $pdo->prepare("SELECT id FROM queue_tickets WHERE queue_date=CURDATE() AND status='waiting' ORDER BY id DESC LIMIT 1");
                $stmt->execute();
                $tid = (int)$stmt->fetchColumn();
                if ($tid) {
                    $pdo->prepare("UPDATE queue_tickets SET status='serving', counter_id=?, staff_id=?, called_at=?, started_at=NULL WHERE id=?")
                        ->execute([$counter['id'], $counter['staff_id'], now(), $tid]);
                    $pdo->prepare("UPDATE counters SET status='serving' WHERE id=?")->execute([$counter['id']]);
                }
            }
            log_activity((int)$user['id'], 'reseed_demo', 'demo', "Created $created demo tickets");
            flash('success', "Demo queue re-seeded with $created new tickets.");
        } elseif ($action === 'simulate_walkin') {
            $services = $pdo->query('SELECT id, name FROM services WHERE is_active=1 ORDER BY RAND() LIMIT 1')->fetch();
            if (!$services) throw new RuntimeException('No active services.');
            $ticket = create_queue_ticket([
                'visitor_name' => 'Walk-in Customer ' . random_int(100, 999),
                'visitor_ic' => 'W' . random_int(100000, 999999),
                'visitor_phone' => '01' . random_int(10000000, 99999999),
                'service_id' => (int)$services['id'],
                'ticket_type' => 'walk_in',
                'performed_by' => (int)$user['id'],
                'notes' => 'Simulated walk-in visitor',
            ]);
            log_activity((int)$user['id'], 'simulate_walkin', 'demo', $ticket['queue_number']);
            flash('success', 'Simulated walk-in created: ' . $ticket['queue_number'] . ' (' . $services['name'] . ')');
        } elseif ($action === 'reset_today') {
            $pdo->beginTransaction();
            $ids = $pdo->query("SELECT id FROM queue_tickets WHERE queue_date = CURDATE()")->fetchAll(PDO::FETCH_COLUMN);
            if ($ids) {
                $in = implode(',', array_map('intval', $ids));
                $pdo->exec("DELETE FROM queue_history WHERE ticket_id IN ($in)");
                $pdo->exec("DELETE FROM queue_tickets WHERE queue_date = CURDATE()");
            }
            $pdo->exec("UPDATE counters SET status = IF(is_active=1, 'available', 'offline')");
            $pdo->commit();
            log_activity((int)$user['id'], 'reset_today_queue', 'demo', 'Today queue reset');
            flash('success', "Today's queue has been reset successfully.");
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('danger', $e->getMessage());
    }
    redirect('admin/demo_toolkit.php');
}

$todayStats = admin_dashboard_stats();
$pageTitle = 'Presentation Demo & Simulation Toolkit';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-shell">
<?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
<div class="main-content">
<?php require dirname(__DIR__) . '/includes/navbar.php'; ?>
<div class="page-body">
<?php $flash = get_flash(); if ($flash): ?><div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>

<div class="mb-3">
  <h4 class="page-title">Presentation Demo & Simulation Toolkit</h4>
  <p class="page-subtitle">Use these tools during presentations to generate live demonstration queue data.</p>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="stat-card"><div class="stat-label">Today Tickets</div><div class="stat-value"><?= (int)$todayStats['total_tickets'] ?></div></div></div>
  <div class="col-md-3"><div class="stat-card"><div class="stat-label">Waiting</div><div class="stat-value"><?= (int)$todayStats['waiting'] ?></div></div></div>
  <div class="col-md-3"><div class="stat-card"><div class="stat-label">Serving</div><div class="stat-value"><?= (int)$todayStats['serving'] ?></div></div></div>
  <div class="col-md-3"><div class="stat-card"><div class="stat-label">Completed</div><div class="stat-value"><?= (int)$todayStats['completed'] ?></div></div></div>
</div>

<div class="row g-4">
  <div class="col-md-4">
    <div class="demo-card">
      <div class="demo-icon bg-primary text-white"><i class="fa-solid fa-database"></i></div>
      <h5>A. Re-Seed Demo Queue</h5>
      <p class="text-muted">Generate demonstration queue records with sample visitors and tickets for each active service.</p>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reseed">
        <button class="btn btn-primary w-100" data-confirm="Generate new demo queue tickets?">Re-Seed Demo Queue</button>
      </form>
    </div>
  </div>
  <div class="col-md-4">
    <div class="demo-card">
      <div class="demo-icon bg-success text-white"><i class="fa-solid fa-person-walking"></i></div>
      <h5>B. Simulate Walk-in Visitor</h5>
      <p class="text-muted">Generate a new walk-in visitor, assign a queue number automatically and add to the waiting queue.</p>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="simulate_walkin">
        <button class="btn btn-success w-100">Simulate Walk-in Visitor</button>
      </form>
    </div>
  </div>
  <div class="col-md-4">
    <div class="demo-card">
      <div class="demo-icon bg-danger text-white"><i class="fa-solid fa-rotate-left"></i></div>
      <h5>C. Reset Today's Queue</h5>
      <p class="text-muted">Safely clear today's queue records and reset counter statuses. Requires confirmation.</p>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reset_today">
        <button class="btn btn-danger w-100" data-confirm="Reset ALL of today's queue tickets? This cannot be undone.">Reset Today's Queue</button>
      </form>
    </div>
  </div>
</div>
</div></div></div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
