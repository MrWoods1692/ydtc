<?php
session_start();
// 引入必要的类（修复JWT类未找到问题）
require_once 'EnvHelper.php';
require_once 'JwtHelper.php';
require_once 'security_headers.php';

// 仅允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

// CSRF校验
if (!isset($_POST['alias']) || !isset($_SERVER['HTTP_X_CSRF_TOKEN']) || 
    $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    die('CSRF验证失败');
}

// 验证登录（使用新的JwtHelper）
$token = $_COOKIE['admin_token'] ?? '';
if (empty($token) || !JwtHelper::verifyToken($token)) {
    http_response_code(401);
    die('未登录');
}

// URL别名映射（核心：隐藏真实路径）
function getRealUrl(string $alias): string {
    $urlMap = [
        'config' => '../config/index.php',
        'tools' => '../../tools/admin.php',
        'logs' => '../logs/index.php',
        'users' => '../users/index.php',
        'announcement' => '../../announcement/admin.php',
        'mall' => '../../mall/admin.php',
        'change_password' => 'change_password.php',
        'frontend' => '../../main/'
    ];
    return $urlMap[$alias] ?? '';
}

// 获取真实URL（仅返回给已登录的同域请求）
$alias = trim($_POST['alias'] ?? '');
echo getRealUrl($alias);
