<?php
require_once dirname(__DIR__) . '/includes/functions.php';
$user = require_login('staff');
$staff = get_staff_profile((int)$user['id']);
if (!$staff) {
    flash('danger', 'Staff profile not found. Contact administrator.');
    redirect('auth/logout.php');
}
$serviceIds = array_column(staff_services((int)$staff['id']), 'id');
if (!$serviceIds) {
    flash('warning', 'No services assigned to your account.');
    redirect('staff/dashboard.php');
}

$q = trim((string)get('q', ''));
$status = (string)get('status', '');
$in = implode(',', array_map('intval', $serviceIds));
$sql = "SELECT qt.*, s.name AS service_name, c.counter_number,
        TIMESTAMPDIFF(MINUTE, qt.created_at, NOW()) AS wait_minutes
        FROM queue_tickets qt
        JOIN services s ON s.id = qt.service_id
        LEFT JOIN counters c ON c.id = qt.counter_id
        WHERE qt.queue_date = CURDATE() AND qt.service_id IN ($in)";
$params = [];
if ($status !== '') { $sql .= ' AND qt.status = ?'; $params[] = $status; }
if ($q !== '') { $sql .= ' AND (qt.queue_number LIKE ? OR qt.visitor_name LIKE ?)'; $like='%'.$q.'%'; $params[]=$like; $params[]=$like; }
$sql .= ' ORDER BY FIELD(qt.status,"serving","waiting","completed","skipped","cancelled","no_show"), qt.created_at ASC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$pageTitle = 'Staff Queue List';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-shell">
<?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
<div class="main-content">
<?php require dirname(__DIR__) . '/includes/navbar.php'; ?>
<div class="page-body">
<div class="card-panel">
  <div class="card-panel-header">Authorized Queue Tickets</div>
  <div class="card-panel-body">
    <form class="row g-2 mb-3" method="get">
      <div class="col-md-4"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search queue number / visitor"></div>
      <div class="col-md-3">
        <select class="form-select" name="status">
          <option value="">All Status</option>
          <?php foreach (['waiting','serving','completed','skipped'] as $st): ?>
            <option value="<?= $st ?>" <?= $status===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2"><button class="btn btn-primary w-100">Search</button></div>
    </form>
    <div class="table-responsive">
      <table class="table table-hover">
        <thead><tr><th>Queue #</th><th>Visitor</th><th>Service</th><th>Type</th><th>Counter</th><th>Waiting</th><th>Status</th><th>Created</th></tr></thead>
        <tbody>
        <?php foreach ($tickets as $t): ?>
          <tr>
            <td><strong><?= e($t['queue_number']) ?></strong></td>
            <td><?= e($t['visitor_name']) ?><br><small><?= e($t['visitor_phone'] ?? '') ?></small></td>
            <td><?= e($t['service_name']) ?></td>
            <td><?= e(ucwords(str_replace('_',' ',$t['ticket_type']))) ?></td>
            <td><?= e($t['counter_number'] ?? '-') ?></td>
            <td><?= (int)$t['wait_minutes'] ?> min</td>
            <td><?= status_badge($t['status']) ?></td>
            <td><?= format_datetime($t['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$tickets): ?><tr><td colspan="8" class="text-center text-muted">No tickets found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</div></div></div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
