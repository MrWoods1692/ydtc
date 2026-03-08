<?php
declare(strict_types=1);

// 错误处理
error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('Asia/Shanghai');

/**
 * 加载.env配置
 * @param string $path .env文件路径
 * @return array 配置数组
 */
function loadEnv(string $path): array
{
    $env = [];
    if (!file_exists($path)) {
        die("配置文件 {$path} 不存在");
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim($value);
    }
    return $env;
}

// 加载配置
$envPath = __DIR__ . '/../in/.env';
$config = loadEnv($envPath);

/**
 * 数据库连接（MySQL）
 * @param array $config 数据库配置
 * @return PDO
 */
function connectDB(array $config): PDO
{
    try {
        $dsn = "mysql:host={$config['DB_HOST']};port={$config['DB_PORT']};dbname={$config['DB_NAME']};charset={$config['DB_CHARSET']}";
        $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASS'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die("MySQL数据库连接失败: " . $e->getMessage());
    }
}

/**
 * 连接SQLite数据库（key.db）- 简化表结构
 * @param string $dbPath SQLite文件路径
 * @return PDO
 */
function connectSQLite(string $dbPath): PDO
{
    try {
        // 自动创建数据库文件（如果不存在）
        $pdo = new PDO("sqlite:{$dbPath}", null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        // 创建简化的密钥表（移除id、created_at、updated_at）
        $createTableSql = "
        CREATE TABLE IF NOT EXISTS user_keys (
            email TEXT NOT NULL PRIMARY KEY,
            `key` TEXT NOT NULL UNIQUE
        );
        CREATE INDEX IF NOT EXISTS idx_key ON user_keys(`key`);
        ";
        $pdo->exec($createTableSql);
        
        return $pdo;
    } catch (PDOException $e) {
        die("SQLite数据库连接失败: " . $e->getMessage());
    }
}

// 验证用户Token
$userToken = $_COOKIE['user_token'] ?? '';
if (empty($userToken)) {
    die("未检测到登录令牌，请先登录");
}

// 连接MySQL并查询用户邮箱
$pdoMysql = connectDB($config);
$stmt = $pdoMysql->prepare("SELECT email FROM users WHERE token = :token LIMIT 1");
$stmt->bindValue(':token', $userToken);
$stmt->execute();
$user = $stmt->fetch();

if (!$user) {
    die("无效的登录令牌，请重新登录");
}
$userEmail = $user['email'];
$encodedEmail = urlencode($userEmail);

// 路径配置
$usersRootDir = __DIR__ . "/../users";
$userDir = "{$usersRootDir}/{$encodedEmail}";
$userJsonPath = "{$userDir}/user.json";
$sqliteDbPath = "{$usersRootDir}/key.db"; // SQLite格式的key.db

// 确保目录存在
if (!is_dir($usersRootDir)) {
    mkdir($usersRootDir, 0755, true);
}
if (!is_dir($userDir)) {
    mkdir($userDir, 0755, true);
}

/**
 * 读取/初始化用户信息
 */
function getUserInfo(string $path): array
{
    $defaultInfo = [
        "username" => "默认用户名",
        "points" => 1,
        "used_space_kb" => 0
    ];
    
    if (!file_exists($path)) {
        file_put_contents($path, json_encode($defaultInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $defaultInfo;
    }
    
    $content = file_get_contents($path);
    $info = json_decode($content, true);
    return $info ?: $defaultInfo;
}

/**
 * 生成更长的唯一sk-开头密钥（64位随机字符）
 */
function generateLongUniqueKey(PDO $pdoSqlite): string
{
    // 先获取所有已存在的密钥
    $stmt = $pdoSqlite->query("SELECT `key` FROM user_keys");
    $existingKeys = $stmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];
    
    // 生成sk-前缀 + 64位随机字符
    do {
        $randomStr = bin2hex(random_bytes(64));
        $key = "sk-{$randomStr}";
    } while (in_array($key, $existingKeys));
    
    return $key;
}

/**
 * 获取用户密钥（无则创建）- 简化版
 */
function getUserKey(string $email, PDO $pdoSqlite): string
{
    // 检查用户是否已有密钥
    $stmt = $pdoSqlite->prepare("SELECT `key` FROM user_keys WHERE email = :email LIMIT 1");
    $stmt->bindValue(':email', $email);
    $stmt->execute();
    $existingKey = $stmt->fetchColumn();
    
    if ($existingKey) {
        return $existingKey;
    }
    
    // 生成新密钥
    $newKey = generateLongUniqueKey($pdoSqlite);
    
    // 插入新密钥
    $stmt = $pdoSqlite->prepare("
        INSERT OR IGNORE INTO user_keys (email, `key`)
        VALUES (:email, :key)
    ");
    $stmt->bindValue(':email', $email);
    $stmt->bindValue(':key', $newKey);
    $stmt->execute();
    
    return $newKey;
}

/**
 * 记录操作日志
 * @param string $email 用户邮箱
 * @param string $action 操作类型
 * @param string $detail 操作详情
 */
function writeOperationLog(string $email, string $action, string $detail): void
{
    $logDir = __DIR__ . '/../open-platform_log';
    $logFile = $logDir . '/' . urlencode($email) . '.log';
    
    // 确保日志目录存在
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // 格式化日志条目
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] 操作：{$action} | 详情：{$detail}\n";
    
    // 追加写入日志文件
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * 重置用户密钥 - 简化版
 */
function resetUserKey(string $email, PDO $pdoSqlite): string
{
    // 获取旧密钥用于日志记录
    $stmt = $pdoSqlite->prepare("SELECT `key` FROM user_keys WHERE email = :email LIMIT 1");
    $stmt->bindValue(':email', $email);
    $stmt->execute();
    $oldKey = $stmt->fetchColumn();
    
    // 生成新密钥
    $newKey = generateLongUniqueKey($pdoSqlite);
    
    // 更新密钥（简化版）
    $stmt = $pdoSqlite->prepare("
        UPDATE user_keys 
        SET `key` = :key 
        WHERE email = :email
    ");
    $stmt->bindValue(':email', $email);
    $stmt->bindValue(':key', $newKey);
    $stmt->execute();
    
    // 记录操作日志
    writeOperationLog($email, '重置密钥', "密钥已重置，旧密钥前8位：" . substr($oldKey ?: 'unknown', 0, 11) . "...，新密钥前8位：" . substr($newKey, 0, 11) . "...");
    
    return $newKey;
}

// 连接SQLite
$pdoSqlite = connectSQLite($sqliteDbPath);

// 处理密钥重置请求
if ($_POST['action'] ?? '' === 'reset_key') {
    resetUserKey($userEmail, $pdoSqlite);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// 读取用户信息和密钥
$userInfo = getUserInfo($userJsonPath);
$userKey = getUserKey($userEmail, $pdoSqlite);

// 模拟登录地区
$loginRegion = "登录地区：地球";

// 时间段问候语
$hour = (int)date('H');
if ($hour >= 5 && $hour < 9) {
    $greeting = "早上好";
    $warmText = "新的一天，元气满满！愿您今天工作顺利，心情愉快。";
} elseif ($hour >= 9 && $hour < 12) {
    $greeting = "上午好";
    $warmText = "美好的一天正在进行中，愿每一份努力都有收获。";
} elseif ($hour >= 12 && $hour < 14) {
    $greeting = "中午好";
    $warmText = "记得休息一下，吃顿美味的午餐，为下午充电。";
} elseif ($hour >= 14 && $hour < 18) {
    $greeting = "下午好";
    $warmText = "下午茶时间到啦，保持好心情，效率更高哦。";
} elseif ($hour >= 18 && $hour < 24) {
    $greeting = "晚上好";
    $warmText = "忙碌了一天辛苦了，放松心情，享受美好夜晚。";
} else {
    $greeting = "凌晨好";
    $warmText = "夜深了，注意休息，熬夜伤身哦。";
}

/**
 * 格式化长邮箱显示（截断+悬浮提示）
 * @param string $email 原始邮箱
 * @param int $maxLength 最大显示长度
 * @return string 格式化后的HTML
 */
function formatLongEmail(string $email, int $maxLength = 20): string
{
    if (mb_strlen($email) <= $maxLength) {
        return htmlspecialchars($email);
    }
    
    // 截断并添加省略号，保留@前后部分
    $atPos = strpos($email, '@');
    if ($atPos !== false) {
        $prefix = mb_substr($email, 0, $maxLength - 10);
        $suffix = mb_substr($email, $atPos - 5);
        $shortEmail = $prefix . '...' . $suffix;
    } else {
        $shortEmail = mb_substr($email, 0, $maxLength) . '...';
    }
    
    return sprintf(
        '<span title="%s" class="truncated-email">%s</span>',
        htmlspecialchars($email),
        htmlspecialchars($shortEmail)
    );
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>开放平台接入管理</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Microsoft Yahei", "Segoe UI", system-ui, -apple-system, sans-serif;
        }
        body {
            background-color: #f8fafc;
            background-image: 
                linear-gradient(rgba(66, 153, 225, 0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(66, 153, 225, 0.04) 1px, transparent 1px);
            background-size: 20px 20px;
            color: #333;
            line-height: 1.6;
            padding: 20px 0;
        }
        .greeting-bar {
            max-width: 1200px;
            margin: 0 auto 25px;
            padding: 0 30px;
            font-size: 22px;
            font-weight: 600;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .greeting-bar span {
            background: linear-gradient(135deg, #4299e1, #38b2ac);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 30px;
        }
        .card {
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.06);
            margin-bottom: 30px;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.8);
        }
        .card:hover {
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.09);
            transform: translateY(-3px);
        }
        .card-header {
            padding: 24px 30px;
            background: linear-gradient(135deg, #f8f9fc 0%, #eef2f7 100%);
            border-bottom: 1px solid #f0f2f5;
            display: flex;
            align-items: center;
        }
        .card-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #2d3748;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .card-header h2 img {
            width: 24px;
            height: 24px;
        }
        .card-body {
            padding: 30px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-bottom: 30px;
        }
        .info-item {
            display: flex;
            flex-direction: column;
            padding: 20px;
            background: #fafbfc;
            border-radius: 14px;
            border: 1px solid #e8eef4;
            transition: all 0.2s ease;
        }
        .info-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(66, 153, 225, 0.08);
            border-color: #d1e7f5;
        }
        .info-item .label {
            font-size: 14px;
            color: #718096;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .info-item .label img {
            width: 18px;
            height: 18px;
        }
        .info-item .value {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
            word-break: break-word;
        }
        
        .truncated-email {
            position: relative;
            cursor: help;
        }
        .truncated-email:hover {
            color: #4299e1;
        }
        
        .status-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
            font-size: 15px;
            color: #4a5568;
        }
        .status-item .badge {
            padding: 5px 14px;
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(72, 187, 120, 0.2);
        }
        .tips-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 10px;
        }
        .tips-card {
            padding: 18px;
            background: #fafbfc;
            border-radius: 12px;
            transition: all 0.2s ease;
        }
        .tips-card:hover {
            box-shadow: 0 4px 12px rgba(66, 153, 225, 0.08);
        }
        .tips-title {
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
        }
        .tips-text {
            font-size: 14px;
            color: #4a5568;
            line-height: 1.7;
        }
        
        .key-section {
            margin-top: 20px;
        }
        .key-container {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin: 20px 0;
        }
        .key-display {
            position: relative;
        }
        .key-input {
            width: 100%;
            padding: 18px 22px;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            font-size: 15px;
            color: #2d3748;
            background: #fafbfc;
            font-family: 'Consolas', 'Monaco', monospace;
            word-break: break-all;
            line-height: 1.5;
            transition: all 0.3s ease;
        }
        .key-input:focus {
            outline: none;
            border-color: #4299e1;
            box-shadow: 0 0 0 4px rgba(66, 153, 225, 0.1);
        }
        .key-wrapper {
            display: flex;
            gap: 12px;
            align-items: stretch;
        }
        .key-input-wrapper {
            flex: 1;
            position: relative;
        }
        .key-input {
            padding-right: 50px;
        }
        .copy-btn {
            padding: 14px 24px;
            background: linear-gradient(135deg, #3cb829 0%, #20b422 100%);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 255, 229, 0.83);
            white-space: nowrap;
        }
        .copy-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgb(0, 242, 255);
            background: linear-gradient(135deg, #00ff1a 0%, #5fb886 100%);
        }
        .copy-btn:active {
            transform: translateY(0);
        }
        .copy-btn img {
            width: 18px;
            height: 18px;
        }
        .copy-btn.copied {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
        }
        .btn-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .reset-btn {
            align-self: flex-start;
            padding: 14px 32px;
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(237, 137, 54, 0.25);
        }
        .reset-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(237, 137, 54, 0.35);
            background: linear-gradient(135deg, #f6ad55 0%, #ed8936 100%);
        }
        .reset-btn:active {
            transform: translateY(0);
        }
        .reset-btn img {
            width: 18px;
            height: 18px;
        }
        
        /* 弹窗样式 */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.2s ease;
        }
        .modal-overlay.show {
            display: flex;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-box {
            background: #ffffff;
            border-radius: 20px;
            padding: 32px;
            max-width: 420px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            animation: slideIn 0.3s ease;
        }
        .modal-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #fff5eb 0%, #fed7aa 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .modal-icon img {
            width: 32px;
            height: 32px;
        }
        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: #2d3748;
            text-align: center;
            margin-bottom: 12px;
        }
        .modal-message {
            font-size: 15px;
            color: #718096;
            text-align: center;
            line-height: 1.7;
            margin-bottom: 28px;
        }
        .modal-message strong {
            color: #dd6b20;
        }
        .modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        .modal-btn {
            padding: 12px 28px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
        }
        .modal-btn-cancel {
            background: #f7fafc;
            color: #4a5568;
            border: 1px solid #e2e8f0;
        }
        .modal-btn-cancel:hover {
            background: #edf2f7;
            border-color: #cbd5e0;
        }
        .modal-btn-confirm {
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(237, 137, 54, 0.25);
        }
        .modal-btn-confirm:hover {
            box-shadow: 0 6px 16px rgba(237, 137, 54, 0.35);
            transform: translateY(-1px);
        }
        
        /* 温馨文案样式 */
        .greeting-subtitle {
            font-size: 14px;
            color: #718096;
            margin-top: 8px;
            font-weight: 400;
        }
        .credit-tip {
            margin-top: 10px;
            font-size: 14px;
            color: #ed8936;
            padding: 14px 18px;
            background: #fff7ed;
            border-radius: 10px;
        }
        .security-notice {
            background: #f8fafc;
            padding: 22px;
            border-radius: 14px;
            margin-top: 20px;
            border: 1px solid #e2e8f0;
        }
        .warning-text {
            color: #e53e3e;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .tips-group {
                grid-template-columns: 1fr;
            }
            .card-body {
                padding: 20px;
            }
            .card-header {
                padding: 20px;
            }
            .greeting-bar {
                font-size: 19px;
            }
            .key-wrapper {
                flex-direction: column;
            }
            .copy-btn {
                justify-content: center;
            }
            .btn-group {
                flex-direction: column;
            }
            .reset-btn {
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="greeting-bar">
    <div>
        <?= htmlspecialchars($greeting) ?>，<span><?= htmlspecialchars($userInfo['username']) ?></span>
        <div class="greeting-subtitle"><?= htmlspecialchars($warmText) ?></div>
    </div>
</div>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2>
                <img src="../svg/用户.svg" alt="用户概览">
                用户概览
            </h2>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <div class="label">
                        <img src="../svg/用户名称.svg" alt="用户名">
                        用户名
                    </div>
                    <div class="value"><?= htmlspecialchars($userInfo['username']) ?></div>
                </div>
                <div class="info-item">
                    <div class="label">
                        <img src="../svg/用户.svg" alt="注册邮箱">
                        邮箱
                    </div>
                    <div class="value"><?= formatLongEmail($userEmail) ?></div>
                </div>
                <div class="info-item">
                    <div class="label">
                        <img src="../svg/积分.svg" alt="用户积分">
                        积分
                    </div>
                    <div class="value"><?= (int)$userInfo['points'] ?></div>
                </div>
            </div>
            
            <div class="status-item">
                <span>账户状态：</span>
                <span class="badge">正常</span>
                <span><?= htmlspecialchars($loginRegion) ?></span>
            </div>

            <div class="tips-group">
                <div class="tips-card">
                    <div class="tips-title">账户安全</div>
                    <div class="tips-text">
                        您的账户已启用多重安全保护，建议定期更换密码以提升账户安全性。
                    </div>
                </div>
                <div class="tips-card">
                    <div class="tips-title">账户权益</div>
                    <div class="tips-text">
                        每日签到可获得额外积分，积分可用于API接口调用。
                    </div>
                </div>
                <div class="tips-card">
                    <div class="tips-title">使用建议</div>
                    <div class="tips-text">
                        合理规划API调用频率，避免短时间内大量请求，以获得更好的使用体验。
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>
                <img src="../svg/刷新.svg" alt="接入管理">
                API接入管理
            </h2>
        </div>
        <div class="card-body">
            <div class="tips-text" style="margin-bottom: 20px;">
                管理您的API接入凭证。请妥善保管您的密钥信息，切勿泄露给他人。
            </div>
            
            <div class="key-section">
                <div class="key-container">
                    <div class="key-wrapper">
                        <div class="key-input-wrapper">
                            <input type="text" class="key-input" id="keyDisplay" 
                                   value="<?= htmlspecialchars(substr($userKey, 0, 11) . str_repeat('*', 20) . substr($userKey, -8)) ?>" 
                                   readonly placeholder="您的API密钥将在此显示">
                            <input type="hidden" id="fullKey" value="<?= htmlspecialchars($userKey) ?>">
                        </div>
                        <button type="button" class="copy-btn" onclick="copyKey()">
                            <img src="../svg/复制.svg" alt="复制">
                            <span id="copyText">复制密钥</span>
                        </button>
                    </div>
                    
                    <div class="btn-group">
                        <form method="post" id="resetForm" style="margin:0;">
                            <button type="button" class="reset-btn" onclick="showResetModal()">
                                <img src="../svg/刷新.svg" alt="刷新">
                                重置密钥
                            </button>
                            <input type="hidden" name="action" value="reset_key">
                        </form>
                    </div>
                </div>
                
                <div class="credit-tip">
                    提示：每成功发起一次API请求将消耗积分
                </div>

                <div class="security-notice">
                    <div class="tips-title" style="margin-bottom: 12px;">Key安全须知</div>
                    <p class="tips-text" style="margin-bottom: 0;">
                        <span class="warning-text">重要：</span>
                        请妥善保管您的密钥，不要泄露给他人。如发现密钥泄露或账户异常，请立即重置密钥。
                        密钥重置后原密钥将立即失效，请及时更新您的调用配置。建议每3个月更换一次密钥，提升账户安全性。
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 重置密钥确认弹窗 -->
<div class="modal-overlay" id="resetModal">
    <div class="modal-box">
        <div class="modal-icon">
            <img src="../svg/警告.svg" alt="警告" onerror="this.innerHTML='⚠️'">
        </div>
        <div class="modal-title">确认重置密钥？</div>
        <div class="modal-message">
            重置后，<strong>原密钥将立即失效</strong>，使用该密钥的应用将无法继续调用接口。<br>
            请确保您已了解此操作的影响。
        </div>
        <div class="modal-buttons">
            <button class="modal-btn modal-btn-cancel" onclick="hideResetModal()">取消</button>
            <button class="modal-btn modal-btn-confirm" onclick="confirmReset()">确认重置</button>
        </div>
    </div>
</div>

<script>
// 完整密钥（用于复制）
const fullKey = document.getElementById('fullKey').value;

// 复制密钥到剪贴板
function copyKey() {
    const copyBtn = document.querySelector('.copy-btn');
    const copyText = document.getElementById('copyText');
    
    // 使用 Clipboard API 复制完整密钥
    navigator.clipboard.writeText(fullKey).then(function() {
        // 显示复制成功状态
        copyBtn.classList.add('copied');
        copyText.textContent = '已复制!';
        
        // 2秒后恢复原状
        setTimeout(function() {
            copyBtn.classList.remove('copied');
            copyText.textContent = '复制密钥';
        }, 4000);
    }).catch(function(err) {
        // 降级方案：使用传统复制方法
        const textArea = document.createElement('textarea');
        textArea.value = fullKey;
        textArea.style.position = 'fixed';
        textArea.style.left = '-9999px';
        document.body.appendChild(textArea);
        textArea.select();
        
        try {
            document.execCommand('copy');
            copyBtn.classList.add('copied');
            copyText.textContent = '已复制!';
            
            setTimeout(function() {
                copyBtn.classList.remove('copied');
                copyText.textContent = '复制密钥';
            }, 2000);
        } catch (e) {
            alert('复制失败，请手动复制：' + fullKey);
        }
        
        document.body.removeChild(textArea);
    });
}

// 显示重置确认弹窗
function showResetModal() {
    document.getElementById('resetModal').classList.add('show');
}

// 隐藏弹窗
function hideResetModal() {
    document.getElementById('resetModal').classList.remove('show');
}

// 确认重置
function confirmReset() {
    document.getElementById('resetForm').submit();
}

// 点击遮罩层关闭弹窗
document.getElementById('resetModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideResetModal();
    }
});

// ESC键关闭弹窗
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideResetModal();
    }
});
</script>

</body>
</html>
