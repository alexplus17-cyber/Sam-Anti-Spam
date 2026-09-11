<?php

// 1. Error Reporting
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// 2. Autoloading and Config
require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config.php';

// 3. CORS and Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Preflight Handling
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // 4. PDO Connection
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['db_name']};charset={$config['db']['charset']}";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // 5. FastRoute Setup
    $dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) {
        $r->addRoute('POST', '/v1/check', 'check_spam_handler');
        $r->addRoute('GET', '/v1/sfw-list', 'sfw_list_handler');
        $r->addRoute('POST', '/v1/report', 'report_handler');
        $r->addRoute('POST', '/v1/register', 'register_handler');
    });

    // 6. Dispatch Logic
    $httpMethod = $_SERVER['REQUEST_METHOD'];
    $uri = $_SERVER['REQUEST_URI'];

    // Strip query string (?foo=bar) and decode URI
    if (false !== $pos = strpos($uri, '?')) {
        $uri = substr($uri, 0, $pos);
    }
    $uri = rawurldecode($uri);

    // Strip the script's base directory so routes match when served from a subpath
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    if ($scriptDir !== '' && $scriptDir !== '/' && strpos($uri, $scriptDir) === 0) {
        $uri = substr($uri, strlen($scriptDir));
    }
    if ($uri === '' || $uri === false) {
        $uri = '/';
    }

    $routeInfo = $dispatcher->dispatch($httpMethod, $uri);

    // 7. Route Handling
    switch ($routeInfo[0]) {
        case FastRoute\Dispatcher::NOT_FOUND:
            http_response_code(404);
            echo json_encode(['error' => 'Not Found']);
            break;
        case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
            $allowedMethods = $routeInfo[1];
            http_response_code(405);
            header("Allow: " . implode(', ', $allowedMethods));
            echo json_encode(['error' => 'Method Not Allowed']);
            break;
        case FastRoute\Dispatcher::FOUND:
            $handler = $routeInfo[1];
            $vars = $routeInfo[2];
            
            if ($handler === 'check_spam_handler') {
                $auth = new \SamApi\Middleware\AuthMiddleware($pdo);
                if (!$auth->authenticate()) {
                    http_response_code(401);
                    echo json_encode(['error' => 'Unauthorized']);
                    break;
                }

                $controller = new \SamApi\Controllers\SpamCheckController($pdo);
                $controller->handle();

            } elseif ($handler === 'sfw_list_handler') {
                $auth = new \SamApi\Middleware\AuthMiddleware($pdo);
                if (!$auth->authenticate()) {
                    http_response_code(401);
                    echo json_encode(['error' => 'Unauthorized']);
                    break;
                }

                $controller = new \SamApi\Controllers\SfwListController($pdo);
                $controller->handle();

            } elseif ($handler === 'report_handler') {
                $auth = new \SamApi\Middleware\AuthMiddleware($pdo);
                if (!$auth->authenticate()) {
                    http_response_code(401);
                    echo json_encode(['error' => 'Unauthorized']);
                    break;
                }

                $controller = new \SamApi\Controllers\ReportController($pdo);
                $controller->handle();

            } elseif ($handler === 'register_handler') {
                // Public endpoint, no AuthMiddleware
                $controller = new \SamApi\Controllers\RegisterController($pdo);
                $controller->handle();

            } else {
                http_response_code(200);
                echo json_encode([
                    'status' => 'Route connected successfully',
                    'route'  => $handler
                ]);
            }
            break;
    }

} catch (PDOException $e) {
    // Note: Do not expose $e->getMessage() in production
    error_log("Database Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error']);
} catch (Exception $e) {
    error_log("General Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error']);
}
