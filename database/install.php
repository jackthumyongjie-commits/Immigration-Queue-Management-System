<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_OFF);

$host = '127.0.0.1';
$port = 3307;
$user = 'root';
$pass = '';
$sqlFile = __DIR__ . '/immigration_queue_system.sql';

echo "Connecting to MySQL on {$host}:{$port}...\n";
$mysqli = @new mysqli($host, $user, $pass, '', $port);
if ($mysqli->connect_error) {
    $port = 3306;
    echo "Retrying on port {$port}...\n";
    $mysqli = @new mysqli($host, $user, $pass, '', $port);
    if ($mysqli->connect_error) {
        fwrite(STDERR, 'Connect failed: ' . $mysqli->connect_error . PHP_EOL);
        exit(1);
    }
}

$mysqli->set_charset('utf8mb4');
$mysqli->query('SET SESSION sql_mode = ""');

$sql = file_get_contents($sqlFile);
if ($sql === false) {
    fwrite(STDERR, "Unable to read SQL file.\n");
    exit(1);
}

$sql = preg_replace('/^\s*--.*$/m', '', $sql);
$statements = array_filter(array_map('trim', explode(';', (string)$sql)));

echo 'Executing ' . count($statements) . " statements...\n";
$ok = 0;
foreach ($statements as $i => $statement) {
    if ($statement === '') {
        continue;
    }
    if (!$mysqli->query($statement)) {
        fwrite(STDERR, 'Error on statement #' . ($i + 1) . ': ' . $mysqli->error . PHP_EOL);
        fwrite(STDERR, substr($statement, 0, 200) . "...\n");
        exit(1);
    }
    $ok++;
}

$res = $mysqli->query('SELECT COUNT(*) AS c FROM immigration_queue_system.users');
$row = $res->fetch_assoc();
echo "Import complete ({$ok} statements). Users: {$row['c']}\n";
echo "Connected via port {$port}\n";
$mysqli->close();

file_put_contents(__DIR__ . '/db_port.txt', (string)$port);
