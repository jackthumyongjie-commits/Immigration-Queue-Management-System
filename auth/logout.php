<?php
require_once dirname(__DIR__) . '/includes/functions.php';
logout_user();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['flash'] = ['type' => 'success', 'message' => 'You have been logged out successfully.'];
header('Location: ' . url('auth/login.php'));
exit;
