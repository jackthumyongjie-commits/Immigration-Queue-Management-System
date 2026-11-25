<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';

$errors = [];
$ok = [];

try {
    $pdo = db();
    $ok[] = 'DB connected';
} catch (Throwable $e) {
    fwrite(STDERR, 'DB FAIL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$tables = [
    'users','staff','services','counters','staff_counter_assignments','staff_service_permissions',
    'counter_services','appointments','appointment_slots','queue_tickets','queue_history',
    'applications','application_documents','application_remarks','notifications','system_settings','activity_logs'
];
foreach ($tables as $t) {
    try {
        $pdo->query("SELECT 1 FROM `$t` LIMIT 1");
        $ok[] = "table:$t";
    } catch (Throwable $e) {
        $errors[] = "missing table $t";
    }
}

foreach ([
    'admin@immigration.gov' => 'admin123',
    'staff01@immigration.gov' => 'staff123',
    'ahmad@email.com' => 'user123',
] as $email => $pass) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email=?');
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    if (!$u || !password_verify($pass, $u['password'])) {
        $errors[] = "login fail $email";
    } else {
        $ok[] = "login:$email";
    }
}

$staff = get_staff_profile(2);
if (!$staff || empty($staff['counter_id'])) {
    $errors[] = 'staff01 counter not assigned';
} else {
    $ok[] = 'staff counter=' . $staff['counter_number'];
}

$services = $pdo->query('SELECT COUNT(*) FROM services WHERE is_active=1')->fetchColumn();
$counters = $pdo->query('SELECT COUNT(*) FROM counters WHERE is_active=1')->fetchColumn();
$slots = $pdo->query('SELECT COUNT(*) FROM appointment_slots WHERE slot_date >= CURDATE()')->fetchColumn();
$ok[] = "services=$services counters=$counters future_slots=$slots";

// Workflow smoke: create ticket, call, start, complete
try {
    $ticket = create_queue_ticket([
        'visitor_name' => 'Final Check Visitor',
        'visitor_ic' => 'FC999',
        'visitor_phone' => '011222333',
        'service_id' => 1,
        'ticket_type' => 'walk_in',
        'notes' => 'final check',
    ]);
    $ok[] = 'ticket created ' . $ticket['queue_number'];

    // Clear any existing serving for staff 1
    $pdo->exec("UPDATE queue_tickets SET status='skipped', completed_at=NOW() WHERE staff_id=1 AND status='serving' AND queue_date=CURDATE()");
    $pdo->exec("UPDATE counters SET status='available' WHERE id=" . (int)$staff['counter_id']);

    $called = call_next_ticket($staff);
    if (!$called) throw new RuntimeException('call_next returned null');
    if (!empty($called['started_at'])) $errors[] = 'call_next should leave started_at NULL';
    else $ok[] = 'call_next ok started_at null';

    $started = start_serving($staff);
    if (empty($started['started_at'])) $errors[] = 'start_serving failed';
    else $ok[] = 'start_serving ok';

    $recalled = recall_ticket($staff);
    if ((int)$recalled['recall_count'] < 1) $errors[] = 'recall failed';
    else $ok[] = 'recall ok count=' . $recalled['recall_count'];

    $done = complete_ticket($staff);
    if ($done['status'] !== 'completed') $errors[] = 'complete failed';
    else $ok[] = 'complete ok';

    $phase = tracker_phase($done);
    if ($phase['key'] !== 'completed') $errors[] = 'tracker_phase wrong';
    else $ok[] = 'tracker_phase ok';
} catch (Throwable $e) {
    $errors[] = 'workflow: ' . $e->getMessage();
}

// Appointment booking + office check-in
try {
    // Prefer a same-day slot so check-in is allowed
    $slot = $pdo->query("SELECT * FROM appointment_slots WHERE service_id=1 AND slot_date = CURDATE() AND booked_count < max_capacity ORDER BY start_time LIMIT 1")->fetch();
    if (!$slot) {
        $slot = $pdo->query("SELECT * FROM appointment_slots WHERE service_id=1 AND slot_date >= CURDATE() AND booked_count < max_capacity ORDER BY slot_date, start_time LIMIT 1")->fetch();
    }
    if ($slot) {
        $appt = book_appointment(4, 1, (int)$slot['id'], 'final check', 'email');
        $ok[] = 'appointment ' . $appt['reference_no'];

        if ($appt['appointment_date'] === today()) {
            $check = check_in_appointment($appt['reference_no'], null);
            if (empty($check['ticket']['queue_number'])) {
                $errors[] = 'check_in missing ticket';
            } else {
                $ok[] = 'check_in ticket ' . $check['ticket']['queue_number'];
            }
        } else {
            $ok[] = 'check_in skipped (appointment not today)';
        }
    } else {
        $errors[] = 'no appointment slot available';
    }
} catch (Throwable $e) {
    $errors[] = 'appointment: ' . $e->getMessage();
}

// Key helper / path sanity
foreach ([
    'includes/header.php', 'includes/footer.php', 'includes/public_header.php',
    'assets/css/style.css', 'assets/js/app.js', 'display/queue_board.php',
    'staff/counter_operations.php', 'public/live_tracker.php', 'auth/login.php',
] as $rel) {
    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!is_file($path)) $errors[] = "missing file $rel";
    else $ok[] = "file:$rel";
}

if (!function_exists('csrf_token') || csrf_token() === '') $errors[] = 'csrf_token broken';
else $ok[] = 'csrf_token ok';

if (!is_dir(UPLOAD_PATH)) {
    ensure_upload_dir();
}
if (!is_dir(UPLOAD_PATH)) $errors[] = 'upload dir missing';
else $ok[] = 'upload dir ok';

echo "=== OK (" . count($ok) . ") ===\n";
foreach ($ok as $line) echo "  + $line\n";
echo "=== ERRORS (" . count($errors) . ") ===\n";
foreach ($errors as $line) echo "  ! $line\n";
exit($errors ? 1 : 0);
