<?php
/**
 * 日志查看系统 - PHP8.1
 * 优化：全局自动加载、铺满页面、去除页头
 * 修改：移除自动刷新、切换选项立即加载、顶部改为白色、添加缩进显示
 */

// ==================== 配置区域 ====================
// 日志目录配置（键为显示名称，值为实际路径）
$logDirectories = [
    '登录日志' => '/www/wwwroot/app.mrcwoods.com/in/log/',
    '商城日志' => '/www/wwwroot/app.mrcwoods.com/mall/logs/',
    '上传日志' => '/www/wwwroot/app.mrcwoods.com/tc/logs/'
];

// 商城日志每页显示的行数
$linesPerPage = 50;

// 安全配置：允许的文件扩展名
$allowedExtensions = ['log'];

// ==================== 初始化变量 ====================
$selectedDir = $_GET['dir'] ?? key($logDirectories);
$selectedFile = $_GET['file'] ?? '';
$viewMode = $_GET['mode'] ?? 'formatted'; // formatted=格式化, raw=源文件
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$logContent = '';
$totalLines = 0;
$totalPages = 0;
$fileList = [];
$errorMsg = '';
// 判断是否为商城日志（仅商城日志分页）
$isMallLog = $selectedDir === '商城日志';

// ==================== 核心函数 ====================

/**
 * 安全验证文件路径，防止路径遍历攻击
 * @param string $filePath 待验证的文件路径
 * @param string $baseDir 基础目录
 * @return bool
 */
function validateFilePath(string $filePath, string $baseDir): bool {
    $realBaseDir = realpath($baseDir);
    $realFilePath = realpath($filePath);
    
    if (!$realBaseDir || !$realFilePath) {
        return false;
    }
    
    // 确保文件在基础目录内
    return str_starts_with($realFilePath, $realBaseDir . DIRECTORY_SEPARATOR);
}

/**
 * 获取目录下的日志文件列表
 * @param string $dir 目录路径
 * @return array
 */
function getLogFiles(string $dir): array {
    global $allowedExtensions;
    $files = [];
    
    if (is_dir($dir)) {
        $dirIterator = new DirectoryIterator($dir);
        foreach ($dirIterator as $fileInfo) {
            if ($fileInfo->isFile()) {
                $extension = strtolower(pathinfo($fileInfo->getFilename(), PATHINFO_EXTENSION));
                if (in_array($extension, $allowedExtensions)) {
                    $files[] = $fileInfo->getFilename();
                }
            }
        }
        
        // 按文件名降序排序（最新的日志在前）
        rsort($files);
    }
    
    return $files;
}

/**
 * 解码Unicode转义字符
 * @param string $str 包含Unicode转义的字符串
 * @return string 解码后的字符串
 */
function decodeUnicode(string $str): string {
    // 匹配\uXXXX格式的Unicode转义
    $str = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function($matches) {
        return mb_convert_encoding(pack('H*', $matches[1]), 'UTF-8', 'UCS-2BE');
    }, $str);
    
    // 处理其他转义字符
    $str = stripslashes($str);
    return $str;
}

/**
 * 格式化日志内容（解码、转码、JSON格式化、增强缩进）
 * @param string $content 原始日志内容
 * @return string
 */
function formatLogContent(string $content): string {
    $lines = explode("\n", $content);
    $formattedLines = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
            $formattedLines[] = '<br>';
            continue;
        }
        
        // 尝试解析JSON格式的日志行
        $decodedJson = json_decode($line, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            // 先解码Unicode和转义字符，生成带缩进的JSON字符串
            $jsonString = json_encode($decodedJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            $decodedLine = decodeUnicode($jsonString);
            // 使用pre标签包裹，确保缩进和换行正常显示
            $formattedLines[] = '<div class="json-log"><pre class="indented-pre">' . htmlspecialchars($decodedLine) . '</pre></div>';
        } else {
            // 普通文本日志 - 解码Unicode并保留缩进
            $decodedLine = decodeUnicode($line);
            $decodedLine = str_replace('|', ' | ', $decodedLine); // 美化分隔符
            // 保留原始缩进并增强显示
            $formattedLines[] = '<div class="text-log"><pre class="indented-pre">' . htmlspecialchars($decodedLine) . '</pre></div>';
        }
    }
    
    return implode("\n", $formattedLines);
}

/**
 * 获取日志内容（商城日志分页，其他日志显示全部）
 * @param string $filePath 文件路径
 * @param int $page 当前页码
 * @param int $linesPerPage 每页行数
 * @param string $mode 查看模式
 * @param bool $isPagination 是否分页
 * @return array [content, totalLines, totalPages]
 */
function getLogContent(string $filePath, int $page, int $linesPerPage, string $mode, bool $isPagination): array {
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return ['', 0, 0];
    }
    
    // 读取所有行并反转（最新的日志在前）
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lines = array_reverse($lines);
    $totalLines = count($lines);
    $totalPages = 1;
    $paginatedLines = $lines;
    
    // 仅当需要分页时处理
    if ($isPagination && $totalLines > 0) {
        $totalPages = max(1, (int)ceil($totalLines / $linesPerPage));
        // 确保页码有效
        $page = max(1, min($page, $totalPages));
        // 计算分页偏移
        $offset = ($page - 1) * $linesPerPage;
        $paginatedLines = array_slice($lines, $offset, $linesPerPage);
    }
    
    // 恢复原始顺序（每页内的日志按时间正序显示）
    $paginatedLines = array_reverse($paginatedLines);
    $content = implode("\n", $paginatedLines);
    
    // 根据模式处理内容
    if ($mode === 'formatted') {
        $content = formatLogContent($content);
    } else {
        // 源文件模式也解码Unicode，增强缩进显示
        $content = decodeUnicode($content);
        $content = '<pre class="raw-log indented-pre">' . htmlspecialchars($content) . '</pre>';
    }
    
    return [$content, $totalLines, $totalPages];
}

// ==================== 业务逻辑处理 ====================

// 验证选中的目录是否有效
if (!isset($logDirectories[$selectedDir])) {
    $selectedDir = key($logDirectories);
    $isMallLog = $selectedDir === '商城日志';
}

// 获取当前目录下的日志文件列表
$currentDirPath = $logDirectories[$selectedDir];
$fileList = getLogFiles($currentDirPath);

// 如果没有选中文件，默认选第一个
if (empty($selectedFile) && !empty($fileList)) {
    $selectedFile = $fileList[0];
}

// 读取并处理日志文件
if (!empty($selectedFile)) {
    $filePath = $currentDirPath . $selectedFile;
    
    // 安全验证文件路径
    if (validateFilePath($filePath, $currentDirPath)) {
        list($logContent, $totalLines, $totalPages) = getLogContent(
            $filePath,
            $currentPage,
            $linesPerPage,
            $viewMode,
            $isMallLog
        );
    } else {
        $errorMsg = '文件路径验证失败，可能存在安全风险！';
    }
} else {
    $errorMsg = '当前目录下没有找到日志文件';
}

// 计算进度条百分比
$progressPercent = $totalPages > 0 ? ($currentPage / $totalPages) * 100 : 0;

// ==================== HTML输出 ====================
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>日志查看系统</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Microsoft YaHei', monospace;
        }
        
        body {
            background-color: #f5f7fa;
            height: 100vh;
            width: 100vw;
            overflow: hidden;
        }
        
        /* 控制区 - 固定顶部（修改为白色背景） */
        .controls {
            padding: 10px 15px;
            background-color: #ffffff; /* 改为白色 */
            color: #333333; /* 文字改为深灰色，保证可读性 */
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            width: 100%;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 100;
            border-bottom: 1px solid #eeeeee; /* 加底部边框，区分内容区 */
        }
        
        .control-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .control-group label {
            font-weight: 500;
            color: #333333; /* 标签文字改为深灰色 */
        }
        
        select, button {
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
            background-color: white;
            cursor: pointer;
        }
        
        select:focus, button:focus {
            outline: none;
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
        
        .btn {
            background-color: #007bff;
            color: white;
            border: none;
            transition: background-color 0.2s;
        }
        
        .btn:hover {
            background-color: #0056b3;
        }
        
        .btn-outline {
            background-color: transparent;
            color: #007bff;
            border: 1px solid #007bff;
        }
        
        .btn-outline:hover {
            background-color: #007bff;
            color: white;
        }
        
        /* 分页控制 - 仅商城日志显示 */
        .pagination {
            padding: 10px 15px;
            background-color: #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            position: fixed;
            top: 60px;
            left: 0;
            z-index: 99;
        }
        
        .pagination-info {
            color: #495057;
            font-size: 14px;
        }
        
        .progress-container {
            width: 100%;
            max-width: 400px;
            height: 8px;
            background-color: #dee2e6;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 100%;
            background-color: #28a745;
            border-radius: 4px;
            transition: width 0.3s ease;
            width: <?php echo $progressPercent; ?>%;
        }
        
        .page-controls {
            display: flex;
            gap: 8px;
        }
        
        /* 日志内容区域 - 铺满剩余页面 */
        .log-content {
            padding: 20px;
            position: fixed;
            top: <?php echo $isMallLog ? '100px' : '60px'; ?>;
            left: 0;
            right: 0;
            bottom: 0;
            overflow-y: auto;
            background-color: white;
            font-size: 14px;
            line-height: 1.6;
        }
        
        /* 缩进样式核心 - 确保日志缩进正确显示 */
        .indented-pre {
            white-space: pre-wrap;
            word-break: break-all;
            margin: 0;
            padding: 0;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 13px;
            line-height: 1.5;
            /* 基础缩进，让整体内容右移，更美观 */
            padding-left: 10px;
        }
        
        .json-log {
            background-color: #e9f5ff;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
            border-left: 4px solid #007bff;
        }
        
        .text-log {
            background-color: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 8px;
            border-left: 4px solid #6c757d;
        }
        
        .raw-log {
            white-space: pre-wrap;
            word-break: break-all;
            color: #212529;
            /* 源文件模式也增强缩进 */
            padding-left: 20px;
        }
        
        .error {
            color: #dc3545;
            padding: 20px;
            text-align: center;
            font-weight: 500;
        }
        
        /* 响应式适配 */
        @media (max-width: 768px) {
            .controls {
                padding: 8px 10px;
                gap: 10px;
            }
            
            .pagination {
                padding: 8px 10px;
                flex-direction: column;
                gap: 8px;
                align-items: flex-start;
                top: 100px;
            }
            
            .log-content {
                top: <?php echo $isMallLog ? '140px' : '100px'; ?>;
            }
            
            .progress-container {
                width: 100%;
            }
            
            .indented-pre {
                font-size: 12px;
                padding-left: 5px;
            }
            
            .raw-log {
                padding-left: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- 控制区 - 固定顶部 -->
    <div class="controls">
        <div class="control-group">
            <label for="log-dir">日志目录：</label>
            <select id="log-dir" onchange="updateFileList()">
                <?php foreach ($logDirectories as $name => $path): ?>
                    <option value="<?php echo htmlspecialchars($name); ?>" 
                        <?php echo $selectedDir === $name ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="control-group">
            <label for="log-file">日志文件：</label>
            <select id="log-file" onchange="loadLog()">
                <?php foreach ($fileList as $file): ?>
                    <option value="<?php echo htmlspecialchars($file); ?>" 
                        <?php echo $selectedFile === $file ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($file); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="control-group">
            <label for="view-mode">查看模式：</label>
            <select id="view-mode" onchange="loadLog()">
                <option value="formatted" <?php echo $viewMode === 'formatted' ? 'selected' : ''; ?>>格式化显示</option>
                <option value="raw" <?php echo $viewMode === 'raw' ? 'selected' : ''; ?>>源文件显示</option>
            </select>
        </div>

    </div>
    
    <!-- 分页控制 - 仅商城日志显示 -->
    <?php if ($isMallLog && $totalPages > 0): ?>
    <div class="pagination">
        <div class="pagination-info">
            第 <?php echo $currentPage; ?> 页 / 共 <?php echo $totalPages; ?> 页 | 
            总行数：<?php echo $totalLines; ?> | 每页：<?php echo $linesPerPage; ?> 行
        </div>
        
        <div class="progress-container">
            <div class="progress-bar"></div>
        </div>
        
        <div class="page-controls">
            <button class="btn-outline" onclick="changePage(1)" <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>>首页</button>
            <button class="btn-outline" onclick="changePage(<?php echo $currentPage - 1; ?>)" <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>>上一页</button>
            <button class="btn-outline" onclick="changePage(<?php echo $currentPage + 1; ?>)" <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>>下一页</button>
            <button class="btn-outline" onclick="changePage(<?php echo $totalPages; ?>)" <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>>尾页</button>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- 日志内容 - 铺满剩余页面 -->
    <div class="log-content">
        <?php if ($errorMsg): ?>
            <div class="error"><?php echo htmlspecialchars($errorMsg); ?></div>
        <?php elseif ($logContent): ?>
            <?php echo $logContent; ?>
        <?php else: ?>
            <div class="error">暂无日志内容</div>
        <?php endif; ?>
    </div>

    <script>
        // 更新文件列表
        function updateFileList() {
            const dir = document.getElementById('log-dir').value;
            // 重新加载页面，传递选中的目录，重置页码
            window.location.href = `?dir=${encodeURIComponent(dir)}&page=1`;
        }
        
        // 加载日志
        function loadLog() {
            const dir = document.getElementById('log-dir').value;
            const file = document.getElementById('log-file').value;
            const mode = document.getElementById('view-mode').value;
            
            window.location.href = `?dir=${encodeURIComponent(dir)}&file=${encodeURIComponent(file)}&mode=${mode}&page=1`;
        }
        
        // 切换页码
        function changePage(page) {
            const dir = document.getElementById('log-dir').value;
            const file = document.getElementById('log-file').value;
            const mode = document.getElementById('view-mode').value;
            
            window.location.href = `?dir=${encodeURIComponent(dir)}&file=${encodeURIComponent(file)}&mode=${mode}&page=${page}`;
        }
        
        // 页面加载完成后初始化
        document.addEventListener('DOMContentLoaded', function() {
            // 按回车键触发加载
            document.getElementById('log-file').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') loadLog();
            });
            
            document.getElementById('view-mode').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') loadLog();
            });
        });
    </script>
</body>
</html>
