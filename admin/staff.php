<?php
require_once dirname(__DIR__) . '/includes/functions.php';
$user = require_login('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    try {
        if ($action === 'save') {
            $staffId = (int)post('staff_id', 0);
            $fullName = trim((string)post('full_name', ''));
            $email = trim((string)post('email', ''));
            $phone = trim((string)post('phone', ''));
            $ic = trim((string)post('ic_passport', ''));
            $staffCode = trim((string)post('staff_code', ''));
            $department = trim((string)post('department', 'Immigration Counter'));
            $active = (int)post('is_active', 1);
            $working = (string)post('working_status', 'offline');
            $counterId = (int)post('counter_id', 0);
            $services = array_map('intval', (array)post('services', []));
            $password = (string)post('password', '');

            if ($fullName === '' || $email === '' || $staffCode === '') throw new RuntimeException('Name, email and staff ID are required.');

            if ($staffId > 0) {
                $stmt = $pdo->prepare('SELECT user_id FROM staff WHERE id=?');
                $stmt->execute([$staffId]);
                $uid = (int)$stmt->fetchColumn();
                $stmt = $pdo->prepare('UPDATE users SET full_name=?, email=?, phone=?, ic_passport=?, is_active=?, updated_at=? WHERE id=?');
                $stmt->execute([$fullName, $email, $phone, $ic, $active, now(), $uid]);
                if ($password !== '') {
                    $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), $uid]);
                }
                $pdo->prepare('UPDATE staff SET staff_code=?, department=?, working_status=?, is_active=?, updated_at=? WHERE id=?')
                    ->execute([$staffCode, $department, $working, $active, now(), $staffId]);
            } else {
                if (strlen($password) < 6) throw new RuntimeException('Password must be at least 6 characters for new staff.');
                $pdo->prepare('INSERT INTO users (full_name, ic_passport, email, phone, password, role, is_active) VALUES (?,?,?,?,?,"staff",?)')
                    ->execute([$fullName, $ic ?: $staffCode, $email, $phone, password_hash($password, PASSWORD_DEFAULT), $active]);
                $uid = (int)$pdo->lastInsertId();
                $pdo->prepare('INSERT INTO staff (user_id, staff_code, department, working_status, is_active) VALUES (?,?,?,?,?)')
                    ->execute([$uid, $staffCode, $department, $working, $active]);
                $staffId = (int)$pdo->lastInsertId();
            }

            $pdo->prepare('UPDATE staff_counter_assignments SET is_current=0 WHERE staff_id=?')->execute([$staffId]);
            if ($counterId > 0) {
                $pdo->prepare('UPDATE staff_counter_assignments SET is_current=0 WHERE counter_id=?')->execute([$counterId]);
                $pdo->prepare('INSERT INTO staff_counter_assignments (staff_id, counter_id, is_current) VALUES (?,?,1)')->execute([$staffId, $counterId]);
            }

            $pdo->prepare('DELETE FROM staff_service_permissions WHERE staff_id=?')->execute([$staffId]);
            $ins = $pdo->prepare('INSERT INTO staff_service_permissions (staff_id, service_id) VALUES (?,?)');
            foreach ($services as $sid) if ($sid > 0) $ins->execute([$staffId, $sid]);

            flash('success', 'Staff account saved.');
        } elseif ($action === 'delete') {
            $staffId = (int)post('staff_id');
            $stmt = $pdo->prepare('SELECT user_id FROM staff WHERE id=?');
            $stmt->execute([$staffId]);
            $uid = (int)$stmt->fetchColumn();
            if ($uid) $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$uid]);
            flash('success', 'Staff deleted.');
        }
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    redirect('admin/staff.php');
}

$services = $pdo->query('SELECT * FROM services ORDER BY id')->fetchAll();
$counters = $pdo->query('SELECT * FROM counters WHERE is_active=1 ORDER BY counter_number')->fetchAll();
$staffRows = $pdo->query("SELECT s.*, u.full_name, u.email, u.phone, u.ic_passport, u.is_active AS user_active,
  c.id AS counter_id, c.counter_number,
  (SELECT GROUP_CONCAT(sv.name SEPARATOR ', ') FROM staff_service_permissions ssp JOIN services sv ON sv.id=ssp.service_id WHERE ssp.staff_id=s.id) AS service_names
  FROM staff s
  JOIN users u ON u.id = s.user_id
  LEFT JOIN staff_counter_assignments sca ON sca.staff_id = s.id AND sca.is_current=1
  LEFT JOIN counters c ON c.id = sca.counter_id
  ORDER BY s.staff_code")->fetchAll();

$editId = (int)get('edit', 0);
$edit = null; $editServices = [];
if ($editId) {
    foreach ($staffRows as $r) if ((int)$r['id'] === $editId) $edit = $r;
    $stmt = $pdo->prepare('SELECT service_id FROM staff_service_permissions WHERE staff_id=?');
    $stmt->execute([$editId]);
    $editServices = array_map('intval', array_column($stmt->fetchAll(), 'service_id'));
}

$logs = $pdo->query("SELECT al.*, u.full_name FROM activity_logs al LEFT JOIN users u ON u.id=al.user_id WHERE u.role='staff' OR al.module IN ('staff','counter') ORDER BY al.created_at DESC LIMIT 20")->fetchAll();

$pageTitle = 'Staff Management';
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
      <div class="card-panel-header"><?= $edit ? 'Edit Staff' : 'Register Staff' ?></div>
      <div class="card-panel-body">
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="save">
          <input type="hidden" name="staff_id" value="<?= (int)($edit['id'] ?? 0) ?>">
          <div class="mb-2"><label class="form-label">Full Name</label><input class="form-control" name="full_name" required value="<?= e($edit['full_name'] ?? '') ?>"></div>
          <div class="mb-2"><label class="form-label">Staff ID</label><input class="form-control" name="staff_code" required value="<?= e($edit['staff_code'] ?? '') ?>"></div>
          <div class="mb-2"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required value="<?= e($edit['email'] ?? '') ?>"></div>
          <div class="mb-2"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= e($edit['phone'] ?? '') ?>"></div>
          <div class="mb-2"><label class="form-label">IC / Passport</label><input class="form-control" name="ic_passport" value="<?= e($edit['ic_passport'] ?? '') ?>"></div>
          <div class="mb-2"><label class="form-label">Department</label><input class="form-control" name="department" value="<?= e($edit['department'] ?? 'Immigration Counter') ?>"></div>
          <div class="mb-2"><label class="form-label">Password <?= $edit ? '(leave blank to keep)' : '' ?></label><input type="password" class="form-control" name="password" <?= $edit ? '' : 'required' ?>></div>
          <div class="mb-2"><label class="form-label">Assign Counter</label>
            <select class="form-select" name="counter_id"><option value="0">-- None --</option>
              <?php foreach ($counters as $c): ?><option value="<?= (int)$c['id'] ?>" <?= ((int)($edit['counter_id'] ?? 0)===(int)$c['id'])?'selected':'' ?>><?= e($c['counter_number'].' - '.$c['counter_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2"><label class="form-label">Service Permissions</label>
            <?php foreach ($services as $s): ?>
              <div class="form-check"><input class="form-check-input" type="checkbox" name="services[]" value="<?= (int)$s['id'] ?>" <?= in_array((int)$s['id'], $editServices, true)?'checked':'' ?>><label class="form-check-label"><?= e($s['name']) ?></label></div>
            <?php endforeach; ?>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6"><label class="form-label">Working</label>
              <select class="form-select" name="working_status"><?php foreach (['online','offline','break'] as $w): ?><option value="<?= $w ?>" <?= (($edit['working_status'] ?? '')===$w)?'selected':'' ?>><?= ucfirst($w) ?></option><?php endforeach; ?></select>
            </div>
            <div class="col-6"><label class="form-label">Active</label>
              <select class="form-select" name="is_active"><option value="1" <?= ((int)($edit['is_active'] ?? 1)===1)?'selected':'' ?>>Yes</option><option value="0" <?= ((int)($edit['is_active'] ?? 1)===0)?'selected':'' ?>>No</option></select>
            </div>
          </div>
          <button class="btn btn-primary w-100">Save Staff</button>
          <?php if ($edit): ?><a href="<?= e(url('admin/staff.php')) ?>" class="btn btn-outline-secondary w-100 mt-2">Cancel</a><?php endif; ?>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card-panel mb-3">
      <div class="card-panel-header">Staff Accounts</div>
      <div class="card-panel-body p-0 table-responsive">
        <table class="table mb-0">
          <thead><tr><th>Staff</th><th>Counter</th><th>Services</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($staffRows as $s): ?>
            <tr>
              <td><strong><?= e($s['staff_code']) ?></strong><br><?= e($s['full_name']) ?><br><small class="text-muted"><?= e($s['email']) ?></small></td>
              <td><?= e($s['counter_number'] ?? '-') ?></td>
              <td><small><?= e($s['service_names'] ?? '-') ?></small></td>
              <td><?= status_badge($s['working_status']) ?><?= !(int)$s['is_active'] ? ' <span class="badge bg-dark">Inactive</span>' : '' ?></td>
              <td class="text-nowrap">
                <a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$s['id'] ?>"><i class="fa-solid fa-pen"></i></a>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete staff account?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="staff_id" value="<?= (int)$s['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button></form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card-panel">
      <div class="card-panel-header">Staff Activity History</div>
      <div class="card-panel-body p-0">
        <ul class="list-group list-group-flush">
          <?php foreach ($logs as $l): ?>
            <li class="list-group-item small d-flex justify-content-between">
              <span><strong><?= e($l['full_name'] ?? 'System') ?></strong> · <?= e($l['action']) ?> · <?= e($l['details'] ?? '') ?></span>
              <span class="text-muted"><?= format_datetime($l['created_at']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
</div>
</div></div></div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
