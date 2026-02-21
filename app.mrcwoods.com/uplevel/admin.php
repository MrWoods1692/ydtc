<?php
/**
 * 积分商城 - 卡密/兑换码管理后台（优化版）
 * 优化点：代码结构重构 | 安全加固 | 界面美化 | 交互提升
 */
session_start();

// ====================== 1. 配置项（集中管理） ======================
define('ADMIN_PASSWORD_HASH', password_hash('admin123', PASSWORD_DEFAULT)); // 密码哈希（建议修改后重新生成）
define('CARD_DB_PATH', __DIR__ . '/data/card_codes.db');
define('REDEEM_DB_PATH', __DIR__ . '/data/redeem_codes.db');
define('PAGE_TITLE', '卡密/兑换码管理后台');
define('CODE_LENGTH', 32); // 卡密长度（bin2hex后为64位）
define('CSRF_TOKEN_NAME', 'csrf_token_' . md5(__FILE__));

// ====================== 2. 工具函数（复用逻辑） ======================

/**
 * 初始化CSRF令牌
 */
function initCsrfToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(16));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * 验证CSRF令牌
 */
function verifyCsrfToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * 初始化SQLite数据库连接
 */
function initDb($dbPath) {
    // 确保目录存在
    $dir = dirname($dbPath);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        die("无法创建数据库目录：{$dir}");
    }

    try {
        $pdo = new PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        die("数据库连接失败：" . $e->getMessage());
    }
}

/**
 * 初始化数据表结构
 */
function initTables() {
    // 外部卡密表
    $cardPdo = initDb(CARD_DB_PATH);
    $cardPdo->exec("CREATE TABLE IF NOT EXISTS card_codes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL UNIQUE,
        points INTEGER NOT NULL,
        user_email TEXT NOT NULL,
        created_at INTEGER NOT NULL,
        used INTEGER DEFAULT 0,
        used_at INTEGER DEFAULT 0
    )");

    // 商城兑换码表
    $redeemPdo = initDb(REDEEM_DB_PATH);
    $redeemPdo->exec("CREATE TABLE IF NOT EXISTS redeem_codes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL UNIQUE,
        points INTEGER NOT NULL,
        created_at INTEGER NOT NULL
    )");
}

/**
 * 生成唯一卡密/兑换码
 */
function generateUniqueCode($pdo, $table, $codeField) {
    do {
        $code = bin2hex(random_bytes(CODE_LENGTH));
        $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE {$codeField} = ? LIMIT 1");
        $stmt->execute([$code]);
        $exists = $stmt->fetch();
    } while ($exists); // 确保码唯一
    return $code;
}

/**
 * XSS过滤函数
 */
function e($content) {
    return htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
}

// ====================== 3. 初始化与业务逻辑 ======================
initTables(); // 初始化数据表
$cardPdo = initDb(CARD_DB_PATH);
$redeemPdo = initDb(REDEEM_DB_PATH);

$message = '';
$messageType = '';
$csrfToken = initCsrfToken();

// 处理登出
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['admin_login'], $_SESSION[CSRF_TOKEN_NAME]);
    $message = '已成功登出';
    $messageType = 'success';
}

// 处理登录
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $password = trim($_POST['password'] ?? '');
    if (password_verify($password, ADMIN_PASSWORD_HASH)) {
        $_SESSION['admin_login'] = true;
        $message = '登录成功，欢迎回来！';
        $messageType = 'success';
    } else {
        $message = '密码错误，请重试';
        $messageType = 'error';
    }
}

// 未登录则显示登录页
if (!isset($_SESSION['admin_login']) || !$_SESSION['admin_login']) {
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e(PAGE_TITLE) ?> - 登录</title>
        <style>
            :root {
                --primary: #4299e1;
                --primary-light: #63b3ed;
                --primary-dark: #3182ce;
                --success: #38a169;
                --success-light: #48bb78;
                --error: #e53e3e;
                --error-light: #f56565;
                --gray-100: #f7fafc;
                --gray-200: #edf2f7;
                --gray-300: #e2e8f0;
                --gray-700: #4a5568;
                --gray-800: #2d3748;
                --radius: 12px;
                --shadow: 0 4px 12px rgba(0,0,0,0.08);
                --shadow-hover: 0 6px 16px rgba(0,0,0,0.12);
                --transition: all 0.3s ease;
            }
            * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Inter", "Microsoft YaHei", sans-serif; }
            body { 
                background: linear-gradient(135deg, #f5f7fa 0%, #e9eef6 100%); 
                display: flex; 
                justify-content: center; 
                align-items: center; 
                min-height: 100vh; 
                padding: 20px; 
            }
            .login-box { 
                background: white; 
                padding: 50px 40px; 
                border-radius: var(--radius); 
                box-shadow: var(--shadow); 
                width: 100%; 
                max-width: 420px; 
                transition: var(--transition);
            }
            .login-box:hover {
                box-shadow: var(--shadow-hover);
            }
            .login-box h1 { 
                text-align: center; 
                margin-bottom: 30px; 
                color: var(--gray-800); 
                font-size: 26px; 
                font-weight: 600;
            }
            .login-box h1 span {
                color: var(--primary);
            }
            .form-group { margin-bottom: 24px; }
            .form-group label { 
                display: block; 
                margin-bottom: 8px; 
                color: var(--gray-700); 
                font-size: 14px; 
                font-weight: 500;
            }
            .form-group input { 
                width: 100%; 
                padding: 14px 16px; 
                border: 1px solid var(--gray-300); 
                border-radius: 8px; 
                font-size: 16px; 
                transition: var(--transition);
            }
            .form-group input:focus { 
                outline: none; 
                border-color: var(--primary); 
                box-shadow: 0 0 0 3px rgba(66,153,225,0.1); 
            }
            .btn { 
                width: 100%; 
                padding: 14px; 
                background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
                color: white; 
                border: none; 
                border-radius: 8px; 
                font-size: 16px; 
                cursor: pointer; 
                transition: var(--transition);
                font-weight: 500;
            }
            .btn:hover { 
                background: linear-gradient(135deg, var(--primary-dark) 0%, #2b6cb0 100%);
                transform: translateY(-2px);
            }
            .message { 
                padding: 14px; 
                border-radius: 8px; 
                margin-bottom: 24px; 
                text-align: center; 
                animation: fadeIn 0.5s ease;
            }
            .message.success { 
                background: #c6f6d5; 
                color: #22543d; 
                border: 1px solid #9ae6b4;
            }
            .message.error { 
                background: #fed7d7; 
                color: #742a2a;
                border: 1px solid #feb2b2;
            }
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h1><span>积分商城</span> - 管理登录</h1>
            <?php if ($message): ?>
                <div class="message <?= e($messageType) ?>"><?= e($message) ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <div class="form-group">
                    <label for="password">管理密码</label>
                    <input type="password" id="password" name="password" required placeholder="请输入管理密码">
                </div>
                <button type="submit" class="btn">登录管理后台</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 已登录：处理生成卡密/兑换码请求
if (isset($_POST['action']) && $_SESSION['admin_login']) {
    // 验证CSRF令牌
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '请求验证失败，请刷新页面重试';
        $messageType = 'error';
    } else {
        try {
            // 生成外部卡密
            if ($_POST['action'] === 'generate_card') {
                $points = (int)trim($_POST['card_points'] ?? 0);
                $userEmail = trim($_POST['card_user'] ?? '');
                
                // 严格验证
                if ($points <= 0 || $points > 999999) throw new Exception('积分必须在1-999999之间');
                if (empty($userEmail) || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('请输入有效的用户邮箱');
                }
                
                // 生成唯一卡密
                $code = generateUniqueCode($cardPdo, 'card_codes', 'code');
                $createdAt = time();
                
                // 写入数据库
                $stmt = $cardPdo->prepare("INSERT INTO card_codes (code, points, user_email, created_at) VALUES (?, ?, ?, ?)");
                $stmt->execute([$code, $points, $userEmail, $createdAt]);
                
                $message = "外部卡密生成成功！<br>卡密：<code>{$code}</code><br>所属用户：{$userEmail}<br>对应积分：{$points}";
                $messageType = 'success';
            }
            
            // 生成商城兑换码
            elseif ($_POST['action'] === 'generate_redeem') {
                $points = (int)trim($_POST['redeem_points'] ?? 0);
                
                if ($points <= 0 || $points > 999999) throw new Exception('积分必须在1-999999之间');
                
                $code = generateUniqueCode($redeemPdo, 'redeem_codes', 'code');
                $createdAt = time();
                
                $stmt = $redeemPdo->prepare("INSERT INTO redeem_codes (code, points, created_at) VALUES (?, ?, ?)");
                $stmt->execute([$code, $points, $createdAt]);
                
                $message = "商城兑换码生成成功！<br>兑换码：<code>{$code}</code><br>对应积分：{$points}";
                $messageType = 'success';
            }
        } catch (Exception $e) {
            $message = '生成失败：' . e($e->getMessage());
            $messageType = 'error';
        }
    }
}

// 查询卡密/兑换码列表
try {
    $cardList = $cardPdo->query("SELECT * FROM card_codes ORDER BY id DESC")->fetchAll();
    $redeemList = $redeemPdo->query("SELECT * FROM redeem_codes ORDER BY id DESC")->fetchAll();
} catch (Exception $e) {
    $cardList = [];
    $redeemList = [];
    $message = '查询失败：' . e($e->getMessage());
    $messageType = 'error';
}

// ====================== 4. 管理页面渲染 ======================
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(PAGE_TITLE) ?></title>
    <style>
        :root {
            --primary: #4299e1;
            --primary-light: #63b3ed;
            --primary-dark: #3182ce;
            --success: #38a169;
            --success-light: #48bb78;
            --danger: #e53e3e;
            --danger-light: #f56565;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --radius: 12px;
            --shadow: 0 4px 12px rgba(0,0,0,0.06);
            --shadow-hover: 0 8px 24px rgba(0,0,0,0.12);
            --transition: all 0.3s ease;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Inter", "Microsoft YaHei", sans-serif; }
        body { 
            background: linear-gradient(135deg, #f5f7fa 0%, #e9eef6 100%); 
            padding: 20px; 
            min-height: 100vh;
            color: var(--gray-800);
        }
        .container { 
            max-width: 1600px; 
            margin: 0 auto; 
            background: white; 
            border-radius: var(--radius); 
            box-shadow: var(--shadow); 
            padding: 40px;
            transition: var(--transition);
        }
        .container:hover {
            box-shadow: var(--shadow-hover);
        }
        .header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 40px; 
            padding-bottom: 20px; 
            border-bottom: 1px solid var(--gray-200);
        }
        .header h1 { 
            color: var(--gray-800); 
            font-size: 28px; 
            font-weight: 600;
        }
        .header h1 span {
            color: var(--primary);
        }
        .header .logout { 
            color: var(--primary); 
            text-decoration: none; 
            padding: 10px 20px; 
            border: 1px solid var(--primary); 
            border-radius: 8px; 
            transition: var(--transition);
            font-weight: 500;
        }
        .header .logout:hover { 
            background: var(--primary); 
            color: white; 
            transform: translateY(-2px);
        }
        .section { 
            margin-bottom: 50px; 
        }
        .section h2 { 
            color: var(--gray-800); 
            font-size: 22px; 
            margin-bottom: 24px; 
            padding-bottom: 12px; 
            border-bottom: 2px solid #e8f4f8;
            font-weight: 600;
        }
        .generate-area { 
            background: linear-gradient(135deg, var(--gray-50) 0%, #f0f8fb 100%); 
            padding: 30px; 
            border-radius: var(--radius); 
            margin-bottom: 30px;
            border: 1px solid var(--gray-200);
        }
        .form-row { 
            display: flex; 
            gap: 24px; 
            margin-bottom: 24px; 
            flex-wrap: wrap; 
        }
        .form-group { 
            flex: 1; 
            min-width: 280px; 
        }
        .form-group label { 
            display: block; 
            margin-bottom: 10px; 
            color: var(--gray-700); 
            font-size: 14px; 
            font-weight: 500;
        }
        .form-group input { 
            width: 100%; 
            padding: 14px 16px; 
            border: 1px solid var(--gray-300); 
            border-radius: 8px; 
            font-size: 16px; 
            transition: var(--transition);
        }
        .form-group input:focus { 
            outline: none; 
            border-color: var(--primary); 
            box-shadow: 0 0 0 3px rgba(66,153,225,0.1); 
        }
        .btn { 
            padding: 12px 28px; 
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white; 
            border: none; 
            border-radius: 8px; 
            font-size: 16px; 
            cursor: pointer; 
            transition: var(--transition);
            font-weight: 500;
        }
        .btn:hover { 
            background: linear-gradient(135deg, var(--primary-dark) 0%, #2b6cb0 100%);
            transform: translateY(-2px);
        }
        .message { 
            padding: 16px; 
            border-radius: 8px; 
            margin-bottom: 24px; 
            animation: fadeIn 0.5s ease;
            border: 1px solid transparent;
        }
        .message.success { 
            background: #c6f6d5; 
            color: #22543d; 
            border-color: #9ae6b4;
        }
        .message.error { 
            background: #fed7d7; 
            color: #742a2a;
            border-color: #feb2b2;
        }
        .code-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .code-table th { 
            padding: 16px 20px; 
            text-align: left; 
            background: linear-gradient(135deg, var(--gray-100) 0%, var(--gray-50) 100%);
            color: var(--gray-800); 
            font-weight: 600;
            font-size: 14px;
            border-bottom: 2px solid var(--gray-200);
            position: sticky;
            top: 0;
        }
        .code-table td { 
            padding: 16px 20px; 
            color: var(--gray-700); 
            border-bottom: 1px solid var(--gray-200);
            font-size: 14px;
        }
        .code-table tbody tr:nth-child(even) {
            background: var(--gray-50);
        }
        .code-table tbody tr:hover {
            background: #f0f8fb;
            transition: var(--transition);
        }
        .code-text { 
            font-family: "Courier New", monospace; 
            max-width: 250px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            position: relative;
            cursor: pointer;
        }
        .code-text:hover::after {
            content: attr(data-code);
            position: absolute;
            top: -40px;
            left: 0;
            background: var(--gray-800);
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 100;
            min-width: 300px;
        }
        .status-tag {
            padding: 4px 10px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-used { 
            background: #fee2e2;
            color: var(--danger); 
        }
        .status-unused { 
            background: #d1fae5;
            color: var(--success); 
        }
        .empty-tip { 
            text-align: center; 
            padding: 60px 20px; 
            color: var(--gray-500); 
            font-size: 16px; 
            background: var(--gray-50);
            border-radius: 8px;
            margin-top: 20px;
            color: #9ca3af;
        }
        .copy-btn {
            padding: 4px 8px;
            background: var(--primary-light);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            margin-left: 8px;
            transition: var(--transition);
        }
        .copy-btn:hover {
            background: var(--primary);
        }
        code { 
            background: #f0f8fb; 
            padding: 4px 8px; 
            border-radius: 4px; 
            color: var(--gray-800); 
            font-family: "Courier New", monospace; 
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        /* 响应式适配 */
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            .header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            .form-row {
                gap: 16px;
            }
            .code-table th:nth-child(2), 
            .code-table td:nth-child(2) { 
                display: none; 
            }
            .code-table th:nth-child(4), 
            .code-table td:nth-child(4) { 
                display: none; 
            }
            .code-text {
                max-width: 150px;
            }
        }
    </style>
    <script>
        // 一键复制卡密/兑换码
        document.addEventListener('DOMContentLoaded', function() {
            // 为所有卡密文本添加复制按钮
            const codeElements = document.querySelectorAll('.code-text');
            codeElements.forEach(el => {
                const copyBtn = document.createElement('button');
                copyBtn.className = 'copy-btn';
                copyBtn.textContent = '复制';
                copyBtn.onclick = function(e) {
                    e.stopPropagation();
                    const code = el.getAttribute('data-code');
                    navigator.clipboard.writeText(code).then(() => {
                        copyBtn.textContent = '已复制';
                        setTimeout(() => {
                            copyBtn.textContent = '复制';
                        }, 1500);
                    }).catch(err => {
                        alert('复制失败：' + err);
                    });
                };
                el.parentNode.insertBefore(copyBtn, el.nextSibling);
            });

            // 消息自动消失（成功消息）
            const successMsg = document.querySelector('.message.success');
            if (successMsg) {
                setTimeout(() => {
                    successMsg.style.opacity = '0';
                    successMsg.style.transform = 'translateY(10px)';
                    successMsg.style.transition = 'all 0.5s ease';
                    setTimeout(() => successMsg.remove(), 500);
                }, 5000);
            }
        });
    </script>
</head>
<body>
    <div class="container">

        <!-- 提示信息 -->
        <?php if ($message): ?>
            <div class="message <?= e($messageType) ?>"><?= $message ?></div>
        <?php endif; ?>

        <!-- 生成外部卡密区域 -->
        <div class="section">
            <h2>卡密管理</h2>
            <div class="generate-area">
                <form method="POST">
                    <input type="hidden" name="action" value="generate_card">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="card_points">价值</label>
                            <input type="number" id="card_points" name="card_points" required min="1" max="999999" placeholder="请输入1-999999之间的积分值">
                        </div>
                        <div class="form-group">
                            <label for="card_user">所属用户邮箱</label>
                            <input type="email" id="card_user" name="card_user" required placeholder="如：user@example.com">
                        </div>
                    </div>
                    <button type="submit" class="btn">生成卡密</button>
                </form>
            </div>

            <!-- 外部卡密列表 -->
            <h3 style="margin-bottom: 16px; font-size: 18px; color: var(--gray-700);">外部卡密列表（共<?= count($cardList) ?>条）</h3>
            <?php if (empty($cardList)): ?>
                <div class="empty-tip">暂无外部卡密数据，点击上方按钮生成</div>
            <?php else: ?>
                <table class="code-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>卡密内容</th>
                            <th>积分</th>
                            <th>所属用户</th>
                            <th>创建时间</th>
                            <th>使用状态</th>
                            <th>使用时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cardList as $item): ?>
                            <tr>
                                <td><?= e($item['id']) ?></td>
                                <td>
                                    <span class="code-text" data-code="<?= e($item['code']) ?>"><?= e($item['code']) ?></span>
                                </td>
                                <td><?= e($item['points']) ?></td>
                                <td><?= e($item['user_email']) ?></td>
                                <td><?= date('Y-m-d H:i:s', $item['created_at']) ?></td>
                                <td>
                                    <?= $item['used'] 
                                        ? '<span class="status-tag status-used">已使用</span>' 
                                        : '<span class="status-tag status-unused">未使用</span>' 
                                    ?>
                                </td>
                                <td><?= $item['used_at'] ? date('Y-m-d H:i:s', $item['used_at']) : '未使用' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <hr style="border: none; border-top: 1px solid var(--gray-200); margin: 40px 0;">

        <!-- 生成商城兑换码区域 -->
        <div class="section">
            <h2>积分兑换码管理</h2>
            <div class="generate-area">
                <form method="POST">
                    <input type="hidden" name="action" value="generate_redeem">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="redeem_points">兑换码积分</label>
                            <input type="number" id="redeem_points" name="redeem_points" required min="1" max="999999" placeholder="请输入1-999999之间的积分值">
                        </div>
                    </div>
                    <button type="submit" class="btn">生成兑换码</button>
                </form>
            </div>

            <!-- 商城兑换码列表 -->
            <h3 style="margin-bottom: 16px; font-size: 18px; color: var(--gray-700);">商城兑换码列表（共<?= count($redeemList) ?>条）</h3>
            <?php if (empty($redeemList)): ?>
                <div class="empty-tip">暂无积分兑换码数据，点击上方按钮生成</div>
            <?php else: ?>
                <table class="code-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>兑换码内容</th>
                            <th>积分</th>
                            <th>创建时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($redeemList as $item): ?>
                            <tr>
                                <td><?= e($item['id']) ?></td>
                                <td>
                                    <span class="code-text" data-code="<?= e($item['code']) ?>"><?= e($item['code']) ?></span>
                                </td>
                                <td><?= e($item['points']) ?></td>
                                <td><?= date('Y-m-d H:i:s', $item['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
