<?php
require_once dirname(__DIR__) . '/includes/functions.php';

$user = current_user();
if ($user) {
    redirect(role_home($user['role']));
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $fullName = trim((string)post('full_name', ''));
    $ic = trim((string)post('ic_passport', ''));
    $email = trim((string)post('email', ''));
    $phone = trim((string)post('phone', ''));
    $password = (string)post('password', '');
    $confirm = (string)post('confirm_password', '');

    if ($fullName === '') $errors[] = 'Full name is required.';
    if ($ic === '') $errors[] = 'IC / Passport number is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if ($phone === '') $errors[] = 'Phone number is required.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Email is already registered.';
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = db()->prepare('INSERT INTO users (full_name, ic_passport, email, phone, password, role, is_active) VALUES (?, ?, ?, ?, ?, "public", 1)');
        $stmt->execute([$fullName, $ic, $email, $phone, $hash]);
        $newId = (int)db()->lastInsertId();
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$newId]);
        $account = $stmt->fetch();
        login_user($account);
        flash('success', 'Registration successful. Welcome!');
        redirect('public/dashboard.php');
    }
}

$pageTitle = 'Register';
$bodyClass = 'auth-register';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="auth-wrapper" style="height:auto;min-height:100vh;overflow:auto">
  <div class="auth-card" style="max-width:560px">
    <div class="auth-brand">
      <div class="logo-circle"><i class="fa-solid fa-user-plus"></i></div>
      <h4>Public User Registration</h4>
      <p class="text-muted mb-0">Create your immigration portal account</p>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-12">
          <label class="form-label">Full Name</label>
          <input type="text" name="full_name" class="form-control" required value="<?= e((string)post('full_name', '')) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">IC / Passport Number</label>
          <input type="text" name="ic_passport" class="form-control" required value="<?= e((string)post('ic_passport', '')) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Phone Number</label>
          <input type="text" name="phone" class="form-control" required value="<?= e((string)post('phone', '')) ?>">
        </div>
        <div class="col-md-12">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-control" required value="<?= e((string)post('email', '')) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Confirm Password</label>
          <input type="password" name="confirm_password" class="form-control" required>
        </div>
      </div>
      <button class="btn btn-primary w-100 mt-4 py-2" type="submit">Create Account</button>
    </form>
    <div class="text-center mt-3">
      Already have an account? <a href="<?= e(url('auth/login.php')) ?>">Login</a>
    </div>
  </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
