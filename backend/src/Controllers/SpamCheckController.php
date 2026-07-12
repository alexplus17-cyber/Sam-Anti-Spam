<?php
namespace SamApi\Controllers;

use PDO;

class SpamCheckController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function handle() {
        // 1. Input Parsing & Validation
        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input) || empty($input['ip']) || empty($input['action_type'])) {
            http_response_code(422);
            echo json_encode(['error' => 'Unprocessable Entity: Missing required fields']);
            return;
        }

        $ip = filter_var($input['ip'], FILTER_VALIDATE_IP);
        if (!$ip) {
            http_response_code(422);
            echo json_encode(['error' => 'Unprocessable Entity: Invalid IP address']);
            return;
        }

        $email = isset($input['email']) ? filter_var($input['email'], FILTER_SANITIZE_EMAIL) : '';

        // 2. Spam Detection Logic

        // Check 1: Hard Block (Firewall Blacklist)
        $stmtBlacklist = $this->pdo->prepare('
            SELECT reason FROM firewall_blacklist
            WHERE ip = :ip AND (expires_at IS NULL OR expires_at > NOW())
            LIMIT 1
        ');
        $stmtBlacklist->execute(['ip' => $ip]);
        $blacklistEntry = $stmtBlacklist->fetch();

        if ($blacklistEntry) {
            http_response_code(200);
            echo json_encode([
                'spam' => true,
                'reason' => 'Blacklisted IP'
            ]);
            return;
        }

        // Check 2: Reputation Check (Spam Reports)
        if (!empty($email)) {
            $stmtReputation = $this->pdo->prepare('
                SELECT COUNT(id) as report_count FROM spam_reports
                WHERE email = :email
            ');
            $stmtReputation->execute(['email' => $email]);
            $reputation = $stmtReputation->fetch();

            if ($reputation && (int)$reputation['report_count'] >= 5) {
                http_response_code(200);
                echo json_encode([
                    'spam' => true,
                    'reason' => 'Spam Email Reputation'
                ]);
                return;
            }
        }

        // Check 3: Pass (Ham)
        http_response_code(200);
        echo json_encode([
            'spam' => false
        ]);
    }
}
