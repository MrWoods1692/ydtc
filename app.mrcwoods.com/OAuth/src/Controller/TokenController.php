<?php

namespace OAuth\Controller;

use OAuth\Core\OAuthException;
use OAuth\OAuth\AuthorizationServer;

class TokenController
{
    private AuthorizationServer $server;

    public function __construct()
    {
        $this->server = new AuthorizationServer();
    }

    /**
     * 处理令牌请求
     */
    public function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            // 仅支持POST请求
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new OAuthException(
                    OAuthException::INVALID_REQUEST,
                    '仅支持POST请求',
                    405
                );
            }

            // 获取请求参数
            $grantType = $_POST['grant_type'] ?? null;
            $clientId = $_POST['client_id'] ?? null;
            $clientSecret = $_POST['client_secret'] ?? null;

            // 验证必要参数
            if (!$grantType || !$clientId || !$clientSecret) {
                throw new OAuthException(
                    OAuthException::INVALID_REQUEST,
                    '缺少必要参数: grant_type, client_id, client_secret',
                    400
                );
            }

            // 验证客户端凭证
            $client = $this->server->validateClientSecret($clientId, $clientSecret);

            // 处理不同授权类型
            switch ($grantType) {
                case 'authorization_code':
                    $response = $this->handleAuthorizationCodeGrant($clientId);
                    break;
                default:
                    throw new OAuthException(
                        OAuthException::UNSUPPORTED_GRANT_TYPE,
                        '不支持的grant_type: ' . $grantType,
                        400
                    );
            }

            // 返回成功响应
            echo json_encode($response, JSON_UNESCAPED_UNICODE);

        } catch (OAuthException $e) {
            http_response_code($e->getCode());
            echo json_encode($e->toArray(), JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * 处理授权码授权
     */
    private function handleAuthorizationCodeGrant(string $clientId): array
    {
        $code = $_POST['code'] ?? null;
        $redirectUri = $_POST['redirect_uri'] ?? null;

        if (!$code) {
            throw new OAuthException(
                OAuthException::INVALID_REQUEST,
                '缺少授权码: code',
                400
            );
        }

        // 验证授权码
        $authCode = $this->server->validateAuthCode($code, $clientId);

        // 验证redirect_uri是否匹配
        if ($redirectUri && $authCode['redirect_uri'] !== $redirectUri) {
            throw new OAuthException(
                OAuthException::INVALID_REQUEST,
                'redirect_uri不匹配',
                400
            );
        }

        // 生成访问令牌
        return $this->server->generateAccessToken($clientId, $authCode['user_id']);
    }
}