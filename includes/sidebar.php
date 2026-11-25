<?php
$role = $user['role'] ?? '';
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
?>
<aside class="sidebar" id="sidebar">
  <div class="brand">
    <i class="fa-solid fa-building-columns"></i>
    <span>Immigration Queue<br>Management System</span>
  </div>

  <?php if ($role === 'admin'): ?>
    <div class="nav-section">Main</div>
    <a class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="<?= e(url('admin/dashboard.php')) ?>"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
    <div class="nav-section">Operations</div>
    <a class="nav-link <?= $currentPage === 'counters.php' ? 'active' : '' ?>" href="<?= e(url('admin/counters.php')) ?>"><i class="fa-solid fa-desktop"></i> Counter Management</a>
    <a class="nav-link <?= $currentPage === 'services.php' ? 'active' : '' ?>" href="<?= e(url('admin/services.php')) ?>"><i class="fa-solid fa-list-check"></i> Service Management</a>
    <a class="nav-link <?= $currentPage === 'queue_tickets.php' ? 'active' : '' ?>" href="<?= e(url('admin/queue_tickets.php')) ?>"><i class="fa-solid fa-ticket"></i> Queue Tickets</a>
    <a class="nav-link <?= $currentPage === 'applications.php' ? 'active' : '' ?>" href="<?= e(url('admin/applications.php')) ?>"><i class="fa-solid fa-folder-open"></i> Applications</a>
    <a class="nav-link <?= $currentPage === 'staff.php' ? 'active' : '' ?>" href="<?= e(url('admin/staff.php')) ?>"><i class="fa-solid fa-user-tie"></i> Staff Management</a>
    <div class="nav-section">Tools</div>
    <a class="nav-link <?= $currentPage === 'daily_reports.php' ? 'active' : '' ?>" href="<?= e(url('admin/daily_reports.php')) ?>"><i class="fa-solid fa-chart-column"></i> Daily Reports</a>
    <a class="nav-link <?= $currentPage === 'demo_toolkit.php' ? 'active' : '' ?>" href="<?= e(url('admin/demo_toolkit.php')) ?>"><i class="fa-solid fa-flask"></i> Demo Toolkit</a>
    <a class="nav-link" href="<?= e(url('display/queue_board.php')) ?>" target="_blank"><i class="fa-solid fa-tv"></i> Queue Display</a>

  <?php elseif ($role === 'staff'): ?>
    <div class="nav-section">Workstation</div>
    <a class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="<?= e(url('staff/dashboard.php')) ?>"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
    <a class="nav-link <?= $currentPage === 'counter_operations.php' ? 'active' : '' ?>" href="<?= e(url('staff/counter_operations.php')) ?>"><i class="fa-solid fa-bell-concierge"></i> Counter Operations</a>
    <a class="nav-link <?= $currentPage === 'queue_list.php' ? 'active' : '' ?>" href="<?= e(url('staff/queue_list.php')) ?>"><i class="fa-solid fa-list-ol"></i> Queue List</a>
    <a class="nav-link <?= $currentPage === 'applications.php' ? 'active' : '' ?>" href="<?= e(url('staff/applications.php')) ?>"><i class="fa-solid fa-folder-open"></i> Applications</a>
    <a class="nav-link" href="<?= e(url('display/queue_board.php')) ?>" target="_blank"><i class="fa-solid fa-tv"></i> Queue Display</a>
  <?php endif; ?>

  <div class="nav-section">Account</div>
  <a class="nav-link" href="<?= e(url('auth/logout.php')) ?>"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</aside>
