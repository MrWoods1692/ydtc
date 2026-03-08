<?php

require_once __DIR__ . '/../vendor/autoload.php';

use OAuth\Core\Config;
use OAuth\Core\OAuthException;
use OAuth\Controller\ConsoleController;

// 加载配置
Config::load(__DIR__ . '/../.env');

try {
    $controller = new ConsoleController();
    $controller->handle();
} catch (OAuthException $e) {
    $e->sendResponse();
} catch (\Throwable $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'error' => 'server_error',
        'error_description' => '服务器内部错误'
    ], JSON_UNESCAPED_UNICODE);
}