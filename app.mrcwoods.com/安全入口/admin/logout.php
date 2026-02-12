<?php
session_start();
require_once 'security_headers.php';

// 强制清除所有认证信息
setcookie('admin_token', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Strict'
]);
// 销毁Session
session_unset();
session_destroy();
session_write_close();
// 跳登录页
header('Location: login.php');
exit;
