<?php
require_once dirname(__DIR__) . '/includes/functions.php';
$user = require_login('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    try {
        if ($action === 'save') {
            $id = (int)post('id', 0);
            $name = trim((string)post('name', ''));
            $code = strtoupper(trim((string)post('code', '')));
            $desc = trim((string)post('description', ''));
            $duration = max(5, (int)post('estimated_duration', 15));
            $prefix = strtoupper(substr(trim((string)post('queue_prefix', 'A')), 0, 1));
            $icon = trim((string)post('icon', 'fa-passport'));
            $active = (int)post('is_active', 1);
            $counters = array_map('intval', (array)post('counters', []));
            if ($name === '' || $code === '') throw new RuntimeException('Name and code are required.');

            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE services SET name=?, code=?, description=?, estimated_duration=?, queue_prefix=?, icon=?, is_active=?, updated_at=? WHERE id=?');
                $stmt->execute([$name, $code, $desc, $duration, $prefix, $icon, $active, now(), $id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO services (name, code, description, estimated_duration, queue_prefix, icon, is_active) VALUES (?,?,?,?,?,?,?)');
                $stmt->execute([$name, $code, $desc, $duration, $prefix, $icon, $active]);
                $id = (int)$pdo->lastInsertId();
            }

            $pdo->prepare('DELETE FROM counter_services WHERE service_id=?')->execute([$id]);
            $ins = $pdo->prepare('INSERT INTO counter_services (counter_id, service_id) VALUES (?,?)');
            foreach ($counters as $cid) if ($cid > 0) $ins->execute([$cid, $id]);

            flash('success', 'Service saved successfully.');
        } elseif ($action === 'delete') {
            $pdo->prepare('DELETE FROM services WHERE id=?')->execute([(int)post('id')]);
            flash('success', 'Service deleted.');
        }
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    redirect('admin/services.php');
}

$counters = $pdo->query('SELECT * FROM counters WHERE is_active=1 ORDER BY counter_number')->fetchAll();
$services = $pdo->query("SELECT s.*,
  (SELECT GROUP_CONCAT(c.counter_number SEPARATOR ', ') FROM counter_services cs JOIN counters c ON c.id = cs.counter_id WHERE cs.service_id = s.id) AS counters
  FROM services s ORDER BY s.id")->fetchAll();

$editId = (int)get('edit', 0);
$edit = null; $editCounters = [];
if ($editId) {
    foreach ($services as $s) if ((int)$s['id'] === $editId) $edit = $s;
    $stmt = $pdo->prepare('SELECT counter_id FROM counter_services WHERE service_id=?');
    $stmt->execute([$editId]);
    $editCounters = array_map('intval', array_column($stmt->fetchAll(), 'counter_id'));
}

$pageTitle = 'Service Management';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-shell">
<?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
<div class="main-content">
<?php require dirname(__DIR__) . '/includes/navbar.php'; ?>
<div class="page-body">
<?php $flash = get_flash(); if ($flash): ?><div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card-panel">
      <div class="card-panel-header"><?= $edit ? 'Edit Service' : 'Add Service' ?></div>
      <div class="card-panel-body">
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
          <div class="mb-2"><label class="form-label">Service Name</label><input class="form-control" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
          <div class="mb-2"><label class="form-label">Code</label><input class="form-control" name="code" required value="<?= e($edit['code'] ?? '') ?>"></div>
          <div class="mb-2"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3"><?= e($edit['description'] ?? '') ?></textarea></div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label">Duration (min)</label><input type="number" class="form-control" name="estimated_duration" value="<?= (int)($edit['estimated_duration'] ?? 15) ?>"></div>
            <div class="col-6"><label class="form-label">Queue Prefix</label><input class="form-control" name="queue_prefix" maxlength="1" value="<?= e($edit['queue_prefix'] ?? 'A') ?>"></div>
          </div>
          <div class="mb-2"><label class="form-label">Icon (Font Awesome)</label><input class="form-control" name="icon" value="<?= e($edit['icon'] ?? 'fa-passport') ?>"></div>
          <div class="mb-2"><label class="form-label">Available Counters</label>
            <?php foreach ($counters as $c): ?>
              <div class="form-check"><input class="form-check-input" type="checkbox" name="counters[]" value="<?= (int)$c['id'] ?>" <?= in_array((int)$c['id'], $editCounters, true) ? 'checked' : '' ?>><label class="form-check-label"><?= e($c['counter_number'] . ' - ' . $c['counter_name']) ?></label></div>
            <?php endforeach; ?>
          </div>
          <div class="mb-3"><label class="form-label">Enabled</label>
            <select class="form-select" name="is_active"><option value="1" <?= ((int)($edit['is_active'] ?? 1)===1)?'selected':'' ?>>Yes</option><option value="0" <?= ((int)($edit['is_active'] ?? 1)===0)?'selected':'' ?>>No</option></select>
          </div>
          <button class="btn btn-primary w-100">Save Service</button>
          <?php if ($edit): ?><a href="<?= e(url('admin/services.php')) ?>" class="btn btn-outline-secondary w-100 mt-2">Cancel</a><?php endif; ?>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card-panel">
      <div class="card-panel-header">Immigration Services</div>
      <div class="card-panel-body p-0 table-responsive">
        <table class="table mb-0">
          <thead><tr><th>Service</th><th>Prefix</th><th>Duration</th><th>Counters</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($services as $s): ?>
            <tr>
              <td><i class="fa-solid <?= e($s['icon']) ?> me-2 text-primary"></i><strong><?= e($s['name']) ?></strong><br><small class="text-muted"><?= e($s['code']) ?></small></td>
              <td><span class="badge bg-navy" style="background:#123B70"><?= e($s['queue_prefix']) ?></span></td>
              <td><?= (int)$s['estimated_duration'] ?> min</td>
              <td><small><?= e($s['counters'] ?? '-') ?></small></td>
              <td><?= (int)$s['is_active'] ? status_badge('available') : status_badge('offline') ?></td>
              <td class="text-nowrap">
                <a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$s['id'] ?>"><i class="fa-solid fa-pen"></i></a>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete service?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button></form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</div></div></div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
