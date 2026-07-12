<?php
namespace SamApi\Middleware;

use PDO;

class AuthMiddleware {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Authenticates the request using the Authorization header.
     * Expected format: Authorization: Bearer <token>
     *
     * @return bool True if authenticated, false otherwise.
     */
    public function authenticate(): bool {
        $headers = getallheaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

        if (empty($authHeader)) {
            // Nginx might use HTTP_AUTHORIZATION
            $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
        }

        if (empty($authHeader) || strpos($authHeader, 'Bearer ') !== 0) {
            return false;
        }

        $token = substr($authHeader, 7);
        $hashedToken = hash('sha256', $token);

        $stmt = $this->pdo->prepare('SELECT id FROM api_keys WHERE api_key_hash = :hash AND is_active = 1 LIMIT 1');
        $stmt->execute(['hash' => $hashedToken]);

        return $stmt->fetch() !== false;
    }
}
