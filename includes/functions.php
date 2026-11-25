<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ===================== HELPERS ===================== */

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return BASE_URL . ($path !== '' ? '/' . $path : '');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function csrf_token(): string
{
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function csrf_field(): string
{
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token = null): bool
{
    $token = $token ?? ($_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($token) && isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function require_csrf(): void
{
    if (!verify_csrf()) {
        if (is_ajax()) {
            json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
        }
        flash('danger', 'Invalid security token. Please try again.');
        $u = current_user();
        redirect($u ? role_home($u['role']) : 'index.php');
    }
}

function is_ajax(): bool
{
    return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
        || (isset($_POST['ajax']) || isset($_GET['ajax']));
}

function post(string $key, $default = null)
{
    return $_POST[$key] ?? $default;
}

function get(string $key, $default = null)
{
    return $_GET[$key] ?? $default;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function today(): string
{
    return date('Y-m-d');
}

function generate_ref(string $prefix): string
{
    return $prefix . '-' . date('Ymd') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

function status_badge(string $status): string
{
    $map = [
        'waiting' => 'warning',
        'serving' => 'primary',
        'completed' => 'success',
        'skipped' => 'secondary',
        'cancelled' => 'danger',
        'no_show' => 'dark',
        'pending' => 'warning',
        'confirmed' => 'info',
        'checked_in' => 'primary',
        'under_review' => 'info',
        'approved' => 'success',
        'rejected' => 'danger',
        'additional_docs' => 'warning',
        'available' => 'success',
        'offline' => 'secondary',
        'online' => 'success',
        'break' => 'warning',
    ];
    $class = $map[$status] ?? 'secondary';
    $label = ucwords(str_replace('_', ' ', $status));
    return '<span class="badge bg-' . $class . '">' . e($label) . '</span>';
}

function log_activity(?int $userId, string $action, string $module = '', string $details = ''): void
{
    $stmt = db()->prepare('INSERT INTO activity_logs (user_id, action, module, details, ip_address) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $action, $module, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
}

/* ===================== AUTH ===================== */

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    if (isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity']) > SESSION_TIMEOUT) {
        logout_user();
        return null;
    }
    $_SESSION['last_activity'] = time();

    static $cached = null;
    if ($cached && (int)$cached['id'] === (int)$_SESSION['user_id']) {
        return $cached;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        logout_user();
        return null;
    }
    $cached = $user;
    return $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['last_activity'] = time();

    $stmt = db()->prepare('UPDATE users SET last_login = ? WHERE id = ?');
    $stmt->execute([now(), $user['id']]);
    log_activity((int)$user['id'], 'login', 'auth', 'User logged in');
}

function logout_user(): void
{
    if (!empty($_SESSION['user_id'])) {
        log_activity((int)$_SESSION['user_id'], 'logout', 'auth', 'User logged out');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function require_login(string $role = ''): array
{
    $user = current_user();
    if (!$user) {
        flash('warning', 'Please login to continue.');
        redirect('auth/login.php');
    }
    if ($role !== '' && $user['role'] !== $role) {
        flash('danger', 'Unauthorized access.');
        redirect(role_home($user['role']));
    }
    return $user;
}

function role_home(string $role): string
{
    return match ($role) {
        'admin' => 'admin/dashboard.php',
        'staff' => 'staff/dashboard.php',
        default => 'public/dashboard.php',
    };
}

function get_staff_profile(int $userId): ?array
{
    $sql = "SELECT s.*, u.full_name, u.email, u.phone, c.id AS counter_id, c.counter_number, c.counter_name, c.status AS counter_status
            FROM staff s
            JOIN users u ON u.id = s.user_id
            LEFT JOIN staff_counter_assignments sca ON sca.staff_id = s.id AND sca.is_current = 1
            LEFT JOIN counters c ON c.id = sca.counter_id
            WHERE s.user_id = ? LIMIT 1";
    $stmt = db()->prepare($sql);
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

function staff_services(int $staffId): array
{
    $stmt = db()->prepare('SELECT sv.* FROM staff_service_permissions ssp JOIN services sv ON sv.id = ssp.service_id WHERE ssp.staff_id = ? AND sv.is_active = 1');
    $stmt->execute([$staffId]);
    return $stmt->fetchAll();
}

/* ===================== QUEUE ===================== */

function generate_queue_number(int $serviceId, string $date): string
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT queue_prefix FROM services WHERE id = ? FOR UPDATE');
    $stmt->execute([$serviceId]);
    $service = $stmt->fetch();
    if (!$service) {
        throw new RuntimeException('Service not found');
    }
    $prefix = $service['queue_prefix'];

    $stmt = $pdo->prepare("SELECT queue_number FROM queue_tickets WHERE queue_date = ? AND queue_number LIKE ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([$date, $prefix . '%']);
    $last = $stmt->fetchColumn();

    $next = 1;
    if ($last) {
        $next = (int)substr((string)$last, 1) + 1;
    }
    return $prefix . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
}

function create_queue_ticket(array $data): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $date = $data['queue_date'] ?? today();
        $queueNumber = generate_queue_number((int)$data['service_id'], $date);
        $ticketRef = generate_ref('TKT');

        // Estimate wait based on waiting count for service
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM queue_tickets WHERE queue_date = ? AND service_id = ? AND status = 'waiting'");
        $stmt->execute([$date, $data['service_id']]);
        $waiting = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT estimated_duration FROM services WHERE id = ?');
        $stmt->execute([$data['service_id']]);
        $duration = (int)$stmt->fetchColumn();
        $estimated = ($waiting + 1) * max(5, (int)ceil($duration * 0.7));

        $sql = "INSERT INTO queue_tickets
            (ticket_ref, queue_number, queue_date, visitor_name, visitor_ic, visitor_phone, visitor_email, user_id, service_id, appointment_id, ticket_type, status, estimated_wait)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'waiting', ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $ticketRef,
            $queueNumber,
            $date,
            $data['visitor_name'],
            $data['visitor_ic'] ?? null,
            $data['visitor_phone'] ?? null,
            $data['visitor_email'] ?? null,
            $data['user_id'] ?? null,
            $data['service_id'],
            $data['appointment_id'] ?? null,
            $data['ticket_type'] ?? 'walk_in',
            $estimated,
        ]);
        $ticketId = (int)$pdo->lastInsertId();

        add_queue_history($ticketId, 'created', null, 'waiting', $data['performed_by'] ?? null, $data['notes'] ?? 'Ticket created');

        $pdo->commit();
        return get_ticket_by_id($ticketId);
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function add_queue_history(int $ticketId, string $action, ?string $old, ?string $new, ?int $by = null, string $notes = ''): void
{
    $stmt = db()->prepare('INSERT INTO queue_history (ticket_id, action, old_status, new_status, performed_by, notes) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$ticketId, $action, $old, $new, $by, $notes]);
}

function get_ticket_by_id(int $id): ?array
{
    $sql = "SELECT qt.*, s.name AS service_name, s.code AS service_code, s.estimated_duration,
                   c.counter_number, c.counter_name, a.reference_no AS appointment_ref,
                   st.staff_code, u.full_name AS staff_name
            FROM queue_tickets qt
            JOIN services s ON s.id = qt.service_id
            LEFT JOIN counters c ON c.id = qt.counter_id
            LEFT JOIN appointments a ON a.id = qt.appointment_id
            LEFT JOIN staff st ON st.id = qt.staff_id
            LEFT JOIN users u ON u.id = st.user_id
            WHERE qt.id = ? LIMIT 1";
    $stmt = db()->prepare($sql);
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function get_ticket_by_ref(string $ref): ?array
{
    $sql = "SELECT qt.*, s.name AS service_name, s.code AS service_code, s.estimated_duration,
                   c.counter_number, c.counter_name, a.reference_no AS appointment_ref
            FROM queue_tickets qt
            JOIN services s ON s.id = qt.service_id
            LEFT JOIN counters c ON c.id = qt.counter_id
            LEFT JOIN appointments a ON a.id = qt.appointment_id
            WHERE qt.ticket_ref = ? LIMIT 1";
    $stmt = db()->prepare($sql);
    $stmt->execute([$ref]);
    return $stmt->fetch() ?: null;
}

function people_ahead(array $ticket): int
{
    if (($ticket['status'] ?? '') !== 'waiting') {
        return 0;
    }
    $stmt = db()->prepare("SELECT COUNT(*) FROM queue_tickets
        WHERE queue_date = ? AND service_id = ? AND status = 'waiting' AND id < ?");
    $stmt->execute([$ticket['queue_date'], $ticket['service_id'], $ticket['id']]);
    return (int)$stmt->fetchColumn();
}

function currently_serving_for_service(int $serviceId): ?string
{
    // Prefer ticket that has started serving; fallback to latest called ticket
    $stmt = db()->prepare("SELECT queue_number FROM queue_tickets
        WHERE queue_date = CURDATE() AND service_id = ? AND status = 'serving' AND started_at IS NOT NULL
        ORDER BY started_at DESC LIMIT 1");
    $stmt->execute([$serviceId]);
    $num = $stmt->fetchColumn();
    if ($num) {
        return (string)$num;
    }
    $stmt = db()->prepare("SELECT queue_number FROM queue_tickets
        WHERE queue_date = CURDATE() AND service_id = ? AND status = 'serving'
        ORDER BY called_at DESC LIMIT 1");
    $stmt->execute([$serviceId]);
    $num = $stmt->fetchColumn();
    return $num ? (string)$num : null;
}

function tracker_phase(array $ticket): array
{
    $status = $ticket['status'] ?? 'waiting';
    return match ($status) {
        'waiting' => [
            'key' => 'waiting',
            'label' => 'Waiting',
            'label_cn' => '等待中',
            'badge' => 'warning',
            'message' => 'Please wait. Your number will be called soon.',
        ],
        'serving' => [
            'key' => 'serving',
            'label' => 'Serving',
            'label_cn' => '正在服务',
            'badge' => 'primary',
            'message' => !empty($ticket['started_at'])
                ? 'Your turn is in progress at the counter.'
                : 'Your number has been called. Please proceed to the counter.',
        ],
        'completed' => [
            'key' => 'completed',
            'label' => 'Completed',
            'label_cn' => '已结束',
            'badge' => 'success',
            'message' => 'Service completed. Thank you.',
        ],
        'skipped' => [
            'key' => 'skipped',
            'label' => 'Skipped',
            'label_cn' => '已跳过',
            'badge' => 'secondary',
            'message' => 'Your number was skipped. Please get a new ticket if needed.',
        ],
        'cancelled' => [
            'key' => 'cancelled',
            'label' => 'Cancelled',
            'label_cn' => '已取消',
            'badge' => 'danger',
            'message' => 'This ticket was cancelled.',
        ],
        default => [
            'key' => $status,
            'label' => ucwords(str_replace('_', ' ', $status)),
            'label_cn' => ucwords(str_replace('_', ' ', $status)),
            'badge' => 'secondary',
            'message' => '',
        ],
    };
}

/* ===================== STAFF COUNTER OPS ===================== */

function call_next_ticket(array $staff): ?array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $serviceIds = array_column(staff_services((int)$staff['id']), 'id');
        if (!$serviceIds || empty($staff['counter_id'])) {
            throw new RuntimeException('No counter or services assigned.');
        }

        // Complete check: if already serving, don't call next
        $stmt = $pdo->prepare("SELECT id FROM queue_tickets WHERE staff_id = ? AND status = 'serving' AND queue_date = CURDATE() LIMIT 1");
        $stmt->execute([$staff['id']]);
        if ($stmt->fetch()) {
            throw new RuntimeException('Please complete or skip the current ticket first.');
        }

        $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
        $params = array_merge([today()], $serviceIds);
        $sql = "SELECT * FROM queue_tickets
                WHERE queue_date = ? AND status = 'waiting' AND service_id IN ($placeholders)
                ORDER BY created_at ASC LIMIT 1 FOR UPDATE";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $ticket = $stmt->fetch();
        if (!$ticket) {
            $pdo->commit();
            return null;
        }

        // Call Next assigns the ticket; Start Serving will set started_at later
        $stmt = $pdo->prepare("UPDATE queue_tickets SET status='serving', counter_id=?, staff_id=?, called_at=?, started_at=NULL, updated_at=? WHERE id=?");
        $stmt->execute([$staff['counter_id'], $staff['id'], now(), now(), $ticket['id']]);

        $stmt = $pdo->prepare("UPDATE counters SET status='serving', updated_at=? WHERE id=?");
        $stmt->execute([now(), $staff['counter_id']]);

        add_queue_history((int)$ticket['id'], 'called', 'waiting', 'serving', (int)$staff['user_id'], 'Called next ticket');

        $pdo->commit();
        return get_ticket_by_id((int)$ticket['id']);
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function get_current_serving_ticket(int $staffId): ?array
{
    $stmt = db()->prepare("SELECT id FROM queue_tickets WHERE staff_id = ? AND status = 'serving' AND queue_date = CURDATE() ORDER BY called_at DESC LIMIT 1");
    $stmt->execute([$staffId]);
    $id = $stmt->fetchColumn();
    return $id ? get_ticket_by_id((int)$id) : null;
}

function recall_ticket(array $staff): ?array
{
    $ticket = get_current_serving_ticket((int)$staff['id']);
    if (!$ticket) {
        throw new RuntimeException('No ticket currently being served.');
    }
    $stmt = db()->prepare('UPDATE queue_tickets SET recall_count = recall_count + 1, called_at = ?, updated_at = ? WHERE id = ?');
    $stmt->execute([now(), now(), $ticket['id']]);
    add_queue_history((int)$ticket['id'], 'recall', 'serving', 'serving', (int)$staff['user_id'], 'Number recalled');
    return get_ticket_by_id((int)$ticket['id']);
}

function complete_ticket(array $staff): ?array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $ticket = get_current_serving_ticket((int)$staff['id']);
        if (!$ticket) {
            throw new RuntimeException('No ticket currently being served.');
        }
        $stmt = $pdo->prepare("UPDATE queue_tickets SET status='completed', completed_at=?, updated_at=? WHERE id=?");
        $stmt->execute([now(), now(), $ticket['id']]);

        $stmt = $pdo->prepare("UPDATE counters SET status='available', updated_at=? WHERE id=?");
        $stmt->execute([now(), $staff['counter_id']]);

        if (!empty($ticket['appointment_id'])) {
            $stmt = $pdo->prepare("UPDATE appointments SET status='completed', updated_at=? WHERE id=?");
            $stmt->execute([now(), $ticket['appointment_id']]);
        }

        add_queue_history((int)$ticket['id'], 'completed', 'serving', 'completed', (int)$staff['user_id'], 'Service completed');
        $pdo->commit();
        return get_ticket_by_id((int)$ticket['id']);
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function skip_ticket(array $staff): ?array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $ticket = get_current_serving_ticket((int)$staff['id']);
        if (!$ticket) {
            throw new RuntimeException('No ticket currently being served.');
        }
        $stmt = $pdo->prepare("UPDATE queue_tickets SET status='skipped', completed_at=?, updated_at=? WHERE id=?");
        $stmt->execute([now(), now(), $ticket['id']]);

        $stmt = $pdo->prepare("UPDATE counters SET status='available', updated_at=? WHERE id=?");
        $stmt->execute([now(), $staff['counter_id']]);

        add_queue_history((int)$ticket['id'], 'skipped', 'serving', 'skipped', (int)$staff['user_id'], 'Ticket skipped');
        $pdo->commit();
        return get_ticket_by_id((int)$ticket['id']);
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function start_serving(array $staff): ?array
{
    $ticket = get_current_serving_ticket((int)$staff['id']);
    if (!$ticket) {
        throw new RuntimeException('No ticket to start serving.');
    }
    if (!empty($ticket['started_at'])) {
        throw new RuntimeException('Service already started for ' . $ticket['queue_number'] . '.');
    }
    $stmt = db()->prepare('UPDATE queue_tickets SET started_at = ?, updated_at = ? WHERE id = ?');
    $stmt->execute([now(), now(), $ticket['id']]);
    add_queue_history((int)$ticket['id'], 'start_serving', 'serving', 'serving', (int)$staff['user_id'], 'Started serving');
    return get_ticket_by_id((int)$ticket['id']);
}

/* ===================== APPOINTMENTS ===================== */

function book_appointment(int $userId, int $serviceId, int $slotId, string $remarks = '', string $reminder = 'email'): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM appointment_slots WHERE id = ? FOR UPDATE');
        $stmt->execute([$slotId]);
        $slot = $stmt->fetch();
        if (!$slot || !(int)$slot['is_available'] || (int)$slot['booked_count'] >= (int)$slot['max_capacity']) {
            throw new RuntimeException('Selected time slot is full or unavailable.');
        }
        if ((int)$slot['service_id'] !== $serviceId) {
            throw new RuntimeException('Invalid slot for selected service.');
        }

        $stmtU = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmtU->execute([$userId]);
        $user = $stmtU->fetch();
        if (!$user) {
            throw new RuntimeException('User not found.');
        }

        $ref = generate_ref('APT');
        $stmt = $pdo->prepare("INSERT INTO appointments (reference_no, user_id, service_id, slot_id, appointment_date, appointment_time, remarks, reminder_option, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmed')");
        $stmt->execute([$ref, $userId, $serviceId, $slotId, $slot['slot_date'], $slot['start_time'], $remarks, $reminder]);

        $stmt = $pdo->prepare('UPDATE appointment_slots SET booked_count = booked_count + 1 WHERE id = ?');
        $stmt->execute([$slotId]);

        $pdo->commit();

        $stmt = db()->prepare("SELECT a.*, s.name AS service_name FROM appointments a JOIN services s ON s.id = a.service_id WHERE a.reference_no = ?");
        $stmt->execute([$ref]);
        return $stmt->fetch();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function check_in_appointment(string $reference, ?int $userId = null): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT a.*, u.full_name, u.ic_passport, u.phone, u.email, s.name AS service_name
            FROM appointments a
            JOIN users u ON u.id = a.user_id
            JOIN services s ON s.id = a.service_id
            WHERE a.reference_no = ? FOR UPDATE');
        $stmt->execute([strtoupper(trim($reference))]);
        $appt = $stmt->fetch();
        if (!$appt) {
            // try case insensitive
            $stmt = $pdo->prepare('SELECT a.*, u.full_name, u.ic_passport, u.phone, u.email, s.name AS service_name
                FROM appointments a JOIN users u ON u.id = a.user_id JOIN services s ON s.id = a.service_id
                WHERE UPPER(a.reference_no) = UPPER(?) FOR UPDATE');
            $stmt->execute([trim($reference)]);
            $appt = $stmt->fetch();
        }
        if (!$appt) {
            throw new RuntimeException('Appointment not found.');
        }
        if ($userId && (int)$appt['user_id'] !== $userId) {
            throw new RuntimeException('This appointment does not belong to your account.');
        }
        if ($appt['appointment_date'] !== today()) {
            throw new RuntimeException('Check-in is only allowed on the appointment date (' . $appt['appointment_date'] . ').');
        }
        if (in_array($appt['status'], ['cancelled', 'completed', 'no_show'], true)) {
            throw new RuntimeException('This appointment cannot be checked in (status: ' . $appt['status'] . ').');
        }
        if ($appt['status'] === 'checked_in') {
            throw new RuntimeException('This appointment has already been checked in.');
        }

        // Prevent duplicate ticket
        $stmt = $pdo->prepare("SELECT id FROM queue_tickets WHERE appointment_id = ? LIMIT 1");
        $stmt->execute([$appt['id']]);
        if ($stmt->fetch()) {
            throw new RuntimeException('A queue ticket already exists for this appointment.');
        }

        $stmt = $pdo->prepare("UPDATE appointments SET status='checked_in', updated_at=? WHERE id=?");
        $stmt->execute([now(), $appt['id']]);

        // Create queue ticket in the same transaction
        $date = today();
        $queueNumber = generate_queue_number((int)$appt['service_id'], $date);
        $ticketRef = generate_ref('TKT');
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM queue_tickets WHERE queue_date = ? AND service_id = ? AND status = 'waiting'");
        $stmt->execute([$date, $appt['service_id']]);
        $waiting = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT estimated_duration FROM services WHERE id = ?');
        $stmt->execute([$appt['service_id']]);
        $duration = (int)$stmt->fetchColumn();
        $estimated = ($waiting + 1) * max(5, (int)ceil($duration * 0.7));

        $stmt = $pdo->prepare("INSERT INTO queue_tickets
            (ticket_ref, queue_number, queue_date, visitor_name, visitor_ic, visitor_phone, visitor_email, user_id, service_id, appointment_id, ticket_type, status, estimated_wait)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'appointment', 'waiting', ?)");
        $stmt->execute([
            $ticketRef,
            $queueNumber,
            $date,
            $appt['full_name'],
            $appt['ic_passport'],
            $appt['phone'],
            $appt['email'],
            $appt['user_id'],
            $appt['service_id'],
            $appt['id'],
            $estimated,
        ]);
        $ticketId = (int)$pdo->lastInsertId();
        $stmt = $pdo->prepare('INSERT INTO queue_history (ticket_id, action, old_status, new_status, performed_by, notes) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$ticketId, 'created', null, 'waiting', $userId, 'Office check-in']);

        $pdo->commit();
        return ['appointment' => $appt, 'ticket' => get_ticket_by_id($ticketId)];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/* ===================== DASHBOARD STATS ===================== */

function admin_dashboard_stats(): array
{
    $pdo = db();
    $today = today();
    $stats = [];

    $q = $pdo->prepare("SELECT
        COUNT(*) AS total,
        SUM(status='waiting') AS waiting,
        SUM(status='serving') AS serving,
        SUM(status='completed') AS completed,
        SUM(status='skipped') AS skipped
        FROM queue_tickets WHERE queue_date = ?");
    $q->execute([$today]);
    $row = $q->fetch() ?: [];
    $stats['total_tickets'] = (int)($row['total'] ?? 0);
    $stats['waiting'] = (int)($row['waiting'] ?? 0);
    $stats['serving'] = (int)($row['serving'] ?? 0);
    $stats['completed'] = (int)($row['completed'] ?? 0);
    $stats['skipped'] = (int)($row['skipped'] ?? 0);

    $stats['pending_applications'] = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE status IN ('pending','under_review','additional_docs')")->fetchColumn();
    $stats['total_users'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='public'")->fetchColumn();
    $stats['active_staff'] = (int)$pdo->query("SELECT COUNT(*) FROM staff WHERE is_active=1")->fetchColumn();

    return $stats;
}

function ensure_upload_dir(): void
{
    if (!is_dir(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0755, true);
    }
}

function handle_secure_upload(array $file, int $applicationId): ?array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File upload failed.');
    }
    $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException('Only PDF, JPG and PNG files are allowed.');
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('File size must be under 5MB.');
    }
    ensure_upload_dir();
    $stored = 'doc_' . $applicationId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = UPLOAD_PATH . '/' . $stored;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Unable to save uploaded file.');
    }
    $stmt = db()->prepare('INSERT INTO application_documents (application_id, file_name, original_name, file_type, file_size) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$applicationId, $stored, $file['name'], $ext, (int)$file['size']]);
    return ['file_name' => $stored, 'original_name' => $file['name']];
}

function format_datetime(?string $dt): string
{
    if (!$dt) {
        return '-';
    }
    return date('d M Y, h:i A', strtotime($dt));
}

function format_date(?string $d): string
{
    if (!$d) {
        return '-';
    }
    return date('d M Y', strtotime($d));
}

function format_time(?string $t): string
{
    if (!$t) {
        return '-';
    }
    return date('h:i A', strtotime($t));
}
