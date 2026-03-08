<?php

namespace OAuth\Controller;

use OAuth\Core\Database;
use OAuth\Core\OAuthException;
use OAuth\OAuth\AuthorizationServer;

class ResourceController
{
    private AuthorizationServer $server;

    public function __construct()
    {
        $this->server = new AuthorizationServer();
    }

    /**
     * 处理资源请求（获取用户信息）
     */
    public function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            // 获取Authorization头
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            
            if (!preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
                throw new OAuthException(
                    OAuthException::INVALID_REQUEST,
                    '缺少或无效的Authorization头',
                    401
                );
            }

            $accessToken = $matches[1];

            // 验证访问令牌
            $token = $this->server->validateAccessToken($accessToken);

            // 获取用户信息
            $user = Database::queryOne(
                "SELECT id, email, created_at FROM users WHERE id = ?",
                [$token['user_id']]
            );

            if (!$user) {
                throw new OAuthException(
                    OAuthException::INVALID_GRANT,
                    '用户不存在',
                    401
                );
            }

            // 返回用户信息
            echo json_encode([
                'user_id' => (int) $user['id'],
                'email' => $user['email'],
                'created_at' => (int) $user['created_at']
            ], JSON_UNESCAPED_UNICODE);

        } catch (OAuthException $e) {
            http_response_code($e->getCode());
            echo json_encode($e->toArray(), JSON_UNESCAPED_UNICODE);
        }
    }
}