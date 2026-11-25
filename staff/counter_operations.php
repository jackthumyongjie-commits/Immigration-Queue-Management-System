<?php
require_once dirname(__DIR__) . '/includes/functions.php';
$user = require_login('staff');
$staff = get_staff_profile((int)$user['id']);
if (!$staff || empty($staff['counter_id'])) {
    flash('danger', 'No counter assigned. Contact administrator.');
    redirect('staff/dashboard.php');
}

// Mark staff online
db()->prepare("UPDATE staff SET working_status='online', updated_at=? WHERE id=?")->execute([now(), $staff['id']]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)post('action');
    $ajax = is_ajax() || (string)post('ajax') === '1';
    try {
        $ticket = null;
        $message = '';
        if ($action === 'call_next') {
            $ticket = call_next_ticket($staff);
            $message = $ticket ? 'Called ' . $ticket['queue_number'] . '. Click Start Serving when ready.' : 'No waiting tickets available.';
        } elseif ($action === 'recall') {
            $ticket = recall_ticket($staff);
            $message = 'Recalled ' . $ticket['queue_number'] . ' (recall #' . (int)$ticket['recall_count'] . ')';
        } elseif ($action === 'complete') {
            $ticket = complete_ticket($staff);
            $message = 'Completed ' . $ticket['queue_number'];
        } elseif ($action === 'skip') {
            $ticket = skip_ticket($staff);
            $message = 'Skipped ' . $ticket['queue_number'];
        } elseif ($action === 'start') {
            $ticket = start_serving($staff);
            $message = 'Started serving ' . $ticket['queue_number'];
        } else {
            throw new RuntimeException('Unknown action.');
        }
        if ($ajax) {
            json_response([
                'success' => true,
                'message' => $message,
                'action' => $action,
                'ticket' => $ticket,
            ]);
        }
        flash('success', $message);
    } catch (Throwable $e) {
        if ($ajax) {
            json_response(['success' => false, 'message' => $e->getMessage(), 'action' => $action ?? ''], 400);
        }
        flash('danger', $e->getMessage());
    }
    redirect('staff/counter_operations.php');
}

$currentTicket = get_current_serving_ticket((int)$staff['id']);
$serviceIds = array_column(staff_services((int)$staff['id']), 'id');
$waitingList = [];
if ($serviceIds) {
    $in = implode(',', array_map('intval', $serviceIds));
    $waitingList = db()->query("SELECT qt.*, s.name AS service_name FROM queue_tickets qt JOIN services s ON s.id=qt.service_id WHERE qt.queue_date=CURDATE() AND qt.status='waiting' AND qt.service_id IN ($in) ORDER BY qt.created_at ASC LIMIT 10")->fetchAll();
}

$pageTitle = 'Counter Operations';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-shell">
<?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
<div class="main-content">
<?php require dirname(__DIR__) . '/includes/navbar.php'; ?>
<div class="page-body">
<?php $flash = get_flash(); if ($flash): ?><div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" id="opAlert"><?= e($flash['message']) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<div id="actionToast" class="alert alert-info d-none"></div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card-panel">
      <div class="card-panel-header">
        <span>Counter <?= e($staff['counter_number']) ?> · <?= e($staff['counter_name']) ?></span>
        <?= status_badge($staff['counter_status'] ?? 'available') ?>
      </div>
      <div class="card-panel-body text-center" id="currentTicketPanel">
        <?php if ($currentTicket): ?>
          <div class="text-muted mb-2">
            <?php if (!empty($currentTicket['started_at'])): ?>
              <span class="badge bg-success">NOW SERVING</span>
            <?php else: ?>
              <span class="badge bg-warning text-dark">CALLED · Click Start Serving</span>
            <?php endif; ?>
            <?= !empty($currentTicket['recall_count']) ? ' · Recalled x' . (int)$currentTicket['recall_count'] : '' ?>
          </div>
          <div class="queue-number-display mb-3" id="queueNumber"><?= e($currentTicket['queue_number']) ?></div>
          <div class="row text-start justify-content-center">
            <div class="col-md-8">
              <table class="table table-borderless mb-0">
                <tr><th>Visitor Name</th><td id="visitorName"><?= e($currentTicket['visitor_name']) ?></td></tr>
                <tr><th>Service</th><td id="serviceName"><?= e($currentTicket['service_name']) ?></td></tr>
                <tr><th>Appointment Ref</th><td id="apptRef"><?= e($currentTicket['appointment_ref'] ?? '-') ?></td></tr>
                <tr><th>Ticket Ref</th><td><?= e($currentTicket['ticket_ref']) ?></td></tr>
                <tr><th>Called At</th><td><?= format_datetime($currentTicket['called_at']) ?></td></tr>
              </table>
            </div>
          </div>
        <?php else: ?>
          <div class="py-4">
            <div class="queue-number-display text-muted mb-2" style="font-size:2.5rem">---</div>
            <p class="text-muted">No ticket is currently being served. Click <strong>Call Next</strong> to continue.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="row g-2" id="opsButtons">
      <div class="col-6 col-md"><button class="btn btn-navy ops-btn w-100" data-action="call_next"><i class="fa-solid fa-forward me-1"></i>Call Next</button></div>
      <div class="col-6 col-md"><button class="btn btn-warning ops-btn w-100" data-action="recall" <?= $currentTicket ? '' : 'disabled' ?>><i class="fa-solid fa-bullhorn me-1"></i>Recall Number</button></div>
      <div class="col-6 col-md"><button class="btn btn-success ops-btn w-100" data-action="complete" <?= $currentTicket ? '' : 'disabled' ?>><i class="fa-solid fa-check me-1"></i>Mark Completed</button></div>
      <div class="col-6 col-md"><button class="btn btn-secondary ops-btn w-100" data-action="skip" <?= $currentTicket ? '' : 'disabled' ?>><i class="fa-solid fa-forward-step me-1"></i>Skip Number</button></div>
      <div class="col-12 col-md"><button class="btn btn-primary ops-btn w-100" data-action="start" <?= ($currentTicket && empty($currentTicket['started_at'])) ? '' : 'disabled' ?>><i class="fa-solid fa-play me-1"></i>Start Serving</button></div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card-panel">
      <div class="card-panel-header">Waiting Queue</div>
      <div class="card-panel-body p-0">
        <ul class="list-group list-group-flush" id="waitingList">
          <?php foreach ($waitingList as $w): ?>
            <li class="list-group-item d-flex justify-content-between">
              <div><strong><?= e($w['queue_number']) ?></strong> · <?= e($w['visitor_name']) ?><div class="small text-muted"><?= e($w['service_name']) ?></div></div>
              <span class="small text-muted"><?= format_time($w['created_at']) ?></span>
            </li>
          <?php endforeach; ?>
          <?php if (!$waitingList): ?><li class="list-group-item text-muted text-center">No waiting tickets</li><?php endif; ?>
        </ul>
      </div>
    </div>
  </div>
</div>
</div></div></div>
<?php
$extraJs = '<script>
function showToast(msg, ok){
  const el = document.getElementById("actionToast");
  el.className = "alert alert-" + (ok ? "success" : "danger");
  el.textContent = msg;
  el.classList.remove("d-none");
}

document.querySelectorAll("#opsButtons [data-action]").forEach(btn => {
  btn.addEventListener("click", async () => {
    if(btn.disabled) return;
    const action = btn.dataset.action;
    btn.disabled = true;
    try {
      const data = await postJSON("' . e(url('staff/counter_operations.php')) . '", { action });
      if (!data.success) {
        showToast(data.message || "Action failed", false);
        alert(data.message || "Action failed");
        btn.disabled = false;
        return;
      }
      showToast(data.message || "Done", true);
      // No sound here. Queue Display announces only on Start Serving / Recall (female voice).
      location.reload();
    } catch (err) {
      showToast("Request failed. Please try again.", false);
      alert("Request failed");
      btn.disabled = false;
    }
  });
});
</script>';
require dirname(__DIR__) . '/includes/footer.php';
?>