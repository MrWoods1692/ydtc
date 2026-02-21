<?php
declare(strict_types=1);

// -------------------------- 1. 基础配置与函数定义 --------------------------
function loadEnv(string $filePath = '../in/.env'): array {
    $env = [];
    if (!file_exists($filePath)) {
        die("环境配置文件.env不存在");
    }
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with($line, '#')) continue;
        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim($value);
    }
    return $env;
}

function getDbConnection(array $env): PDO {
    try {
        $dsn = "mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_NAME']};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        return new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], $options);
    } catch (PDOException $e) {
        die("数据库连接失败: " . $e->getMessage());
    }
}

function logUserAction(string $userDir, string $action): void {
    $logFile = $userDir . '/log.txt';
    $time = date('Y-m-d H:i:s');
    $logContent = "[$time] $action" . PHP_EOL;
    if (!file_exists($logFile)) {
        touch($logFile);
        chmod($logFile, 0644);
    }
    file_put_contents($logFile, $logContent, FILE_APPEND | LOCK_EX);
}

function validateAlbumName(string $name): bool {
    $pattern = '/^[\x{4e00}-\x{9fa5}a-zA-Z0-9]{1,16}$/u';
    return preg_match($pattern, $name) === 1;
}

function getMaxAlbums(int $level): int {
    if ($level <= 0) return 0;
    if ($level >= 10) return $level == 10 ? 20 : PHP_INT_MAX;
    return $level * 2;
}

function sanitizePath(string $path): string {
    return str_replace(['../', '..\\', './', '.\\'], '', $path);
}

function updateAlbumRemark(string $albumDir, string $remark): bool {
    $pJsonFile = $albumDir . '/p.json';
    if (!file_exists($pJsonFile)) return false;
    $pData = json_decode(file_get_contents($pJsonFile), true);
    $pData['remark'] = $remark;
    return file_put_contents($pJsonFile, json_encode($pData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}

// -------------------------- 2. 初始化与身份验证 --------------------------
$env = loadEnv();
$db = getDbConnection($env);

$userEmail = '';
$userLevel = 0;
$userDir = '';
$errorMsg = '';
$successMsg = '';

$userToken = $_COOKIE['user_token'] ?? '';
if (empty($userToken)) {
    die("未检测到登录令牌，请先登录");
}

try {
    $stmt = $db->prepare("SELECT email FROM users WHERE token = :token LIMIT 1");
    $stmt->bindValue(':token', $userToken, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch();
    
    if (!$user) {
        die("无效的登录令牌，请重新登录");
    }
    $userEmail = $user['email'];
    
    setcookie(
        'user_email',
        $userEmail,
        time() + (int)$env['COOKIE_EXPIRE'],
        $env['COOKIE_PATH'],
        $env['COOKIE_DOMAIN'],
        (bool)$env['COOKIE_SECURE'],
        (bool)$env['COOKIE_HTTPONLY']
    );

    $encodedEmail = urlencode($userEmail);
    $userDir = __DIR__ . '/../users/' . $encodedEmail;
    if (!is_dir($userDir)) {
        die("用户目录不存在，请联系管理员");
    }

    $userJsonFile = $userDir . '/user.json';
    if (!file_exists($userJsonFile)) {
        die("用户信息文件不存在，请联系管理员");
    }
    $userJsonContent = file_get_contents($userJsonFile);
    $userData = json_decode($userJsonContent, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($userData['level'])) {
        die("用户信息文件格式错误");
    }
    $userLevel = (int)$userData['level'];

} catch (PDOException $e) {
    die("数据库查询失败: " . $e->getMessage());
}

// -------------------------- 3. 处理POST请求 --------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $albumName = sanitizePath($_POST['album_name'] ?? '');
    $oldAlbumName = sanitizePath($_POST['old_album_name'] ?? '');
    $albumRemark = $_POST['album_remark'] ?? '';

    switch ($action) {
        case 'create_album':
            if (empty($albumName) || !validateAlbumName($albumName)) {
                $errorMsg = "相册名称仅支持中文、英文、数字，且长度为1-16字符";
                break;
            }
            // 新增：禁止创建draw相册
            if ($albumName === 'draw') {
                $errorMsg = "相册名称不能为draw";
                break;
            }

            // 获取所有相册目录，但排除 draw 和 回收站
            $allDirs = glob($userDir . '/' . '*', GLOB_ONLYDIR);
            $albumDirs = array_filter($allDirs, function($dir) {
                $name = basename($dir);
                // 排除 draw 和 回收站 这两个特殊目录
                return !in_array($name, ['draw', '回收站']);
            });
            $currentAlbumCount = count($albumDirs);
            $maxAlbums = getMaxAlbums($userLevel);

            if ($currentAlbumCount >= $maxAlbums && $maxAlbums !== PHP_INT_MAX) {
                $errorMsg = "你的等级为{$userLevel}级，最多可创建{$maxAlbums}个相册，当前已创建{$currentAlbumCount}个";
                break;
            }

            $albumDir = $userDir . '/' . $albumName;
            if (is_dir($albumDir)) {
                $errorMsg = "相册名称已存在";
                break;
            }
            mkdir($albumDir, 0755, true);

            $now = time();
            $defaultImage = [
                "url" => "https://app.mrcwoods.com/images/logo.png",
                "size_kb" => 16.38,
                "upload_time" => $now,
                "tags" => [],
                "size" => "375*300",
                "name" => "logo.png",
                "remark" => ""
            ];

            $pJson = [
                "album_name" => $albumName,
                "create_time" => $now,
                "last_upload_time" => $now,
                "image_count" => 1,
                "used_space_kb" => 16.38,
                "remark" => $albumRemark
            ];
            file_put_contents($albumDir . '/p.json', json_encode($pJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $lsJson = [$defaultImage];
            file_put_contents($albumDir . '/ls.json', json_encode($lsJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            logUserAction($userDir, "创建相册：{$albumName}，备注：{$albumRemark}");
            $successMsg = "相册「{$albumName}」创建成功";
            break;

        case 'delete_album':
            if (empty($albumName)) {
                $errorMsg = "请指定要删除的相册";
                break;
            }
            $albumDir = $userDir . '/' . $albumName;
            if (!is_dir($albumDir)) {
                $errorMsg = "相册不存在";
                break;
            }

            function deleteDir(string $dir): bool {
                $files = array_diff(scandir($dir), ['.', '..']);
                foreach ($files as $file) {
                    $path = $dir . '/' . $file;
                    is_dir($path) ? deleteDir($path) : unlink($path);
                }
                return rmdir($dir);
            }
            deleteDir($albumDir);

            logUserAction($userDir, "删除相册：{$albumName}");
            $successMsg = "相册「{$albumName}」已彻底删除";
            break;

        case 'rename_album':
            if (empty($oldAlbumName) || empty($albumName) || !validateAlbumName($albumName)) {
                $errorMsg = "相册名称格式错误";
                break;
            }
            // 新增：禁止相册名称为draw
            if ($albumName === 'draw') {
                $errorMsg = "相册名称不能为draw";
                break;
            }
            $oldAlbumDir = $userDir . '/' . $oldAlbumName;
            $newAlbumDir = $userDir . '/' . $albumName;
            if (!is_dir($oldAlbumDir)) {
                $errorMsg = "原相册不存在";
                break;
            }
            if (is_dir($newAlbumDir)) {
                $errorMsg = "新相册名称已存在";
                break;
            }
            rename($oldAlbumDir, $newAlbumDir);

            $pJsonFile = $newAlbumDir . '/p.json';
            $pJsonContent = file_get_contents($pJsonFile);
            $pData = json_decode($pJsonContent, true);
            $pData['album_name'] = $albumName;
            file_put_contents($pJsonFile, json_encode($pData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            logUserAction($userDir, "重命名相册：{$oldAlbumName} → {$albumName}");
            $successMsg = "相册已重命名为「{$albumName}」";
            break;

        case 'update_remark':
            if (empty($albumName)) {
                $errorMsg = "请指定要修改的相册";
                break;
            }
            $albumDir = $userDir . '/' . $albumName;
            if (!is_dir($albumDir)) {
                $errorMsg = "相册不存在";
                break;
            }
            if (updateAlbumRemark($albumDir, $albumRemark)) {
                logUserAction($userDir, "修改相册「{$albumName}」备注：{$albumRemark}");
                $successMsg = "相册备注修改成功";
            } else {
                $errorMsg = "相册备注修改失败";
            }
            break;

        default:
            $errorMsg = "无效的操作";
            break;
    }
}

// -------------------------- 4. 获取相册列表 --------------------------
$albumList = [];
if (is_dir($userDir)) {
    $albumDirs = glob($userDir . '/' . '*', GLOB_ONLYDIR);
    foreach ($albumDirs as $dir) {
        $albumName = basename($dir);
        
        // 新增：跳过draw和回收站相册，不显示
        if (in_array($albumName, ['draw', '回收站'])) {
            continue;
        }
        
        $pJsonFile = $dir . '/p.json';
        $pData = json_decode(file_get_contents($pJsonFile), true) ?? [];
        $albumList[] = [
            'name' => $albumName,
            'create_time' => $pData['create_time'] ?? 0,
            'last_upload_time' => $pData['last_upload_time'] ?? 0,
            'image_count' => $pData['image_count'] ?? 0,
            'used_space_kb' => $pData['used_space_kb'] ?? 0,
            'remark' => $pData['remark'] ?? ''
        ];
    }
}


// -------------------------- 5. HTML界面（功能完整版） --------------------------
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的相册e</title>
    <style>
        /* 核心变量 - 苹果风格 */
        :root {
            --apple-bg: #f5f5f7;
            --apple-card: #ffffff;
            --apple-text: #1d1d1f;
            --apple-gray: #86868b;
            --apple-light-gray: #e8e8ed;
            --apple-hover: #f0f0f5;
            --apple-border: #e6e6e6;
            --apple-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 4px 6px rgba(0, 0, 0, 0.03);
            --apple-shadow-hover: 0 4px 12px rgba(0, 0, 0, 0.08), 0 2px 4px rgba(0, 0, 0, 0.05);
            --apple-shadow-menu: 0 8px 24px rgba(0, 0, 0, 0.12), 0 4px 8px rgba(0, 0, 0, 0.08);
            --apple-radius-sm: 8px;
            --apple-radius-md: 12px;
            --apple-radius-lg: 16px;
            --apple-btn-bg: #f5f5f7;
            --apple-btn-hover: #e8e8ed;
            --apple-btn-active: #dcdce0;
            --apple-primary: #0071e3;
            --apple-primary-hover: #0077ed;
            --apple-primary-active: #0066cc;
            --success-color: #34c759;
            --error-color: #ff3b30;
            --text-secondary: #6e6e73;
            --transition-fast: 0.15s ease;
            --transition-normal: 0.25s ease;
        }

        /* 预加载SVG图标，提升加载速度 */
        @keyframes preload { 0% { opacity: 0; } 100% { opacity: 0; } }
        .preload-icons {
            position: absolute;
            width: 0;
            height: 0;
            overflow: hidden;
            animation: preload 0.001s;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Helvetica Neue", Arial, sans-serif;
        }

        body {
            background-color: var(--apple-bg);
            color: var(--apple-text);
            line-height: 1.5;
            padding-bottom: 40px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* 提示消息 - 绝对定位不占空间 */
        .msg-container {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            width: 90%;
            max-width: 500px;
            pointer-events: none; /* 不阻挡下层点击 */
        }

        .msg {
            padding: 14px 18px;
            border-radius: var(--apple-radius-md);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
            margin-bottom: 8px;
            opacity: 0;
            animation: msgFadeIn 0.3s ease forwards, msgFadeOut 0.3s ease 3s forwards;
        }

        @keyframes msgFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes msgFadeOut {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(-20px); }
        }

        .msg.error {
            background: #ffebee;
            color: var(--error-color);
            border: 1px solid #ffcdd2;
        }

        .msg.success {
            background: #e8f5e9;
            color: var(--success-color);
            border: 1px solid #c8e6c9;
        }

        /* 顶部导航 */
        .header {
            padding: 16px 24px;
            display: flex;
            gap: 12px;
            align-items: center;
            background-color: var(--apple-card);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
            position: sticky;
            top: 0;
            z-index: 90;
            margin-bottom: 24px;
        }

        /* 按钮样式 */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border: none;
            border-radius: var(--apple-radius-lg);
            background: var(--apple-btn-bg);
            color: var(--apple-text);
            cursor: pointer;
            font-size: 14px;
            font-weight: 400;
            transition: all var(--transition-fast);
            user-select: none;
            height: 40px;
            line-height: 1;
        }

        .btn:hover {
            background: var(--apple-btn-hover);
            transform: translateY(-1px);
        }

        .btn:active {
            background: var(--apple-btn-active);
            transform: translateY(0);
        }

        .btn svg, .btn img {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

.btn-primary {
    background: var(--apple-btn-bg) !important;
    color: var(--apple-text) !important;
}
.btn-primary:hover {
    background: var(--apple-btn-hover) !important;
    transform: translateY(-1px) !important;
}
.btn-primary:active {
    background: var(--apple-btn-active) !important;
    transform: translateY(0) !important;
}

        .btn-cancel {
            background: var(--apple-light-gray);
        }

        .btn-delete {
            color: var(--error-color);
            background: #fff5f5;
        }

        .btn-delete:hover {
            background: #ffebee;
        }

        /* 相册容器 */
        .album-container {
            padding: 0 24px;
        }

        /* 相册列表 */
        .album-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 24px;
        }

        /* 相册卡片 */
        .album-item {
            background: var(--apple-card);
            border-radius: var(--apple-radius-lg);
            box-shadow: var(--apple-shadow);
            padding: 24px;
            cursor: pointer;
            transition: all var(--transition-normal);
            position: relative;
            overflow: visible !important;
            border: 1px solid transparent;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 180px;
        }

        .album-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--apple-shadow-hover);
            border-color: var(--apple-light-gray);
        }

        .album-item.active {
            border: 1px solid var(--apple-primary);
            background: #f8fbff;
        }

        .album-icon {
            width: 64px;
            height: 64px;
            margin-bottom: 20px;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
        }

        .album-name {
            font-size: 18px;
            font-weight: 500;
            text-align: center;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            width: 100%;
        }

        /* 全局相册菜单（核心：脱离卡片限制） */
        #albumMenuContainer {
            position: fixed;
            z-index: 1000;
            display: none;
            flex-direction: column;
            background: var(--apple-card);
            border-radius: var(--apple-radius-md);
            box-shadow: var(--apple-shadow-menu);
            min-width: 180px;
            border: 1px solid var(--apple-border);
            opacity: 0;
            transform: translateY(8px);
            transition: all var(--transition-fast);
        }

        #albumMenuContainer.show {
            display: flex;
            opacity: 1;
            transform: translateY(0);
        }

        .menu-item {
            padding: 10px 18px;
            font-size: 14px;
            border: none;
            background: none;
            cursor: pointer;
            text-align: left;
            transition: background var(--transition-fast);
            border-radius: var(--apple-radius-sm);
            margin: 2px;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .menu-item:hover {
            background: var(--apple-hover);
        }

        .menu-item.delete {
            color: var(--error-color);
        }

        .menu-item.delete:hover {
            background: #fff5f5;
        }

        .menu-item img {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
            opacity: 0.8;
        }

        /* 空状态 */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--apple-gray);
        }

        .empty-icon {
            width: 96px;
            height: 96px;
            margin-bottom: 20px;
            opacity: 0.4;
        }

        .empty-text {
            font-size: 17px;
            margin-bottom: 32px;
        }

        /* 弹窗样式 */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            opacity: 0;
            transition: opacity var(--transition-normal);
            will-change: opacity;
        }

        .modal.show {
            display: flex;
            opacity: 1;
        }

        .modal-content {
            background: var(--apple-card);
            border-radius: var(--apple-radius-lg);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12), 0 4px 16px rgba(0, 0, 0, 0.08);
            width: 440px;
            max-width: 92%;
            overflow: hidden;
            transform: translateY(20px) scale(0.98);
            transition: all var(--transition-normal);
            opacity: 0;
            will-change: transform, opacity;
        }

        .modal.show .modal-content {
            transform: translateY(0) scale(1);
            opacity: 1;
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--apple-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 500;
        }

        .modal-close {
            background: none;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background var(--transition-fast);
        }

        .modal-close:hover {
            background: var(--apple-hover);
        }

        .modal-close svg {
            width: 18px;
            height: 18px;
            opacity: 0.7;
        }

        .modal-body {
            padding: 24px;
        }

        .modal-footer {
            padding: 18px 24px;
            border-top: 1px solid var(--apple-border);
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            background: var(--apple-bg);
            border-bottom-left-radius: var(--apple-radius-lg);
            border-bottom-right-radius: var(--apple-radius-lg);
        }

        /* 表单样式 */
        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            margin-bottom: 10px;
            font-size: 15px;
            font-weight: 500;
            color: var(--apple-text);
        }

        .form-input, .form-textarea {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid var(--apple-border);
            border-radius: var(--apple-radius-md);
            font-size: 15px;
            background: var(--apple-bg);
            transition: all var(--transition-fast);
            resize: none;
        }

        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--apple-primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(0, 113, 227, 0.1);
        }

        .form-textarea {
            min-height: 100px;
            line-height: 1.5;
        }

        /* 删除确认区域 */
        .delete-confirm {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background: #fff5f5;
            border-radius: var(--apple-radius-md);
            border: 1px solid #ffcdd2;
        }

        .delete-confirm.show {
            display: block;
            animation: fadeIn var(--transition-normal);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .countdown {
            color: var(--error-color);
            font-weight: 500;
            font-size: 15px;
        }

        /* 相册信息展示 */
        .info-item {
            display: flex;
            margin-bottom: 16px;
            font-size: 15px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--apple-border);
        }

        .info-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .info-label {
            width: 110px;
            color: var(--apple-gray);
            flex-shrink: 0;
            font-weight: 400;
        }

        .info-value {
            flex: 1;
            color: var(--apple-text);
            font-weight: 500;
        }

        /* 响应式适配 */
        @media (max-width: 768px) {
            .album-list {
                grid-template-columns: repeat(auto-fill, minmax(100%, 1fr));
                gap: 16px;
            }

            .header {
                padding: 12px 16px;
                margin-bottom: 16px;
            }

            .album-container {
                padding: 0 16px;
            }

            .album-item {
                padding: 20px;
                min-height: 160px;
            }

            .modal-content {
                width: 92%;
            }

            .modal-header {
                padding: 16px 20px;
            }

            .modal-body {
                padding: 20px;
            }

            .modal-footer {
                padding: 16px 20px;
            }

            .msg-container {
                top: 16px;
            }

            #albumMenuContainer {
                min-width: 160px;
            }
        }

        /* 加载优化 */
        [hidden] {
            display: none !important;
        }
    </style>
</head>
<body>
    <!-- 预加载SVG图标 -->
    <div class="preload-icons">
        <img src="../svg/打开.svg" alt="预加载">
        <img src="../svg/删除.svg" alt="预加载">
        <img src="../svg/信息.svg" alt="预加载">
        <img src="../svg/重命名.svg" alt="预加载">
        <img src="../svg/回收站.svg" alt="预加载">
        <img src="../svg/新建文件夹.svg" alt="预加载">
        <img src="../svg/文件夹.svg" alt="预加载">
    </div>

    <!-- 提示消息 -->
    <div class="msg-container">
        <?php if ($errorMsg): ?>
            <div class="msg error">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                </svg>
                <?= htmlspecialchars($errorMsg) ?>
            </div>
        <?php endif; ?>
        <?php if ($successMsg): ?>
            <div class="msg success">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                </svg>
                <?= htmlspecialchars($successMsg) ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 顶部按钮 -->
    <div class="header">
        <button class="btn" onclick="window.location.href='../trask/?user=<?= urlencode($userEmail) ?>'">
            <img src="../svg/回收站.svg" alt="回收站">
            回收站
        </button>
        <button class="btn btn-primary" onclick="openCreateModal()">
            <img src="../svg/新建文件夹.svg" alt="新建相册">
            新建相册
        </button>
    </div>

    <!-- 相册列表 -->
    <div class="album-container">
        <div class="album-list">
            <?php if (empty($albumList)): ?>
                <div class="empty-state">
                    <img src="../svg/文件夹.svg" class="empty-icon" alt="空相册">
                    <div class="empty-text">暂无相册，点击「新建相册」创建第一个相册吧！</div>
                    <button class="btn btn-primary" onclick="openCreateModal()">新建相册</button>
                </div>
            <?php else: ?>
                <?php foreach ($albumList as $album): ?>
                    <div class="album-item" 
                         data-album-name="<?= htmlspecialchars($album['name']) ?>" 
                         data-album-data='<?= htmlspecialchars(json_encode($album, JSON_UNESCAPED_UNICODE)) ?>'>
                        <img src="../svg/文件夹.svg" class="album-icon" alt="文件夹">
                        <div class="album-name"><?= htmlspecialchars($album['name']) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- 全局相册菜单 -->
    <div id="albumMenuContainer">
        <button class="menu-item" id="menuOpenAlbum">
            <img src="../svg/打开.svg" alt="打开相册">
            打开相册
        </button>
        <button class="menu-item" id="menuRenameAlbum">
            <img src="../svg/重命名.svg" alt="重命名">
            重命名
        </button>
        <button class="menu-item" id="menuEditRemark">
            <img src="../svg/重命名.svg" alt="修改备注">
            修改备注
        </button>
        <button class="menu-item" id="menuViewInfo">
            <img src="../svg/信息.svg" alt="查看信息">
            查看信息
        </button>
        <button class="menu-item delete" id="menuDeleteAlbum">
            <img src="../svg/删除.svg" alt="删除相册">
            删除相册
        </button>
    </div>

    <!-- 新建相册弹窗 -->
    <div class="modal" id="createModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">新建相册</div>
                <button class="modal-close" onclick="closeModal('createModal')">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z"/>
                    </svg>
                </button>
            </div>
            <form id="createForm" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_album">
                    <div class="form-group">
                        <label class="form-label" for="albumName">相册名称</label>
                        <input type="text" class="form-input" id="albumName" name="album_name" placeholder="请输入相册名称（1-16字符）" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="albumRemark">相册备注</label>
                        <textarea class="form-textarea" id="albumRemark" name="album_remark" placeholder="可选：输入相册备注信息"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeModal('createModal')">取消</button>
                    <button type="submit" class="btn btn-primary">创建</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 重命名相册弹窗 -->
    <div class="modal" id="renameModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">重命名相册</div>
                <button class="modal-close" onclick="closeModal('renameModal')">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z"/>
                    </svg>
                </button>
            </div>
            <form id="renameForm" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="rename_album">
                    <input type="hidden" name="old_album_name" id="oldAlbumName">
                    <div class="form-group">
                        <label class="form-label" for="newAlbumName">新相册名称</label>
                        <input type="text" class="form-input" id="newAlbumName" name="album_name" placeholder="请输入新相册名称" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeModal('renameModal')">取消</button>
                    <button type="submit" class="btn btn-primary">确认重命名</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 修改备注弹窗 -->
    <div class="modal" id="editRemarkModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">修改相册备注</div>
                <button class="modal-close" onclick="closeModal('editRemarkModal')">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z"/>
                    </svg>
                </button>
            </div>
            <form id="editRemarkForm" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_remark">
                    <input type="hidden" name="album_name" id="editRemarkAlbumName">
                    <div class="form-group">
                        <label class="form-label" for="editRemarkContent">备注内容</label>
                        <textarea class="form-textarea" id="editRemarkContent" name="album_remark" placeholder="输入相册备注信息"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeModal('editRemarkModal')">取消</button>
                    <button type="submit" class="btn btn-primary">保存备注</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 查看相册信息弹窗 -->
    <div class="modal" id="infoModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">相册信息</div>
                <button class="modal-close" onclick="closeModal('infoModal')">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body" id="infoModalContent">
                <!-- 动态填充相册信息 -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="closeModal('infoModal')">关闭</button>
            </div>
        </div>
    </div>

<!-- 删除相册确认弹窗 -->
<div class="modal" id="deleteModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">删除相册</div>
            <button class="modal-close" onclick="closeModal('deleteModal')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <div style="font-size: 15px; line-height: 1.6;">确认要彻底删除相册「<span id="deleteAlbumName" style="font-weight: 500;"></span>」吗？此操作不可恢复！</div>
            <div class="delete-confirm" id="deleteConfirm">
                <div style="margin-bottom: 12px; font-size: 14px;">请确认删除（倒计时：<span class="countdown" id="countdown">3</span>秒）</div>
                <button type="button" class="btn btn-delete" id="confirmDeleteBtn" disabled>确认删除</button>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-cancel" onclick="closeModal('deleteModal')">取消</button>
            <button type="button" class="btn btn-delete" id="showDeleteConfirmBtn" onclick="showDeleteConfirm()">我已确认要删除</button>
        </div>
    </div>
</div>

    <script>
        // 全局变量
        let activeAlbumItem = null; // 当前激活的相册项
        let menuTimer = null;
        const menuContainer = document.getElementById('albumMenuContainer');
        const userEmail = "<?= htmlspecialchars($userEmail) ?>"; // 缓存用户邮箱，避免重复解析

        // ==================== 核心：修复菜单点击无反应问题 ====================
        // 1. 绑定相册项点击事件（页面加载完成后立即绑定）
        document.addEventListener('DOMContentLoaded', function() {
            // 给所有相册项绑定点击事件
            const albumItems = document.querySelectorAll('.album-item');
            albumItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    showAlbumMenu(e, this);
                });
            });

            // 2. 绑定菜单项点击事件（核心：确保事件正确触发）
            bindMenuEvents();

            // 3. 点击页面空白处关闭菜单
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.album-item') && !e.target.closest('#albumMenuContainer')) {
                    hideAlbumMenu();
                }
            });

            // 4. ESC键关闭所有弹窗和菜单
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    hideAlbumMenu();
                    document.querySelectorAll('.modal.show').forEach(modal => {
                        modal.classList.remove('show');
                    });
                }
            });

            // 5. 弹窗点击外部关闭
            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        this.classList.remove('show');
                    }
                });
            });

            // 6. 绑定表单提交验证
            bindFormValidations();
        });

        // 2. 绑定菜单项具体功能
        function bindMenuEvents() {
            // 打开相册
            document.getElementById('menuOpenAlbum').addEventListener('click', function() {
                if (!activeAlbumItem) return;
                const albumName = activeAlbumItem.dataset.albumName;
                // 先执行功能，再隐藏菜单（避免执行被中断）
                window.location.href = `../album-look/?user=${encodeURIComponent(userEmail)}&album=${encodeURIComponent(albumName)}`;
                hideAlbumMenu();
            });

            // 重命名
            document.getElementById('menuRenameAlbum').addEventListener('click', function() {
                if (!activeAlbumItem) return;
                const albumName = activeAlbumItem.dataset.albumName;
                openRenameModal(albumName);
                hideAlbumMenu();
            });

            // 修改备注
            document.getElementById('menuEditRemark').addEventListener('click', function() {
                if (!activeAlbumItem) return;
                const albumName = activeAlbumItem.dataset.albumName;
                const albumData = JSON.parse(activeAlbumItem.dataset.albumData || '{}');
                openEditRemarkModal(albumName, albumData.remark || '');
                hideAlbumMenu();
            });

            // 查看信息
            document.getElementById('menuViewInfo').addEventListener('click', function() {
                if (!activeAlbumItem) return;
                try {
                    const albumData = JSON.parse(activeAlbumItem.dataset.albumData || '{}');
                    openInfoModal(albumData);
                } catch (e) {
                    console.error('解析相册数据失败:', e);
                    alertModal('错误', '无法获取相册信息，请重试');
                }
                hideAlbumMenu();
            });

            // 删除相册
            document.getElementById('menuDeleteAlbum').addEventListener('click', function() {
                if (!activeAlbumItem) return;
                const albumName = activeAlbumItem.dataset.albumName;
                openDeleteConfirm(albumName);
                hideAlbumMenu();
            });

            // 阻止菜单点击事件冒泡
            menuContainer.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }

        // 3. 表单验证绑定（删除所有PHP代码，替换为正确的JS逻辑）
        function bindFormValidations() {
            // 新建相册验证
            const createForm = document.getElementById('createForm');
            if (createForm) {
                createForm.addEventListener('submit', function(e) {
                    const nameInput = document.getElementById('albumName');
                    const name = nameInput.value.trim();
                    const pattern = /^[\u4e00-\u9fa5a-zA-Z0-9]{1,16}$/;
                    
                    // JS版：禁止名称为draw
                    if (name === 'draw') {
                        alertModal('错误', '相册名称不能为draw');
                        e.preventDefault();
                        return;
                    }
                    
                    if (!pattern.test(name)) {
                        alertModal('错误', '相册名称仅支持中文、英文、数字，且长度为1-16字符');
                        e.preventDefault();
                    }
                });
            }
            
            // 重命名验证
            const renameForm = document.getElementById('renameForm');
            if (renameForm) {
                renameForm.addEventListener('submit', function(e) {
                    const nameInput = document.getElementById('newAlbumName');
                    const name = nameInput.value.trim();
                    const pattern = /^[\u4e00-\u9fa5a-zA-Z0-9]{1,16}$/;
                    
                    // JS版：禁止名称为draw
                    if (name === 'draw') {
                        alertModal('错误', '相册名称不能为draw');
                        e.preventDefault();
                        return;
                    }
                    
                    if (!pattern.test(name)) {
                        alertModal('错误', '相册名称仅支持中文、英文、数字，且长度为1-16字符');
                        e.preventDefault();
                    }
                });
            }
        }

        // ==================== 基础功能函数 ====================
        // 显示相册菜单（智能定位）
        function showAlbumMenu(event, albumItem) {
            // 清除之前的定时器
            if (menuTimer) clearTimeout(menuTimer);
            
            // 隐藏之前的菜单
            hideAlbumMenu();
            
            // 验证相册项
            if (!albumItem || !albumItem.dataset.albumName) {
                console.warn('无效的相册项');
                return;
            }

            // 标记当前激活的相册项
            activeAlbumItem = albumItem;
            albumItem.classList.add('active');

            // 获取点击位置和相册项位置
            const rect = albumItem.getBoundingClientRect();
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            
            // 计算菜单位置（避免超出视口）
            let menuLeft = rect.right + 10;
            let menuTop = rect.top;

            // 右侧空间不足时，显示在左侧
            if (menuLeft + 200 > viewportWidth) {
                menuLeft = rect.left - 200 - 10;
            }

            // 底部空间不足时，向上调整
            const menuHeight = 200; // 菜单预估高度
            if (menuTop + menuHeight > viewportHeight) {
                menuTop = viewportHeight - menuHeight - 20;
            }

            // 设置菜单位置并显示
            menuContainer.style.left = `${menuLeft}px`;
            menuContainer.style.top = `${menuTop}px`;
            menuContainer.classList.add('show');

            // 阻止事件冒泡
            event.stopPropagation();
        }

        // 隐藏相册菜单
        function hideAlbumMenu() {
            if (menuContainer) {
                menuContainer.classList.remove('show');
                menuContainer.style.left = '-9999px';
                menuContainer.style.top = '-9999px';
            }
            
            if (activeAlbumItem) {
                activeAlbumItem.classList.remove('active');
                activeAlbumItem = null;
            }
        }

        // 打开新建相册弹窗（现在能正常定义了）
        function openCreateModal() {
            const modal = document.getElementById('createModal');
            if (modal) {
                modal.classList.add('show');
                const input = document.getElementById('albumName');
                if (input) {
                    input.focus();
                    input.select();
                }
            }
        }

        // 关闭弹窗
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            
            modal.classList.remove('show');
            
            // 重置删除确认区域
            if (modalId === 'deleteModal') {
                const deleteConfirm = document.getElementById('deleteConfirm');
                if (deleteConfirm) {
                    deleteConfirm.classList.remove('show');
                    const confirmBtn = document.getElementById('confirmDeleteBtn');
                    if (confirmBtn) {
                        confirmBtn.disabled = true;
                        confirmBtn.onclick = null;
                    }
                    const countdown = document.getElementById('countdown');
                    if (countdown) countdown.textContent = '3';
                }
                // 恢复显示底部的"我已确认要删除"按钮
                const showDeleteBtn = document.getElementById('showDeleteConfirmBtn');
                if (showDeleteBtn) {
                    showDeleteBtn.style.display = 'inline-block';
                }
            }
            
            // 重置表单
            const form = modal.querySelector('form');
            if (form) form.reset();
        }

        // 打开重命名弹窗
        function openRenameModal(oldAlbumName) {
            const oldInput = document.getElementById('oldAlbumName');
            const newInput = document.getElementById('newAlbumName');
            const modal = document.getElementById('renameModal');
            
            if (oldInput) oldInput.value = oldAlbumName;
            if (newInput) {
                newInput.value = oldAlbumName;
                newInput.focus();
                newInput.select();
            }
            if (modal) modal.classList.add('show');
        }

        // 打开修改备注弹窗
        function openEditRemarkModal(albumName, remark) {
            const nameInput = document.getElementById('editRemarkAlbumName');
            const contentInput = document.getElementById('editRemarkContent');
            const modal = document.getElementById('editRemarkModal');
            
            if (nameInput) nameInput.value = albumName;
            if (contentInput) {
                contentInput.value = remark || '';
                contentInput.focus();
            }
            if (modal) modal.classList.add('show');
        }

        // 打开查看信息弹窗
        function openInfoModal(albumData) {
            if (!albumData || typeof albumData !== 'object') {
                alertModal('错误', '相册数据异常');
                return;
            }

            const contentEl = document.getElementById('infoModalContent');
            const modal = document.getElementById('infoModal');
            if (!contentEl || !modal) return;

            // 生成相册信息内容
            const content = `
                <div class="info-item">
                    <div class="info-label">相册名称：</div>
                    <div class="info-value">${htmlEscape(albumData.name || '未知')}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">创建时间：</div>
                    <div class="info-value">${formatTime(albumData.create_time || 0)}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">最后上传：</div>
                    <div class="info-value">${formatTime(albumData.last_upload_time || 0)}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">图片数量：</div>
                    <div class="info-value">${albumData.image_count || 0}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">占用空间：</div>
                    <div class="info-value">${Number(albumData.used_space_kb || 0).toFixed(2)} KB</div>
                </div>
                <div class="info-item">
                    <div class="info-label">备注：</div>
                    <div class="info-value">${htmlEscape(albumData.remark || '无')}</div>
                </div>
            `;

            contentEl.innerHTML = content;
            modal.classList.add('show');
        }

        // 打开删除确认弹窗
        function openDeleteConfirm(albumName) {
            const nameEl = document.getElementById('deleteAlbumName');
            const modal = document.getElementById('deleteModal');
            
            if (nameEl) nameEl.textContent = albumName;
            if (modal) modal.classList.add('show');
        }
        
        // 显示删除倒计时确认
        function showDeleteConfirm() {
            // 隐藏底部的"我已确认要删除"按钮
            const showDeleteBtn = document.getElementById('showDeleteConfirmBtn');
            if (showDeleteBtn) {
                showDeleteBtn.style.display = 'none';
            }

            const deleteConfirm = document.getElementById('deleteConfirm');
            const countdownEl = document.getElementById('countdown');
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            const albumNameEl = document.getElementById('deleteAlbumName');
            
            if (!deleteConfirm || !countdownEl || !confirmBtn || !albumNameEl) return;
            
            deleteConfirm.classList.add('show');
            
            let countdown = 3;
            countdownEl.textContent = countdown;
            confirmBtn.disabled = true;
            
            const timer = setInterval(() => {
                countdown--;
                countdownEl.textContent = countdown;
                
                if (countdown <= 0) {
                    clearInterval(timer);
                    confirmBtn.disabled = false;
                    confirmBtn.onclick = function() {
                        const albumName = albumNameEl.textContent;
                        if (!albumName) return;
                        
                        // 创建删除表单并提交
                        const form = document.createElement('form');
                        form.method = 'post';
                        form.innerHTML = `
                            <input type="hidden" name="action" value="delete_album">
                            <input type="hidden" name="album_name" value="${htmlEscape(albumName)}">
                        `;
                        document.body.appendChild(form);
                        form.submit();
                    };
                }
            }, 1000);
        }

        // ==================== 工具函数 ====================
        // 时间格式化
        function formatTime(timestamp) {
            if (!timestamp || isNaN(timestamp)) return '未知';
            const date = new Date(timestamp * 1000);
            return date.toLocaleString('zh-CN', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // HTML转义（防止XSS和语法错误）
        function htmlEscape(str) {
            if (str === undefined || str === null) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;')
                .replace(/`/g, '&#96;');
        }

        // 自定义提示弹窗
        function alertModal(title, message) {
            if (!title || !message) return;
            
            const modal = document.createElement('div');
            modal.className = 'modal show';
            modal.innerHTML = `
                <div class="modal-content" style="width: 360px;">
                    <div class="modal-header">
                        <div class="modal-title">${htmlEscape(title)}</div>
                        <button class="modal-close" onclick="this.closest('.modal').remove()">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z"/>
                            </svg>
                        </button>
                    </div>
                    <div class="modal-body" style="font-size: 15px; line-height: 1.6;">${htmlEscape(message)}</div>
                    <div class="modal-footer">
                        <button class="btn btn-primary" onclick="this.closest('.modal').remove()">确定</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
    </script>
</body>
</html>
