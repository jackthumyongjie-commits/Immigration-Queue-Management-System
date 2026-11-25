<?php
require_once dirname(__DIR__) . '/includes/functions.php';
$user = current_user(); // kiosk can be browsed without login, but booking may require it

$services = db()->query('SELECT s.*,
  (SELECT COUNT(*) FROM queue_tickets qt WHERE qt.service_id=s.id AND qt.queue_date=CURDATE() AND qt.status="waiting") AS waiting_count
  FROM services s WHERE s.is_active=1 ORDER BY s.id')->fetchAll();

$pageTitle = 'Self-Service Kiosk';
require dirname(__DIR__) . '/includes/header.php';
require dirname(__DIR__) . '/includes/public_header.php';
?>
<section class="hero-strip">
  <div class="container text-center">
    <h1 class="h3 mb-2">Immigration Department Self-Service Kiosk</h1>
    <p class="mb-0 opacity-75">Select a service to book an appointment or join the walk-in queue</p>
  </div>
</section>
<div class="container pb-5">
  <div class="row g-4">
    <?php foreach ($services as $s):
      $est = ((int)$s['waiting_count'] + 1) * max(5, (int)ceil((int)$s['estimated_duration'] * 0.7));
    ?>
    <div class="col-md-6 col-lg-4">
      <div class="service-card">
        <div class="icon-wrap"><i class="fa-solid <?= e($s['icon']) ?>"></i></div>
        <h5><?= e($s['name']) ?></h5>
        <p class="text-muted small"><?= e($s['description']) ?></p>
        <div class="d-flex justify-content-between align-items-center mb-3">
          <span><?= (int)$s['is_active'] ? status_badge('available') : status_badge('offline') ?></span>
          <small class="text-muted">Est. wait ~ <?= $est ?> min · <?= (int)$s['waiting_count'] ?> waiting</small>
        </div>
        <div class="d-grid gap-2">
          <a class="btn btn-primary" href="<?= e(url('public/appointments.php')) ?>?service_id=<?= (int)$s['id'] ?>">Select Service · Book Appointment</a>
          <a class="btn btn-outline-primary" href="<?= e(url('public/walk_in.php')) ?>?service_id=<?= (int)$s['id'] ?>">Join Walk-in Queue</a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
