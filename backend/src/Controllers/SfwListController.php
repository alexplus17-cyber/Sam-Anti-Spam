<?php
namespace SamApi\Controllers;

use PDO;

class SfwListController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function handle() {
        // Critical: Override global JSON header for this specific route
        header('Content-Type: text/plain; charset=utf-8');

        $stmt = $this->pdo->query('
            SELECT ip FROM firewall_blacklist 
            WHERE expires_at IS NULL OR expires_at > NOW()
        ');

        // Fetch exactly as requested: raw list separated by newlines
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo $row['ip'] . "\n";
        }
    }
}
