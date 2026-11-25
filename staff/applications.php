<?php
require_once dirname(__DIR__) . '/includes/functions.php';
$user = require_login('staff');
$staff = get_staff_profile((int)$user['id']);
if (!$staff) {
    flash('danger', 'Staff profile not found. Contact administrator.');
    redirect('auth/logout.php');
}
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int)post('id');
    $action = post('action');
    try {
        $stmt = $pdo->prepare('SELECT * FROM applications WHERE id=? AND assigned_staff_id=?');
        $stmt->execute([$id, $staff['id']]);
        $app = $stmt->fetch();
        if (!$app) throw new RuntimeException('Application not found or not assigned to you.');

        if ($action === 'update') {
            $status = (string)post('status');
            // Staff can recommend but final approve/reject still allowed as processing update;
            // Admin retains full oversight. Staff can set under_review / additional_docs / pending and add recommendations.
            $allowed = ['pending','under_review','additional_docs','approved','rejected'];
            if (!in_array($status, $allowed, true)) throw new RuntimeException('Invalid status.');
            // For final decisions, mark reviewed
            $pdo->prepare('UPDATE applications SET status=?, updated_at=?, reviewed_by=IF(? IN ("approved","rejected"), ?, reviewed_by), reviewed_at=IF(? IN ("approved","rejected"), ?, reviewed_at) WHERE id=?')
                ->execute([$status, now(), $status, $user['id'], $status, now(), $id]);
            $remark = trim((string)post('remark', ''));
            if ($remark !== '') {
                $pdo->prepare('INSERT INTO application_remarks (application_id, user_id, remark, is_internal) VALUES (?,?,?,1)')
                    ->execute([$id, $user['id'], $remark]);
            }
            flash('success', 'Application updated.');
        }
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    redirect('staff/applications.php?view=' . $id);
}

$stmt = $pdo->prepare("SELECT a.*, s.name AS service_name FROM applications a JOIN services s ON s.id=a.service_id WHERE a.assigned_staff_id=? OR a.assigned_staff_id IS NULL ORDER BY a.created_at DESC");
$stmt->execute([$staff['id']]);
$apps = $stmt->fetchAll();

$viewId = (int)get('view', 0);
$view = null; $docs=[]; $remarks=[];
if ($viewId) {
    $stmt = $pdo->prepare("SELECT a.*, s.name AS service_name FROM applications a JOIN services s ON s.id=a.service_id WHERE a.id=?");
    $stmt->execute([$viewId]); $view = $stmt->fetch();
    if ($view) {
        $d=$pdo->prepare('SELECT * FROM application_documents WHERE application_id=?'); $d->execute([$viewId]); $docs=$d->fetchAll();
        $r=$pdo->prepare('SELECT ar.*, u.full_name FROM application_remarks ar JOIN users u ON u.id=ar.user_id WHERE application_id=? ORDER BY ar.created_at DESC'); $r->execute([$viewId]); $remarks=$r->fetchAll();
        if (empty($view['assigned_staff_id'])) {
            $pdo->prepare('UPDATE applications SET assigned_staff_id=? WHERE id=?')->execute([$staff['id'], $viewId]);
            $view['assigned_staff_id'] = $staff['id'];
        }
    }
}

$pageTitle = 'Application Processing';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-shell">
<?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
<div class="main-content">
<?php require dirname(__DIR__) . '/includes/navbar.php'; ?>
<div class="page-body">
<?php $flash = get_flash(); if ($flash): ?><div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
<div class="row g-3">
  <div class="col-lg-<?= $view?'6':'12' ?>">
    <div class="card-panel">
      <div class="card-panel-header">Assigned / Available Applications</div>
      <div class="card-panel-body p-0 table-responsive">
        <table class="table mb-0">
          <thead><tr><th>Reference</th><th>Applicant</th><th>Service</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($apps as $a): ?>
            <tr>
              <td><?= e($a['reference_no']) ?></td>
              <td><?= e($a['applicant_name']) ?></td>
              <td><?= e($a['service_name']) ?></td>
              <td><?= status_badge($a['status']) ?></td>
              <td><a class="btn btn-sm btn-outline-primary" href="?view=<?= (int)$a['id'] ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php if ($view): ?>
  <div class="col-lg-6">
    <div class="card-panel">
      <div class="card-panel-header">Process Application</div>
      <div class="card-panel-body">
        <h5><?= e($view['reference_no']) ?></h5>
        <p><?= e($view['applicant_name']) ?> · <?= e($view['applicant_ic']) ?></p>
        <p class="text-muted"><?= e($view['service_name']) ?></p>
        <p><?= e($view['purpose'] ?? '') ?></p>
        <h6>Documents</h6>
        <ul><?php foreach ($docs as $d): ?><li><a target="_blank" href="<?= e(url('uploads/applications/'.$d['file_name'])) ?>"><?= e($d['original_name']) ?></a></li><?php endforeach; ?></ul>
        <form method="post" class="mt-3">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" value="<?= (int)$view['id'] ?>">
          <label class="form-label">Processing Status</label>
          <select class="form-select mb-2" name="status">
            <?php foreach (['pending','under_review','additional_docs','approved','rejected'] as $st): ?>
              <option value="<?= $st ?>" <?= $view['status']===$st?'selected':'' ?>><?= ucwords(str_replace('_',' ',$st)) ?></option>
            <?php endforeach; ?>
          </select>
          <textarea class="form-control mb-2" name="remark" rows="3" placeholder="Internal remarks / recommendation"></textarea>
          <button class="btn btn-primary">Submit Update</button>
        </form>
        <hr>
        <?php foreach ($remarks as $r): ?>
          <div class="small border rounded p-2 mb-2"><strong><?= e($r['full_name']) ?></strong> · <?= format_datetime($r['created_at']) ?><div><?= e($r['remark']) ?></div></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
</div></div></div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
