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
            $number = trim((string)post('counter_number', ''));
            $name = trim((string)post('counter_name', ''));
            $status = (string)post('status', 'offline');
            $active = (int)post('is_active', 1);
            $services = array_map('intval', (array)post('services', []));
            $staffId = (int)post('staff_id', 0);

            if ($number === '' || $name === '') throw new RuntimeException('Counter number and name are required.');

            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE counters SET counter_number=?, counter_name=?, status=?, is_active=?, updated_at=? WHERE id=?');
                $stmt->execute([$number, $name, $status, $active, now(), $id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO counters (counter_number, counter_name, status, is_active) VALUES (?, ?, ?, ?)');
                $stmt->execute([$number, $name, $status, $active]);
                $id = (int)$pdo->lastInsertId();
            }

            $pdo->prepare('DELETE FROM counter_services WHERE counter_id=?')->execute([$id]);
            $ins = $pdo->prepare('INSERT INTO counter_services (counter_id, service_id) VALUES (?, ?)');
            foreach ($services as $sid) {
                if ($sid > 0) $ins->execute([$id, $sid]);
            }

            // Clear current assignment for this counter and staff
            $pdo->prepare('UPDATE staff_counter_assignments SET is_current=0 WHERE counter_id=? OR staff_id=?')->execute([$id, $staffId ?: 0]);
            if ($staffId > 0) {
                $pdo->prepare('UPDATE staff_counter_assignments SET is_current=0 WHERE staff_id=?')->execute([$staffId]);
                $pdo->prepare('INSERT INTO staff_counter_assignments (staff_id, counter_id, is_current) VALUES (?, ?, 1)')->execute([$staffId, $id]);
            }

            flash('success', 'Counter saved successfully.');
        } elseif ($action === 'delete') {
            $id = (int)post('id', 0);
            $pdo->prepare('DELETE FROM counters WHERE id=?')->execute([$id]);
            flash('success', 'Counter deleted.');
        }
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    redirect('admin/counters.php');
}

$services = $pdo->query('SELECT * FROM services ORDER BY id')->fetchAll();
$staffList = $pdo->query('SELECT s.id, s.staff_code, u.full_name FROM staff s JOIN users u ON u.id = s.user_id WHERE s.is_active=1')->fetchAll();
$counters = $pdo->query("SELECT c.*,
  (SELECT GROUP_CONCAT(sv.name SEPARATOR ', ') FROM counter_services cs JOIN services sv ON sv.id = cs.service_id WHERE cs.counter_id = c.id) AS service_names,
  (SELECT st.id FROM staff_counter_assignments sca JOIN staff st ON st.id = sca.staff_id WHERE sca.counter_id = c.id AND sca.is_current=1 LIMIT 1) AS assigned_staff_id,
  (SELECT u.full_name FROM staff_counter_assignments sca JOIN staff st ON st.id = sca.staff_id JOIN users u ON u.id = st.user_id WHERE sca.counter_id = c.id AND sca.is_current=1 LIMIT 1) AS staff_name,
  (SELECT COUNT(*) FROM queue_tickets qt WHERE qt.counter_id = c.id AND qt.queue_date=CURDATE() AND qt.status='waiting') AS waiting_count,
  (SELECT qt.queue_number FROM queue_tickets qt WHERE qt.counter_id=c.id AND qt.status='serving' AND qt.queue_date=CURDATE() LIMIT 1) AS serving_now
  FROM counters c ORDER BY c.counter_number")->fetchAll();

$editId = (int)get('edit', 0);
$edit = null;
$editServices = [];
if ($editId) {
    foreach ($counters as $c) if ((int)$c['id'] === $editId) $edit = $c;
    $stmt = $pdo->prepare('SELECT service_id FROM counter_services WHERE counter_id=?');
    $stmt->execute([$editId]);
    $editServices = array_map('intval', array_column($stmt->fetchAll(), 'service_id'));
}

$pageTitle = 'Counter Management';
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
        <div class="card-panel-header"><?= $edit ? 'Edit Counter' : 'Create Counter' ?></div>
        <div class="card-panel-body">
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
            <div class="mb-3">
              <label class="form-label">Counter Number</label>
              <input class="form-control" name="counter_number" required value="<?= e($edit['counter_number'] ?? '') ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Counter Name</label>
              <input class="form-control" name="counter_name" required value="<?= e($edit['counter_name'] ?? '') ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Status</label>
              <select class="form-select" name="status">
                <?php foreach (['available','serving','offline'] as $st): ?>
                  <option value="<?= $st ?>" <?= (($edit['status'] ?? '') === $st) ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Assign Staff</label>
              <select class="form-select" name="staff_id">
                <option value="0">-- None --</option>
                <?php foreach ($staffList as $s): ?>
                  <option value="<?= (int)$s['id'] ?>" <?= ((int)($edit['assigned_staff_id'] ?? 0) === (int)$s['id']) ? 'selected' : '' ?>><?= e($s['staff_code'] . ' - ' . $s['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Services</label>
              <?php foreach ($services as $sv): ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="services[]" value="<?= (int)$sv['id'] ?>" id="sv<?= (int)$sv['id'] ?>" <?= in_array((int)$sv['id'], $editServices, true) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="sv<?= (int)$sv['id'] ?>"><?= e($sv['name']) ?></label>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="mb-3">
              <label class="form-label">Active</label>
              <select class="form-select" name="is_active">
                <option value="1" <?= ((int)($edit['is_active'] ?? 1) === 1) ? 'selected' : '' ?>>Yes</option>
                <option value="0" <?= ((int)($edit['is_active'] ?? 1) === 0) ? 'selected' : '' ?>>No</option>
              </select>
            </div>
            <button class="btn btn-primary w-100" type="submit">Save Counter</button>
            <?php if ($edit): ?><a class="btn btn-outline-secondary w-100 mt-2" href="<?= e(url('admin/counters.php')) ?>">Cancel Edit</a><?php endif; ?>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="card-panel">
        <div class="card-panel-header">All Counters</div>
        <div class="card-panel-body p-0">
          <div class="table-responsive">
            <table class="table mb-0">
              <thead><tr><th>Counter</th><th>Status</th><th>Staff</th><th>Services</th><th>Serving</th><th>Waiting</th><th></th></tr></thead>
              <tbody>
              <?php foreach ($counters as $c): ?>
                <tr>
                  <td><strong><?= e($c['counter_number']) ?></strong><br><small><?= e($c['counter_name']) ?></small></td>
                  <td><?= status_badge($c['status']) ?><?= !(int)$c['is_active'] ? ' <span class="badge bg-dark">Inactive</span>' : '' ?></td>
                  <td><?= e($c['staff_name'] ?? '-') ?></td>
                  <td><small><?= e($c['service_names'] ?? '-') ?></small></td>
                  <td><?= e($c['serving_now'] ?? '-') ?></td>
                  <td><?= (int)$c['waiting_count'] ?></td>
                  <td class="text-nowrap">
                    <a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$c['id'] ?>"><i class="fa-solid fa-pen"></i></a>
                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this counter?')">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div></div></div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
