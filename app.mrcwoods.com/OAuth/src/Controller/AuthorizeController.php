<?php

namespace OAuth\Controller;

use OAuth\Core\Database;
use OAuth\Core\Logger;
use OAuth\Core\OAuthException;
use OAuth\OAuth\AuthorizationServer;

class AuthorizeController
{
    private AuthorizationServer $server;

    public function __construct()
    {
        $this->server = new AuthorizationServer();
    }

    /**
     * 获取当前登录用户
     */
    public function getCurrentUser(): ?array
    {
        $token = $_COOKIE['user_token'] ?? null;
        
        if (!$token) {
            return null;
        }

        $user = Database::queryOne(
            "SELECT id, email, created_at FROM users WHERE token = ?",
            [$token]
        );

        return $user;
    }

    /**
     * 处理授权请求
     */
    public function handle(): void
    {
        try {
            // 获取请求参数
            $clientId = $_GET['client_id'] ?? null;
            $redirectUri = $_GET['redirect_uri'] ?? null;
            $responseType = $_GET['response_type'] ?? null;
            $state = $_GET['state'] ?? null;

            // 验证必要参数
            if (!$clientId || !$redirectUri || !$responseType) {
                throw new OAuthException(
                    OAuthException::INVALID_REQUEST,
                    '缺少必要参数: client_id, redirect_uri, response_type',
                    400
                );
            }

            // 验证response_type
            if ($responseType !== 'code') {
                throw new OAuthException(
                    OAuthException::UNSUPPORTED_RESPONSE_TYPE,
                    '不支持的response_type，仅支持code',
                    400
                );
            }

            // 验证客户端
            $client = $this->server->validateClient($clientId, $redirectUri);

            // 检查用户是否登录
            $user = $this->getCurrentUser();
            if (!$user) {
                // 未登录，返回错误或重定向到登录页面
                $errorUrl = $redirectUri . (strpos($redirectUri, '?') !== false ? '&' : '?');
                $errorUrl .= 'error=login_required&error_description=' . urlencode('用户未登录');
                if ($state) {
                    $errorUrl .= '&state=' . urlencode($state);
                }
                header('Location: ' . $errorUrl);
                exit;
            }

            // 检查是否是POST请求（用户提交授权）
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->handleAuthorizationSubmit($client, $user, $redirectUri, $state);
                return;
            }

            // 显示授权确认页面
            $this->showAuthorizePage($client, $user, $state);

        } catch (OAuthException $e) {
            // 对于授权端点，如果可以重定向则重定向
            if (isset($redirectUri)) {
                $errorUrl = $redirectUri . (strpos($redirectUri, '?') !== false ? '&' : '?');
                $errorUrl .= 'error=' . $e->getError() . '&error_description=' . urlencode($e->getErrorDescription());
                if (isset($state)) {
                    $errorUrl .= '&state=' . urlencode($state);
                }
                header('Location: ' . $errorUrl);
                exit;
            }
            $e->sendResponse();
        }
    }

    /**
     * 显示授权确认页面
     */
    private function showAuthorizePage(array $client, array $user, ?string $state): void
    {
        $app = [
            'name' => htmlspecialchars($client['name']),
            'description' => htmlspecialchars($client['description'] ?? '暂无描述'),
            'icon' => htmlspecialchars($client['icon'] ?? ''),
            'homepage' => htmlspecialchars($client['homepage'] ?? ''),
            'client_id' => htmlspecialchars($client['client_id']),
            'redirect_uri' => htmlspecialchars($client['redirect_uri']),
        ];
        
        $userEmail = htmlspecialchars($user['email']);
        $stateValue = $state ? htmlspecialchars($state) : '';
        
        require __DIR__ . '/../../views/authorize.phtml';
    }

    /**
     * 处理用户授权提交
     */
    private function handleAuthorizationSubmit(array $client, array $user, string $redirectUri, ?string $state): void
    {
        $action = $_POST['action'] ?? null;

        if ($action === 'deny') {
            // 用户拒绝授权
            // 记录日志
            Logger::logAuthorization(
                $user['email'], 
                '拒绝授权', 
                "应用: {$client['name']} (Client ID: {$client['client_id']})"
            );

            // 记录到数据库
            $this->logAuthorizationToDb($client['client_id'], $user['id'], 'deny');

            $errorUrl = $redirectUri . (strpos($redirectUri, '?') !== false ? '&' : '?');
            $errorUrl .= 'error=access_denied&error_description=' . urlencode('用户拒绝授权');
            if ($state) {
                $errorUrl .= '&state=' . urlencode($state);
            }
            header('Location: ' . $errorUrl);
            exit;
        }

        if ($action === 'allow') {
            // 用户同意授权，生成授权码
            $code = $this->server->generateAuthCode($client['client_id'], $user['id'], $redirectUri);

            // 记录日志
            Logger::logAuthorization(
                $user['email'], 
                '同意授权', 
                "应用: {$client['name']} (Client ID: {$client['client_id']}), 授权码: {$code}"
            );

            // 记录到数据库
            $this->logAuthorizationToDb($client['client_id'], $user['id'], 'authorize');

            // 重定向到客户端的回调地址
            $callbackUrl = $redirectUri . (strpos($redirectUri, '?') !== false ? '&' : '?');
            $callbackUrl .= 'code=' . $code;
            if ($state) {
                $callbackUrl .= '&state=' . urlencode($state);
            }
            header('Location: ' . $callbackUrl);
            exit;
        }

        throw new OAuthException(
            OAuthException::INVALID_REQUEST,
            '无效的授权操作',
            400
        );
    }

    /**
     * 记录授权日志到数据库
     */
    private function logAuthorizationToDb(string $clientId, int $userId, string $action): void
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $userAgent = $userAgent ? substr($userAgent, 0, 512) : null;

        Database::insert(
            "INSERT INTO oauth_authorization_logs (client_id, user_id, action, ip, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?)",
            [$clientId, $userId, $action, $ip, $userAgent, time()]
        );
    }
}