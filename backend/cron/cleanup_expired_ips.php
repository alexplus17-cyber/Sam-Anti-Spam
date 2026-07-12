<?php
// CRON: Daily Database Cleanup
ini_set('memory_limit', '50M');

$config = require __DIR__ . '/../config.php';

try {
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['db_name']};charset={$config['db']['charset']}";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Delete expired IPs from the firewall blacklist
    $stmt = $pdo->prepare('DELETE FROM firewall_blacklist WHERE expires_at IS NOT NULL AND expires_at < NOW()');
    $stmt->execute();

    $count = $stmt->rowCount();
    echo "[" . date('Y-m-d H:i:s') . "] Cleanup complete. Rows deleted: " . $count . "\n";

} catch (PDOException $e) {
    error_log("Database Cleanup Error: " . $e->getMessage());
    echo "[" . date('Y-m-d H:i:s') . "] Cleanup failed. See error log.\n";
    exit(1);
}
