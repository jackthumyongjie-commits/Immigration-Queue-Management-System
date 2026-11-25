<?php
require_once dirname(__DIR__) . '/includes/functions.php';
$ref = trim((string)get('ref', ''));
$ticket = $ref !== '' ? get_ticket_by_ref($ref) : null;
if (!$ticket) {
    flash('danger', 'Queue ticket not found.');
    redirect('index.php');
}
$ahead = people_ahead($ticket);

$pageTitle = 'Queue Ticket';
require dirname(__DIR__) . '/includes/header.php';
require dirname(__DIR__) . '/includes/public_header.php';
?>
<div class="container py-4">
  <div class="ticket-box printable">
    <div class="text-center mb-3">
      <div class="text-muted small">Immigration Department</div>
      <h5 class="mb-0">Queue Ticket</h5>
    </div>
    <div class="text-center mb-4">
      <div class="queue-number-display"><?= e($ticket['queue_number']) ?></div>
      <div class="mt-2"><?= status_badge($ticket['status']) ?></div>
    </div>
    <table class="table table-borderless mb-0">
      <tr><th>Ticket Ref</th><td><?= e($ticket['ticket_ref']) ?></td></tr>
      <tr><th>Visitor Name</th><td><?= e($ticket['visitor_name']) ?></td></tr>
      <tr><th>Service Type</th><td><?= e($ticket['service_name']) ?></td></tr>
      <tr><th>Registration Date</th><td><?= format_date($ticket['queue_date']) ?></td></tr>
      <tr><th>Registration Time</th><td><?= format_time($ticket['created_at']) ?></td></tr>
      <tr><th>Estimated Waiting</th><td><?= (int)($ticket['estimated_wait'] ?? ($ahead * 10)) ?> minutes</td></tr>
      <tr><th>People Ahead</th><td><?= $ahead ?></td></tr>
      <tr><th>Assigned Counter</th><td><?= e($ticket['counter_number'] ?? 'Pending assignment') ?></td></tr>
      <?php if (!empty($ticket['appointment_ref'])): ?>
      <tr><th>Appointment</th><td><?= e($ticket['appointment_ref']) ?></td></tr>
      <?php endif; ?>
    </table>
  </div>
  <div class="text-center mt-4 no-print d-flex flex-wrap gap-2 justify-content-center">
    <button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print me-1"></i>Print Ticket</button>
    <a class="btn btn-success" href="<?= e(url('public/live_tracker.php')) ?>?ref=<?= e(urlencode($ticket['ticket_ref'])) ?>"><i class="fa-solid fa-mobile-screen me-1"></i>Open Live Mobile Tracker</a>
    <a class="btn btn-outline-secondary" href="<?= e(url('public/dashboard.php')) ?>">Dashboard</a>
  </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
