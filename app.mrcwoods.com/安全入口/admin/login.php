<?php
session_start();
require_once 'security_headers.php';
require_once 'EnvHelper.php';
require_once 'JwtHelper.php';

// XSS过滤（优化版）
function xssFilter(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// 处理登录
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateRequestOrigin(); // 验证请求来源
    // CSRF校验
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = '非法请求（CSRF验证失败）';
    } else {
        // 读取正确的账号密码（修复核心：使用新的EnvHelper）
        $auth = EnvHelper::getAuth();
        $correctName = $auth['username'];
        $correctPass = $auth['password'];
        
        $username = xssFilter(trim($_POST['username'] ?? ''));
        $password = trim($_POST['password'] ?? '');
        
        // 调试：打印对比（上线前删除）
        // var_dump('输入：'.$username, '正确：'.$correctName, '输入密码：'.$password, '正确密码：'.$correctPass);
        
        if ($username === $correctName && $password === $correctPass) {
            $token = JwtHelper::generateToken(['username' => $username, 'role' => 'admin']);
            // 安全设置Cookie
            setcookie(
                'admin_token',
                $token,
                [
                    'expires' => time() + 3600,
                    'path' => '/',
                    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]
            );
            header('Location: index.php');
            exit;
        } else {
            $error = '账号或密码错误';
        }
    }
}

// 生成CSRF Token
$csrfToken = bin2hex(random_bytes(16)) . '_' . time();
$_SESSION['csrf_token'] = $csrfToken;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>云端图片储存 - 登录</title>
    <style>
        /* 苹果风格终极美化 + 动画 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif;
        }
        body {
            background: linear-gradient(135deg, #f5f5f7 0%, #e8e8ed 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            padding: 50px 40px;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            width: 100%;
            max-width: 420px;
            animation: fadeIn 0.5s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .logo {
            text-align: center;
            margin-bottom: 35px;
        }
        .logo img {
            width: 68px;
            height: 68px;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .logo img:hover {
            transform: scale(1.05);
        }
        .logo h1 {
            font-size: 24px;
            color: #1d1d1f;
            margin-top: 20px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .form-group {
            margin-bottom: 24px;
        }
        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #1d1d1f;
            font-size: 14px;
            font-weight: 500;
            letter-spacing: 0.3px;
        }
        .form-group input {
            width: 100%;
            padding: 14px 18px;
            border: 1px solid #e0e0e5;
            border-radius: 12px;
            font-size: 16px;
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.2s ease;
        }
        .form-group input:focus {
            outline: none;
            border-color: #0071e3;
            box-shadow: 0 0 0 4px rgba(0, 113, 227, 0.1);
            background: white;
        }
        .error {
            color: #ff3b30;
            font-size: 14px;
            text-align: center;
            margin-bottom: 24px;
            padding: 10px;
            border-radius: 8px;
            background: rgba(255, 59, 48, 0.08);
            animation: shake 0.3s ease;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #0071e3 0%, #0077ed 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(0, 113, 227, 0.2);
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 113, 227, 0.3);
        }
        .btn-login:active {
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <img src="../../images/logo.png" alt="云端图片储存">
            <h1>云端图片储存管理后台</h1>
        </div>
        <?php if ($error): ?>
            <div class="error"><?= xssFilter($error) ?></div>
        <?php endif; ?>
        <form method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= xssFilter($csrfToken) ?>">
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" required autocomplete="off">
            </div>
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" required autocomplete="off">
            </div>
            <button type="submit" class="btn-login">登录</button>
        </form>
    </div>
</body>
</html>
