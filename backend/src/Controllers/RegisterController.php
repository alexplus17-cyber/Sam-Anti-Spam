<?php
namespace SamApi\Controllers;

use PDO;

class RegisterController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function handle() {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input) || empty($input['site_url']) || empty($input['admin_email'])) {
            http_response_code(422);
            echo json_encode(['error' => 'Unprocessable Entity: Missing required fields']);
            return;
        }

        $site_url = filter_var($input['site_url'], FILTER_VALIDATE_URL);
        if (!$site_url) {
            http_response_code(422);
            echo json_encode(['error' => 'Unprocessable Entity: Invalid URL']);
            return;
        }

        $admin_email = filter_var($input['admin_email'], FILTER_VALIDATE_EMAIL);
        if (!$admin_email) {
            http_response_code(422);
            echo json_encode(['error' => 'Unprocessable Entity: Invalid Email']);
            return;
        }

        try {
            // Generate cryptographically secure 64-char API key
            $plainTextKey = bin2hex(random_bytes(32));
            $hashedKey = hash('sha256', $plainTextKey);

            // Insert into api_keys table
            $stmt = $this->pdo->prepare('
                INSERT INTO api_keys (website_domain, api_key_hash, is_active)
                VALUES (:domain, :hash, 1)
                ON DUPLICATE KEY UPDATE
                    api_key_hash = :update_hash,
                    is_active = 1
            ');

            $stmt->execute([
                'domain'      => $site_url,
                'hash'        => $hashedKey,
                'update_hash' => $hashedKey
            ]);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'api_key' => $plainTextKey
            ]);

        } catch (\Exception $e) {
            error_log('Registration Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error']);
        }
    }
}
