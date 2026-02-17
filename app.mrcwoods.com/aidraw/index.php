<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Shanghai');

// ===================== 核心函数（原有功能不变） =====================
function cleanExpiredRecords(string $drawDir): void {
    $indexDbPath = "{$drawDir}/index.db";
    if (!file_exists($indexDbPath)) return;

    $content = file_get_contents($indexDbPath);
    $lines = explode("\n", $content);
    $newLines = [];
    $currentTime = time();
    $expireTime = 24 * 3600;

    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        if (empty($trimmedLine) || str_starts_with($trimmedLine, 'AI绘画记录') || str_starts_with($trimmedLine, '创建时间：')) {
            $newLines[] = $line;
            continue;
        }

        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $trimmedLine, $matches)) {
            $recordTime = strtotime($matches[1]);
            if ($currentTime - $recordTime < $expireTime) {
                $newLines[] = $line;
            }
        }
    }

    file_put_contents($indexDbPath, implode("\n", $newLines));
}

function readDrawRecords(string $drawDir): array {
    $indexDbPath = "{$drawDir}/index.db";
    $records = [];
    $currentTime = time();
    $expireTime = 24 * 3600;

    if (!file_exists($indexDbPath)) return $records;

    $content = file_get_contents($indexDbPath);
    $lines = explode("\n", $content);

    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        if (empty($trimmedLine) || !str_starts_with($trimmedLine, '[')) continue;

        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] 提示词：(.*?) \| 尺寸：(.*?) \| 精细度：(.*?) \| 图片链接：(.*?) \| 扣除积分：(\d+)/', $trimmedLine, $matches)) {
            $recordTime = strtotime($matches[1]);
            if ($currentTime - $recordTime < $expireTime) {
                $records[] = [
                    'time' => $matches[1],
                    'prompt' => $matches[2],
                    'size' => $matches[3],
                    'steps' => $matches[4],
                    'url' => $matches[5],
                    'points' => $matches[6],
                    'time_diff' => formatTimeDiff($recordTime)
                ];
            }
        }
    }

    usort($records, function($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });

    return $records;
}

function formatTimeDiff(int $timestamp): string {
    $diff = time() - $timestamp;
    if ($diff < 60) return $diff . '秒前';
    if ($diff < 3600) return floor($diff / 60) . '分钟前';
    if ($diff < 86400) return floor($diff / 3600) . '小时前';
    return date('Y-m-d H:i', $timestamp);
}

function loadEnv(): array {
    $envPath = '../in/.env';
    if (!file_exists($envPath)) {
        die(json_encode(['success' => false, 'msg' => "错误：.env文件不存在，请检查路径"]));
    }
    
    $env = [];
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with($line, '#')) continue;
        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim($value);
    }
    return $env;
}

function connectDB(array $env): PDO {
    try {
        $dsn = "mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_NAME']};charset=utf8mb4";
        $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die(json_encode(['success' => false, 'msg' => "数据库连接失败：" . $e->getMessage()]));
    }
}

function validateUser(PDO $pdo): ?string {
    if (!isset($_COOKIE['user_token'])) {
        die(json_encode(['success' => false, 'msg' => "错误：未检测到用户登录状态，请先登录"]));
    }
    
    $token = $_COOKIE['user_token'];
    $stmt = $pdo->prepare("SELECT email FROM users WHERE token = :token LIMIT 1");
    $stmt->bindValue(':token', $token);
    $stmt->execute();
    
    $user = $stmt->fetch();
    return $user ? $user['email'] : null;
}

function createUserDir(string $emailEncoded): string {
    $userRoot = "../users/{$emailEncoded}";
    $drawDir = "{$userRoot}/draw";
    
    if (!is_dir($userRoot)) {
        mkdir($userRoot, 0755, true);
    }
    if (!is_dir($drawDir)) {
        mkdir($drawDir, 0755, true);
    }
    
    $indexDb = "{$drawDir}/index.db";
    if (!file_exists($indexDb)) {
        file_put_contents($indexDb, "AI绘画记录\n创建时间：" . date('Y-m-d H:i:s') . "\n");
    }

    // ===================== 新增：自动创建 p.json 和 ls.json =====================
    $pJsonPath = "{$drawDir}/p.json";
    if (!file_exists($pJsonPath)) {
        $defaultPData = [
            "album_name" => "draw",
            "create_time" => time(),
            "last_upload_time" => time(),
            "image_count" => 0,
            "used_space_kb" => 0,
            "remark" => "AI绘画自动保存相册"
        ];
        file_put_contents($pJsonPath, json_encode($defaultPData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    $lsJsonPath = "{$drawDir}/ls.json";
    if (!file_exists($lsJsonPath)) {
        $defaultLsData = [];
        file_put_contents($lsJsonPath, json_encode($defaultLsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    // ===================== 新增结束 =====================

    cleanExpiredRecords($drawDir);
    
    return $userRoot;
}

function readUserJson(string $userRoot): array {
    $userJsonPath = "{$userRoot}/user.json";
    
    if (!file_exists($userJsonPath)) {
        $defaultUser = [
            "username" => explode('@', urldecode(basename($userRoot)))[0],
            "level" => 1,
            "points" => 100,
            "used_space_kb" => 0,
            "updated_at" => time()
        ];
        file_put_contents($userJsonPath, json_encode($defaultUser, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $defaultUser;
    }
    
    $content = file_get_contents($userJsonPath);
    return json_decode($content, true) ?: [];
}

function updateUserPoints(string $userRoot, int $points): bool {
    $userJsonPath = "{$userRoot}/user.json";
    $userInfo = readUserJson($userRoot);
    
    $newPoints = $userInfo['points'] + $points;
    if ($newPoints < 0) {
        return false;
    }
    
    $userInfo['points'] = $newPoints;
    $userInfo['updated_at'] = time();
    
    file_put_contents($userJsonPath, json_encode($userInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return true;
}

function validateDrawParams(array $params): array {
    $prompt = trim($params['prompt'] ?? '');
    if (empty($prompt)) {
        return [false, "提示词不能为空"];
    }
    if (mb_strlen($prompt) > 500) {
        return [false, "提示词长度不能超过500个字符"];
    }
    
    $allowedSizes = ['512*1024', '768*512', '768*1024', '1024*576', '576*1024', '1024*1024'];
    $size = $params['size'] ?? '1024*1024';
    if (!in_array($size, $allowedSizes)) {
        return [false, "不支持的图片尺寸，请选择指定尺寸"];
    }
    
    $steps = (int)($params['steps'] ?? 4);
    if ($steps < 1 || $steps > 10) {
        return [false, "精细度必须在1-10之间"];
    }
    
    return [true, "参数验证通过", [
        'prompt' => $prompt,
        'size' => $size,
        'steps' => $steps
    ]];
}

function callAIDrawApi(array $params): array {
    $apiToken = 'OiIOttqk9jZS';
    $apiUrl = 'https://yunzhiapi.cn/API/flux.1/ ';
    
    $requestParams = [
        'token' => $apiToken,
        'prompt' => $params['prompt'],
        'size' => $params['size'],
        'steps' => $params['steps'],
        'type' => 'json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($requestParams));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return [false, "API请求失败，状态码：{$httpCode}"];
    }
    
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [false, "API返回非合法JSON：{$response}"];
    }
    
    if ($result['error'] === false && isset($result['data']['url'])) {
        $apiData = [
            'url' => $result['data']['url'],
            'prompt' => $result['data']['prompt'],
            'size' => $result['data']['size'],
            'steps' => $result['data']['steps']
        ];
        return [true, $apiData];
    } else {
        $errorMsg = $result['message'] ?? '未知错误';
        return [false, "API生成失败：{$errorMsg}（原始响应：{$response}）"];
    }
}

// ===================== AJAX请求处理 =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // 绘画生成请求
    if (isset($_POST['action']) && $_POST['action'] === 'draw') {
        $env = loadEnv();
        $pdo = connectDB($env);
        
        $userEmail = validateUser($pdo);
        if (!$userEmail) {
            echo json_encode(['success' => false, 'msg' => "错误：用户Token无效，请重新登录"]);
            exit;
        }
        $emailEncoded = urlencode($userEmail);
        
        $userRoot = createUserDir($emailEncoded);
        $userInfo = readUserJson($userRoot);
        
        [$isValid, $msg, $params] = validateDrawParams($_POST);
        if (!$isValid) {
            echo json_encode(['success' => false, 'msg' => $msg]);
            exit;
        }
        
        if ($userInfo['points'] < 1) {
            echo json_encode(['success' => false, 'msg' => "积分不足，无法生成图片"]);
            exit;
        }
        
        [$apiSuccess, $apiData] = callAIDrawApi($params);
        if (!$apiSuccess) {
            echo json_encode(['success' => false, 'msg' => "图片生成失败：{$apiData}"]);
            exit;
        }
        
        $updateSuccess = updateUserPoints($userRoot, -1);
        if (!$updateSuccess) {
            echo json_encode(['success' => false, 'msg' => "图片生成成功，但积分扣除失败"]);
            exit;
        }
        
        $drawDir = "{$userRoot}/draw";
        $log = "\n[" . date('Y-m-d H:i:s') . "] " .
               "提示词：{$params['prompt']} | " .
               "尺寸：{$params['size']} | " .
               "精细度：{$params['steps']} | " .
               "图片链接：{$apiData['url']} | " .
               "扣除积分：1";
        file_put_contents("{$drawDir}/index.db", $log, FILE_APPEND);
        
        $remainingPoints = $userInfo['points'] - 1;
        echo json_encode([
            'success' => true,
            'msg' => "图片生成成功！",
            'img_url' => $apiData['url'],
            'remaining_points' => $remainingPoints,
            'size' => $params['size'],
            'prompt' => $params['prompt'],
            'record' => [
                'time' => date('Y-m-d H:i:s'),
                'prompt' => $params['prompt'],
                'size' => $params['size'],
                'steps' => $params['steps'],
                'url' => $apiData['url'],
                'points' => 1,
                'time_diff' => '刚刚'
            ]
        ]);
        exit;
    }
    
    // 刷新记录请求
    if (isset($_POST['action']) && $_POST['action'] === 'refresh_records') {
        $env = loadEnv();
        $pdo = connectDB($env);
        
        $userEmail = validateUser($pdo);
        if (!$userEmail) {
            echo json_encode(['success' => false, 'msg' => "用户验证失败"]);
            exit;
        }
        $emailEncoded = urlencode($userEmail);
        $userRoot = createUserDir($emailEncoded);
        $drawDir = "{$userRoot}/draw";
        
        cleanExpiredRecords($drawDir);
        $records = readDrawRecords($drawDir);
        
        echo json_encode([
            'success' => true,
            'records' => $records
        ]);
        exit;
    }
}

// ===================== 页面渲染 =====================
$env = loadEnv();
$pdo = connectDB($env);
$userEmail = validateUser($pdo);
if (!$userEmail) {
    die("错误：用户Token无效，请重新登录");
}
$emailEncoded = urlencode($userEmail);
$userRoot = createUserDir($emailEncoded); // 这里会自动创建 p.json 和 ls.json
$userInfo = readUserJson($userRoot);
$drawDir = "{$userRoot}/draw";
$drawRecords = readDrawRecords($drawDir);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI绘画工具 - 精美版（带保存功能）</title>
    <style>
        /* 全局样式：苹果风格美化 */
        :root {
            --apple-blue: #0071E3;
            --apple-blue-hover: #0077ED;
            --apple-gray-50: #F9F9FB;
            --apple-gray-100: #F5F5F7;
            --apple-gray-200: #E8E8ED;
            --apple-gray-300: #DCDCE0;
            --apple-gray-400: #C7C7CC;
            --apple-gray-500: #AEAEB2;
            --apple-gray-600: #8E8E93;
            --apple-gray-700: #6E6E73;
            --apple-gray-800: #48484A;
            --apple-gray-900: #1D1D1F;
            --apple-green: #34C759;
            --apple-red: #FF3B30;
            --apple-radius-sm: 8px;
            --apple-radius-md: 12px;
            --apple-radius-lg: 18px;
            --apple-shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.08);
            --apple-shadow-md: 0 4px 12px rgba(0, 0, 0, 0.1);
            --apple-transition: all 0.2s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Helvetica Neue", sans-serif;
        }

        body {
            background-color: var(--apple-gray-100);
            color: var(--apple-gray-900);
            padding: 24px;
            min-height: 100vh;
            line-height: 1.5;
        }
/* ===================== 隐藏滚动条核心代码 ===================== */

/* 1. 隐藏 Webkit 浏览器滚动条 (Chrome, Safari, Edge) */
::-webkit-scrollbar {
    width: 0 !important;
    height: 0 !important;
    background: transparent;
    display: none;
}

/* 2. 隐藏 Firefox 滚动条 */
* {
    scrollbar-width: none !important;
    -ms-overflow-style: none !important;  /* IE 10+ */
}

/* 3. 确保内容仍可滚动 */
body, .chat-area, .params-area {
    overflow: -moz-scrollbars-none;  /* 旧版 Firefox */
    -ms-overflow-style: none;         /* IE 10+ */
    scrollbar-width: none;          /* 新版 Firefox */
}

/* 4. 针对特定容器的滚动条隐藏 */
.chat-area::-webkit-scrollbar,
.params-area::-webkit-scrollbar,
body::-webkit-scrollbar {
    display: none;
    width: 0;
    height: 0;
}

/* 5. 确保滚动功能保留 */
.chat-area, .params-area {
    overflow-y: auto;        /* 保留垂直滚动 */
    scrollbar-width: none;   /* Firefox */
    -ms-overflow-style: none; /* IE */
}
        /* SVG图标通用样式：统一尺寸、对齐方式 */
        .svg-icon {
            width: 24px;
            height: 24px;
            object-fit: contain;
            vertical-align: middle;
            fill: currentColor;
        }

        .svg-icon-sm {
            width: 20px;
            height: 20px;
        }

        /* 顶部导航栏美化 */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: var(--apple-radius-md);
            box-shadow: var(--apple-shadow-sm);
            margin-bottom: 24px;
        }

        .navbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .navbar-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--apple-gray-900);
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info, .points-info {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
            color: var(--apple-gray-800);
        }

        .points-value {
            color: var(--apple-blue);
            font-weight: 600;
            font-size: 16px;
        }

        /* 主容器：美化分栏 */
        .main-container {
            display: flex;
            gap: 24px;
            height: calc(100vh - 120px);
        }

        /* 左侧对话区美化 */
        .chat-area {
            flex: 2;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: var(--apple-radius-md);
            box-shadow: var(--apple-shadow-sm);
            padding: 24px;
            overflow-y: auto;
            transition: var(--apple-transition);
        }

        .chat-area:hover {
            box-shadow: var(--apple-shadow-md);
        }

        /* 空状态美化 */
        .chat-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--apple-gray-600);
            text-align: center;
            padding: 40px 20px;
        }

        .chat-empty-icon {
            width: 64px;
            height: 64px;
            margin-bottom: 20px;
            opacity: 0.7;
        }

        .chat-empty-text {
            font-size: 18px;
            margin-bottom: 8px;
            color: var(--apple-gray-700);
        }

        .chat-empty-subtext {
            font-size: 14px;
            color: var(--apple-gray-500);
            max-width: 300px;
        }

        /* 对话气泡美化 + 图片缩小 */
        .chat-message {
            margin-bottom: 24px;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .user-message {
            text-align: left;
        }

        .ai-message {
            text-align: right;
        }

        .bubble {
            display: inline-block;
            padding: 14px 18px;
            border-radius: var(--apple-radius-lg);
            max-width: 85%;
            box-shadow: var(--apple-shadow-sm);
        }

        .user-bubble {
            background: var(--apple-gray-100);
            color: var(--apple-gray-900);
            border-bottom-left-radius: 4px;
        }

        .ai-bubble {
            background: var(--apple-blue);
            color: white;
            border-bottom-right-radius: 4px;
        }

        .bubble-time {
            font-size: 12px;
            margin-bottom: 6px;
            opacity: 0.8;
        }

        .user-bubble .bubble-time {
            color: var(--apple-gray-600);
        }

        .ai-bubble .bubble-time {
            color: rgba(255, 255, 255, 0.8);
        }

        .bubble-meta {
            font-size: 12px;
            margin-top: 8px;
            opacity: 0.9;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        /* 核心：图片缩小显示 + 保存按钮容器 */
        .img-container {
            display: flex;
            flex-direction: column;  /* 垂直排列 */
            align-items: center;     /* 水平居中 */
            gap: 12px;              /* 图片和按钮间距 */
            margin-top: 10px;
        }

        .chat-img {
            max-width: 180px;
            max-height: 180px;
            width: auto;
            height: auto;
            border-radius: var(--apple-radius-sm);
            cursor: pointer;
            transition: var(--apple-transition);
            object-fit: cover;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .chat-img:hover {
            transform: scale(1.03);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .save-btn {
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.95);
            color: var(--apple-gray-900);
            border: 1px solid var(--apple-gray-300);
            border-radius: var(--apple-radius-sm);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--apple-transition);
            box-shadow: var(--apple-shadow-sm);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-width: 100px;       /* 最小宽度 */
            justify-content: center; /* 文字居中 */
        }

        .save-btn:hover {
            background: white;
            border-color: var(--apple-blue);
            color: var(--apple-blue);
            transform: translateY(-1px);
            box-shadow: var(--apple-shadow-md);
        }

        .save-btn:active {
            transform: translateY(0);
        }

        .save-btn:disabled {
            background: var(--apple-gray-200);
            color: var(--apple-gray-600);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .save-btn .spinner-sm {
            width: 16px;
            height: 16px;
            border: 2px solid var(--apple-gray-300);
            border-top: 2px solid var(--apple-blue);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* 右侧参数区美化 */
        .params-area {
            flex: 1;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: var(--apple-radius-md);
            box-shadow: var(--apple-shadow-sm);
            padding: 28px;
            min-width: 380px;
            display: block !important;
            transition: var(--apple-transition);
        }

        .params-area:hover {
            box-shadow: var(--apple-shadow-md);
        }

        .params-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 18px;
            font-weight: 600;
            color: var(--apple-gray-900);
            margin-bottom: 24px;
        }

        /* 表单项美化 */
        .form-item {
            margin-bottom: 22px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--apple-gray-800);
            padding-left: 2px;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid var(--apple-gray-300);
            border-radius: var(--apple-radius-sm);
            background: rgba(255, 255, 255, 0.95);
            font-size: 15px;
            color: var(--apple-gray-900);
            outline: none;
            transition: var(--apple-transition);
        }

        .form-control:focus {
            border-color: var(--apple-blue);
            box-shadow: 0 0 0 4px rgba(0, 113, 227, 0.1);
        }

        .form-control::placeholder {
            color: var(--apple-gray-500);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
            line-height: 1.6;
        }

        /* 生成按钮美化 */
        #generateBtn {
            width: 100%;
            padding: 15px 0;
            background: var(--apple-blue);
            color: white;
            border: none;
            border-radius: var(--apple-radius-sm);
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--apple-transition);
            margin-top: 8px;
            box-shadow: var(--apple-shadow-sm);
        }

        #generateBtn:hover {
            background: var(--apple-blue-hover);
            transform: translateY(-2px);
            box-shadow: var(--apple-shadow-md);
        }

        #generateBtn:active {
            transform: translateY(0);
        }

        #generateBtn:disabled {
            background: var(--apple-gray-400);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* 加载状态美化 */
        #loadingCard {
            display: none;
            text-align: center;
            padding: 60px 20px;
        }

        .spinner {
            width: 32px;
            height: 32px;
            border: 3px solid var(--apple-gray-200);
            border-top: 3px solid var(--apple-blue);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        .loading-text {
            font-size: 15px;
            color: var(--apple-gray-700);
        }

        /* 弹窗美化 */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: var(--apple-transition);
        }

        .modal.show {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: var(--apple-radius-lg);
            padding: 32px;
            max-width: 420px;
            width: 90%;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            transform: translateY(-20px) scale(0.98);
            transition: var(--apple-transition);
        }

        .modal.show .modal-content {
            transform: translateY(0) scale(1);
        }

        .modal-icon {
            font-size: 40px;
            margin-bottom: 16px;
        }

        .modal-success .modal-icon {
            color: var(--apple-green);
        }

        .modal-error .modal-icon {
            color: var(--apple-red);
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--apple-gray-900);
            margin-bottom: 8px;
        }

        .modal-msg {
            font-size: 15px;
            color: var(--apple-gray-700);
            margin-bottom: 24px;
            line-height: 1.6;
        }

        .modal-btn {
            padding: 12px 24px;
            background: var(--apple-blue);
            color: white;
            border: none;
            border-radius: var(--apple-radius-sm);
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--apple-transition);
        }

        .modal-btn:hover {
            background: var(--apple-blue-hover);
        }

        /* 滚动条美化 */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: var(--apple-gray-200);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--apple-gray-400);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--apple-gray-500);
        }

        /* 响应式适配 */
        @media (max-width: 992px) {
            .main-container {
                flex-direction: column;
                height: auto;
            }

            .params-area {
                min-width: 100%;
                margin-top: 24px;
            }

            .chat-area {
                height: 400px;
            }

            .chat-img {
                max-width: 150px;
                max-height: 150px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 16px;
            }

            .navbar {
                padding: 12px 16px;
                flex-wrap: wrap;
                gap: 12px;
            }

            .navbar-right {
                width: 100%;
                justify-content: space-between;
            }

            .chat-area, .params-area {
                padding: 16px;
            }

            .chat-img {
                max-width: 120px;
                max-height: 120px;
            }

            .save-btn {
                padding: 6px 12px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <!-- 顶部导航栏：整合AI绘画、用户、积分SVG图标 -->
    <div class="navbar">
        <div class="navbar-left">
            <img src="../svg/AI绘画.svg" alt="AI绘画" class="svg-icon">
            <div class="navbar-title">AI绘画工具Flux</div>
        </div>
        <div class="navbar-right">
            <div class="user-info">
                <img src="../svg/用户.svg" alt="用户" class="svg-icon svg-icon-sm">
                <span><?= htmlspecialchars($userEmail) ?></span>
            </div>
            <div class="points-info">
                <img src="../svg/积分.svg" alt="积分" class="svg-icon svg-icon-sm">
                <span class="points-value" id="remainingPoints"><?= $userInfo['points'] ?></span>
            </div>
        </div>
    </div>

    <!-- 主容器 -->
    <div class="main-container">
        <!-- 左侧对话记录区：空状态用AI绘画SVG -->
        <div class="chat-area" id="chatArea">
            <?php if (empty($drawRecords)): ?>
                <div class="chat-empty">
                    <img src="../svg/AI绘画.svg" alt="AI绘画" class="chat-empty-icon">
                    <div class="chat-empty-text">开始你的AI绘画创作</div>
                    <div class="chat-empty-subtext">在右侧输入提示词和参数，点击生成按钮，创作记录会展示在这里</div>
                </div>
            <?php else: ?>
                <?php foreach ($drawRecords as $record): ?>
                    <!-- 用户参数气泡 -->
                    <div class="chat-message user-message">
                        <div class="bubble user-bubble">
                            <div class="bubble-time"><?= $record['time_diff'] ?></div>
                            <div><?= htmlspecialchars($record['prompt']) ?></div>
                            <div class="bubble-meta">
                                <span>尺寸：<?= $record['size'] ?></span>
                                <span>精细度：<?= $record['steps'] ?></span>
                            </div>
                        </div>
                    </div>
                    <!-- AI生成结果气泡：添加保存按钮 -->
                    <div class="chat-message ai-message">
                        <div class="bubble ai-bubble">
                            <div class="bubble-time"><?= $record['time_diff'] ?></div>
                            <!-- 图片容器 + 保存按钮 -->
                            <div class="img-container">
                                <img src="<?= $record['url'] ?>" class="chat-img" onclick="window.open('<?= $record['url'] ?>', '_blank')" data-url="<?= $record['url'] ?>" data-size="<?= $record['size'] ?>" data-prompt="<?= htmlspecialchars($record['prompt']) ?>">
                                <button class="save-btn" onclick="saveImage(this)" data-url="<?= $record['url'] ?>" data-size="<?= $record['size'] ?>" data-prompt="<?= htmlspecialchars($record['prompt']) ?>">
                                    <span>保存图片</span>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- 右侧参数区：整合参数SVG图标 -->
        <div class="params-area" id="paramsArea">
            <!-- 加载状态 -->
            <div id="loadingCard">
                <div class="spinner"></div>
                <div class="loading-text">正在生成图片，请稍候...</div>
            </div>

            <!-- 参数表单：参数图标 + 输入项 -->
            <div id="paramsForm">
                <div class="params-title">
                    <img src="../svg/参数.svg" alt="参数" class="svg-icon">
                    <span>绘图参数</span>
                </div>
                
                <div class="form-item">
                    <label class="form-label" for="prompt">绘画提示词（必填）</label>
                    <textarea id="prompt" class="form-control" placeholder="例如：蓝天白云，卡通风格，苹果壁纸，高细节..."></textarea>
                </div>

                <div class="form-item">
                    <label class="form-label" for="size">图片尺寸</label>
                    <select id="size" class="form-control">
                        <option value="512*1024">512 × 1024</option>
                        <option value="768*512">768 × 512</option>
                        <option value="768*1024">768 × 1024</option>
                        <option value="1024*576">1024 × 576</option>
                        <option value="576*1024">576 × 1024</option>
                        <option value="1024*1024" selected>1024 × 1024（默认）</option>
                    </select>
                </div>

                <div class="form-item">
                    <label class="form-label" for="steps">绘画精细度（1-10）</label>
                    <input type="number" id="steps" class="form-control" min="1" max="10" value="4">
                </div>

                <button id="generateBtn">生成图片（扣除1积分）</button>
            </div>
        </div>
    </div>

    <!-- 提示弹窗 -->
    <div class="modal" id="modal">
        <div class="modal-content">
            <div class="modal-icon" id="modalIcon">✓</div>
            <div class="modal-title" id="modalTitle">成功</div>
            <div class="modal-msg" id="modalMsg">图片生成成功！</div>
            <button class="modal-btn" onclick="hideModal()">确定</button>
        </div>
    </div>

    <script>
        // 核心元素
        const promptInput = document.getElementById('prompt');
        const sizeSelect = document.getElementById('size');
        const stepsInput = document.getElementById('steps');
        const generateBtn = document.getElementById('generateBtn');
        const loadingCard = document.getElementById('loadingCard');
        const paramsForm = document.getElementById('paramsForm');
        const chatArea = document.getElementById('chatArea');
        const remainingPointsEl = document.getElementById('remainingPoints');
        const modal = document.getElementById('modal');
        const modalIcon = document.getElementById('modalIcon');
        const modalTitle = document.getElementById('modalTitle');
        const modalMsg = document.getElementById('modalMsg');

        // 显示弹窗
        function showModal(type, title, msg) {
            modal.className = `modal show ${type}`;
            modalIcon.textContent = type === 'success' ? '✓' : '×';
            modalTitle.textContent = title;
            modalMsg.textContent = msg;
        }

        // 隐藏弹窗
        function hideModal() {
            modal.className = 'modal';
        }

        // 生成按钮点击事件
        generateBtn.addEventListener('click', async function() {
            // 验证参数
            const prompt = promptInput.value.trim();
            const size = sizeSelect.value;
            const steps = parseInt(stepsInput.value);

            if (!prompt) {
                showModal('error', '提示', '请输入绘画提示词（必填）！');
                return;
            }
            if (prompt.length > 500) {
                showModal('error', '提示', '提示词长度不能超过500字符！');
                return;
            }
            if (isNaN(steps) || steps < 1 || steps > 10) {
                showModal('error', '提示', '精细度必须是1-10之间的数字！');
                return;
            }

            // 显示加载
            loadingCard.style.display = 'block';
            paramsForm.style.display = 'none';
            generateBtn.disabled = true;

            try {
                // 提交请求
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'draw',
                        prompt: prompt,
                        size: size,
                        steps: steps
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // 更新积分
                    remainingPointsEl.textContent = data.remaining_points;
                    
                    // 添加对话记录（包含保存按钮）
                    const userMsg = `
                        <div class="chat-message user-message">
                            <div class="bubble user-bubble">
                                <div class="bubble-time">刚刚</div>
                                <div>${htmlEscape(prompt)}</div>
                                <div class="bubble-meta">
                                    <span>尺寸：${size}</span>
                                    <span>精细度：${steps}</span>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    const aiMsg = `
                        <div class="chat-message ai-message">
                            <div class="bubble ai-bubble">
                                <div class="bubble-time">刚刚</div>
                                <div class="img-container">
                                    <img src="${data.img_url}" class="chat-img" onclick="window.open('${data.img_url}', '_blank')" data-url="${data.img_url}" data-size="${size}" data-prompt="${htmlEscape(prompt)}">
                                    <button class="save-btn" onclick="saveImage(this)" data-url="${data.img_url}" data-size="${size}" data-prompt="${htmlEscape(prompt)}">
                                        <span>保存图片</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // 清空空状态，添加新记录
                    chatArea.innerHTML = chatArea.innerHTML.replace(/<div class="chat-empty">[\s\S]*?<\/div>/, '') + userMsg + aiMsg;
                    chatArea.scrollTop = chatArea.scrollHeight;

                    // 清空输入框
                    promptInput.value = '';
                    showModal('success', '生成成功', '图片已添加到左侧记录中，点击图片可查看原图');
                } else {
                    showModal('error', '生成失败', data.msg || '生成图片失败，请重试！');
                }
            } catch (error) {
                showModal('error', '请求异常', '网络错误：' + error.message);
            } finally {
                // 恢复显示
                loadingCard.style.display = 'none';
                paramsForm.style.display = 'block';
                generateBtn.disabled = false;
            }
        });

        // 保存图片核心函数（调用upload.php）
        async function saveImage(btn) {
            // 获取参数
            const imgUrl = btn.dataset.url;
            const size = btn.dataset.size;
            const prompt = btn.dataset.prompt;

            // 禁用按钮，显示加载状态
            btn.disabled = true;
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<div class="spinner-sm"></div><span>保存中...</span>';

            try {
                // 调用upload.php接口
                const response = await fetch('upload.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        img_url: imgUrl,
                        size: size,
                        prompt: prompt
                    })
                });

                const result = await response.text();

                if (result === 'success') {
                    showModal('success', '保存成功', '图片已成功保存到你的图库！');
                    btn.innerHTML = '<span>已保存</span>';
                } else {
                    showModal('error', '保存失败', '图片保存失败，请重试！');
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }
            } catch (error) {
                showModal('error', '保存异常', '网络错误：' + error.message);
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }
        }

        // HTML转义
        function htmlEscape(str) {
            return str.replace(/&/g, '&amp;')
                      .replace(/</g, '&lt;')
                      .replace(/>/g, '&gt;')
                      .replace(/"/g, '&quot;')
                      .replace(/'/g, '&#039;');
        }

        // 点击弹窗外部关闭
        modal.addEventListener('click', function(e) {
            if (e.target === modal) hideModal();
        });

        // ESC关闭弹窗
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') hideModal();
        });
    </script>
</body>
</html>