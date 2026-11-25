<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';

$pdo = db();
$pdo->beginTransaction();
try {
    $pdo->exec('DELETE FROM queue_history');
    $pdo->exec('DELETE FROM queue_tickets');
    $pdo->exec("UPDATE counters SET status = IF(is_active = 1, 'available', 'offline')");
    // Revert checked-in appointments that still have no ticket back to confirmed if needed
    $pdo->exec("UPDATE appointments SET status = 'confirmed' WHERE status = 'checked_in' AND appointment_date = CURDATE()");
    $pdo->commit();
    echo "OK: All queue tickets deleted. Counters reset to available.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'FAILED: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$left = (int)$pdo->query('SELECT COUNT(*) FROM queue_tickets')->fetchColumn();
echo "Remaining tickets: {$left}\n";
