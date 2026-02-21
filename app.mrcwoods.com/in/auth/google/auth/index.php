<?php
/**
 * Google OAuth2 授权入口文件
 * PHP 8.1+
 */

// 加载环境变量
$env = parse_ini_file('../../../.env');
if (!$env) {
    die('环境配置文件 .env 加载失败');
}

// 加载Google配置（也可以直接使用硬编码配置，二选一）
$googleConfigFile = __DIR__ . '/.json';
if (file_exists($googleConfigFile)) {
    $googleConfig = json_decode(file_get_contents($googleConfigFile), true)['web'];
} else {
    // 备用：直接使用提供的配置信息
    $googleConfig = [
        'client_id'     => '',
        'client_secret' => '',
        'auth_uri'      => 'https://accounts.google.com/o/oauth2/auth',
        'redirect_uris' => ['https://app.mrcwoods.com/in/auth/google/callback'],
    ];
}

// OAuth2 配置
$config = [
    'client_id'     => $googleConfig['client_id'],
    'client_secret' => $googleConfig['client_secret'],
    'redirect_uri'  => $googleConfig['redirect_uris'][0],
    'auth_endpoint' => $googleConfig['auth_uri'],
    'scope'         => 'openid email profile', // 请求邮箱和基本信息权限
    'state'         => bin2hex(random_bytes(16)), // 防CSRF随机值
    'access_type'   => 'online', // 在线授权（无需刷新令牌）
    'response_type' => 'code',
    'prompt'        => 'select_account', // 强制用户选择账号
];

// 存储state到session（回调验证用）
session_start();
$_SESSION['google_oauth_state'] = $config['state'];

// 构建Google授权URL
$authUrl = $config['auth_endpoint'] . '?' . http_build_query([
    'response_type' => $config['response_type'],
    'client_id'     => $config['client_id'],
    'redirect_uri'  => $config['redirect_uri'],
    'scope'         => $config['scope'],
    'state'         => $config['state'],
    'access_type'   => $config['access_type'],
    'prompt'        => $config['prompt'],
    'include_granted_scopes' => 'true',
]);

// 跳转到Google授权页面
header('Location: ' . $authUrl);
exit;
?>