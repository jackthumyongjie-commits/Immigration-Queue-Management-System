<?php
require_once dirname(__DIR__) . '/includes/functions.php';
$user = require_login('public');
$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    try {
        if ($action === 'profile') {
            $name = trim((string)post('full_name', ''));
            $phone = trim((string)post('phone', ''));
            $ic = trim((string)post('ic_passport', ''));
            if ($name === '' || $ic === '') throw new RuntimeException('Name and IC/Passport are required.');
            $pdo->prepare('UPDATE users SET full_name=?, phone=?, ic_passport=?, updated_at=? WHERE id=?')
                ->execute([$name, $phone, $ic, now(), $user['id']]);
            flash('success', 'Profile updated.');
        } elseif ($action === 'password') {
            $current = (string)post('current_password', '');
            $new = (string)post('new_password', '');
            $confirm = (string)post('confirm_password', '');
            $stmt = $pdo->prepare('SELECT password FROM users WHERE id=?');
            $stmt->execute([$user['id']]);
            $hash = $stmt->fetchColumn();
            if (!password_verify($current, (string)$hash)) throw new RuntimeException('Current password is incorrect.');
            if (strlen($new) < 6) throw new RuntimeException('New password must be at least 6 characters.');
            if ($new !== $confirm) throw new RuntimeException('New passwords do not match.');
            $pdo->prepare('UPDATE users SET password=?, updated_at=? WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), now(), $user['id']]);
            flash('success', 'Password changed successfully.');
        }
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    redirect('public/profile.php');
}

$user = current_user();
$appts = $pdo->prepare('SELECT a.*, s.name AS service_name FROM appointments a JOIN services s ON s.id=a.service_id WHERE user_id=? ORDER BY created_at DESC LIMIT 10');
$appts->execute([$user['id']]);
$tickets = $pdo->prepare('SELECT qt.*, s.name AS service_name FROM queue_tickets qt JOIN services s ON s.id=qt.service_id WHERE user_id=? ORDER BY created_at DESC LIMIT 10');
$tickets->execute([$user['id']]);
$apps = $pdo->prepare('SELECT a.*, s.name AS service_name FROM applications a JOIN services s ON s.id=a.service_id WHERE user_id=? ORDER BY created_at DESC LIMIT 10');
$apps->execute([$user['id']]);

$pageTitle = 'My Profile';
require dirname(__DIR__) . '/includes/header.php';
require dirname(__DIR__) . '/includes/public_header.php';
?>
<div class="container py-4">
  <h3 class="page-title">Public User Profile</h3>
  <div class="row g-3">
    <div class="col-lg-5">
      <div class="card-panel mb-3">
        <div class="card-panel-header">Personal Information</div>
        <div class="card-panel-body">
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="profile">
            <div class="mb-2"><label class="form-label">Full Name</label><input class="form-control" name="full_name" value="<?= e($user['full_name']) ?>" required></div>
            <div class="mb-2"><label class="form-label">IC / Passport</label><input class="form-control" name="ic_passport" value="<?= e($user['ic_passport']) ?>" required></div>
            <div class="mb-2"><label class="form-label">Email</label><input class="form-control" value="<?= e($user['email']) ?>" disabled></div>
            <div class="mb-3"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= e($user['phone'] ?? '') ?>"></div>
            <button class="btn btn-primary">Save Profile</button>
          </form>
        </div>
      </div>
      <div class="card-panel">
        <div class="card-panel-header">Change Password</div>
        <div class="card-panel-body">
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="password">
            <div class="mb-2"><input type="password" class="form-control" name="current_password" placeholder="Current password" required></div>
            <div class="mb-2"><input type="password" class="form-control" name="new_password" placeholder="New password" required></div>
            <div class="mb-3"><input type="password" class="form-control" name="confirm_password" placeholder="Confirm new password" required></div>
            <button class="btn btn-outline-primary">Update Password</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-7">
      <div class="card-panel mb-3"><div class="card-panel-header">Appointment History</div><div class="card-panel-body p-0 table-responsive">
        <table class="table mb-0"><thead><tr><th>Ref</th><th>Service</th><th>Date</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($appts->fetchAll() as $a): ?><tr><td><?= e($a['reference_no']) ?></td><td><?= e($a['service_name']) ?></td><td><?= format_date($a['appointment_date']) ?></td><td><?= status_badge($a['status']) ?></td></tr><?php endforeach; ?>
        </tbody></table>
      </div></div>
      <div class="card-panel mb-3"><div class="card-panel-header">Queue History</div><div class="card-panel-body p-0 table-responsive">
        <table class="table mb-0"><thead><tr><th>Queue</th><th>Service</th><th>Date</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($tickets->fetchAll() as $t): ?><tr><td><?= e($t['queue_number']) ?></td><td><?= e($t['service_name']) ?></td><td><?= format_datetime($t['created_at']) ?></td><td><?= status_badge($t['status']) ?></td></tr><?php endforeach; ?>
        </tbody></table>
      </div></div>
      <div class="card-panel"><div class="card-panel-header">Application Records</div><div class="card-panel-body p-0 table-responsive">
        <table class="table mb-0"><thead><tr><th>Ref</th><th>Service</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($apps->fetchAll() as $a): ?><tr><td><?= e($a['reference_no']) ?></td><td><?= e($a['service_name']) ?></td><td><?= status_badge($a['status']) ?></td></tr><?php endforeach; ?>
        </tbody></table>
      </div></div>
    </div>
  </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
