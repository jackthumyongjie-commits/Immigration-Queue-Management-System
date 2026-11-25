<?php
require_once dirname(__DIR__) . '/includes/functions.php';
$ref = trim((string)get('ref', ''));

if (isset($_GET['ajax'])) {
    $ticket = get_ticket_by_ref($ref);
    if (!$ticket) {
        json_response(['success' => false, 'message' => 'Ticket not found'], 404);
    }
    $ahead = people_ahead($ticket);
    $serving = currently_serving_for_service((int)$ticket['service_id']);
    $phase = tracker_phase($ticket);
    $estWait = ($ticket['status'] === 'waiting')
        ? (int)($ticket['estimated_wait'] ?? max(0, $ahead * 8))
        : 0;

    json_response([
        'success' => true,
        'ticket' => [
            'queue_number' => $ticket['queue_number'],
            'status' => $ticket['status'],
            'started_at' => $ticket['started_at'],
            'phase' => $phase,
            'service_name' => $ticket['service_name'],
            'counter_number' => $ticket['counter_number'],
            'estimated_wait' => $estWait,
            'people_ahead' => $ahead,
            'currently_serving' => $serving,
            'updated_at' => date('h:i:s A'),
        ],
    ]);
}

$ticket = $ref !== '' ? get_ticket_by_ref($ref) : null;
$phase = $ticket ? tracker_phase($ticket) : null;
$pageTitle = 'Live Mobile Queue Tracker';
$bodyClass = 'bg-light';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="container py-3 no-print">
  <?php if (!$ticket): ?>
    <div class="tracker-card">
      <div class="tracker-header"><h5 class="mb-0">Live Queue Tracker</h5></div>
      <div class="p-4">
        <form method="get">
          <label class="form-label">Enter Ticket Reference</label>
          <input class="form-control mb-3" name="ref" placeholder="TKT-..." required>
          <button class="btn btn-primary w-100">Track</button>
        </form>
      </div>
    </div>
  <?php else:
    $ahead = people_ahead($ticket);
    $estWait = ($ticket['status'] === 'waiting')
      ? (int)($ticket['estimated_wait'] ?? max(0, $ahead * 8))
      : 0;
    $pollUrl = url('public/live_tracker.php') . '?ajax=1&ref=' . rawurlencode($ticket['ticket_ref']);
  ?>
    <div class="tracker-card tracker-phase-<?= e($phase['key']) ?>" id="tracker"
         data-poll-url="<?= htmlspecialchars($pollUrl, ENT_QUOTES, 'UTF-8') ?>">
      <div class="tracker-header" id="tHeader">
        <div class="small opacity-75">Queue Number</div>
        <div class="display-4 fw-bold" id="tQueue"><?= e($ticket['queue_number']) ?></div>
        <div id="tStatus" class="mt-2">
          <span class="tracker-status-badge badge bg-<?= e($phase['badge']) ?>">
            <?= e($phase['label']) ?> · <?= e($phase['label_cn']) ?>
          </span>
        </div>
        <div class="small mt-2 opacity-75" id="tMessage"><?= e($phase['message']) ?></div>
      </div>

      <div class="tracker-steps px-3 pt-3">
        <div class="tracker-step <?= $ticket['status'] === 'waiting' ? 'is-active' : (in_array($ticket['status'], ['serving','completed','skipped'], true) ? 'is-done' : '') ?>" id="stepWaiting">
          <span class="dot"></span><span>Waiting<br><small>等待</small></span>
        </div>
        <div class="tracker-step-line"></div>
        <div class="tracker-step <?= $ticket['status'] === 'serving' ? 'is-active' : ($ticket['status'] === 'completed' ? 'is-done' : '') ?>" id="stepServing">
          <span class="dot"></span><span>Serving<br><small>服务中</small></span>
        </div>
        <div class="tracker-step-line"></div>
        <div class="tracker-step <?= in_array($ticket['status'], ['completed','skipped','cancelled'], true) ? 'is-active is-done' : '' ?>" id="stepDone">
          <span class="dot"></span><span>Finished<br><small>结束</small></span>
        </div>
      </div>

      <div class="p-4">
        <div class="row g-3 text-center mb-3">
          <div class="col-6"><div class="border rounded p-3"><div class="text-muted small">People Ahead</div><div class="fs-3 fw-bold" id="tAhead"><?= $ahead ?></div></div></div>
          <div class="col-6"><div class="border rounded p-3"><div class="text-muted small">Now Serving</div><div class="fs-3 fw-bold" id="tServing"><?= e(currently_serving_for_service((int)$ticket['service_id']) ?? '-') ?></div></div></div>
          <div class="col-6"><div class="border rounded p-3"><div class="text-muted small">Counter</div><div class="fs-5 fw-bold" id="tCounter"><?= e($ticket['counter_number'] ?? '-') ?></div></div></div>
          <div class="col-6"><div class="border rounded p-3"><div class="text-muted small">Est. Wait</div><div class="fs-5 fw-bold" id="tWait"><?= $estWait ?> min</div></div></div>
        </div>
        <div class="text-muted small text-center">Service: <span id="tService"><?= e($ticket['service_name']) ?></span></div>
        <div class="text-muted small text-center">
          Auto-updating · Last Updated: <span id="tUpdated"><?= date('h:i:s A') ?></span>
          <span class="tracker-live-dot" title="Live"></span>
        </div>
      </div>
    </div>
    <div class="text-center mt-3">
      <a href="<?= e(url('public/queue_ticket.php')) ?>?ref=<?= e(urlencode($ticket['ticket_ref'])) ?>" class="btn btn-outline-primary btn-sm">View Ticket</a>
    </div>
  <?php endif; ?>
</div>
<?php if ($ticket): ?>
<script>
(function () {
  const pollUrl = document.getElementById('tracker').dataset.pollUrl;

  function setStepState(status) {
    const waiting = document.getElementById('stepWaiting');
    const serving = document.getElementById('stepServing');
    const done = document.getElementById('stepDone');
    [waiting, serving, done].forEach((el) => el.classList.remove('is-active', 'is-done'));
    if (status === 'waiting') {
      waiting.classList.add('is-active');
    } else if (status === 'serving') {
      waiting.classList.add('is-done');
      serving.classList.add('is-active');
    } else {
      waiting.classList.add('is-done');
      serving.classList.add('is-done');
      done.classList.add('is-active', 'is-done');
    }
  }

  async function pollTracker() {
    try {
      const res = await fetch(pollUrl, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        cache: 'no-store',
      });
      const data = await res.json();
      if (!data.success) return;
      const t = data.ticket;
      const p = t.phase;
      const card = document.getElementById('tracker');
      card.className = 'tracker-card tracker-phase-' + p.key;
      document.getElementById('tQueue').textContent = t.queue_number;
      document.getElementById('tStatus').innerHTML =
        '<span class="tracker-status-badge badge bg-' + p.badge + '">' + p.label + ' · ' + p.label_cn + '</span>';
      document.getElementById('tMessage').textContent = p.message || '';
      document.getElementById('tAhead').textContent = t.people_ahead;
      document.getElementById('tServing').textContent = t.currently_serving || '-';
      document.getElementById('tCounter').textContent = t.counter_number || '-';
      document.getElementById('tWait').textContent = t.estimated_wait + ' min';
      document.getElementById('tUpdated').textContent = t.updated_at;
      setStepState(t.status);
    } catch (e) {
      console.error('Tracker poll failed', e);
    }
  }

  pollTracker();
  setInterval(pollTracker, 1500);
})();
</script>
<?php endif; ?>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
