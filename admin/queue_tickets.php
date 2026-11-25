<?php
require_once dirname(__DIR__) . '/includes/functions.php';
$user = require_login('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    $id = (int)post('id', 0);
    try {
        if ($action === 'update_status') {
            $status = (string)post('status');
            $allowed = ['waiting','serving','completed','skipped','cancelled','no_show'];
            if (!in_array($status, $allowed, true)) throw new RuntimeException('Invalid status.');
            $ticket = get_ticket_by_id($id);
            if (!$ticket) throw new RuntimeException('Ticket not found.');
            $stmt = $pdo->prepare('UPDATE queue_tickets SET status=?, updated_at=?, completed_at=IF(? IN ("completed","skipped","cancelled","no_show"), ?, completed_at) WHERE id=?');
            $stmt->execute([$status, now(), $status, now(), $id]);
            add_queue_history($id, 'admin_update', $ticket['status'], $status, (int)$user['id'], 'Manual status update by admin');
            flash('success', 'Ticket status updated.');
        }
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    redirect('admin/queue_tickets.php?' . http_build_query(array_filter([
        'q' => get('q'), 'date' => get('date'), 'service_id' => get('service_id'), 'status' => get('status')
    ])));
}

$q = trim((string)get('q', ''));
$date = (string)get('date', today());
$serviceId = (int)get('service_id', 0);
$status = (string)get('status', '');

$sql = "SELECT qt.*, s.name AS service_name, c.counter_number, a.reference_no AS appointment_ref
        FROM queue_tickets qt
        JOIN services s ON s.id = qt.service_id
        LEFT JOIN counters c ON c.id = qt.counter_id
        LEFT JOIN appointments a ON a.id = qt.appointment_id
        WHERE 1=1";
$params = [];
if ($date !== '') { $sql .= ' AND qt.queue_date = ?'; $params[] = $date; }
if ($serviceId > 0) { $sql .= ' AND qt.service_id = ?'; $params[] = $serviceId; }
if ($status !== '') { $sql .= ' AND qt.status = ?'; $params[] = $status; }
if ($q !== '') {
    $sql .= ' AND (qt.queue_number LIKE ? OR qt.visitor_name LIKE ? OR qt.ticket_ref LIKE ? OR qt.visitor_ic LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
$sql .= ' ORDER BY qt.created_at DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();
$services = $pdo->query('SELECT id, name FROM services ORDER BY name')->fetchAll();

$viewId = (int)get('view', 0);
$viewTicket = $viewId ? get_ticket_by_id($viewId) : null;
$history = [];
if ($viewTicket) {
    $h = $pdo->prepare('SELECT qh.*, u.full_name FROM queue_history qh LEFT JOIN users u ON u.id = qh.performed_by WHERE ticket_id=? ORDER BY qh.created_at');
    $h->execute([$viewId]);
    $history = $h->fetchAll();
}

$pageTitle = 'Queue Tickets Administration';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-shell">
<?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
<div class="main-content">
<?php require dirname(__DIR__) . '/includes/navbar.php'; ?>
<div class="page-body">
<?php $flash = get_flash(); if ($flash): ?><div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>

<div class="card-panel">
  <div class="card-panel-header">Search & Filters</div>
  <div class="card-panel-body">
    <form class="row g-2" method="get">
      <div class="col-md-3"><input class="form-control" name="q" placeholder="Search queue / name / ref" value="<?= e($q) ?>"></div>
      <div class="col-md-2"><input type="date" class="form-control" name="date" value="<?= e($date) ?>"></div>
      <div class="col-md-3">
        <select class="form-select" name="service_id">
          <option value="0">All Services</option>
          <?php foreach ($services as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $serviceId===(int)$s['id']?'selected':'' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select class="form-select" name="status">
          <option value="">All Status</option>
          <?php foreach (['waiting','serving','completed','skipped','cancelled','no_show'] as $st): ?>
            <option value="<?= $st ?>" <?= $status===$st?'selected':'' ?>><?= ucwords(str_replace('_',' ',$st)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
    </form>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-<?= $viewTicket ? '7' : '12' ?>">
    <div class="card-panel">
      <div class="card-panel-header">Queue Tickets (<?= count($tickets) ?>)</div>
      <div class="card-panel-body p-0 table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Queue #</th><th>Visitor</th><th>Service</th><th>Type</th><th>Counter</th><th>Created</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($tickets as $t): ?>
            <tr>
              <td><strong><?= e($t['queue_number']) ?></strong><br><small class="text-muted"><?= e($t['ticket_ref']) ?></small></td>
              <td><?= e($t['visitor_name']) ?><br><small><?= e($t['visitor_ic'] ?? '') ?></small></td>
              <td><?= e($t['service_name']) ?></td>
              <td><?= e(ucwords(str_replace('_',' ',$t['ticket_type']))) ?></td>
              <td><?= e($t['counter_number'] ?? '-') ?></td>
              <td><?= format_datetime($t['created_at']) ?></td>
              <td><?= status_badge($t['status']) ?></td>
              <td><a class="btn btn-sm btn-outline-primary" href="?view=<?= (int)$t['id'] ?>&<?= e(http_build_query(['q'=>$q,'date'=>$date,'service_id'=>$serviceId,'status'=>$status])) ?>">View</a></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$tickets): ?><tr><td colspan="8" class="text-center text-muted py-4">No tickets found.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php if ($viewTicket): ?>
  <div class="col-lg-5">
    <div class="card-panel">
      <div class="card-panel-header">Ticket Details</div>
      <div class="card-panel-body">
        <div class="text-center mb-3"><div class="queue-number-display" style="font-size:2.5rem"><?= e($viewTicket['queue_number']) ?></div><?= status_badge($viewTicket['status']) ?></div>
        <dl class="row mb-0 small">
          <dt class="col-5">Reference</dt><dd class="col-7"><?= e($viewTicket['ticket_ref']) ?></dd>
          <dt class="col-5">Visitor</dt><dd class="col-7"><?= e($viewTicket['visitor_name']) ?></dd>
          <dt class="col-5">IC / Passport</dt><dd class="col-7"><?= e($viewTicket['visitor_ic'] ?? '-') ?></dd>
          <dt class="col-5">Phone</dt><dd class="col-7"><?= e($viewTicket['visitor_phone'] ?? '-') ?></dd>
          <dt class="col-5">Service</dt><dd class="col-7"><?= e($viewTicket['service_name']) ?></dd>
          <dt class="col-5">Appointment</dt><dd class="col-7"><?= e($viewTicket['appointment_ref'] ?? '-') ?></dd>
          <dt class="col-5">Counter</dt><dd class="col-7"><?= e($viewTicket['counter_number'] ?? '-') ?></dd>
          <dt class="col-5">Created</dt><dd class="col-7"><?= format_datetime($viewTicket['created_at']) ?></dd>
        </dl>
        <form method="post" class="mt-3">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_status">
          <input type="hidden" name="id" value="<?= (int)$viewTicket['id'] ?>">
          <div class="input-group">
            <select class="form-select" name="status">
              <?php foreach (['waiting','serving','completed','skipped','cancelled','no_show'] as $st): ?>
                <option value="<?= $st ?>" <?= $viewTicket['status']===$st?'selected':'' ?>><?= ucwords(str_replace('_',' ',$st)) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-primary">Update</button>
          </div>
        </form>
        <hr>
        <h6>Queue History</h6>
        <ul class="list-group list-group-flush">
          <?php foreach ($history as $h): ?>
            <li class="list-group-item px-0 small">
              <strong><?= e($h['action']) ?></strong>
              <?= e(($h['old_status'] ?? '-') . ' → ' . ($h['new_status'] ?? '-')) ?>
              <div class="text-muted"><?= format_datetime($h['created_at']) ?> · <?= e($h['full_name'] ?? 'System') ?></div>
              <?php if ($h['notes']): ?><div><?= e($h['notes']) ?></div><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
</div></div></div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
