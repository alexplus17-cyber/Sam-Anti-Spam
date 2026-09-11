<?php
namespace SamApi\Controllers;

use PDO;

class ReportController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function handle() {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input) || empty($input['ip']) || empty($input['reason'])) {
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
        $reason = htmlspecialchars(strip_tags($input['reason']));
        
        try {
            // Insert report
            $stmtInsert = $this->pdo->prepare('
                INSERT INTO spam_reports (ip, email, spam_score, created_at)
                VALUES (:ip, :email, 5.00, NOW())
            ');
            $stmtInsert->execute([
                'ip'    => $ip,
                'email' => $email
            ]);

            // Auto-Blacklist check
            $stmtCount = $this->pdo->prepare('
                SELECT COUNT(id) as total_reports 
                FROM spam_reports 
                WHERE ip = :ip
            ');
            $stmtCount->execute(['ip' => $ip]);
            $countResult = $stmtCount->fetch();

            if ($countResult && (int)$countResult['total_reports'] >= 3) {
                // Insert or Update firewall_blacklist for 30 days
                $stmtBlacklist = $this->pdo->prepare('
                    INSERT INTO firewall_blacklist (ip, reason, added_at, expires_at) 
                    VALUES (:ip, :reason, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))
                    ON DUPLICATE KEY UPDATE 
                        reason = :update_reason, 
                        expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY)
                ');
                $stmtBlacklist->execute([
                    'ip'            => $ip,
                    'reason'        => 'Auto-Blacklisted (High Spam Score)',
                    'update_reason' => 'Auto-Blacklisted (High Spam Score)'
                ]);
            }

            http_response_code(200);
            echo json_encode(['status' => 'Report processed successfully']);
        } catch (\Exception $e) {
            error_log('Report Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error']);
        }
    }
}
