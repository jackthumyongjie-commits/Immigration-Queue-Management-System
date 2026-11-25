<?php
declare(strict_types=1);

// Copy this file to database.php and fill in YOUR values.
// Do not commit database.php if it contains real production secrets.

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'Immigration Queue Management System');
define('APP_SHORT', 'IQMS');
define('BASE_PATH', dirname(__DIR__));

$docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
$appRoot = str_replace('\\', '/', BASE_PATH);
$webBase = '';
if ($docRoot !== '' && str_starts_with($appRoot, $docRoot)) {
    $webBase = substr($appRoot, strlen($docRoot));
}
define('BASE_URL', str_replace(' ', '%20', rtrim($webBase, '/')));

define('UPLOAD_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'applications');
define('SESSION_TIMEOUT', 3600);
define('CSRF_TOKEN_NAME', '_csrf_token');

date_default_timezone_set('Asia/Kuala_Lumpur');

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        http_response_code(500);
        die('Database connection failed. Check config/database.php and import the SQL file.');
    }

    return $pdo;
}
