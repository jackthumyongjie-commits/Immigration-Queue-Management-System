<?php
require_once dirname(__DIR__) . '/includes/functions.php';
$user = require_login('public');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    try {
        if ($action === 'book') {
            $serviceId = (int)post('service_id');
            $slotId = (int)post('slot_id');
            $remarks = trim((string)post('remarks', ''));
            $reminder = (string)post('reminder_option', 'email');
            $appt = book_appointment((int)$user['id'], $serviceId, $slotId, $remarks, $reminder);
            flash('success', 'Appointment confirmed. Reference: ' . $appt['reference_no']);
            redirect('public/appointments.php?confirmed=' . urlencode($appt['reference_no']));
        } elseif ($action === 'cancel') {
            $id = (int)post('id');
            $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id=? AND user_id=?");
            $stmt->execute([$id, $user['id']]);
            $appt = $stmt->fetch();
            if (!$appt) throw new RuntimeException('Appointment not found.');
            if (!in_array($appt['status'], ['pending','confirmed'], true)) throw new RuntimeException('This appointment cannot be cancelled.');
            $pdo->prepare("UPDATE appointments SET status='cancelled', updated_at=? WHERE id=?")->execute([now(), $id]);
            $pdo->prepare('UPDATE appointment_slots SET booked_count = GREATEST(booked_count-1,0) WHERE id=?')->execute([$appt['slot_id']]);
            flash('success', 'Appointment cancelled.');
        }
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    redirect('public/appointments.php');
}

$services = $pdo->query('SELECT * FROM services WHERE is_active=1 ORDER BY id')->fetchAll();
$step = (int)get('step', 1);
$serviceId = (int)get('service_id', 0);
$date = (string)get('date', '');
$slotId = (int)get('slot_id', 0);
$confirmed = (string)get('confirmed', '');

$dates = [];
$slots = [];
if ($serviceId > 0) {
    $stmt = $pdo->prepare("SELECT DISTINCT slot_date FROM appointment_slots WHERE service_id=? AND slot_date >= CURDATE() AND is_available=1 AND booked_count < max_capacity ORDER BY slot_date LIMIT 14");
    $stmt->execute([$serviceId]);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
if ($serviceId > 0 && $date !== '') {
    $stmt = $pdo->prepare("SELECT * FROM appointment_slots WHERE service_id=? AND slot_date=? AND is_available=1 AND booked_count < max_capacity ORDER BY start_time");
    $stmt->execute([$serviceId, $date]);
    $slots = $stmt->fetchAll();
}

$myAppts = $pdo->prepare('SELECT a.*, s.name AS service_name FROM appointments a JOIN services s ON s.id=a.service_id WHERE a.user_id=? ORDER BY a.appointment_date DESC');
$myAppts->execute([$user['id']]);
$appointments = $myAppts->fetchAll();

$selectedService = null;
foreach ($services as $s) if ((int)$s['id'] === $serviceId) $selectedService = $s;

$pageTitle = 'Online Appointment Booking';
require dirname(__DIR__) . '/includes/header.php';
require dirname(__DIR__) . '/includes/public_header.php';
?>
<div class="container py-4">
  <h3 class="page-title">Online Appointment Booking</h3>
  <p class="page-subtitle">Book your immigration service appointment in a few steps.</p>

  <?php if ($confirmed): 
    $stmt = $pdo->prepare('SELECT a.*, s.name AS service_name FROM appointments a JOIN services s ON s.id=a.service_id WHERE a.reference_no=? AND a.user_id=?');
    $stmt->execute([$confirmed, $user['id']]);
    $c = $stmt->fetch();
  ?>
    <div class="alert alert-success">
      <h5>Appointment Confirmed</h5>
      <?php if ($c): ?>
        <p class="mb-1">Reference: <strong><?= e($c['reference_no']) ?></strong></p>
        <p class="mb-1">Service: <?= e($c['service_name']) ?></p>
        <p class="mb-0">Date/Time: <?= format_date($c['appointment_date']) ?> · <?= format_time($c['appointment_time']) ?></p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="card-panel mb-4">
    <div class="card-panel-header">Booking Wizard</div>
    <div class="card-panel-body">
      <div class="mb-3">
        <span class="badge <?= $serviceId?'bg-primary':'bg-secondary' ?>">1 Service</span>
        <span class="badge <?= $date?'bg-primary':'bg-secondary' ?>">2 Date</span>
        <span class="badge <?= $slotId?'bg-primary':'bg-secondary' ?>">3 Time</span>
        <span class="badge bg-secondary">4 Confirm</span>
      </div>

      <?php if (!$serviceId): ?>
        <h6>Step 1: Select Immigration Service</h6>
        <div class="row g-3">
          <?php foreach ($services as $s): ?>
            <div class="col-md-6"><a class="btn btn-outline-primary w-100 text-start py-3" href="?service_id=<?= (int)$s['id'] ?>"><i class="fa-solid <?= e($s['icon']) ?> me-2"></i><?= e($s['name']) ?></a></div>
          <?php endforeach; ?>
        </div>
      <?php elseif (!$date): ?>
        <h6>Step 2: Select Available Appointment Date</h6>
        <p class="text-muted">Service: <strong><?= e($selectedService['name'] ?? '') ?></strong></p>
        <div class="row g-2">
          <?php foreach ($dates as $d): ?>
            <div class="col-6 col-md-3"><a class="btn btn-outline-primary w-100" href="?service_id=<?= $serviceId ?>&date=<?= e($d) ?>"><?= format_date($d) ?></a></div>
          <?php endforeach; ?>
          <?php if (!$dates): ?><p class="text-muted">No available dates.</p><?php endif; ?>
        </div>
        <a class="btn btn-link mt-3" href="?">Back</a>
      <?php elseif (!$slotId): ?>
        <h6>Step 3: Select Available Time Slot</h6>
        <p class="text-muted"><?= e($selectedService['name'] ?? '') ?> · <?= format_date($date) ?></p>
        <div class="row g-2">
          <?php foreach ($slots as $sl): ?>
            <div class="col-6 col-md-3">
              <a class="btn btn-outline-primary w-100" href="?service_id=<?= $serviceId ?>&date=<?= e($date) ?>&slot_id=<?= (int)$sl['id'] ?>">
                <?= format_time($sl['start_time']) ?><br><small><?= (int)$sl['max_capacity'] - (int)$sl['booked_count'] ?> left</small>
              </a>
            </div>
          <?php endforeach; ?>
          <?php if (!$slots): ?><p class="text-muted">No slots available for this date.</p><?php endif; ?>
        </div>
        <a class="btn btn-link mt-3" href="?service_id=<?= $serviceId ?>">Back</a>
      <?php else:
        $slot = null;
        foreach ($slots as $sl) if ((int)$sl['id'] === $slotId) $slot = $sl;
        if (!$slot) {
          $stmt = $pdo->prepare('SELECT * FROM appointment_slots WHERE id=?'); $stmt->execute([$slotId]); $slot = $stmt->fetch();
        }
      ?>
        <h6>Step 4–7: Remarks, Reminder & Confirm</h6>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="book">
          <input type="hidden" name="service_id" value="<?= $serviceId ?>">
          <input type="hidden" name="slot_id" value="<?= $slotId ?>">
          <div class="alert alert-light border">
            <div><strong><?= e($selectedService['name'] ?? '') ?></strong></div>
            <div><?= format_date($slot['slot_date'] ?? $date) ?> · <?= format_time($slot['start_time'] ?? '') ?> - <?= format_time($slot['end_time'] ?? '') ?></div>
          </div>
          <div class="mb-3">
            <label class="form-label">Step 5: Additional Remarks / Purpose</label>
            <textarea class="form-control" name="remarks" rows="3" placeholder="Optional remarks"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Step 6: Appointment Reminder</label>
            <select class="form-select" name="reminder_option">
              <option value="email">Email</option>
              <option value="sms">SMS</option>
              <option value="both">Email & SMS</option>
              <option value="none">None</option>
            </select>
          </div>
          <button class="btn btn-primary" type="submit">Step 7: Confirm Appointment</button>
          <a class="btn btn-link" href="?service_id=<?= $serviceId ?>&date=<?= e($date) ?>">Back</a>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="card-panel">
    <div class="card-panel-header">My Appointments</div>
    <div class="card-panel-body p-0 table-responsive">
      <table class="table mb-0">
        <thead><tr><th>Reference</th><th>Service</th><th>Date</th><th>Time</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($appointments as $a): ?>
          <tr>
            <td><?= e($a['reference_no']) ?></td>
            <td><?= e($a['service_name']) ?></td>
            <td><?= format_date($a['appointment_date']) ?></td>
            <td><?= format_time($a['appointment_time']) ?></td>
            <td><?= status_badge($a['status']) ?></td>
            <td>
              <?php if (in_array($a['status'], ['pending','confirmed'], true)): ?>
              <form method="post" class="d-inline" onsubmit="return confirm('Cancel this appointment?')">
                <?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <button class="btn btn-sm btn-outline-danger">Cancel</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
