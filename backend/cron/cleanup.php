<?php
// CRON: Daily Database Cleanup
$config = require __DIR__ . '/../config.php';

try {
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['db_name']};charset={$config['db']['charset']}";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Delete expired IPs from the firewall blacklist
    $stmt = $pdo->prepare('DELETE FROM firewall_blacklist WHERE expires_at < NOW()');
    $stmt->execute();

    echo "Cleanup successful. Removed " . $stmt->rowCount() . " expired IPs.\n";

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    exit(1);
}
