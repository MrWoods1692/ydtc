<?php
// ===================== 代码优化：常量与全局配置 =====================
define('JSON_FILE_PATH', 'tools.json'); // 提取常量，便于维护
define('CSRF_TOKEN_NAME', 'tool_csrf_token'); // CSRF令牌名称

// 统一系统/工具类型映射表（避免PHP/JS重复定义）
$nameMappings = [
    'system' => [
        'windows' => 'Windows',
        'macos' => 'macOS',
        'linux' => 'Linux',
        'ios' => 'iOS',
        'android' => 'Android'
    ],
    'toolType' => [
        'app' => '应用',
        'web' => '网页工具',
        'offweb' => '本地网页工具'
    ]
];

// 页面不缓存设置（保持原有功能）
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header("Content-Type: text/html; charset=UTF-8"); // 明确字符编码

// ===================== 代码优化：CSRF防护（基础） =====================
session_start();
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}

// ===================== 代码优化：工具函数封装（增强错误处理） =====================
/**
 * 更新工具使用量（优化：增加文件锁防止并发写入）
 */
function updateToolUsage(string $toolName): array {
    $jsonFile = JSON_FILE_PATH;
    
    // 统一错误处理
    if (!file_exists($jsonFile)) {
        return ['status' => 'error', 'message' => '配置文件不存在'];
    }

    $jsonContent = file_get_contents($jsonFile);
    $data = json_decode($jsonContent, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['status' => 'error', 'message' => '配置文件解析失败: ' . json_last_error_msg()];
    }

    if (!isset($data['tools']) || !is_array($data['tools'])) {
        return ['status' => 'error', 'message' => '配置文件格式错误'];
    }

    // 遍历工具并更新（优化：找到后立即退出循环）
    $updated = false;
    foreach ($data['tools'] as &$tool) {
        if ($tool['name'] === $toolName) {
            $tool['usage'] = isset($tool['usage']) ? (int)$tool['usage'] + 1 : 1;
            $newUsage = $tool['usage'];
            $updated = true;
            break;
        }
    }

    if (!$updated) {
        return ['status' => 'error', 'message' => '工具不存在'];
    }

    // 优化：加文件锁防止并发写入
    $handle = fopen($jsonFile, 'w');
    if ($handle && flock($handle, LOCK_EX)) {
        fwrite($handle, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        flock($handle, LOCK_UN);
        fclose($handle);
        return ['status' => 'success', 'new_usage' => $newUsage];
    } else {
        return ['status' => 'error', 'message' => '文件写入失败'];
    }
}

/**
 * 获取排序后的工具列表（优化：增加完整错误处理）
 */
function getSortedTools(): array {
    $jsonFile = JSON_FILE_PATH;
    
    if (!file_exists($jsonFile)) {
        return [];
    }

    $data = json_decode(file_get_contents($jsonFile), true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['tools'])) {
        return [];
    }

    // 排序（优化：增加类型检查，避免排序错误）
    usort($data['tools'], function($a, $b) {
        $usageA = isset($a['usage']) ? (int)$a['usage'] : 0;
        $usageB = isset($b['usage']) ? (int)$b['usage'] : 0;
        return $usageB - $usageA;
    });

    return $data['tools'];
}

// ===================== 业务逻辑处理 =====================
// 处理使用量更新请求（优化：增加CSRF验证）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tool_name'])) {
    // CSRF验证
    if (!isset($_POST[CSRF_TOKEN_NAME]) || $_POST[CSRF_TOKEN_NAME] !== $_SESSION[CSRF_TOKEN_NAME]) {
        echo json_encode(['status' => 'error', 'message' => '请求验证失败']);
        exit;
    }
    
    $response = updateToolUsage(trim($_POST['tool_name']));
    echo json_encode($response);
    exit;
}

// 获取工具数据
$tools = getSortedTools();

// 提取去重的分类和系统（优化：简化逻辑）
$categories = $tools ? array_unique(array_column($tools, 'category')) : [];
$systems = [];
foreach ($tools as $tool) {
    if (isset($tool['adapted_systems']) && is_array($tool['adapted_systems'])) {
        $systems = array_merge($systems, $tool['adapted_systems']);
    }
}
$systems = array_unique($systems);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>工具库 - 苹果风格</title>
    <style>
        /* ===================== 界面美化：全局样式优化 ===================== */
        @font-face {
            font-family: 'AlimamaAgileVF';
            src: url('../font/AlimamaAgileVF-Thin.woff2') format('woff2'),
                 url('../font/AlimamaAgileVF-Thin.woff') format('woff'),
                 url('../font/AlimamaAgileVF-Thin.ttf') format('truetype');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }

        :root {
            /* 界面美化：更贴近原生苹果的配色体系 */
            --apple-bg: #f5f5f7;
            --apple-white: #ffffff;
            --apple-primary: #0071e3;
            --apple-primary-hover: #0077ed;
            --apple-primary-light: rgba(0, 113, 227, 0.08);
            --apple-primary-dark: #0066cc;
            --apple-text-main: #1d1d1f;
            --apple-text-secondary: #86868b;
            --apple-text-tertiary: #a1a1a6;
            --apple-border: #e6e6e8;
            --apple-border-hover: #d2d2d7;
            --apple-shadow-light: 0 2px 10px rgba(0, 0, 0, 0.04);
            --apple-shadow-hover: 0 8px 24px rgba(0, 0, 0, 0.08);
            --apple-shadow-modal: 0 12px 40px rgba(0, 0, 0, 0.12);
            --apple-success: #34c759;
            --apple-error: #ff3b30;
            
            /* 界面美化：统一圆角和间距体系 */
            --radius-xs: 6px;
            --radius-sm: 10px;
            --radius-md: 18px;
            --radius-lg: 22px;
            --radius-full: 50%;
            --spacing-xs: 4px;
            --spacing-sm: 8px;
            --spacing-md: 12px;
            --spacing-lg: 16px;
            --spacing-xl: 20px;
            
            /* 界面美化：更丝滑的过渡动画 */
            --transition-fast: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-base: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            
            /* 渐变优化 */
            --gradient-card: linear-gradient(145deg, #ffffff, #f8f8fa);
            --gradient-modal: linear-gradient(180deg, #ffffff 0%, #fafafa 100%);
            --gradient-header: linear-gradient(90deg, #ffffff 0%, #fafafa 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'AlimamaAgileVF', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-weight: 400;
        }

        body {
            background-color: var(--apple-bg);
            color: var(--apple-text-main);
            line-height: 1.5;
            padding: 0 var(--spacing-md) 80px;
            /* 界面美化：全局滚动条美化 */
            scrollbar-width: thin;
            scrollbar-color: var(--apple-text-secondary) var(--apple-bg);
        }

        /* 界面美化：webkit滚动条样式 */
        body::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        body::-webkit-scrollbar-track {
            background: var(--apple-bg);
            border-radius: var(--radius-xs);
        }
        body::-webkit-scrollbar-thumb {
            background-color: var(--apple-text-secondary);
            border-radius: var(--radius-xs);
            border: 2px solid var(--apple-bg);
        }
        body::-webkit-scrollbar-thumb:hover {
            background-color: var(--apple-text-tertiary);
        }

        /* ===================== 界面美化：头部区域优化 ===================== */
        .header {
            margin: var(--spacing-lg) auto;
            width: 95%;
            max-width: 1280px;
            padding: var(--spacing-lg) var(--spacing-md);
            background: var(--gradient-header);
            border: 1px solid var(--apple-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--apple-shadow-light);
            position: sticky;
            top: var(--spacing-md);
            z-index: 999;
            overflow: hidden;
            /* 界面美化：增加轻微的边框高光 */
            transition: var(--transition-base);
        }
        .header:hover {
            border-color: var(--apple-border-hover);
            box-shadow: var(--apple-shadow-hover);
        }

        .header-main {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            flex-wrap: wrap;
            width: 100%;
        }

        .header-logo {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            font-size: 20px;
            font-weight: 500;
            color: var(--apple-text-main);
            text-decoration: none;
            white-space: nowrap;
            /* 界面美化：增加hover效果 */
            transition: var(--transition-fast);
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-sm);
        }
        .header-logo:hover {
            background-color: var(--apple-primary-light);
            color: var(--apple-primary);
        }

        .header-logo img {
            width: 24px;
            height: 24px;
            object-fit: contain;
        }

        /* ===================== 界面美化：搜索框优化 ===================== */
        .search-container {
            flex: 1;
            min-width: 200px;
            position: relative;
        }

        .search-input {
            width: 220px;
            height: 40px;
            padding: 0 var(--spacing-md) 0 40px;
            border: 1px solid var(--apple-border);
            border-radius: var(--radius-sm);
            background-color: var(--apple-bg);
            font-size: 15px;
            outline: none;
            transition: var(--transition-base);
            /* 界面美化：移除默认轮廓 */
            appearance: none;
        }

        .search-input:focus {
            width: 100%;
            border-color: var(--apple-primary);
            box-shadow: 0 0 0 4px var(--apple-primary-light);
            background-color: var(--apple-white);
            border-radius: var(--radius-sm);
        }

        .search-input::placeholder {
            color: var(--apple-text-secondary);
            /* 界面美化：占位符字体更柔和 */
            font-weight: 300;
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            fill: var(--apple-text-secondary);
            z-index: 1;
            /* 界面美化：搜索图标过渡效果 */
            transition: var(--transition-fast);
        }
        .search-input:focus + .search-icon {
            fill: var(--apple-primary);
        }

        /* ===================== 界面美化：筛选栏优化 ===================== */
        .filter-group {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            white-space: nowrap;
        }

        .filter-select {
            height: 36px;
            padding: 0 var(--spacing-md);
            border: 1px solid var(--apple-border);
            border-radius: var(--radius-sm);
            background-color: var(--apple-white);
            font-size: 14px;
            outline: none;
            cursor: pointer;
            transition: var(--transition-base);
            min-width: 100px;
            /* 界面美化：移除默认样式 */
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2386868b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 8px center;
            background-size: 16px;
            padding-right: 30px;
        }

        .filter-select:focus {
            border-color: var(--apple-primary);
            box-shadow: 0 0 0 4px var(--apple-primary-light);
        }
        .filter-select:hover {
            border-color: var(--apple-border-hover);
        }

        /* ===================== 界面美化：工具容器与空状态 ===================== */
        .tools-container {
            width: 95%;
            max-width: 1280px;
            margin: 0 auto;
            padding-bottom: var(--spacing-xl);
        }

        /* 界面美化：空状态样式 */
        .empty-state {
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: var(--spacing-xl) 0;
            color: var(--apple-text-secondary);
            text-align: center;
        }
        .empty-state.active {
            display: flex;
        }
        .empty-icon {
            width: 64px;
            height: 64px;
            margin-bottom: var(--spacing-md);
            opacity: 0.5;
        }
        .empty-text {
            font-size: 16px;
            margin-bottom: var(--spacing-sm);
        }
        .empty-subtext {
            font-size: 14px;
            color: var(--apple-text-tertiary);
        }

        .tools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: var(--spacing-lg);
        }

        /* ===================== 界面美化：工具卡片深度优化 ===================== */
        .tool-card {
            background: var(--gradient-card);
            border-radius: var(--radius-md);
            padding: var(--spacing-lg);
            box-shadow: var(--apple-shadow-light);
            cursor: pointer;
            transition: var(--transition-slow);
            border: 1px solid var(--apple-border);
            position: relative;
            overflow: hidden;
            /* 界面美化：增加轻微的内边距阴影 */
            backdrop-filter: blur(2px);
        }

        /* 界面美化：卡片高光效果 */
        .tool-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(120deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 60%);
            transition: var(--transition-base);
            z-index: 0;
            pointer-events: none;
        }

        .tool-card:hover {
            transform: translateY(-4px) scale(1.01); /* 界面美化：轻微缩放+上移 */
            box-shadow: var(--apple-shadow-hover);
            border-color: var(--apple-primary-light);
        }

        .tool-card:active {
            transform: translateY(-2px) scale(1.005); /* 界面美化：点击反馈 */
            transition: var(--transition-fast);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-md);
            position: relative;
            z-index: 1;
        }

        .tool-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-sm);
            object-fit: contain;
            background-color: var(--apple-bg);
            padding: var(--spacing-xs);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08); /* 界面美化：更细腻的阴影 */
            /* 界面美化：图标hover效果 */
            transition: var(--transition-fast);
        }
        .tool-card:hover .tool-icon {
            transform: rotate(5deg);
        }

        .tool-name {
            font-size: 18px;
            font-weight: 500;
            color: var(--apple-text-main);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            /* 界面美化：文字抗锯齿 */
            -webkit-font-smoothing: antialiased;
        }

        .usage-count {
            font-size: 14px;
            color: var(--apple-text-secondary);
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            font-weight: 400;
            margin-bottom: var(--spacing-md);
            position: relative;
            z-index: 1;
            /* 界面美化：使用量文字间距 */
            letter-spacing: 0.2px;
        }

        /* 界面美化：使用量图标样式优化 */
        .usage-count svg {
            width: 16px;
            height: 16px;
            fill: var(--apple-text-secondary);
            transition: var(--transition-fast);
        }
        .tool-card:hover .usage-count svg {
            fill: var(--apple-primary);
        }

        .system-icons {
            display: flex;
            gap: var(--spacing-xs);
            align-items: center;
            position: relative;
            z-index: 1;
            /* 界面美化：系统图标容器间距 */
            flex-wrap: wrap;
        }

        .system-icon {
            width: 22px;
            height: 22px;
            object-fit: contain;
            filter: grayscale(5%);
            background-color: var(--apple-bg);
            border-radius: 4px;
            padding: 2px;
            /* 界面美化：系统图标hover效果 */
            transition: var(--transition-fast);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        .tool-card:hover .system-icon {
            filter: grayscale(0%);
            transform: scale(1.1);
        }

        /* ===================== 界面美化：弹窗深度优化 ===================== */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(12px); /* 界面美化：更强烈的毛玻璃效果 */
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            pointer-events: none;
            transition: var(--transition-slow);
        }

        .modal-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }

        .modal-content {
            width: 90%;
            max-width: 640px;
            background: var(--gradient-modal);
            border-radius: var(--radius-md);
            padding: var(--spacing-xl);
            box-shadow: var(--apple-shadow-modal);
            transform: translateY(20px) scale(0.98);
            transition: var(--transition-slow);
            border: 1px solid var(--apple-border);
            position: relative;
            overflow: hidden;
            /* 界面美化：弹窗毛玻璃 */
            backdrop-filter: blur(8px);
        }

        /* 界面美化：弹窗顶部高光 */
        .modal-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 80px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.2) 0%, rgba(255, 255, 255, 0) 100%);
            z-index: 0;
            pointer-events: none;
        }

        .modal-overlay.active .modal-content {
            transform: translateY(0) scale(1);
        }

        .modal-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-md);
            padding-bottom: var(--spacing-md);
            border-bottom: 1px solid var(--apple-border);
            position: relative;
            z-index: 1;
        }

        .modal-tool-icon {
            width: 64px;
            height: 64px;
            border-radius: var(--radius-sm);
            object-fit: contain;
            background-color: var(--apple-bg);
            padding: var(--spacing-sm);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1); /* 界面美化：更立体的阴影 */
            /* 界面美化：图标旋转动画 */
            transition: var(--transition-slow);
        }
        .modal-overlay.active .modal-tool-icon {
            transform: rotate(10deg);
        }

        .modal-tool-name {
            font-size: 24px;
            font-weight: 500;
            color: var(--apple-text-main);
            -webkit-font-smoothing: antialiased;
        }

        .modal-body {
            margin-bottom: var(--spacing-xl);
            position: relative;
            z-index: 1;
        }

        .modal-info {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-md);
        }

        .info-item {
            display: flex;
            gap: var(--spacing-sm);
            flex-wrap: wrap;
            padding: 6px 8px; /* 界面美化：更大的点击区域 */
            border-radius: var(--radius-xs);
            transition: var(--transition-fast);
        }

        .info-item:hover {
            background-color: var(--apple-primary-light);
            border-radius: var(--radius-xs);
        }

        .info-label {
            font-size: 14px;
            color: var(--apple-text-secondary);
            min-width: 70px;
            font-weight: 400;
        }

        .info-value {
            font-size: 14px;
            color: var(--apple-text-main);
            flex: 1;
            font-weight: 400;
        }

        .modal-description {
            font-size: 15px;
            color: var(--apple-text-main);
            line-height: 1.7;
            padding: var(--spacing-md);
            border-top: 1px solid var(--apple-border);
            margin-top: var(--spacing-sm);
            background-color: var(--apple-primary-light);
            border-radius: var(--radius-sm); /* 界面美化：更圆润的圆角 */
            font-weight: 400;
            /* 界面美化：描述文本排版 */
            line-height: 1.8;
            letter-spacing: 0.3px;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: var(--spacing-md);
            padding-top: var(--spacing-md);
            border-top: 1px solid var(--apple-border);
            position: relative;
            z-index: 1;
        }

        /* ===================== 界面美化：按钮样式深度优化 ===================== */
        .modal-btn {
            height: 42px;
            padding: 0 var(--spacing-xl);
            border-radius: var(--radius-sm); /* 界面美化：统一按钮圆角 */
            border: none;
            font-size: 15px;
            cursor: pointer;
            transition: var(--transition-base);
            font-weight: 400;
            /* 界面美化：按钮文字抗锯齿 */
            -webkit-font-smoothing: antialiased;
            /* 界面美化：按钮点击反馈 */
            position: relative;
            overflow: hidden;
        }

        /* 界面美化：按钮点击波纹效果 */
        .modal-btn::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
        }
        .modal-btn:active::after {
            width: 200px;
            height: 200px;
            opacity: 0;
        }

        .btn-cancel {
            background-color: var(--apple-bg);
            color: var(--apple-text-main);
        }
        .btn-cancel:hover {
            background-color: var(--apple-border);
        }
        .btn-cancel:active {
            background-color: #e0e0e2;
        }

        .btn-action {
            background-color: var(--apple-primary);
            color: var(--apple-white);
        }
        .btn-action:hover {
            background-color: var(--apple-primary-hover);
            box-shadow: 0 2px 8px rgba(0, 113, 227, 0.3);
        }
        .btn-action:active {
            background-color: var(--apple-primary-dark);
        }

        .system-select {
            height: 42px;
            padding: 0 var(--spacing-md);
            border-radius: var(--radius-sm);
            border: 1px solid var(--apple-border);
            font-size: 15px;
            margin-right: var(--spacing-sm);
            outline: none;
            transition: var(--transition-base);
            font-weight: 400;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2386868b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 8px center;
            background-size: 16px;
            padding-right: 30px;
        }
        .system-select:focus {
            border-color: var(--apple-primary);
            box-shadow: 0 0 0 4px var(--apple-primary-light);
        }
        .system-select:hover {
            border-color: var(--apple-border-hover);
        }

        /* ===================== 界面美化：返回顶部按钮优化 ===================== */
        .back-to-top {
            position: fixed;
            bottom: var(--spacing-xl);
            right: var(--spacing-xl);
            width: 52px;
            height: 52px;
            border-radius: var(--radius-full);
            background-color: var(--apple-white);
            box-shadow: var(--apple-shadow-light);
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            opacity: 0;
            pointer-events: none;
            transition: var(--transition-base);
            z-index: 999;
            border: 1px solid var(--apple-border);
            /* 界面美化：返回顶部按钮hover效果 */
        }
        .back-to-top.active {
            opacity: 1;
            pointer-events: auto;
        }
        .back-to-top:hover {
            box-shadow: var(--apple-shadow-hover);
            transform: translateY(-2px);
            background-color: var(--apple-primary-light);
            border-color: var(--apple-primary-light);
        }
        .back-to-top:active {
            transform: translateY(0);
            transition: var(--transition-fast);
        }
        .back-to-top img {
            width: 24px;
            height: 24px;
            object-fit: contain;
            /* 界面美化：图标旋转动画 */
            transition: var(--transition-base);
        }
        .back-to-top:hover img {
            transform: translateY(-2px);
        }

        /* ===================== 响应式适配优化 ===================== */
        @media (max-width: 992px) {
            .header-main {
                flex-direction: column;
                align-items: stretch;
                gap: var(--spacing-sm);
            }
            .filter-group {
                justify-content: space-between;
                width: 100%;
                flex-wrap: wrap;
                gap: var(--spacing-sm);
            }
            .filter-select {
                flex: 1;
                min-width: 120px;
            }
        }

        @media (max-width: 768px) {
            .tools-grid {
                grid-template-columns: 1fr;
            }
            .modal-content {
                padding: var(--spacing-md);
                max-width: 95%;
            }
            .modal-tool-name {
                font-size: 22px;
            }
        }

        @media (max-width: 480px) {
            .tool-name {
                font-size: 16px;
            }
            .modal-tool-name {
                font-size: 20px;
            }
            .back-to-top {
                width: 48px;
                height: 48px;
                bottom: var(--spacing-md);
                right: var(--spacing-md);
            }
            .header {
                padding: var(--spacing-md);
                margin: var(--spacing-md) auto;
            }
            .search-input {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- 头部区域 -->
    <div class="header">
        <div class="header-main">
            <a href="#" class="header-logo">
                <img src="../svg/工具.svg" alt="工具库">
                <span>工具库</span>
            </a>

            <div class="search-container">
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                </svg>
                <input type="text" class="search-input" id="search-input" placeholder="搜索工具名称、分类...">
            </div>

            <div class="filter-group">
                <select class="filter-select" id="category-filter">
                    <option value="all">所有分类</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                    <?php endforeach; ?>
                </select>

                <select class="filter-select" id="system-filter">
                    <option value="all">所有系统</option>
                    <?php foreach ($systems as $system): ?>
                        <option value="<?php echo htmlspecialchars($system); ?>">
                            <?php echo htmlspecialchars($nameMappings['system'][$system] ?? ucfirst($system)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- 工具展示区域 -->
    <div class="tools-container">
        <!-- 界面美化：空状态提示 -->
        <div class="empty-state" id="empty-state">
            <svg class="empty-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
            </svg>
            <div class="empty-text">未找到匹配的工具</div>
            <div class="empty-subtext">请尝试调整搜索关键词或筛选条件</div>
        </div>

        <div class="tools-grid" id="tools-display">
            <?php foreach ($tools as $tool): ?>
                <div class="tool-card" 
                     data-name="<?php echo htmlspecialchars($tool['name']); ?>"
                     data-category="<?php echo htmlspecialchars($tool['category'] ?? ''); ?>"
                     data-systems="<?php echo htmlspecialchars(implode(',', $tool['adapted_systems'] ?? [])); ?>"
                     data-type="<?php echo htmlspecialchars($tool['type'] ?? ''); ?>"
                     data-description="<?php echo htmlspecialchars($tool['description'] ?? '无描述'); ?>"
                     data-links="<?php echo htmlspecialchars(json_encode($tool['links'] ?? [])); ?>"
                     data-icon="<?php echo htmlspecialchars($tool['icon'] ?? '../svg/default.svg'); ?>">
                    <div class="card-header">
                        <img src="<?php echo htmlspecialchars($tool['icon'] ?? '../svg/default.svg'); ?>" alt="<?php echo htmlspecialchars($tool['name']); ?>" class="tool-icon">
                        <div class="tool-name"><?php echo htmlspecialchars($tool['name']); ?></div>
                    </div>
                    <div class="usage-count">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="var(--apple-text-secondary)">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                        </svg>
                        使用量: <?php echo htmlspecialchars($tool['usage'] ?? 0); ?>
                    </div>
                    <div class="system-icons">
                        <?php foreach ($tool['adapted_systems'] ?? [] as $sys): ?>
                            <img src="../svg/<?php echo htmlspecialchars($sys); ?>.svg" alt="<?php echo htmlspecialchars($nameMappings['system'][$sys] ?? $sys); ?>" class="system-icon">
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 详情弹窗 -->
    <div class="modal-overlay" id="modal">
        <div class="modal-content">
            <div class="modal-header">
                <img src="" alt="" class="modal-tool-icon" id="modal-tool-icon">
                <div class="modal-tool-name" id="modal-tool-name"></div>
            </div>
            <div class="modal-body">
                <div class="modal-info">
                    <div class="info-item">
                        <div class="info-label">分类:</div>
                        <div class="info-value" id="modal-category"></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">类型:</div>
                        <div class="info-value" id="modal-type"></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">使用量:</div>
                        <div class="info-value" id="modal-usage"></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">适配系统:</div>
                        <div class="info-value" id="modal-systems"></div>
                    </div>
                </div>
                <div class="modal-description" id="modal-description"></div>
            </div>
            <div class="modal-footer">
                <button class="modal-btn btn-cancel" id="modal-cancel">关闭</button>
                <div id="action-container">
                    <!-- 动态生成操作按钮/下拉 -->
                </div>
            </div>
        </div>
    </div>

    <!-- 返回顶部按钮 -->
    <div class="back-to-top" id="back-to-top">
        <img src="../svg/返回顶部.svg" alt="返回顶部">
    </div>

    <!-- CSRF令牌隐藏域（代码优化：安全防护） -->
    <input type="hidden" id="csrf-token" value="<?php echo htmlspecialchars($_SESSION[CSRF_TOKEN_NAME]); ?>">
    
    <script>
        // ===================== 代码优化：JS模块化重构 =====================
        document.addEventListener('DOMContentLoaded', () => {
            // 1. 全局配置（从PHP统一映射表，避免重复）
            const config = {
                systemNameMap: <?php echo json_encode($nameMappings['system']); ?>,
                toolTypeNameMap: <?php echo json_encode($nameMappings['toolType']); ?>,
                csrfTokenName: '<?php echo CSRF_TOKEN_NAME; ?>',
                csrfTokenValue: document.getElementById('csrf-token').value
            };

            // 2. 工具函数封装（代码优化：更模块化）
            const utils = {
                // 首字母大写
                ucfirst: (str) => str ? str.charAt(0).toUpperCase() + str.slice(1) : '',
                
                // 获取系统名称
                getSystemName: (sys) => config.systemNameMap[sys] || utils.ucfirst(sys),
                
                // 获取工具类型名称
                getToolTypeName: (type) => config.toolTypeNameMap[type] || utils.ucfirst(type),
                
                // 防抖函数（代码优化：增加立即执行选项）
                debounce: (fn, delay = 300, immediate = false) => {
                    let timer = null;
                    return (...args) => {
                        const callNow = immediate && !timer;
                        clearTimeout(timer);
                        
                        timer = setTimeout(() => {
                            timer = null;
                            if (!immediate) fn.apply(this, args);
                        }, delay);
                        
                        if (callNow) fn.apply(this, args);
                    };
                },
                
                // 更新工具使用量（代码优化：增加错误提示、CSRF验证）
                updateUsage: async (toolName, card) => {
                    try {
                        const formData = new FormData();
                        formData.append('tool_name', toolName);
                        formData.append(config.csrfTokenName, config.csrfTokenValue);
                        
                        const response = await fetch(window.location.href, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest' // 标识AJAX请求
                            }
                        });
                        
                        const data = await response.json();
                        if (data.status === 'success') {
                            // 更新卡片使用量
                            const usageElement = card.querySelector('.usage-count');
                            if (usageElement) {
                                usageElement.textContent = `使用量: ${data.new_usage}`;
                            }
                            // 更新弹窗使用量
                            const modalUsage = document.getElementById('modal-usage');
                            if (modalUsage) {
                                modalUsage.textContent = data.new_usage;
                            }
                        } else {
                            console.warn('更新使用量失败:', data.message);
                        }
                    } catch (error) {
                        console.error('更新使用量请求失败:', error);
                    }
                },
                
                // 检查空状态（界面美化：空状态处理）
                checkEmptyState: (toolCards) => {
                    const emptyState = document.getElementById('empty-state');
                    const visibleCards = Array.from(toolCards).filter(card => card.style.display !== 'none');
                    
                    if (visibleCards.length === 0) {
                        emptyState.classList.add('active');
                    } else {
                        emptyState.classList.remove('active');
                    }
                }
            };

            // 3. 筛选逻辑（代码优化：事件委托、空状态检测）
            const initFilter = () => {
                const searchInput = document.getElementById('search-input');
                const categoryFilter = document.getElementById('category-filter');
                const systemFilter = document.getElementById('system-filter');
                const toolCards = document.querySelectorAll('.tool-card');
                
                // 筛选函数（代码优化：逻辑简化、空状态检测）
                const filterTools = utils.debounce(() => {
                    const searchTerm = searchInput.value.toLowerCase().trim();
                    const selectedCategory = categoryFilter.value;
                    const selectedSystem = systemFilter.value;

                    toolCards.forEach(card => {
                        const name = (card.dataset.name || '').toLowerCase();
                        const category = (card.dataset.category || '').toLowerCase();
                        const systems = (card.dataset.systems || '').toLowerCase().split(',');
                        
                        const searchMatch = !searchTerm || name.includes(searchTerm) || category.includes(searchTerm);
                        const categoryMatch = selectedCategory === 'all' || card.dataset.category === selectedCategory;
                        const systemMatch = selectedSystem === 'all' || systems.includes(selectedSystem);

                        card.style.display = (searchMatch && categoryMatch && systemMatch) ? 'block' : 'none';
                    });
                    
                    // 检查空状态
                    utils.checkEmptyState(toolCards);
                }, 300);

                // 绑定筛选事件
                searchInput.addEventListener('input', filterTools);
                categoryFilter.addEventListener('change', filterTools);
                systemFilter.addEventListener('change', filterTools);
                
                // 初始执行一次筛选
                filterTools();
            };

            // 4. 弹窗逻辑（代码优化：封装为独立函数、事件委托）
            const initModal = () => {
                const modal = document.getElementById('modal');
                const modalCancel = document.getElementById('modal-cancel');
                const toolsDisplay = document.getElementById('tools-display');
                
                // 关闭弹窗函数
                const closeModal = () => {
                    modal.classList.remove('active');
                    document.body.style.overflow = 'auto';
                };

                // 打开弹窗函数
                const openModal = (card) => {
                    // 获取卡片数据
                    const toolName = card.dataset.name || '';
                    const toolIcon = card.dataset.icon || '../svg/default.svg';
                    const category = card.dataset.category || '未分类';
                    const type = card.dataset.type || '';
                    const usageText = card.querySelector('.usage-count')?.textContent || '使用量: 0';
                    const usage = usageText.replace('使用量: ', '') || '0';
                    const systems = (card.dataset.systems || '').split(',');
                    const description = card.dataset.description || '无描述';
                    const links = JSON.parse(card.dataset.links || '{}');

                    // 填充弹窗内容
                    document.getElementById('modal-tool-icon').src = toolIcon;
                    document.getElementById('modal-tool-name').textContent = toolName;
                    document.getElementById('modal-category').textContent = category;
                    document.getElementById('modal-type').textContent = utils.getToolTypeName(type);
                    document.getElementById('modal-usage').textContent = usage;
                    document.getElementById('modal-systems').textContent = systems.map(sys => utils.getSystemName(sys)).join('、');
                    document.getElementById('modal-description').textContent = description;

                    // 生成操作按钮
                    const actionContainer = document.getElementById('action-container');
                    actionContainer.innerHTML = '';

                    if (type === 'app') {
                        // 应用类型：系统选择下拉+按钮
                        const select = document.createElement('select');
                        select.className = 'system-select';
                        select.id = 'app-system-select';
                        
                        systems.forEach(sys => {
                            const option = document.createElement('option');
                            option.value = sys;
                            option.textContent = utils.getSystemName(sys);
                            select.appendChild(option);
                        });

                        const btn = document.createElement('button');
                        btn.className = 'modal-btn btn-action';
                        btn.textContent = '获取应用';
                        btn.addEventListener('click', async () => {
                            const selectedSys = select.value;
                            const link = links[selectedSys];
                            if (link) {
                                await utils.updateUsage(toolName, card);
                                window.open(link, '_blank');
                            }
                        });

                        actionContainer.appendChild(select);
                        actionContainer.appendChild(btn);
                    } else if (type === 'web') {
                        // 网页工具：直接打开
                        const btn = document.createElement('button');
                        btn.className = 'modal-btn btn-action';
                        btn.textContent = '打开网页';
                        btn.addEventListener('click', async () => {
                            await utils.updateUsage(toolName, card);
                            window.open(links.web, '_blank');
                        });
                        actionContainer.appendChild(btn);
                    } else if (type === 'offweb') {
                        // 本地网页工具：跳转
                        const btn = document.createElement('button');
                        btn.className = 'modal-btn btn-action';
                        btn.textContent = '使用';
                        btn.addEventListener('click', async () => {
                            await utils.updateUsage(toolName, card);
                            window.location.href = links.offweb;
                        });
                        actionContainer.appendChild(btn);
                    }

                    // 显示弹窗
                    modal.classList.add('active');
                    document.body.style.overflow = 'hidden';
                };

                // 事件委托：卡片点击打开弹窗（代码优化：替代逐个绑定）
                toolsDisplay.addEventListener('click', (e) => {
                    const card = e.target.closest('.tool-card');
                    if (card) openModal(card);
                });

                // 关闭弹窗事件
                modalCancel.addEventListener('click', closeModal);
                modal.addEventListener('click', (e) => e.target === modal && closeModal);
                
                // 界面美化：ESC键关闭弹窗
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && modal.classList.contains('active')) {
                        closeModal();
                    }
                });
            };

            // 5. 返回顶部逻辑（代码优化：平滑滚动）
            const initBackToTop = () => {
                const backToTop = document.getElementById('back-to-top');
                
                // 滚动监听
                window.addEventListener('scroll', () => {
                    backToTop.classList.toggle('active', window.scrollY > 300);
                });

                // 点击返回顶部
                backToTop.addEventListener('click', () => {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
            };

            // 初始化所有功能
            initFilter();
            initModal();
            initBackToTop();
        });
    </script>
</body>
</html>
