<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';

echo "DB OK\n";
$stats = admin_dashboard_stats();
echo 'Tickets today: ' . $stats['total_tickets'] . "\n";
echo 'Waiting: ' . $stats['waiting'] . "\n";

$stmt = db()->prepare('SELECT * FROM users WHERE email=?');
$stmt->execute(['admin@immigration.gov']);
$admin = $stmt->fetch();
echo 'Admin verify: ' . (password_verify('admin123', $admin['password']) ? 'OK' : 'FAIL') . "\n";

$ticket = create_queue_ticket([
    'visitor_name' => 'Smoke Test Visitor',
    'visitor_ic' => 'SMK001',
    'visitor_phone' => '0111111111',
    'service_id' => 1,
    'ticket_type' => 'walk_in',
    'notes' => 'smoke test',
]);
echo 'Created ticket: ' . $ticket['queue_number'] . ' / ' . $ticket['ticket_ref'] . "\n";

$staff = get_staff_profile(2);
echo 'Staff counter: ' . ($staff['counter_number'] ?? 'none') . "\n";
echo "SMOKE PASS\n";
