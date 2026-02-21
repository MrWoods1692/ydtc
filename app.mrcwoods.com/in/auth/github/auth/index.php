<?php
/**
 * Github OAuth2 授权入口文件
 * PHP 8.1+
 */

// 加载环境变量
$env = parse_ini_file('../../../.env');
if (!$env) {
    die('环境配置文件 .env 加载失败');
}

// Github OAuth2 配置
$config = [
    'client_id'     => '',
    'client_secret' => '',
    'redirect_uri'  => 'https://app.mrcwoods.com/in/auth/github/callback',
    'auth_endpoint' => 'https://github.com/login/oauth/authorize',
    'scope'         => 'user:email', // 仅请求邮箱权限（最小权限原则）
    'state'         => bin2hex(random_bytes(16)), // 防CSRF随机值
];

// 存储state到session（回调验证用）
session_start();
$_SESSION['github_oauth_state'] = $config['state'];

// 构建Github授权URL
$authUrl = $config['auth_endpoint'] . '?' . http_build_query([
    'client_id'     => $config['client_id'],
    'redirect_uri'  => $config['redirect_uri'],
    'scope'         => $config['scope'],
    'state'         => $config['state'],
    'allow_signup'  => 'true', // 允许新用户注册
]);

// 跳转到Github授权页面
header('Location: ' . $authUrl);
exit;
?>