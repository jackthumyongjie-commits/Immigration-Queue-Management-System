<?php
require_once dirname(__DIR__) . '/includes/functions.php';

$user = current_user();
if ($user) {
    redirect(role_home($user['role']));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = trim((string)post('email', ''));
    $password = (string)post('password', '');

    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $account = $stmt->fetch();
        if (!$account || !(int)$account['is_active'] || !password_verify($password, $account['password'])) {
            $error = 'Invalid email or password.';
        } else {
            login_user($account);
            flash('success', 'Welcome back, ' . $account['full_name'] . '!');
            redirect(role_home($account['role']));
        }
    }
}

$pageTitle = 'Login';
$bodyClass = 'auth-page';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="auth-wrapper auth-compact">
  <div class="auth-card">
    <div class="auth-brand">
      <div class="logo-circle"><i class="fa-solid fa-building-columns"></i></div>
      <h4 class="mb-0">Immigration Queue System</h4>
      <p class="text-muted mb-0 small">Sign in to continue</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger py-2 mb-2 small"><?= e($error) ?></div>
    <?php endif; ?>
    <?php $flash = get_flash(); if ($flash): ?>
      <div class="alert alert-<?= e($flash['type']) ?> py-2 mb-2 small"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off" id="loginForm">
      <?= csrf_field() ?>
      <div class="mb-2">
        <label class="form-label mb-1">Email Address</label>
        <input type="email" name="email" id="loginEmail" class="form-control form-control-sm" required value="<?= e((string)post('email', '')) ?>">
      </div>
      <div class="mb-3">
        <label class="form-label mb-1">Password</label>
        <input type="password" name="password" id="loginPassword" class="form-control form-control-sm" required>
      </div>
      <button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-right-to-bracket me-1"></i> Login</button>
    </form>

    <div class="mt-3">
      <div class="small fw-semibold mb-2 text-muted">Demo Accounts (click to fill)</div>
      <div class="demo-row">
        <button type="button" class="btn btn-outline-primary btn-sm demo-fill" data-email="admin@immigration.gov" data-password="admin123">Admin</button>
        <button type="button" class="btn btn-outline-secondary btn-sm demo-fill" data-email="staff01@immigration.gov" data-password="staff123">Staff</button>
        <button type="button" class="btn btn-outline-success btn-sm demo-fill" data-email="ahmad@email.com" data-password="user123">Public</button>
      </div>
    </div>

    <div class="text-center mt-3 small">
      <a href="<?= e(url('auth/register.php')) ?>">Create account</a>
      <span class="mx-2 text-muted">|</span>
      <a href="<?= e(url('index.php')) ?>">Home</a>
    </div>
  </div>
</div>
<script>
document.querySelectorAll('.demo-fill').forEach(function (btn) {
  btn.addEventListener('click', function () {
    document.getElementById('loginEmail').value = btn.dataset.email;
    document.getElementById('loginPassword').value = btn.dataset.password;
    document.getElementById('loginEmail').focus();
  });
});
</script>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
