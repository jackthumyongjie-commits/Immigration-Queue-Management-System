<?php
require_once dirname(__DIR__) . '/includes/functions.php';
$user = current_user();
$error = '';
$preview = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)post('action', 'verify');
    $ref = trim((string)post('reference_no', ''));
    try {
        if ($action === 'verify') {
            $stmt = db()->prepare("SELECT a.*, u.full_name, u.ic_passport, u.phone, s.name AS service_name
                FROM appointments a
                JOIN users u ON u.id = a.user_id
                JOIN services s ON s.id = a.service_id
                WHERE UPPER(a.reference_no) = UPPER(?) LIMIT 1");
            $stmt->execute([$ref]);
            $preview = $stmt->fetch();
            if (!$preview) throw new RuntimeException('Appointment reference not found.');
        } elseif ($action === 'confirm') {
            // Office check-in validates reference/date/status; ownership not required at kiosk
            $result = check_in_appointment($ref, null);
            redirect('public/queue_ticket.php?ref=' . urlencode($result['ticket']['ticket_ref']));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'Office Check-in';
require dirname(__DIR__) . '/includes/header.php';
require dirname(__DIR__) . '/includes/public_header.php';
?>
<div class="container py-4" style="max-width:720px">
  <h3 class="page-title">Office Check-in</h3>
  <p class="page-subtitle">Enter your appointment reference to check in and receive a queue ticket.</p>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

  <?php if (!$preview): ?>
  <div class="card-panel">
    <div class="card-panel-header">Appointment Reference</div>
    <div class="card-panel-body">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="verify">
        <label class="form-label">Enter Appointment Reference</label>
        <input class="form-control form-control-lg mb-3" name="reference_no" required placeholder="e.g. APT-20260001" value="<?= e((string)post('reference_no','')) ?>">
        <button class="btn btn-primary w-100">Verify Appointment</button>
      </form>
    </div>
  </div>
  <?php else: ?>
  <div class="card-panel">
    <div class="card-panel-header">Confirm Visitor Identity</div>
    <div class="card-panel-body">
      <dl class="row">
        <dt class="col-4">Reference</dt><dd class="col-8"><?= e($preview['reference_no']) ?></dd>
        <dt class="col-4">Visitor</dt><dd class="col-8"><?= e($preview['full_name']) ?></dd>
        <dt class="col-4">IC / Passport</dt><dd class="col-8"><?= e($preview['ic_passport']) ?></dd>
        <dt class="col-4">Service</dt><dd class="col-8"><?= e($preview['service_name']) ?></dd>
        <dt class="col-4">Date</dt><dd class="col-8"><?= format_date($preview['appointment_date']) ?></dd>
        <dt class="col-4">Time</dt><dd class="col-8"><?= format_time($preview['appointment_time']) ?></dd>
        <dt class="col-4">Status</dt><dd class="col-8"><?= status_badge($preview['status']) ?></dd>
      </dl>
      <form method="post" class="d-grid gap-2">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="confirm">
        <input type="hidden" name="reference_no" value="<?= e($preview['reference_no']) ?>">
        <button class="btn btn-success btn-lg">Confirm Check-in & Generate Queue Ticket</button>
        <a class="btn btn-outline-secondary" href="<?= e(url('public/office_checkin.php')) ?>">Cancel</a>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
