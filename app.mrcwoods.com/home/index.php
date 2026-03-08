<?php
// ========== 核心修复：按请求类型动态设置响应头 ==========
// 1. 基础跨域配置（PHP8.1兼容）
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("X-Frame-Options: ALLOWALL"); // 允许在其他网页嵌入
header("Pragma: no-cache");
header("Expires: 0");

// 2. 处理OPTIONS预检请求（单独处理，避免干扰）
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Content-Type: text/plain; charset=utf-8");
    http_response_code(200);
    exit;
}
define('ENV_FILE_PATH', __DIR__ . '/../in/.env');
// 3. 初始化变量
$userEmail = '';
$pdo = null;
$signResult = ['code' => 0, 'msg' => '', 'data' => []];

// ========== 数据库连接（抽离为公共逻辑） ==========
function connectDB($envPath) {
    if (!file_exists($envPath)) {
        return ['error' => "配置文件不存在"];
    }
    $env = parse_ini_file($envPath, true);
    if (!$env) {
        return ['error' => "无法解析.env配置"];
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'sign') {
    $isSignRequest = true;
    header("Content-Type: application/json; charset=utf-8"); // 仅签到请求返回JSON
    $dbResult = connectDB(ENV_FILE_PATH);
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
    $basePoints = rand(800, 1000);
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
$dbResult = connectDB(ENV_FILE_PATH);
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


// 获取随机一言 - 适配新接口
function getRandomWord() {
    $url = 'https://app.mrcwoods.com/api/语录.php';
    
    // 使用POST请求
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode([]),
            'timeout' => 3
        ]
    ];
    
    $context = stream_context_create($options);
    $content = @file_get_contents($url, false, $context);
    
    if ($content) {
        $data = json_decode($content, true);
        if (is_array($data) && isset($data['code']) && $data['code'] === 200 && isset($data['data']['content'])) {
            return $data['data']['content'];
        }
    }
    
    // 失败时返回默认语句
    return '精益求精。';
}
$randomWord = getRandomWord();

// 用户主题颜色定义
$userColors = ['#3B82F6', '#6366F1', '#8B5CF6', '#EC4899', '#F97316', '#10B981', '#06B6D4', '#EF4444'];
$userColor = $userColors[array_rand($userColors)];
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
        /* 随机一言响应式 */
.random-word p {
    font-size: 1rem !important;
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
            <!-- 随机一言 -->
<div style="text-align: center; margin: 1.5rem 0; padding: 1rem; background: rgba(255,255,255,0.8); border-radius: 20px; border: 1px solid rgba(<?php echo hexdec(substr($userColor,1,2)); ?>, <?php echo hexdec(substr($userColor,3,2)); ?>, <?php echo hexdec(substr($userColor,5,2)); ?>, 0.1);">
    <p style="font-size: 1.1rem; color: #6E6E73; letter-spacing: 0.5px; line-height: 1.6;"><?php echo htmlspecialchars($randomWord); ?></p>
</div>
            <div class="info-row">
                <img src="../../svg/用户名称.svg" alt="用户名" class="icon" loading="lazy">
                <div class="label">名称</div>
                <div class="value"><?php echo htmlspecialchars($userData['username']); ?></div>
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
            

        </div>
    </div>

    <div class="toast" id="signToast"></div>

    <script src="../../main/js/lottie.min.js" defer></script>
    <script>
        // 初始化变量
        const targetPoints = <?php echo (int)$userData['points']; ?>;
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
                } else {
                    element.textContent = currentValue;
                }
                
                if (progress < 1) {
                    requestAnimationFrame(step);
                } else {
                    if (isSpace) {
                        element.textContent = `${targetValue.toFixed(2)} ${unit.toUpperCase()}`;
                    } else {
                        element.textContent = targetValue;
                    }
                }
            };
            
            requestAnimationFrame(step);
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
                loop: false,              // 只播放一次
                autoplay: true,
                path: '../../lottie/bg.json',
                rendererSettings: {
                    preserveAspectRatio: 'xMidYMid slice'
                }
            });
            
            const minLoadTime = 3000;
            const startTime = Date.now();
            
            // 立即绑定事件，不等待动画加载
            function bindEvents() {
                // 签到按钮
                document.getElementById('signBtn').addEventListener('click', doSign);
            }
            
            // 立即绑定事件
            bindEvents();
            
            // 使用 DOMLoaded 事件
            loaderAnimation.addEventListener('DOMLoaded', () => {
                const waitTime = Math.max(minLoadTime - (Date.now() - startTime), 0);
                
                setTimeout(() => {
                    // 等待动画完成（因为 loop: false）
                    loaderAnimation.addEventListener('complete', () => {
                        document.getElementById('loader-wrapper').classList.add('hidden');
                        document.querySelector('.container').classList.add('show');
                        
                        // 初始化页面动画
                        animateNumber('pointsValue', targetPoints);
                    });
                }, waitTime);
            });
        });
    </script>
</body>
</html>
