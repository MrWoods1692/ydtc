<?php

namespace OAuth\Controller;

use OAuth\Core\Database;
use OAuth\Core\Logger;

class ConsoleController
{
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
     * 获取用户的应用
     */
    public function getUserApp(int $userId): ?array
    {
        return Database::queryOne(
            "SELECT * FROM oauth_clients WHERE user_id = ?",
            [$userId]
        );
    }

    /**
     * 生成客户端ID
     */
    private function generateClientId(): string
    {
        return 'client_' . bin2hex(random_bytes(16));
    }

    /**
     * 生成客户端密钥
     */
    private function generateClientSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * 处理请求
     */
    public function handle(): void
    {
        // 检查用户是否登录
        $user = $this->getCurrentUser();
        
        if (!$user) {
            $this->showLoginPage();
            return;
        }

        // 获取用户的应用
        $app = $this->getUserApp($user['id']);

        // 处理POST请求
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost($user, $app);
            return;
        }

        // 显示控制台页面
        $this->showConsolePage($user, $app);
    }

    /**
     * 处理POST请求
     */
    private function handlePost(array $user, ?array $existingApp): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $action = $_POST['action'] ?? '';

        try {
            switch ($action) {
                case 'create':
                    $this->createApp($user, $existingApp);
                    break;
                case 'update':
                    $this->updateApp($user, $existingApp);
                    break;
                case 'delete':
                    $this->deleteApp($user, $existingApp);
                    break;
                case 'regenerate_secret':
                    $this->regenerateSecret($user, $existingApp);
                    break;
                default:
                    throw new \Exception('无效的操作');
            }
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * 创建应用
     */
    private function createApp(array $user, ?array $existingApp): void
    {
        if ($existingApp) {
            throw new \Exception('您已经创建过应用，每人只能创建一个应用');
        }

        $name = trim($_POST['name'] ?? '');
        $homepage = trim($_POST['homepage'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? '');
        $redirectUri = trim($_POST['redirect_uri'] ?? '');

        if (empty($name) || empty($redirectUri)) {
            throw new \Exception('应用名称和回调地址不能为空');
        }

        if (!filter_var($redirectUri, FILTER_VALIDATE_URL)) {
            throw new \Exception('回调地址格式不正确');
        }

        if (!empty($homepage) && !filter_var($homepage, FILTER_VALIDATE_URL)) {
            throw new \Exception('应用主页格式不正确');
        }

        if (!empty($icon) && !filter_var($icon, FILTER_VALIDATE_URL)) {
            throw new \Exception('应用图标格式不正确');
        }

        $clientId = $this->generateClientId();
        $clientSecret = $this->generateClientSecret();

        Database::insert(
            "INSERT INTO oauth_clients (client_id, client_secret, name, homepage, description, icon, redirect_uri, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$clientId, $clientSecret, $name, $homepage ?: null, $description ?: null, $icon ?: null, $redirectUri, $user['id'], time()]
        );

        // 记录日志
        Logger::writeOperationLog($user['email'], '创建应用', "应用名称: {$name}, Client ID: {$clientId}");

        echo json_encode([
            'success' => true,
            'message' => '应用创建成功',
            'app' => [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'name' => $name,
                'homepage' => $homepage,
                'description' => $description,
                'icon' => $icon,
                'redirect_uri' => $redirectUri
            ]
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 更新应用
     */
    private function updateApp(array $user, ?array $existingApp): void
    {
        if (!$existingApp) {
            throw new \Exception('应用不存在');
        }

        $name = trim($_POST['name'] ?? '');
        $homepage = trim($_POST['homepage'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? '');
        $redirectUri = trim($_POST['redirect_uri'] ?? '');

        if (empty($name) || empty($redirectUri)) {
            throw new \Exception('应用名称和回调地址不能为空');
        }

        if (!filter_var($redirectUri, FILTER_VALIDATE_URL)) {
            throw new \Exception('回调地址格式不正确');
        }

        if (!empty($homepage) && !filter_var($homepage, FILTER_VALIDATE_URL)) {
            throw new \Exception('应用主页格式不正确');
        }

        if (!empty($icon) && !filter_var($icon, FILTER_VALIDATE_URL)) {
            throw new \Exception('应用图标格式不正确');
        }

        Database::execute(
            "UPDATE oauth_clients SET name = ?, homepage = ?, description = ?, icon = ?, redirect_uri = ? WHERE user_id = ?",
            [$name, $homepage ?: null, $description ?: null, $icon ?: null, $redirectUri, $user['id']]
        );

        // 记录日志
        Logger::writeOperationLog($user['email'], '更新应用', "应用名称: {$name}");

        echo json_encode([
            'success' => true,
            'message' => '应用更新成功'
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 删除应用
     */
    private function deleteApp(array $user, ?array $existingApp): void
    {
        if (!$existingApp) {
            throw new \Exception('应用不存在');
        }

        $appName = $existingApp['name'];
        $clientId = $existingApp['client_id'];

        Database::execute(
            "DELETE FROM oauth_clients WHERE user_id = ?",
            [$user['id']]
        );

        // 同时删除相关的令牌
        Database::execute(
            "DELETE FROM oauth_access_tokens WHERE client_id = ?",
            [$clientId]
        );

        // 记录日志
        Logger::writeOperationLog($user['email'], '删除应用', "应用名称: {$appName}, Client ID: {$clientId}");

        echo json_encode([
            'success' => true,
            'message' => '应用已删除'
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 重新生成密钥
     */
    private function regenerateSecret(array $user, ?array $existingApp): void
    {
        if (!$existingApp) {
            throw new \Exception('应用不存在');
        }

        $newSecret = $this->generateClientSecret();

        Database::execute(
            "UPDATE oauth_clients SET client_secret = ? WHERE user_id = ?",
            [$newSecret, $user['id']]
        );

        // 记录日志
        Logger::writeOperationLog($user['email'], '重新生成密钥', "Client ID: {$existingApp['client_id']}");

        echo json_encode([
            'success' => true,
            'message' => '密钥已重新生成',
            'client_secret' => $newSecret
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 显示登录提示页面
     */
    private function showLoginPage(): void
    {
        require __DIR__ . '/../../views/login_required.phtml';
    }

    /**
     * 显示控制台页面
     */
    private function showConsolePage(array $user, ?array $app): void
    {
        $userEmail = htmlspecialchars($user['email']);
        $appData = $app ? [
            'client_id' => htmlspecialchars($app['client_id']),
            'client_secret' => htmlspecialchars($app['client_secret']),
            'name' => htmlspecialchars($app['name']),
            'homepage' => htmlspecialchars($app['homepage'] ?? ''),
            'description' => htmlspecialchars($app['description'] ?? ''),
            'icon' => htmlspecialchars($app['icon'] ?? ''),
            'redirect_uri' => htmlspecialchars($app['redirect_uri']),
            'created_at' => $app['created_at']
        ] : null;
        
        require __DIR__ . '/../../views/console.phtml';
    }
}