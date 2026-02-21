<?php
/**
 * Linux DO OAuth2 授权入口文件
 * PHP 8.1+
 */

// 加载环境变量

if (!$env) {
    $env = parse_ini_file('../../../.env');
}

// Linux DO OAuth2 配置
$config = [
    'client_id'     => '',
    'client_secret' => '',
    'redirect_uri'  => 'https://app.mrcwoods.com/in/auth/linuxdo/callback',
    'auth_endpoint' => 'https://connect.linux.do/oauth2/authorize',
    'scope'         => 'openid email profile', // 请求的权限范围
    'state'         => bin2hex(random_bytes(16)), // 防CSRF攻击的随机状态值
];

// 存储state到session（用于回调验证）
session_start();
$_SESSION['linuxdo_oauth_state'] = $config['state'];

// 构建授权URL
$authUrl = $config['auth_endpoint'] . '?' . http_build_query([
    'response_type' => 'code',
    'client_id'     => $config['client_id'],
    'redirect_uri'  => $config['redirect_uri'],
    'scope'         => $config['scope'],
    'state'         => $config['state'],
    'response_mode' => 'query',
]);

// 跳转到Linux DO授权页面
header('Location: ' . $authUrl);
exit;
?>