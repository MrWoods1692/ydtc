<?php
// 错误处理设置
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

// ===================== 1. 核心配置与常量定义 =====================
define('BASE_DIR', realpath(__DIR__ . '/../')); // 基础目录
define('USERS_DIR', BASE_DIR . '/users/');     // 用户目录
define('SVG_DIR', BASE_DIR . '/svg/');         // SVG图标目录
define('FONT_DIR', BASE_DIR . '/font/');       // 字体目录

// 空间限额配置 (单位: KB)
$level_space_map = [
    1 => 2 * 1024 * 1024,     // 2GB
    2 => 4 * 1024 * 1024,     // 4GB
    3 => 8 * 1024 * 1024,     // 8GB
    4 => 16 * 1024 * 1024,    // 4级16GB
    5 => 32 * 1024 * 1024,    // 5级32GB
    6 => 64 * 1024 * 1024,    // 6级64GB
    7 => 128 * 1024 * 1024,   // 7级128GB
    8 => 256 * 1024 * 1024,   // 8级256GB
    9 => 512 * 1024 * 1024,   // 9级512GB
    10 => 1024 * 1024 * 1024, // 10级1TB
    11 => PHP_INT_MAX          // 10+级无限
];

// 回收站保留天数配置
$level_days_map = [
    1 => 5, 2 => 5, 3 => 5,
    4 => 10, 5 => 10, 6 => 10,
    7 => 20, 8 => 20, 9 => 20,
    10 => 30, 11 => 30
];

// 文件类型对应SVG图标
$file_icon_map = [
    'gif' => 'gif.svg',
    'png' => 'png.svg',
    'bmp' => 'bmp.svg',
    'jpeg' => 'jpeg.svg',
    'jpg' => 'jpeg.svg', // 兼容jpg后缀
    'tiff' => 'tiff.svg',
    'webp' => 'webp.svg'
];

// ===================== 2. 用户身份验证 =====================
// 获取URL参数中的用户邮箱
$url_user_email = isset($_GET['user']) ? trim($_GET['user']) : '';
// 获取Cookie中的用户邮箱
$cookie_user_email = isset($_COOKIE['user_email']) ? trim($_COOKIE['user_email']) : '';

// 验证身份
if (empty($url_user_email) || empty($cookie_user_email) || $url_user_email !== $cookie_user_email) {
    // 输出禁止访问的SVG
    $forbid_svg = SVG_DIR . '禁止.svg';
    if (file_exists($forbid_svg)) {
        header('Content-Type: image/svg+xml');
        readfile($forbid_svg);
        exit;
    } else {
        die('访问被拒绝：身份验证失败');
    }
}

// 处理用户目录（URL编码）
$encoded_email = urlencode($url_user_email);
$user_dir = USERS_DIR . $encoded_email . '/';

// 检查用户目录是否存在
if (!is_dir($user_dir)) {
    die('用户目录不存在');
}

// ===================== 3. 读取用户数据 =====================
$user_data = ['level' => 1, 'used_space_kb' => 0];
$user_json = $user_dir . 'user.json';
if (file_exists($user_json)) {
    $user_content = file_get_contents($user_json);
    $user_data = json_decode($user_content, true) ?: $user_data;
}
$user_level = (int)$user_data['level'];
$used_space_kb = (int)$user_data['used_space_kb'];

// 确定用户的空间限额和保留天数
$user_level = $user_level >= 11 ? 11 : ($user_level < 1 ? 1 : $user_level);
$space_limit_kb = $level_space_map[$user_level];
$retain_days = $level_days_map[$user_level];

// ===================== 4. 回收站清理与数据读取 =====================
// 回收站相册名称（假设固定为"回收站"）
$trash_album = '回收站';
$trash_album_dir = $user_dir . $trash_album . '/';
$trash_p_json = $trash_album_dir . 'p.json';
$trash_ls_json = $trash_album_dir . 'ls.json';

// 确保回收站目录存在
if (!is_dir($trash_album_dir)) {
    mkdir($trash_album_dir, 0755, true);
    file_put_contents($trash_ls_json, '[]');
    file_put_contents($trash_p_json, json_encode(['name' => '回收站', 'remark' => ''], JSON_UNESCAPED_UNICODE));
}

// 读取回收站文件列表
$trash_files = [];
if (file_exists($trash_ls_json)) {
    $trash_content = file_get_contents($trash_ls_json);
    $trash_files = json_decode($trash_content, true) ?: [];
}

// 清理过期文件
$current_time = time();
$expired_files = [];
$valid_files = [];
foreach ($trash_files as $file) {
    $trash_time = isset($file['trash_time']) ? (int)$file['trash_time'] : 0;
    $expire_time = $trash_time + ($retain_days * 24 * 3600);
    
    if ($current_time > $expire_time) {
        $expired_files[] = $file;
    } else {
        $valid_files[] = $file;
    }
}

// 更新回收站文件列表（删除过期文件）
if (!empty($expired_files)) {
    file_put_contents($trash_ls_json, json_encode($valid_files, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    // 记录清理日志
    log_operation($user_dir, '自动清理回收站过期文件：共' . count($expired_files) . '张');
}
$trash_files = $valid_files;

// ===================== 5. 恢复文件处理 =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore') {
    $file_index = isset($_POST['file_index']) ? (int)$_POST['file_index'] : -1;
    
    if ($file_index >= 0 && isset($trash_files[$file_index])) {
        $file_to_restore = $trash_files[$file_index];
        $file_size_kb = (int)$file_to_restore['size_kb'];
        $original_album = isset($file_to_restore['original_album']) ? $file_to_restore['original_album'] : '';
        
        // 空间校验
        if ($space_limit_kb !== PHP_INT_MAX && ($used_space_kb + $file_size_kb) > $space_limit_kb) {
            $error_msg = '恢复失败：超出用户空间限额（已用' . format_size($used_space_kb) . '，文件大小' . format_size($file_size_kb) . '，限额' . format_size($space_limit_kb) . '）';
        } elseif (empty($original_album)) {
            $error_msg = '恢复失败：原相册信息缺失';
        } else {
            // 原相册目录
            $original_album_dir = $user_dir . $original_album . '/';
            $original_ls_json = $original_album_dir . 'ls.json';
            
            // 确保原相册目录存在
            if (!is_dir($original_album_dir)) {
                mkdir($original_album_dir, 0755, true);
                file_put_contents($original_ls_json, '[]');
                file_put_contents($original_album_dir . 'p.json', json_encode(['name' => $original_album, 'remark' => ''], JSON_UNESCAPED_UNICODE));
            }
            
            // 读取原相册文件列表
            $original_files = [];
            if (file_exists($original_ls_json)) {
                $original_content = file_get_contents($original_ls_json);
                $original_files = json_decode($original_content, true) ?: [];
            }
            
            // 移除回收站特有字段，恢复为正常文件格式
            unset($file_to_restore['trash_time'], $file_to_restore['original_album']);
            $original_files[] = $file_to_restore;
            
            // 更新原相册文件列表
            file_put_contents($original_ls_json, json_encode($original_files, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
            // 从回收站移除该文件
            array_splice($trash_files, $file_index, 1);
            file_put_contents($trash_ls_json, json_encode($trash_files, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
            // 更新用户已用空间（这里假设恢复文件需要增加已用空间，根据实际业务调整）
            $user_data['used_space_kb'] += $file_size_kb;
            file_put_contents($user_json, json_encode($user_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
            // 记录日志
            log_operation($user_dir, '恢复文件：' . $file_to_restore['name'] . ' 到相册：' . $original_album . '，大小：' . format_size($file_size_kb));
            
            $success_msg = '文件恢复成功：' . $file_to_restore['name'];
        }
    } else {
        $error_msg = '恢复失败：文件不存在';
    }

    // 恢复操作后重新读取文件列表（用于前端展示）
    $trash_files = json_decode(file_get_contents($trash_ls_json), true) ?: [];
}

// ===================== 6. 辅助函数 =====================
/**
 * 记录操作日志
 * @param string $user_dir 用户目录
 * @param string $content 日志内容
 */
function log_operation($user_dir, $content) {
    $log_file = $user_dir . 'log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $log_line = "[$timestamp] $content\n";
    file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
}

/**
 * 格式化文件大小（KB转易读格式）
 * @param int $size_kb 大小（KB）
 * @return string 格式化后的大小
 */
function format_size($size_kb) {
    if ($size_kb < 1024) {
        return $size_kb . 'KB';
    } elseif ($size_kb < 1024 * 1024) {
        return round($size_kb / 1024, 2) . 'MB';
    } elseif ($size_kb < 1024 * 1024 * 1024) {
        return round($size_kb / (1024 * 1024), 2) . 'GB';
    } else {
        return round($size_kb / (1024 * 1024 * 1024), 2) . 'TB';
    }
}

/**
 * 获取文件后缀对应的图标路径
 * @param string $filename 文件名
 * @return string SVG图标路径
 */
function get_file_icon($filename) {
    global $file_icon_map;
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return isset($file_icon_map[$ext]) ? '../svg/' . $file_icon_map[$ext] : '../svg/unknown.svg';
}

/**
 * 时间戳转易读格式
 * @param int $timestamp 时间戳
 * @return string 格式化时间
 */
function format_time($timestamp) {
    return date('Y-m-d H:i:s', $timestamp);
}

/**
 * 将文件列表转为JSON（用于前端搜索）
 */
$trash_files_json = json_encode($trash_files, JSON_UNESCAPED_UNICODE);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>回收站 - 相册管理</title>
    <style>
        /* 引入阿里妈妈灵动体 */
        @font-face {
            font-family: 'AlimamaAgile';
            src: url('../font/AlimamaAgileVF-Thin.woff2') format('woff2'),
                 url('../font/AlimamaAgileVF-Thin.woff') format('woff'),
                 url('../font/AlimamaAgileVF-Thin.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        /* 全局样式 - 苹果风格优化 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'AlimamaAgile', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            outline: none;
        }

        body {
            background-color: #f5f5f7;
            color: #1d1d1f;
            line-height: 1.5;
            padding: 0;
            margin: 0;
        }

        /* 顶部导航栏 */
        .navbar {
            background-color: #ffffff;
            height: 56px;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-back {
            background: transparent;
            border: none;
            cursor: pointer;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.2s;
        }

        .btn-back:hover {
            background-color: #f0f0f2;
        }

        .btn-back img {
            width: 18px;
            height: 18px;
        }

        .navbar-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 18px;
            font-weight: 600;
        }

        .navbar-title img {
            width: 20px;
            height: 20px;
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 14px;
            color: #6e6e73;
        }

        /* 主容器 */
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }

        /* 搜索框样式 */
        .search-container {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .search-icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .search-input {
            flex: 1;
            border: none;
            font-size: 16px;
            color: #1d1d1f;
            background: transparent;
        }

        .search-input::placeholder {
            color: #86868b;
        }

        /* 提示消息 */
        .msg {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .msg-success {
            background-color: #e8f4ea;
            color: #34c759;
            border: 1px solid #d4e9d9;
        }

        .msg-error {
            background-color: #ffebee;
            color: #ff3b30;
            border: 1px solid #ffd6d6;
        }

        /* 列表视图样式 - 苹果风格 */
        .file-list {
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .list-header {
            display: grid;
            grid-template-columns: 60px 1fr 120px 180px 120px 100px;
            padding: 12px 20px;
            background-color: #f9f9fb;
            border-bottom: 1px solid #e6e6e8;
            font-size: 13px;
            color: #86868b;
            font-weight: 500;
        }

        .file-item {
            display: grid;
            grid-template-columns: 60px 1fr 120px 180px 120px 100px;
            padding: 16px 20px;
            align-items: center;
            border-bottom: 1px solid #f0f0f2;
            transition: background-color 0.2s;
        }

        .file-item:last-child {
            border-bottom: none;
        }

        .file-item:hover {
            background-color: #f9f9fb;
        }

        .file-icon {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .file-icon img {
            width: 24px;
            height: 24px;
        }

        .file-name {
            font-size: 15px;
            font-weight: 500;
            color: #1d1d1f;
        }

        .file-meta {
            font-size: 14px;
            color: #6e6e73;
        }

        /* 按钮样式优化 */
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
        }

        .btn-restore {
            background-color: #0071e3;
            color: white;
        }

        .btn-restore:hover {
            background-color: #0077ed;
            transform: translateY(-1px);
        }

        .btn-restore:active {
            transform: translateY(0);
        }

        /* 空状态样式 */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #86868b;
            font-size: 16px;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .empty-state img {
            width: 64px;
            height: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        /* 响应式适配 */
        @media (max-width: 768px) {
            .list-header {
                grid-template-columns: 50px 1fr 100px 140px 80px;
            }
            .file-item {
                grid-template-columns: 50px 1fr 100px 140px 80px;
            }
            .list-header .col-hide-mobile,
            .file-item .col-hide-mobile {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- 顶部导航栏 -->
    <div class="navbar">
        <div class="navbar-left">
            <!-- 返回按钮 -->
            <button class="btn-back" onclick="window.history.back()">
                <img src="../svg/返回.svg" alt="返回">
            </button>
            <!-- 标题 -->
            <div class="navbar-title">
                <img src="../svg/回收站.svg" alt="回收站">
                回收站
            </div>
        </div>
        <!-- 用户信息 -->
        <div class="navbar-right">
            <span>等级：<?= $user_level ?>级</span>
            <span>已用：<?= format_size($used_space_kb) ?> / <?= $space_limit_kb === PHP_INT_MAX ? '无限' : format_size($space_limit_kb) ?></span>
        </div>
    </div>

    <!-- 主容器 -->
    <div class="container">
        <!-- 提示消息 -->
        <?php if (isset($success_msg)): ?>
            <div class="msg msg-success">
                <?= htmlspecialchars($success_msg) ?>
            </div>
        <?php endif; ?>
        <?php if (isset($error_msg)): ?>
            <div class="msg msg-error">
                <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <!-- 搜索框 -->
        <div class="search-container">
            <img src="../svg/搜索.svg" alt="搜索" class="search-icon">
            <input type="text" class="search-input" id="searchInput" placeholder="搜索文件名...">
        </div>

        <!-- 文件列表 -->
        <?php if (empty($trash_files)): ?>
            <div class="empty-state">
                <img src="../svg/回收站.svg" alt="回收站为空">
                <p>回收站中暂无文件</p>
                <p style="font-size: 14px; margin-top: 8px; color: #a1a1a6;">删除的文件会出现在这里</p>
            </div>
        <?php else: ?>
            <div class="file-list" id="fileListContainer">
                <!-- 列表头部 -->
                <div class="list-header">
                    <div>图标</div>
                    <div>文件名</div>
                    <div>大小</div>
                    <div>删除时间</div>
                    <div class="col-hide-mobile">原相册</div>
                    <div>操作</div>
                </div>

                <!-- 文件列表项（由JS动态渲染） -->
                <div id="fileListItems">
                    <!-- JS会在这里生成列表项 -->
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- 恢复文件的表单模板（隐藏） -->
    <form id="restoreFormTemplate" method="post" style="display: none;">
        <input type="hidden" name="action" value="restore">
        <input type="hidden" name="file_index" value="">
    </form>

    <script>
        // 原始文件列表数据
        const trashFiles = <?= $trash_files_json ?>;
        
        // 格式化文件大小（前端复用PHP逻辑）
        function formatSize(sizeKb) {
            if (sizeKb < 1024) {
                return sizeKb + 'KB';
            } else if (sizeKb < 1024 * 1024) {
                return (sizeKb / 1024).toFixed(2) + 'MB';
            } else if (sizeKb < 1024 * 1024 * 1024) {
                return (sizeKb / (1024 * 1024)).toFixed(2) + 'GB';
            } else {
                return (sizeKb / (1024 * 1024 * 1024)).toFixed(2) + 'TB';
            }
        }

        // 格式化时间戳
        function formatTime(timestamp) {
            const date = new Date(timestamp * 1000);
            return date.toLocaleString('zh-CN', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            }).replace(/\//g, '-');
        }

        // 获取文件图标
        function getFileIcon(filename) {
            const ext = filename.split('.').pop().toLowerCase();
            const iconMap = {
                'gif': '../svg/gif.svg',
                'png': '../svg/png.svg',
                'bmp': '../svg/bmp.svg',
                'jpeg': '../svg/jpeg.svg',
                'jpg': '../svg/jpeg.svg',
                'tiff': '../svg/tiff.svg',
                'webp': '../svg/webp.svg'
            };
            return iconMap[ext] || '../svg/unknown.svg';
        }

        // 渲染文件列表
        function renderFileList(files) {
            const listContainer = document.getElementById('fileListItems');
            listContainer.innerHTML = '';

            if (files.length === 0) {
                listContainer.innerHTML = `
                    <div class="file-item" style="grid-column: 1 / -1; text-align: center; padding: 40px 20px;">
                        <p style="color: #86868b;">未找到匹配的文件</p>
                    </div>
                `;
                return;
            }

            files.forEach((file, index) => {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                fileItem.innerHTML = `
                    <div class="file-icon">
                        <img src="${getFileIcon(file.name)}" alt="${file.name}图标">
                    </div>
                    <div class="file-name">${escapeHtml(file.name)}</div>
                    <div class="file-meta">${formatSize(file.size_kb)}</div>
                    <div class="file-meta">${formatTime(file.trash_time)}</div>
                    <div class="file-meta col-hide-mobile">${escapeHtml(file.original_album)}</div>
                    <div>
                        <button class="btn btn-restore" onclick="restoreFile(${index})">恢复</button>
                    </div>
                `;
                listContainer.appendChild(fileItem);
            });
        }

        // 恢复文件函数
        function restoreFile(index) {
            if (confirm(`确定要恢复文件 "${escapeHtml(trashFiles[index].name)}" 吗？`)) {
                // 复制模板表单并提交
                const formTemplate = document.getElementById('restoreFormTemplate');
                const form = formTemplate.cloneNode(true);
                form.style.display = 'block';
                form.querySelector('input[name="file_index"]').value = index;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // HTML转义函数
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // 实时搜索功能
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchText = e.target.value.toLowerCase().trim();
            if (!searchText) {
                renderFileList(trashFiles);
                return;
            }

            // 过滤包含搜索文本的文件
            const filteredFiles = trashFiles.filter(file => 
                file.name.toLowerCase().includes(searchText)
            );
            renderFileList(filteredFiles);
        });

        // 页面加载时初始化渲染
        window.onload = function() {
            renderFileList(trashFiles);
        };
    </script>
</body>
</html>
