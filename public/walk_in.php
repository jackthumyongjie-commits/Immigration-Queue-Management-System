<?php
require_once dirname(__DIR__) . '/includes/functions.php';
$user = current_user(); // allow guest walk-in
$pdo = db();
$services = $pdo->query('SELECT * FROM services WHERE is_active=1 ORDER BY id')->fetchAll();
$preselect = (int)get('service_id', 0);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        $serviceId = (int)post('service_id');
        $name = trim((string)post('visitor_name', ''));
        $ic = trim((string)post('visitor_ic', ''));
        $phone = trim((string)post('visitor_phone', ''));
        $email = trim((string)post('visitor_email', ''));

        if ($serviceId <= 0) throw new RuntimeException('Please select a service.');
        if ($name === '') throw new RuntimeException('Visitor name is required.');
        if ($ic === '') throw new RuntimeException('IC / Passport is required.');

        if ($user && $user['role'] === 'public') {
            if ($name === '') $name = $user['full_name'];
            if ($ic === '') $ic = $user['ic_passport'];
            if ($phone === '') $phone = (string)$user['phone'];
            if ($email === '') $email = $user['email'];
        }

        $ticket = create_queue_ticket([
            'visitor_name' => $name,
            'visitor_ic' => $ic,
            'visitor_phone' => $phone,
            'visitor_email' => $email,
            'user_id' => ($user && $user['role'] === 'public') ? (int)$user['id'] : null,
            'service_id' => $serviceId,
            'ticket_type' => 'walk_in',
            'performed_by' => $user['id'] ?? null,
            'notes' => 'Walk-in queue registration',
        ]);
        redirect('public/queue_ticket.php?ref=' . urlencode($ticket['ticket_ref']));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'Walk-in Queue Registration';
require dirname(__DIR__) . '/includes/header.php';
require dirname(__DIR__) . '/includes/public_header.php';
?>
<div class="container py-4" style="max-width:720px">
  <h3 class="page-title">Walk-in Queue Registration</h3>
  <p class="page-subtitle">Obtain a queue ticket without an appointment.</p>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

  <div class="card-panel">
    <div class="card-panel-header">Register for Queue</div>
    <div class="card-panel-body">
      <form method="post">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label">1. Select Immigration Service</label>
          <select class="form-select" name="service_id" required>
            <option value="">-- Choose Service --</option>
            <?php foreach ($services as $s): ?>
              <option value="<?= (int)$s['id'] ?>" <?= $preselect===(int)$s['id']?'selected':'' ?>><?= e($s['name']) ?> (<?= e($s['queue_prefix']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">2. Visitor Information</label>
          <input class="form-control mb-2" name="visitor_name" required placeholder="Full Name" value="<?= e($user['full_name'] ?? (string)post('visitor_name','')) ?>">
          <input class="form-control mb-2" name="visitor_ic" required placeholder="IC / Passport Number" value="<?= e($user['ic_passport'] ?? (string)post('visitor_ic','')) ?>">
          <input class="form-control mb-2" name="visitor_phone" placeholder="Phone Number" value="<?= e($user['phone'] ?? (string)post('visitor_phone','')) ?>">
          <input class="form-control" type="email" name="visitor_email" placeholder="Email (optional)" value="<?= e($user['email'] ?? (string)post('visitor_email','')) ?>">
        </div>
        <button class="btn btn-primary w-100 py-2" type="submit">3. Confirm Registration & Generate Queue Number</button>
      </form>
    </div>
  </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
