<?php
// ========== 核心修复：按请求类型动态设置响应头 ==========
// 1. 基础跨域配置（PHP8.1兼容）
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("X-Frame-Options: ALLOWALL"); // 允许在其他网页嵌入
header("Cache-Control: no-cache, no-store, must-revalidate"); // 禁用缓存（用户中心适配）
header("Pragma: no-cache");
header("Expires: 0");

// 2. 处理OPTIONS预检请求（单独处理，避免干扰）
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Content-Type: text/plain; charset=utf-8");
    http_response_code(200);
    exit;
}

// 3. 初始化变量
$userEmail = '';
$pdo = null;
$signResult = ['code' => 0, 'msg' => '', 'data' => []];

// ========== 数据库连接（抽离为公共逻辑） ==========
function connectDB($envPath) {
    if (!file_exists($envPath)) {
        return ['error' => "无法找到.env配置文件，请检查路径：{$envPath}"];
    }
    $env = parse_ini_file($envPath, true);
    if (!$env) {
        return ['error' => "无法解析.env配置文件"];
    }
    try {
        $dsn = "mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_NAME']};charset=utf8mb4";
        $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT => true
        ]);
        return ['pdo' => $pdo];
    } catch (PDOException $e) {
        return ['error' => "数据库连接失败: " . $e->getMessage()];
    }
}

// ========== 签到功能（仅处理POST请求，独立逻辑） ==========
$isSignRequest = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'sign') {
    $isSignRequest = true;
    header("Content-Type: application/json; charset=utf-8"); // 仅签到请求返回JSON
    
    // 连接数据库
    $dbResult = connectDB(__DIR__ . '/.env');
    if (isset($dbResult['error'])) {
        echo json_encode(['code' => -1, 'msg' => $dbResult['error']]);
        exit;
    }
    $pdo = $dbResult['pdo'];

    // 获取用户邮箱
    $token = $_COOKIE['user_token'] ?? '';
    if (!empty($token)) {
        try {
            $stmt = $pdo->prepare("SELECT email FROM users WHERE token = :token LIMIT 1");
            $stmt->bindParam(':token', $token, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch();
            $userEmail = $user['email'] ?? '';
        } catch (PDOException $e) {
            echo json_encode(['code' => -1, 'msg' => "查询用户信息失败: " . $e->getMessage()]);
            exit;
        }
    }

    // 验证用户是否存在
    if (empty($userEmail)) {
        echo json_encode(['code' => -1, 'msg' => '未登录或用户不存在']);
        exit;
    }

    // 处理签到逻辑
    $safeEmail = urlencode($userEmail);
    $userDir = __DIR__ . '/../users/' . $safeEmail;
    $signJsonPath = $userDir . '/sign.json';
    $userJsonPath = $userDir . '/user.json';
    
    // 确保目录存在
    if (!is_dir($userDir)) {
        mkdir($userDir, 0755, true);
        chmod($userDir, 0755);
    }

    // 读取签到记录
    $signData = [
        'continuous_days' => 0,
        'records' => []
    ];
    if (file_exists($signJsonPath)) {
        $jsonContent = file_get_contents($signJsonPath);
        $loadedSignData = json_decode($jsonContent, true);
        if (is_array($loadedSignData)) {
            $signData = array_merge($signData, $loadedSignData);
        }
    }

    // 检查今日是否已签到
    $today = date('Y-m-d');
    $hasSignedToday = false;
    foreach ($signData['records'] as $record) {
        if ($record['date'] === $today) {
            $hasSignedToday = true;
            break;
        }
    }
    if ($hasSignedToday) {
        echo json_encode(['code' => 1, 'msg' => '今日已签到，无需重复签到！']);
        exit;
    }

    // 计算连续签到天数
    $lastSignDate = !empty($signData['records']) ? end($signData['records'])['date'] : '';
    $currentContinuousDays = $signData['continuous_days'];
    if (!empty($lastSignDate)) {
        $lastTime = strtotime($lastSignDate);
        $yesterday = strtotime('-1 day', strtotime($today));
        if (date('Y-m-d', $lastTime) === date('Y-m-d', $yesterday)) {
            $currentContinuousDays += 1;
        } else {
            $currentContinuousDays = 1;
        }
    } else {
        $currentContinuousDays = 1;
    }

    // 计算积分
    $basePoints = rand(11, 20);
    $extraPoints = $currentContinuousDays >= 2 ? $currentContinuousDays : 0;
    $totalPoints = $basePoints + $extraPoints;

    // 更新签到记录
    $signData['continuous_days'] = $currentContinuousDays;
    $signData['records'][] = [
        'date' => $today,
        'base_points' => $basePoints,
        'extra_points' => $extraPoints,
        'total_points' => $totalPoints,
        'timestamp' => time()
    ];
    file_put_contents(
        $signJsonPath,
        json_encode($signData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
    chmod($signJsonPath, 0644);

    // 更新用户积分
    $userData = [
        'username' => '默认用户',
        'level' => 1,
        'points' => 0,
        'used_space_kb' => 0
    ];
    if (file_exists($userJsonPath)) {
        $jsonContent = file_get_contents($userJsonPath);
        $loadedUserData = json_decode($jsonContent, true);
        if (is_array($loadedUserData)) {
            $userData = array_merge($userData, $loadedUserData);
        }
    }
    $userData['points'] += $totalPoints;
    file_put_contents(
        $userJsonPath,
        json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
    chmod($userJsonPath, 0644);

    // 返回成功结果
    echo json_encode([
        'code' => 200,
        'msg' => '签到成功！',
        'data' => [
            'base_points' => $basePoints,
            'extra_points' => $extraPoints,
            'total_points' => $totalPoints,
            'continuous_days' => $currentContinuousDays
        ]
    ]);
    exit;
}

// ========== 非签到请求（GET）：输出HTML页面 ==========
if (!$isSignRequest) {
    header("Content-Type: text/html; charset=utf-8"); // 核心修复：HTML页面设置正确的Content-Type
}

// 连接数据库（页面展示用）
$dbResult = connectDB(__DIR__ . '/.env');
if (isset($dbResult['error'])) {
    die("<div style='text-align:center; margin-top:50px; font-size:18px; color:red;'>{$dbResult['error']}</div>");
}
$pdo = $dbResult['pdo'];

// 获取用户邮箱
$token = $_COOKIE['user_token'] ?? '';
if (!empty($token)) {
    try {
        $stmt = $pdo->prepare("SELECT email FROM users WHERE token = :token LIMIT 1");
        $stmt->bindParam(':token', $token, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch();
        $userEmail = $user['email'] ?? '';
    } catch (PDOException $e) {
        die("<div style='text-align:center; margin-top:50px; font-size:18px; color:red;'>查询用户信息失败: {$e->getMessage()}</div>");
    }
}

// 读取用户数据
$userData = [
    'username' => '默认用户',
    'level' => 1,
    'points' => 0,
    'used_space_kb' => 0
];
$signStatus = ['hasSignedToday' => false, 'continuous_days' => 0];

if (!empty($userEmail)) {
    $safeEmail = urlencode($userEmail);
    $userDir = __DIR__ . '/../users/' . $safeEmail;
    $userJsonPath = $userDir . '/user.json';
    
    // 创建目录
    if (!is_dir($userDir)) {
        mkdir($userDir, 0755, true);
        chmod($userDir, 0755);
    }
    
    // 读取用户数据
    if (file_exists($userJsonPath)) {
        $jsonContent = file_get_contents($userJsonPath);
        $loadedData = json_decode($jsonContent, true);
        if (is_array($loadedData)) {
            $userData = array_merge($userData, $loadedData);
        }
    } else {
        file_put_contents(
            $userJsonPath,
            json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        chmod($userJsonPath, 0644);
    }

    // 读取签到状态
    $signJsonPath = $userDir . '/sign.json';
    if (file_exists($signJsonPath)) {
        $jsonContent = file_get_contents($signJsonPath);
        $signData = json_decode($jsonContent, true);
        if (is_array($signData)) {
            $signStatus['continuous_days'] = $signData['continuous_days'] ?? 0;
            $today = date('Y-m-d');
            foreach ($signData['records'] ?? [] as $record) {
                if ($record['date'] === $today) {
                    $signStatus['hasSignedToday'] = true;
                    break;
                }
            }
        }
    }
}

// 工具函数
function convertSpaceUnit(int $kb, string $unit = 'auto'): array {
    $units = ['kb', 'mb', 'gb', 'tb'];
    $conversions = [1, 1024, 1024*1024, 1024*1024*1024];
    
    if ($unit === 'auto') {
        $index = 0;
        $value = $kb;
        while ($value >= 1024 && $index < 3) {
            $value /= 1024;
            $index++;
        }
        return [round($value, 2), $units[$index]];
    }
    
    $index = array_search($unit, $units);
    $index = $index === false ? 0 : $index;
    return [round($kb / $conversions[$index], 2), $unit];
}

function getLevelQuota(int $level): array {
    if ($level > 10) {
        return [PHP_INT_MAX, '无限'];
    }
    $quotas = [
        1 => 2 * 1024 * 1024,    // 2GB
        2 => 4 * 1024 * 1024,    // 4GB
        3 => 8 * 1024 * 1024,    // 8GB
        4 => 16 * 1024 * 1024,   // 16GB
        5 => 32 * 1024 * 1024,   // 32GB
        6 => 64 * 1024 * 1024,   // 64GB
        7 => 128 * 1024 * 1024,  // 128GB
        8 => 256 * 1024 * 1024,  // 256GB
        9 => 512 * 1024 * 1024,  // 512GB
        10 => 1024 * 1024 * 1024 // 1TB
    ];
    $quotaKb = $quotas[$level] ?? $quotas[1];
    $quotaText = $level >= 10 ? '1TB' : ($quotaKb / 1024 / 1024) . 'GB';
    return [$quotaKb, $quotaText];
}

// 计算空间数据
[$quotaKb, $quotaText] = getLevelQuota($userData['level']);
$usedKb = (int)$userData['used_space_kb'];
$remainingKb = $quotaKb === PHP_INT_MAX ? PHP_INT_MAX : max(0, $quotaKb - $usedKb);
$usagePercent = $quotaKb === PHP_INT_MAX ? 0 : min(100, ($usedKb / $quotaKb) * 100);

// 等级颜色
$levelColors = [
    1 => '#FF6B6B',  2 => '#FF8E53',  3 => '#FFCE56',  4 => '#4ECDC4',
    5 => '#45B7D1',  6 => '#96CEB4',  7 => '#FFEAA7',  8 => '#DDA0DD',
    9 => '#87CEEB',  10 => '#FF69B4', 'default' => '#6C757D'
];
$userColor = $levelColors[$userData['level']] ?? $levelColors['default'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>用户中心</title>
    <link rel="preload" href="/../font/AlimamaAgileVF-Thin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/../images/China.png" as="image">
    <style>
        @font-face {
            font-family: 'AlimamaAgile';
            src: url('/../font/AlimamaAgileVF-Thin.woff2') format('woff2'),
                 url('/../font/AlimamaAgileVF-Thin.woff') format('woff'),
                 url('/../font/AlimamaAgileVF-Thin.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }
        
        html {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        html::-webkit-scrollbar {
            display: none;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'AlimamaAgile', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        body {
            background-color: #F5F5F7;
            color: #1D1D1F;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
            background-image: radial-gradient(rgba(0,0,0,0.015) 1px, transparent 1px);
            background-size: 25px 25px;
        }
        
        /* 加载动画 */
        #loader-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #F5F5F7;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: opacity 0.8s ease, visibility 0.8s ease;
        }
        #loader-wrapper.hidden {
            opacity: 0;
            visibility: hidden;
        }
        #lottie-loader {
            width: 200px;
            height: 200px;
        }
        .loader-text {
            position: absolute;
            bottom: 30%;
            font-size: 1.2rem;
            color: #86868B;
            letter-spacing: 0.5px;
        }
        
        /* 国旗样式 */
        .china-flag {
            position: fixed;
            top: 1.2rem;
            right: 1.2rem;
            width: 120px;
            height: 80px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
            z-index: 10;
            transition: all 0.4s ease;
            border: 1px solid rgba(255,255,255,0.8);
        }
        .china-flag:hover {
            transform: scale(1.05) translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        .china-flag img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }
        .china-flag:hover img {
            transform: scale(1.08);
        }
        
        /* 主容器 */
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 3rem 1.5rem;
            position: relative;
            z-index: 1;
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.8s ease 0.3s, transform 0.8s ease 0.3s;
        }
        .container.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* 用户卡片 */
        .user-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.85));
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border-radius: 32px;
            padding: 3.5rem 2.5rem;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.9);
            margin-top: 1rem;
            position: relative;
            overflow: hidden;
        }
        
        /* 网站标题样式 - 新增 */
        .site-title {
            font-size: 2rem;
            font-weight: 600;
            color: #1D1D1F;
            text-align: center;
            margin-bottom: 2rem;
            letter-spacing: 0.8px;
            position: relative;
            padding-bottom: 1rem;
        }
        .site-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 2px;
            background: linear-gradient(90deg, transparent, <?php echo $userColor; ?>, transparent);
        }
        
        /* 签到按钮样式调整 - 适配积分右侧位置 */
        .sign-btn-wrapper {
            margin-left: 0.5rem;
        }
        .sign-btn {
            padding: 0.6rem 1.4rem;
            border-radius: 24px;
            border: 1px solid <?php echo $userColor; ?>;
            background: rgba(255,255,255,0.9);
            color: <?php echo $userColor; ?>;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-weight: 500;
            box-shadow: 0 3px 8px rgba(0,0,0,0.05);
        }
        .sign-btn:disabled {
            background: #f5f5f5;
            border-color: #ddd;
            color: #999;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .sign-btn:hover:not(:disabled) {
            background: <?php echo $userColor; ?>;
            color: #FFF;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .sign-btn svg {
            width: 16px;
            height: 16px;
            transition: transform 0.3s ease;
        }
        .sign-btn:hover:not(:disabled) svg {
            transform: rotate(15deg);
        }
        
        /* Toast提示 */
        .toast {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.8);
            background: rgba(0,0,0,0.8);
            color: #fff;
            padding: 1.5rem 2rem;
            border-radius: 16px;
            font-size: 1.2rem;
            z-index: 99999;
            opacity: 0;
            transition: all 0.3s ease;
            text-align: center;
            min-width: 280px;
        }
        .toast.show {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1);
        }
        
        /* 信息行 */
        .info-row {
            display: flex;
            align-items: center;
            margin: 2.2rem 0;
            gap: 1.5rem;
            padding-bottom: 1.8rem;
            border-bottom: 1px solid rgba(0,0,0,0.03);
            transition: all 0.3s ease;
        }
        .info-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
            margin-bottom: 0;
        }
        .info-row:hover {
            transform: translateX(5px);
            border-bottom-color: rgba(<?php echo hexdec(substr($userColor,1,2)); ?>, <?php echo hexdec(substr($userColor,3,2)); ?>, <?php echo hexdec(substr($userColor,5,2)); ?>, 0.1);
        }
        .info-row .icon {
            width: 36px;
            height: 36px;
            flex-shrink: 0;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.05));
            background: rgba(<?php echo hexdec(substr($userColor,1,2)); ?>, <?php echo hexdec(substr($userColor,3,2)); ?>, <?php echo hexdec(substr($userColor,5,2)); ?>, 0.08);
            border-radius: 12px;
            padding: 6px;
        }
        .info-row .label {
            font-size: 1.25rem;
            color: #6E6E73;
            min-width: 90px;
            letter-spacing: 0.3px;
            font-weight: 400;
        }
        .info-row .value {
            font-size: 1.4rem;
            font-weight: 500;
            flex: 1;
            letter-spacing: 0.2px;
            color: #1D1D1F;
        }
        .info-row .level-value {
            color: <?php echo $userColor; ?>;
            font-weight: 600;
            font-size: 1.5rem;
            position: relative;
        }
        
        /* 进度条 */
        .usage-progress {
            margin: 3rem 0 1rem 0;
            padding: 1.8rem 1.5rem;
            background: rgba(<?php echo hexdec(substr($userColor,1,2)); ?>, <?php echo hexdec(substr($userColor,3,2)); ?>, <?php echo hexdec(substr($userColor,5,2)); ?>, 0.05);
            border-radius: 20px;
            border: 1px solid rgba(<?php echo hexdec(substr($userColor,1,2)); ?>, <?php echo hexdec(substr($userColor,3,2)); ?>, <?php echo hexdec(substr($userColor,5,2)); ?>, 0.1);
        }
        .usage-progress-title {
            font-size: 1.2rem;
            color: #6E6E73;
            margin-bottom: 1rem;
            letter-spacing: 0.3px;
        }
        .progress-bar-container {
            width: 100%;
            height: 24px;
            background: #F0F0F2;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.03);
        }
        .progress-bar-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, <?php echo $userColor; ?>, rgba(<?php echo hexdec(substr($userColor,1,2)); ?>, <?php echo hexdec(substr($userColor,3,2)); ?>, <?php echo hexdec(substr($userColor,5,2)); ?>, 0.8));
            border-radius: 12px;
            transition: width 4s cubic-bezier(0.25, 0.1, 0.25, 1);
            position: relative;
        }
        .progress-bar-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 40%;
            height: 100%;
            background: linear-gradient(90deg, rgba(255,255,255,0.3), rgba(255,255,255,0));
            border-radius: 12px;
        }
        .progress-text {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            font-size: 1rem;
            font-weight: 500;
            color: #1D1D1F;
            z-index: 2;
        }
        .unlimited-space {
            width: 100%;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .unlimited-symbol {
            font-size: 2.5rem;
            color: <?php echo $userColor; ?>;
            font-weight: 300;
            text-shadow: 0 2px 8px rgba(<?php echo hexdec(substr($userColor,1,2)); ?>, <?php echo hexdec(substr($userColor,3,2)); ?>, <?php echo hexdec(substr($userColor,5,2)); ?>, 0.2);
            animation: pulse 3s infinite ease-in-out;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.9; }
            50% { transform: scale(1.1); opacity: 1; }
        }
        
        /* 单位切换按钮 */
        .unit-switch-single {
            margin-left: 0.5rem;
        }
        .unit-toggle-btn {
            padding: 0.6rem 1.4rem;
            border-radius: 24px;
            border: 1px solid <?php echo $userColor; ?>;
            background: rgba(255,255,255,0.9);
            color: <?php echo $userColor; ?>;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-weight: 500;
            box-shadow: 0 3px 8px rgba(0,0,0,0.05);
        }
        .unit-toggle-btn:hover {
            background: <?php echo $userColor; ?>;
            color: #FFF;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .unit-toggle-btn svg {
            width: 16px;
            height: 16px;
            transition: transform 0.3s ease;
        }
        .unit-toggle-btn:hover svg {
            transform: rotate(90deg);
        }
        
        /* 响应式适配 */
        @media (max-width: 768px) {
            .container {
                padding: 2rem 1rem;
                max-width: 100%;
            }
            .china-flag {
                width: 90px;
                height: 60px;
                top: 0.8rem;
                right: 0.8rem;
                border-radius: 12px;
            }
            .user-card {
                padding: 2.5rem 1.8rem;
                border-radius: 24px;
            }
            /* 网站标题响应式 */
            .site-title {
                font-size: 1.6rem;
                margin-bottom: 1.5rem;
            }
            .info-row {
                margin: 1.8rem 0;
                gap: 1rem;
                padding-bottom: 1.5rem;
                /* 移动端积分行换行适配 */
                flex-wrap: wrap;
            }
            .info-row .label {
                min-width: 75px;
                font-size: 1.1rem;
            }
            .info-row .value {
                font-size: 1.3rem;
                flex: 1 1 auto;
            }
            /* 签到按钮和单位按钮响应式 */
            .sign-btn-wrapper, .unit-switch-single {
                margin-left: 0;
                margin-top: 0.8rem;
                width: 100%;
            }
            .sign-btn, .unit-toggle-btn {
                width: 100%;
                justify-content: center;
                padding: 0.5rem 1.2rem;
                font-size: 0.95rem;
            }
            .usage-progress {
                padding: 1.5rem 1.2rem;
                margin: 2rem 0 0.5rem 0;
            }
            .usage-progress-title {
                font-size: 1.1rem;
            }
            .progress-bar-container {
                height: 20px;
                border-radius: 10px;
            }
            .progress-text {
                font-size: 0.9rem;
                right: 8px;
            }
            .unlimited-symbol {
                font-size: 2rem;
            }
            .loader-text {
                font-size: 1rem;
                bottom: 25%;
            }
            #lottie-loader {
                width: 160px;
                height: 160px;
            }
        }
        
        @media (max-width: 480px) {
            .china-flag {
                width: 70px;
                height: 45px;
            }
            .user-card {
                padding: 2rem 1.5rem;
            }
            .site-title {
                font-size: 1.4rem;
            }
            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <div id="loader-wrapper">
        <div id="lottie-loader"></div>
        <div class="loader-text">正在加载...</div>
    </div>

    <div class="china-flag">
        <img src="/../images/China.png" alt="中国国旗" loading="lazy">
    </div>
    
    <div class="container">
        <div class="user-card">
            <!-- 新增：网站标题 -->
            <div class="site-title">云端图片储存</div>
            
            <div class="info-row">
                <img src="../../svg/用户名称.svg" alt="用户名" class="icon" loading="lazy">
                <div class="label">名称</div>
                <div class="value"><?php echo htmlspecialchars($userData['username']); ?></div>
            </div>
            
            <div class="info-row">
                <img src="../../svg/等级.svg" alt="用户等级" class="icon" loading="lazy">
                <div class="label">等级</div>
                <div class="value level-value" id="levelValue">0</div>
            </div>
            
            <div class="info-row">
                <img src="../../svg/积分.svg" alt="用户积分" class="icon" loading="lazy">
                <div class="label">积分</div>
                <div class="value" id="pointsValue">0</div>
                <!-- 调整：签到按钮移到积分右侧 -->
                <div class="sign-btn-wrapper">
                    <button class="sign-btn" id="signBtn" <?php echo $signStatus['hasSignedToday'] ? 'disabled' : ''; ?>>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                        </svg>
                        <span><?php echo $signStatus['hasSignedToday'] ? '今日已签到' : '签到'; ?></span>
                    </button>
                </div>
            </div>
            
            <div class="info-row">
                <img src="../../svg/硬盘.svg" alt="已用空间" class="icon" loading="lazy">
                <div class="label">已用空间</div>
                <div class="value" id="used-space">0</div>
                <div class="unit-switch-single">
                    <button class="unit-toggle-btn" id="unitToggleBtn">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 18l6-6-6-6"/>
                        </svg>
                        <span id="currentUnitText">自动</span>
                    </button>
                </div>
            </div>
            
            <div class="info-row">
                <img src="../../svg/云磁盘.svg" alt="剩余空间" class="icon" loading="lazy">
                <div class="label">剩余空间</div>
                <div class="value" id="remaining-space">0</div>
            </div>
            
            <div class="usage-progress">
                <div class="usage-progress-title">空间使用占比</div>
                <?php if ($quotaKb === PHP_INT_MAX): ?>
                    <div class="unlimited-space">
                        <span class="unlimited-symbol">∞</span>
                    </div>
                <?php else: ?>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" id="progressBarFill"></div>
                        <div class="progress-text" id="progressPercentText">0%</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="toast" id="signToast"></div>

    <script src="../../main/js/lottie.min.js" defer></script>
    <script>
        // 初始化变量
        const targetLevel = <?php echo (int)$userData['level']; ?>;
        const targetPoints = <?php echo (int)$userData['points']; ?>;
        const usedKb = <?php echo $usedKb; ?>;
        const remainingKb = <?php echo $remainingKb; ?>;
        const isUnlimited = remainingKb === <?php echo PHP_INT_MAX; ?>;
        const usagePercent = <?php echo $usagePercent; ?>;
        const unitOrder = ['auto', 'kb', 'mb', 'gb', 'tb'];
        let currentUnitIndex = 0;
        const hasSignedToday = <?php echo $signStatus['hasSignedToday'] ? 'true' : 'false'; ?>;
        
        // 数字动画函数
        function animateNumber(elementId, targetValue, isSpace = false, unit = '') {
            const element = document.getElementById(elementId);
            const duration = Math.floor(Math.random() * 1000) + 1000;
            const startTime = performance.now();
            const startValue = 0;
            
            const isFloat = typeof targetValue === 'number' && targetValue % 1 !== 0;
            const step = (timestamp) => {
                const elapsed = timestamp - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const easeProgress = 1 - Math.pow(1 - progress, 3);
                
                let currentValue = startValue + (targetValue - startValue) * easeProgress;
                if (!isFloat) currentValue = Math.floor(currentValue);
                
                if (isSpace) {
                    element.textContent = `${currentValue.toFixed(2)} ${unit.toUpperCase()}`;
                } else if (elementId === 'levelValue') {
                    element.textContent = `${Math.floor(currentValue)}级`;
                } else {
                    element.textContent = currentValue;
                }
                
                if (progress < 1) {
                    requestAnimationFrame(step);
                } else {
                    if (isSpace) {
                        element.textContent = `${targetValue.toFixed(2)} ${unit.toUpperCase()}`;
                    } else if (elementId === 'levelValue') {
                        element.textContent = `${targetLevel}级`;
                    } else {
                        element.textContent = targetValue;
                    }
                }
            };
            
            requestAnimationFrame(step);
        }
        
        // 进度条动画
        function animateProgressBar() {
            if (isUnlimited) return;
            
            const progressBar = document.getElementById('progressBarFill');
            const progressText = document.getElementById('progressPercentText');
            const duration = 2000;
            const startTime = performance.now();
            
            const step = (timestamp) => {
                const elapsed = timestamp - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const easeProgress = 1 - (1 - progress) * (1 - progress);
                
                const currentPercent = Math.floor(usagePercent * easeProgress);
                progressBar.style.width = `${currentPercent}%`;
                progressText.textContent = `${currentPercent}%`;
                
                if (progress < 1) {
                    requestAnimationFrame(step);
                } else {
                    progressBar.style.width = `${usagePercent}%`;
                    progressText.textContent = `${usagePercent.toFixed(1)}%`;
                }
            };
            
            requestAnimationFrame(step);
        }
        
        // 单位转换
        function convertUnit(kb, unit) {
            const units = ['kb', 'mb', 'gb', 'tb'];
            const conversions = [1, 1024, 1024*1024, 1024*1024*1024];
            
            if (unit === 'auto') {
                let index = 0;
                let value = kb;
                while (value >= 1024 && index < 3) {
                    value /= 1024;
                    index++;
                }
                return [value, units[index]];
            }
            
            const index = units.indexOf(unit);
            return [(kb / conversions[index]), unit];
        }
        
        // 更新空间显示
        function updateSpaceDisplay(unit, animate = true) {
            const [usedVal, usedUnit] = convertUnit(usedKb, unit);
            const [remainingVal, remainingUnit] = convertUnit(remainingKb, unit);
            
            if (animate) {
                animateNumber('used-space', usedVal, true, usedUnit);
                if (!isUnlimited) {
                    animateNumber('remaining-space', remainingVal, true, remainingUnit);
                }
            } else {
                document.getElementById('used-space').textContent = `${usedVal.toFixed(2)} ${usedUnit.toUpperCase()}`;
                if (!isUnlimited) {
                    document.getElementById('remaining-space').textContent = `${remainingVal.toFixed(2)} ${remainingUnit.toUpperCase()}`;
                }
            }
            
            document.getElementById('currentUnitText').textContent = unit === 'auto' ? '自动' : usedUnit.toUpperCase();
        }
        
        // Toast提示
        function showToast(message, duration = 6000) {
            const toast = document.getElementById('signToast');
            toast.textContent = message;
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, duration);
        }
        
        // 签到请求
        async function doSign() {
            const signBtn = document.getElementById('signBtn');
            if (signBtn.disabled) return;
            
            signBtn.disabled = true;
            signBtn.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M16 12a4 4 0 1 1-8 0"></path>
                </svg>
                <span>签到中...</span>
            `;
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=sign'
                });
                
                const result = await response.json();
                if (result.code === 200) {
                    const data = result.data;
                    const message = `
                        签到成功！
                        连续签到${data.continuous_days}天
                        基础积分：${data.base_points}
                        额外积分：${data.extra_points}
                        总计获得：${data.total_points}积分
                    `;
                    showToast(message);
                    
                    const newPoints = targetPoints + data.total_points;
                    animateNumber('pointsValue', newPoints);
                    
                    signBtn.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                        </svg>
                        <span>今日已签到</span>
                    `;
                } else {
                    showToast(result.msg);
                    signBtn.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                        </svg>
                        <span>今日已签到</span>
                    `;
                }
            } catch (error) {
                showToast('签到失败，请稍后重试！');
                signBtn.disabled = false;
                signBtn.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                    </svg>
                    <span>签到</span>
                `;
                console.error('签到请求失败：', error);
            }
        }
        
        // 页面初始化
        document.addEventListener('DOMContentLoaded', () => {
            const loaderAnimation = lottie.loadAnimation({
                container: document.getElementById('lottie-loader'),
                renderer: 'canvas',
                loop: true,
                autoplay: true,
                path: '../../lottie/bg.json',
                rendererSettings: {
                    preserveAspectRatio: 'xMidYMid slice'
                }
            });
            
            const minLoadTime = 3000;
            const startTime = Date.now();
            
            loaderAnimation.addEventListener('DOMLoaded', () => {
                const waitTime = Math.max(minLoadTime - (Date.now() - startTime), 0);
                setTimeout(() => {
                    loaderAnimation.loop = false;
                    loaderAnimation.goToAndPlay(0, true);
                    
                    loaderAnimation.addEventListener('complete', () => {
                        document.getElementById('loader-wrapper').classList.add('hidden');
                        document.querySelector('.container').classList.add('show');
                        
                        animateNumber('levelValue', targetLevel);
                        animateNumber('pointsValue', targetPoints);
                        updateSpaceDisplay(unitOrder[currentUnitIndex], true);
                        animateProgressBar();
                        
                        // 单位切换
                        document.getElementById('unitToggleBtn').addEventListener('click', () => {
                            currentUnitIndex = (currentUnitIndex + 1) % unitOrder.length;
                            updateSpaceDisplay(unitOrder[currentUnitIndex], true);
                            
                            const btn = document.getElementById('unitToggleBtn');
                            btn.style.transform = 'translateY(-2px) scale(0.98)';
                            setTimeout(() => {
                                btn.style.transform = '';
                            }, 200);
                        });
                        
                        // 签到按钮
                        document.getElementById('signBtn').addEventListener('click', doSign);
                    });
                }, waitTime);
            });
        });
    </script>
</body>
</html>
