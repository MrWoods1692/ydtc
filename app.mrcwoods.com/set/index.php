<?php
// 防XSS基础配置
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // 生产环境开启HTTPS时启用
// 生成CSP Nonce值（解决内联样式被阻止问题）
$nonce = bin2hex(random_bytes(16));
// 调整CSP头，允许nonce内联样式、跨域字体，兼容苹果风格内联样式
header("Content-Security-Policy: default-src 'self'; font-src 'self' data:; style-src 'self' 'nonce-{$nonce}'; script-src 'self'; frame-ancestors *;");
header('X-XSS-Protection: 1; mode=block');
header('Content-Type: text/html; charset=utf-8');

// 允许跨域
header('Access-Control-Allow-Origin: *'); // 生产环境建议替换为具体域名（如https://yourdomain.com）
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
// 允许嵌入其他网页
header('X-Frame-Options: ALLOWALL');
header('Frame-Options: ALLOWALL');

// 1. 读取.env配置文件
function loadEnv($path = '.env') {
    if (!file_exists($path)) {
        logUserOperation('', '配置文件.env不存在，访问失败');
        die('配置文件.env不存在');
    }
    $env = parse_ini_file($path);
    foreach ($env as $key => $value) {
        $_ENV[$key] = $value;
    }
}
loadEnv();

// 2. 日志记录函数（核心新增）
function logUserOperation($logPath, $operationDesc) {
    if (empty($logPath)) return;
    // 确保日志目录存在
    $logDir = dirname($logPath);
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    // 格式化时间
    $time = date('Y-m-d H:i:s');
    // 拼接日志内容
    $logContent = "[{$time}] {$operationDesc}" . PHP_EOL;
    // 追加写入日志（防并发写入问题）
    file_put_contents($logPath, $logContent, FILE_APPEND | LOCK_EX);
}

// 3. 数据库连接（PDO，防注入核心）
try {
    $pdo = new PDO(
        "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
        $_ENV['DB_USER'],
        $_ENV['DB_PASS'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false // 禁用模拟预处理，防注入
        ]
    );
} catch (PDOException $e) {
    logUserOperation('', '数据库连接失败：' . htmlspecialchars($e->getMessage()));
    die('数据库连接失败: ' . htmlspecialchars($e->getMessage()));
}

// 4. 初始化变量
$userEmail = '';
$encodedEmail = ''; // urlencode后的邮箱
$userData = ['username' => '', 'points' => 0];
$message = '';
$messageType = ''; // success/error/warning
$userJsonPath = '';
$logPath = '';

// 5. 读取Cookie中的user_token并验证
if (isset($_COOKIE['user_token']) && !empty($_COOKIE['user_token'])) {
    $userToken = htmlspecialchars($_COOKIE['user_token'], ENT_QUOTES);
    
    // 预处理查询，防SQL注入
    $stmt = $pdo->prepare("SELECT email FROM users WHERE token = :token LIMIT 1");
    $stmt->bindParam(':token', $userToken, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch();
    
    if ($user) {
        $userEmail = $user['email'];
        $encodedEmail = urlencode($userEmail); // 对邮箱进行URL编码（核心修改）
        // 构建文件路径（核心修改：使用urlencode后的邮箱）
        $userJsonPath = "../users/{$encodedEmail}/user.json";
        $logPath = "../users/{$encodedEmail}/log.txt";
        
        // 记录访问日志
        logUserOperation($logPath, "访问个人设置页面，当前用户名：{$userData['username']}");
        
        // 读取user.json文件
        if (file_exists($userJsonPath) && is_readable($userJsonPath)) {
            $jsonContent = file_get_contents($userJsonPath);
            $userData = json_decode($jsonContent, true);
            
            // 验证JSON格式
            if (json_last_error() !== JSON_ERROR_NONE) {
                $message = '用户数据文件损坏';
                $messageType = 'error';
                logUserOperation($logPath, "读取用户数据失败：JSON格式错误");
            }
        } else {
            $message = '用户数据文件不存在或无读取权限';
            $messageType = 'error';
            logUserOperation($logPath, "读取用户数据失败：文件不存在或无权限");
        }
    } else {
        $message = '无效的登录令牌，请重新登录';
        $messageType = 'error';
        logUserOperation('', "验证令牌失败：token={$userToken} 无对应用户");
    }
} else {
    $message = '未检测到登录状态，请先登录';
    $messageType = 'error';
    logUserOperation('', "访问设置页面失败：未检测到user_token Cookie");
}

// 6. 处理用户名修改提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($userEmail)) {
    // 防XSS：过滤输入
    $newUsername = trim(htmlspecialchars($_POST['new_username'], ENT_QUOTES));
    $oldUsername = $userData['username'];
    
    // 验证新用户名
    $forbiddenNames = ['null', 'default', 'undefined', 'admin', 'root'];
    if (empty($newUsername)) {
        $message = '用户名不能为空';
        $messageType = 'error';
        logUserOperation($logPath, "修改用户名失败：新名称为空，原名称：{$oldUsername}");
    } elseif (in_array(strtolower($newUsername), $forbiddenNames)) {
        $message = '禁止使用特殊名称（NULL、default等）作为用户名';
        $messageType = 'error';
        logUserOperation($logPath, "修改用户名失败：新名称{$newUsername}属于禁止列表，原名称：{$oldUsername}");
    } elseif ($newUsername === $oldUsername) {
        $message = '新用户名与原用户名一致，无需修改';
        $messageType = 'warning';
        logUserOperation($logPath, "修改用户名失败：新名称与原名称一致（{$oldUsername}）");
    } elseif ($userData['points'] < 1) {
        $message = '积分不足，修改用户名需要消耗1个积分';
        $messageType = 'error';
        logUserOperation($logPath, "修改用户名失败：积分不足（当前{$userData['points']}），原名称：{$oldUsername}，新名称：{$newUsername}");
    } else {
        // 扣减积分并更新用户名
        $userData['username'] = $newUsername;
        $userData['points'] = intval($userData['points']) - 1;
        $userData['updated_at'] = time();
        
        // 写入JSON文件（保证JSON格式正确）
        $updatedJson = json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($userJsonPath, $updatedJson) !== false) {
            $message = '用户名修改成功，已消耗1个积分，当前积分：' . $userData['points'];
            $messageType = 'success';
            logUserOperation($logPath, "修改用户名成功：从{$oldUsername}改为{$newUsername}，消耗1积分，剩余积分：{$userData['points']}");
        } else {
            $message = '用户名修改失败，文件写入错误';
            $messageType = 'error';
            logUserOperation($logPath, "修改用户名失败：文件写入错误，原名称：{$oldUsername}，新名称：{$newUsername}");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>个人设置</title>
    <!-- 添加nonce属性解决CSP内联样式阻止问题 -->
    <style nonce="<?php echo $nonce; ?>">
        /* 引入自定义字体 */
        @font-face {
            font-family: 'AlimamaAgileVF';
            src: url('../font/AlimamaAgileVF-Thin.woff2') format('woff2'),
                 url('../font/AlimamaAgileVF-Thin.woff') format('woff'),
                 url('../font/AlimamaAgileVF-Thin.ttf') format('truetype');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }

        /* 苹果风格基础样式（优化美化） */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'AlimamaAgileVF', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-weight: 400;
            -webkit-font-smoothing: antialiased; /* 苹果字体抗锯齿 */
            -moz-osx-font-smoothing: grayscale;
        }

        body {
            background-color: #f5f5f7;
            color: #1d1d1f;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px 20px;
            background-image: linear-gradient(to bottom, #f8f8fa, #f5f5f7); /* 渐变背景更贴近苹果风格 */
        }

        .container {
            width: 100%;
            max-width: 500px;
            background-color: #ffffff;
            border-radius: 20px; /* 更圆润的圆角 */
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05); /* 更细腻的阴影 */
            padding: 40px 32px;
            position: relative;
            overflow: hidden;
        }

        /* 苹果风格顶部装饰条 */
        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #0071e3, #34c759, #ff9500, #ff3b30);
        }

        .header {
            text-align: center;
            margin-bottom: 36px;
        }

        .header h1 {
            font-size: 28px;
            color: #1d1d1f;
            margin-bottom: 12px;
            letter-spacing: -0.5px; /* 苹果字体紧凑感 */
        }

        .header p {
            color: #86868b;
            font-size: 15px;
            line-height: 1.5;
        }

        .user-info {
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e6e6e8;
            transition: all 0.2s ease;
        }

        .user-info:hover {
            border-color: #d1d1d6;
        }

        .user-info .label {
            font-size: 13px;
            color: #86868b;
            margin-bottom: 8px;
            display: block;
        }

        .user-info .value {
            font-size: 17px;
            color: #1d1d1f;
            letter-spacing: 0.2px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            font-size: 15px;
            color: #1d1d1f;
            margin-bottom: 10px;
            font-weight: 450; /* 轻微加粗标签 */
        }

        .form-group input {
            width: 100%;
            padding: 14px 18px;
            border: 1px solid #e6e6e8;
            border-radius: 14px;
            font-size: 17px;
            transition: all 0.2s ease;
            background-color: #fafafa;
        }

        .form-group input:focus {
            outline: none;
            border-color: #0071e3;
            box-shadow: 0 0 0 4px rgba(0, 113, 227, 0.08); /* 更柔和的聚焦阴影 */
            background-color: #ffffff;
        }

        .form-group input::placeholder {
            color: #a1a1a6;
            font-size: 16px;
        }

        .btn {
            width: 100%;
            padding: 14px;
            background-color: #0071e3;
            color: #ffffff;
            border: none;
            border-radius: 14px;
            font-size: 17px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        /* 苹果按钮hover/active效果 */
        .btn:hover {
            background-color: #0077ed;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 113, 227, 0.2);
        }

        .btn:active {
            background-color: #0066cc;
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(0, 113, 227, 0.15);
        }

        /* 提示消息样式（优化） */
        .message {
            padding: 14px 18px;
            border-radius: 14px;
            margin-bottom: 24px;
            font-size: 15px;
            text-align: center;
            line-height: 1.5;
            transition: all 0.3s ease;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message.success {
            background-color: #e8f6ed;
            color: #34c759;
            border: 1px solid #d4f2dc;
        }

        .message.error {
            background-color: #ffebee;
            color: #ff3b30;
            border: 1px solid #ffd6d6;
        }

        .message.warning {
            background-color: #fff8e6;
            color: #ff9500;
            border: 1px solid #ffe0b2;
        }

        .points-note {
            font-size: 13px;
            color: #86868b;
            margin-top: 10px;
            font-style: italic;
            line-height: 1.4;
        }

        /* 响应式适配（苹果风格移动端优化） */
        @media (max-width: 480px) {
            .container {
                padding: 32px 24px;
                border-radius: 16px;
            }
            .header h1 {
                font-size: 24px;
            }
            .form-group input {
                padding: 12px 16px;
                font-size: 16px;
            }
            .btn {
                padding: 12px;
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>个人设置</h1>
            <p>修改您的用户名（消耗1积分）</p>
        </div>

        <!-- 提示消息 -->
        <?php if (!empty($message)): ?>
            <div class="message <?php echo htmlspecialchars($messageType); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- 用户信息展示 -->
        <?php if (!empty($userEmail)): ?>
            <div class="user-info">
                <span class="label">当前邮箱</span>
                <span class="value"><?php echo htmlspecialchars($userEmail); ?></span>
            </div>
            <div class="user-info">
                <span class="label">当前用户名</span>
                <span class="value"><?php echo htmlspecialchars($userData['username']); ?></span>
            </div>
            <div class="user-info">
                <span class="label">当前积分</span>
                <span class="value"><?php echo intval($userData['points']); ?></span>
            </div>

            <!-- 用户名修改表单 -->
            <form method="POST" action="" autocomplete="off">
                <div class="form-group">
                    <label for="new_username">新用户名</label>
                    <input 
                        type="text" 
                        id="new_username" 
                        name="new_username" 
                        value="<?php echo htmlspecialchars($userData['username']); ?>"
                        placeholder="请输入新用户名"
                        required
                        autocomplete="new-username"
                    >
                    <div class="points-note">修改将消耗1个积分</div>
                </div>
                <button type="submit" class="btn">保存修改</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
