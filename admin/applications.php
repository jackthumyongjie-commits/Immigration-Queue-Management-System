<?php
require_once dirname(__DIR__) . '/includes/functions.php';
$user = require_login('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int)post('id', 0);
    $action = post('action');
    try {
        $app = $pdo->prepare('SELECT * FROM applications WHERE id=?');
        $app->execute([$id]);
        $application = $app->fetch();
        if (!$application) throw new RuntimeException('Application not found.');

        if ($action === 'update_status') {
            $status = (string)post('status');
            $allowed = ['pending','under_review','approved','rejected','additional_docs'];
            if (!in_array($status, $allowed, true)) throw new RuntimeException('Invalid status.');
            $stmt = $pdo->prepare('UPDATE applications SET status=?, reviewed_by=?, reviewed_at=?, updated_at=? WHERE id=?');
            $stmt->execute([$status, $user['id'], now(), now(), $id]);
            $remark = trim((string)post('remark', ''));
            if ($remark !== '') {
                $pdo->prepare('INSERT INTO application_remarks (application_id, user_id, remark, is_internal) VALUES (?,?,?,1)')->execute([$id, $user['id'], $remark]);
            }
            flash('success', 'Application status updated.');
        } elseif ($action === 'add_remark') {
            $remark = trim((string)post('remark', ''));
            if ($remark === '') throw new RuntimeException('Remark required.');
            $pdo->prepare('INSERT INTO application_remarks (application_id, user_id, remark, is_internal) VALUES (?,?,?,1)')->execute([$id, $user['id'], $remark]);
            flash('success', 'Remark added.');
        }
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    redirect('admin/applications.php?view=' . $id);
}

$q = trim((string)get('q', ''));
$type = (int)get('service_id', 0);
$status = (string)get('status', '');
$sql = "SELECT a.*, s.name AS service_name FROM applications a JOIN services s ON s.id = a.service_id WHERE 1=1";
$params = [];
if ($q !== '') { $sql .= ' AND (a.reference_no LIKE ? OR a.applicant_name LIKE ? OR a.applicant_ic LIKE ?)'; $like='%'.$q.'%'; array_push($params,$like,$like,$like); }
if ($type > 0) { $sql .= ' AND a.service_id=?'; $params[]=$type; }
if ($status !== '') { $sql .= ' AND a.status=?'; $params[]=$status; }
$sql .= ' ORDER BY a.created_at DESC';
$stmt = $pdo->prepare($sql); $stmt->execute($params); $apps = $stmt->fetchAll();
$services = $pdo->query('SELECT id,name FROM services')->fetchAll();

$viewId = (int)get('view', 0);
$view = null; $docs = []; $remarks = [];
if ($viewId) {
    $stmt = $pdo->prepare("SELECT a.*, s.name AS service_name, ap.reference_no AS appointment_ref FROM applications a JOIN services s ON s.id=a.service_id LEFT JOIN appointments ap ON ap.id=a.appointment_id WHERE a.id=?");
    $stmt->execute([$viewId]); $view = $stmt->fetch();
    if ($view) {
        $d = $pdo->prepare('SELECT * FROM application_documents WHERE application_id=?'); $d->execute([$viewId]); $docs = $d->fetchAll();
        $r = $pdo->prepare('SELECT ar.*, u.full_name FROM application_remarks ar JOIN users u ON u.id=ar.user_id WHERE application_id=? ORDER BY ar.created_at DESC'); $r->execute([$viewId]); $remarks = $r->fetchAll();
    }
}

$pageTitle = 'Application Records & Approvals';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-shell">
<?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
<div class="main-content">
<?php require dirname(__DIR__) . '/includes/navbar.php'; ?>
<div class="page-body">
<?php $flash = get_flash(); if ($flash): ?><div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>

<div class="card-panel">
  <div class="card-panel-header">Search Applications</div>
  <div class="card-panel-body">
    <form class="row g-2" method="get">
      <div class="col-md-4"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Reference / name / IC"></div>
      <div class="col-md-3"><select class="form-select" name="service_id"><option value="0">All Types</option><?php foreach ($services as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $type===(int)$s['id']?'selected':'' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-3"><select class="form-select" name="status"><option value="">All Status</option><?php foreach (['pending','under_review','approved','rejected','additional_docs'] as $st): ?><option value="<?= $st ?>" <?= $status===$st?'selected':'' ?>><?= ucwords(str_replace('_',' ',$st)) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
    </form>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-<?= $view ? '7' : '12' ?>">
    <div class="card-panel">
      <div class="card-panel-header">Applications</div>
      <div class="card-panel-body p-0 table-responsive">
        <table class="table mb-0">
          <thead><tr><th>Reference</th><th>Applicant</th><th>Type</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($apps as $a): ?>
            <tr>
              <td><?= e($a['reference_no']) ?></td>
              <td><?= e($a['applicant_name']) ?><br><small><?= e($a['applicant_ic']) ?></small></td>
              <td><?= e($a['service_name']) ?></td>
              <td><?= status_badge($a['status']) ?></td>
              <td><?= format_datetime($a['created_at']) ?></td>
              <td><a class="btn btn-sm btn-outline-primary" href="?view=<?= (int)$a['id'] ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php if ($view): ?>
  <div class="col-lg-5">
    <div class="card-panel">
      <div class="card-panel-header">Application Review</div>
      <div class="card-panel-body">
        <h5><?= e($view['reference_no']) ?></h5>
        <p class="mb-1"><?= e($view['applicant_name']) ?> · <?= e($view['applicant_ic']) ?></p>
        <p class="text-muted"><?= e($view['service_name']) ?> · <?= status_badge($view['status']) ?></p>
        <p><?= e($view['purpose'] ?? '') ?></p>
        <p class="small">Appointment: <?= e($view['appointment_ref'] ?? '-') ?></p>

        <h6 class="mt-3">Documents</h6>
        <?php if (!$docs): ?><p class="text-muted small">No documents uploaded.</p><?php endif; ?>
        <ul class="list-group mb-3">
          <?php foreach ($docs as $d): ?>
            <li class="list-group-item d-flex justify-content-between">
              <span><?= e($d['original_name']) ?></span>
              <a target="_blank" href="<?= e(url('uploads/applications/' . $d['file_name'])) ?>">View</a>
            </li>
          <?php endforeach; ?>
        </ul>

        <form method="post" class="mb-3">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_status">
          <input type="hidden" name="id" value="<?= (int)$view['id'] ?>">
          <label class="form-label">Update Status</label>
          <select class="form-select mb-2" name="status">
            <?php foreach (['pending','under_review','approved','rejected','additional_docs'] as $st): ?>
              <option value="<?= $st ?>" <?= $view['status']===$st?'selected':'' ?>><?= ucwords(str_replace('_',' ',$st)) ?></option>
            <?php endforeach; ?>
          </select>
          <textarea class="form-control mb-2" name="remark" rows="2" placeholder="Decision remark (optional)"></textarea>
          <div class="d-grid gap-2">
            <button type="submit" class="btn btn-success" name="status" value="approved">Approve</button>
            <button type="submit" class="btn btn-danger" name="status" value="rejected">Reject</button>
            <button type="submit" class="btn btn-warning" name="status" value="additional_docs">Request Additional Documents</button>
            <button type="submit" class="btn btn-primary">Save Status</button>
          </div>
        </form>

        <h6>Remarks</h6>
        <?php foreach ($remarks as $r): ?>
          <div class="border rounded p-2 mb-2 small">
            <strong><?= e($r['full_name']) ?></strong> · <?= format_datetime($r['created_at']) ?>
            <div><?= e($r['remark']) ?></div>
          </div>
        <?php endforeach; ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_remark">
          <input type="hidden" name="id" value="<?= (int)$view['id'] ?>">
          <textarea class="form-control mb-2" name="remark" required placeholder="Add internal remark"></textarea>
          <button class="btn btn-outline-secondary btn-sm">Add Remark</button>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
</div></div></div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
