<?php
session_start();
require_once 'security_headers.php';
require_once 'JWT.php';

// 验证登录
$token = $_COOKIE['admin_token'] ?? '';
if (empty($token) || !JWT::verifyToken($token)) {
    header('Location: login.php');
    exit;
}

// XSS过滤
function xssFilter(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// 处理密码修改
$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateRequestOrigin();
    // CSRF校验
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = '非法请求（CSRF验证失败）';
        $messageType = 'error';
    } else {
        // 读取原密码
        $envPath = '../../in/.env';
        $content = file_get_contents($envPath);
        preg_match('/PASS=(.*)/', $content, $oldPass);
        $oldPass = trim($oldPass[1] ?? '');

        $inputOldPass = trim($_POST['old_password'] ?? '');
        $newPass = trim($_POST['new_password'] ?? '');
        $confirmPass = trim($_POST['confirm_password'] ?? '');

        // 验证逻辑（强化）
        if ($inputOldPass !== $oldPass) {
            $message = '原密码错误';
            $messageType = 'error';
        } elseif (strlen($newPass) < 8 || !preg_match('/[a-zA-Z0-9]/', $newPass)) {
            $message = '新密码至少8位，包含字母/数字';
            $messageType = 'error';
        } elseif ($newPass !== $confirmPass) {
            $message = '两次输入的新密码不一致';
            $messageType = 'error';
        } else {
            // 安全修改.env（加锁防止并发）
            $tempFile = $envPath . '.tmp';
            $newContent = preg_replace('/PASS=(.*)/', "PASS={$newPass}", $content);
            if (file_put_contents($tempFile, $newContent) && rename($tempFile, $envPath)) {
                $message = '密码修改成功！请重新登录';
                $messageType = 'success';
                // 清除Token
                setcookie('admin_token', '', [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
                // 3秒后跳登录页
                echo '<script>setTimeout(() => window.parent.location.href="login.php", 3000)</script>';
            } else {
                $message = '密码修改失败，请检查.env文件权限';
                $messageType = 'error';
            }
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
    <title>修改密码 - 云端图片储存</title>
    <style>
        /* 适配标签页的苹果风格 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", sans-serif;
        }
        body {
            background-color: #f5f5f7;
            padding: 40px;
        }
        .container {
            max-width: 500px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            animation: fadeIn 0.5s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        h2 {
            font-size: 22px;
            color: #1d1d1f;
            margin-bottom: 30px;
            font-weight: 600;
            text-align: center;
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
        }
        .message {
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 24px;
            text-align: center;
            font-size: 14px;
            animation: shake 0.3s ease;
        }
        .message.error {
            background: rgba(255, 59, 48, 0.08);
            color: #ff3b30;
            border: 1px solid rgba(255, 59, 48, 0.2);
        }
        .message.success {
            background: rgba(52, 199, 89, 0.08);
            color: #34c759;
            border: 1px solid rgba(52, 199, 89, 0.2);
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        .btn-submit {
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
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 113, 227, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>修改密码</h2>
        <?php if ($message): ?>
            <div class="message <?= xssFilter($messageType) ?>"><?= xssFilter($message) ?></div>
        <?php endif; ?>
        <form method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= xssFilter($csrfToken) ?>">
            <div class="form-group">
                <label for="old_password">原密码</label>
                <input type="password" id="old_password" name="old_password" required autocomplete="off">
            </div>
            <div class="form-group">
                <label for="new_password">新密码</label>
                <input type="password" id="new_password" name="new_password" required autocomplete="off">
            </div>
            <div class="form-group">
                <label for="confirm_password">确认新密码</label>
                <input type="password" id="confirm_password" name="confirm_password" required autocomplete="off">
            </div>
            <button type="submit" class="btn-submit">确认修改</button>
        </form>
    </div>
</body>
</html>
