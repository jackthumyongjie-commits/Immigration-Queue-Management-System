<?php
require_once dirname(__DIR__) . '/includes/functions.php';
$user = require_login('public');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        $serviceId = (int)post('service_id');
        $purpose = trim((string)post('purpose', ''));
        if ($serviceId <= 0) throw new RuntimeException('Please select a service type.');
        if ($purpose === '') throw new RuntimeException('Please enter the application purpose.');
        $pdo->beginTransaction();
        $ref = generate_ref('APP');
        $stmt = $pdo->prepare('INSERT INTO applications (reference_no, user_id, service_id, applicant_name, applicant_ic, applicant_email, applicant_phone, purpose, status) VALUES (?,?,?,?,?,?,?,?,"pending")');
        $stmt->execute([$ref, $user['id'], $serviceId, $user['full_name'], $user['ic_passport'], $user['email'], $user['phone'], $purpose]);
        $appId = (int)$pdo->lastInsertId();
        if (!empty($_FILES['document']['name'])) {
            handle_secure_upload($_FILES['document'], $appId);
        }
        $pdo->commit();
        flash('success', 'Application submitted. Reference: ' . $ref);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('danger', $e->getMessage());
    }
    redirect('public/applications.php');
}

$services = $pdo->query('SELECT * FROM services WHERE is_active=1')->fetchAll();
$stmt = $pdo->prepare('SELECT a.*, s.name AS service_name FROM applications a JOIN services s ON s.id=a.service_id WHERE a.user_id=? ORDER BY a.created_at DESC');
$stmt->execute([$user['id']]);
$apps = $stmt->fetchAll();

$pageTitle = 'My Applications';
require dirname(__DIR__) . '/includes/header.php';
require dirname(__DIR__) . '/includes/public_header.php';
?>
<div class="container py-4">
  <h3 class="page-title">Immigration Applications</h3>
  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card-panel">
        <div class="card-panel-header">Submit Application</div>
        <div class="card-panel-body">
          <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="mb-2">
              <label class="form-label">Application Type</label>
              <select class="form-select" name="service_id" required>
                <option value="">-- Select --</option>
                <?php foreach ($services as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="mb-2"><label class="form-label">Purpose</label><textarea class="form-control" name="purpose" rows="3" required></textarea></div>
            <div class="mb-3"><label class="form-label">Upload Document (PDF/JPG/PNG)</label><input type="file" class="form-control" name="document" accept=".pdf,.jpg,.jpeg,.png"></div>
            <button class="btn btn-primary w-100">Submit Application</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="card-panel">
        <div class="card-panel-header">My Application Records</div>
        <div class="card-panel-body p-0 table-responsive">
          <table class="table mb-0">
            <thead><tr><th>Reference</th><th>Type</th><th>Status</th><th>Submitted</th></tr></thead>
            <tbody>
            <?php foreach ($apps as $a): ?>
              <tr>
                <td><?= e($a['reference_no']) ?></td>
                <td><?= e($a['service_name']) ?></td>
                <td><?= status_badge($a['status']) ?></td>
                <td><?= format_datetime($a['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
