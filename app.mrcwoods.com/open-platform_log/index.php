<?php
// 错误处理
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * 加载.env配置文件
 * @param string $path .env文件路径
 * @return array 配置数组
 */
function loadEnv($path) {
    $env = [];
    if (!file_exists($path)) {
        die("配置文件 {$path} 不存在！");
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // 跳过注释行
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        
        // 解析键值对
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            $env[$key] = $value;
        }
    }
    return $env;
}

/**
 * 建立数据库连接
 * @param array $envConfig 环境配置
 * @return PDO|null 数据库连接对象
 */
function connectDB($envConfig) {
    try {
        $dsn = "mysql:host={$envConfig['DB_HOST']};port={$envConfig['DB_PORT']};dbname={$envConfig['DB_NAME']};charset={$envConfig['DB_CHARSET']}";
        $pdo = new PDO($dsn, $envConfig['DB_USER'], $envConfig['DB_PASS']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("数据库连接失败：" . $e->getMessage());
    }
}

/**
 * 解析日志文件内容
 * @param string $logFile 日志文件路径
 * @return array 解析后的日志数组
 */
function parseLogFile($logFile) {
    $logs = [];
    if (!file_exists($logFile)) {
        return $logs;
    }
    
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // 匹配日志格式：[2026-02-23 10:00:00] 操作：登录 | 详情：用户通过验证码登录
        preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] 操作：(.*?) \| 详情：(.*)/', $line, $matches);
        if (count($matches) === 4) {
            $logs[] = [
                'time' => $matches[1],
                'action' => $matches[2],
                'detail' => $matches[3]
            ];
        }
    }
    // 按时间倒序排列（最新的在前）
    usort($logs, function($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });
    return $logs;
}

/**
 * 清除日志文件
 * @param string $logFile 日志文件路径
 * @return bool 操作结果
 */
function clearLogFile($logFile) {
    if (!file_exists($logFile)) {
        return true;
    }
    // 清空文件内容
    return file_put_contents($logFile, '') !== false;
}

/**
 * 根据操作类型获取对应的颜色类名
 * @param string $action 操作类型
 * @return string 颜色类名
 */
function getActionColorClass($action) {
    // 定义常见操作类型的颜色映射，可根据实际需求扩展
    $colorMap = [
        '登录' => 'action-success',
        '登出' => 'action-info',
        '创建' => 'action-primary',
        '修改' => 'action-warning',
        '删除' => 'action-danger',
        '查询' => 'action-secondary',
        '上传' => 'action-upload',
        '下载' => 'action-download',
        '授权' => 'action-authorize',
        '拒绝' => 'action-reject'
    ];
    
    // 转换为小写，去除空格，提高匹配度
    $actionKey = strtolower(str_replace(' ', '', $action));
    
    // 遍历匹配（支持模糊匹配）
    foreach ($colorMap as $key => $class) {
        if (strpos(strtolower($action), strtolower($key)) !== false) {
            return $class;
        }
    }
    
    // 默认颜色
    return 'action-default';
}

// ==================== 核心业务逻辑 ====================
// 加载环境配置
$envPath = '../in/.env';
$envConfig = loadEnv($envPath);

// 初始化变量
$userToken = $_COOKIE['user_token'] ?? '';
$userEmail = '';
$logFile = '';
$allLogs = [];
$filteredLogs = [];
$actionFilter = $_GET['action'] ?? '';
$dateFilter = $_GET['date'] ?? '';
$clearConfirm = $_GET['clear_confirm'] ?? '';

// 分页配置
$perPage = 100; // 每页100条
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$totalPages = 0;
$totalLogs = 0;

// 1. 验证token并获取用户邮箱
if (!empty($userToken)) {
    $pdo = connectDB($envConfig);
    $stmt = $pdo->prepare("SELECT email FROM users WHERE token = :token LIMIT 1");
    $stmt->bindParam(':token', $userToken);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $userEmail = $user['email'];
        // 构建日志文件路径
        $logFile = '../open-platform_log/' . urlencode($userEmail) . '.log';
        
        // 处理清除日志请求
        if ($clearConfirm === 'yes' && !empty($logFile)) {
            clearLogFile($logFile);
            // 清除后重定向，避免重复提交
            header("Location: {$_SERVER['PHP_SELF']}");
            exit;
        }
        
        // 解析日志文件
        $allLogs = parseLogFile($logFile);
        $totalLogs = count($allLogs);
        
        // 应用筛选条件
        if (!empty($actionFilter) || !empty($dateFilter)) {
            $filteredLogs = array_filter($allLogs, function($log) use ($actionFilter, $dateFilter) {
                $matchAction = empty($actionFilter) || $log['action'] === $actionFilter;
                $matchDate = empty($dateFilter) || str_starts_with($log['time'], $dateFilter);
                return $matchAction && $matchDate;
            });
            // 重置数组索引
            $filteredLogs = array_values($filteredLogs);
            $totalLogs = count($filteredLogs);
        } else {
            $filteredLogs = $allLogs;
        }
        
        // 计算分页
        $totalPages = ceil($totalLogs / $perPage);
        $currentPage = min($currentPage, $totalPages); // 防止页码超出范围
        $offset = ($currentPage - 1) * $perPage;
        $paginatedLogs = array_slice($filteredLogs, $offset, $perPage);
        
    } else {
        $errorMsg = "无效的用户Token，未找到对应用户！";
    }
} else {
    $errorMsg = "未检测到用户Token，请先登录！";
}

// 获取所有操作类型（用于筛选下拉框）
$allActions = array_unique(array_column($allLogs, 'action'));
// 获取所有日期（用于筛选下拉框）
$allDates = array_unique(array_map(function($log) {
    return substr($log['time'], 0, 10);
}, $allLogs));

/**
 * 生成分页链接
 * @param int $currentPage 当前页
 * @param int $totalPages 总页数
 * @param string $actionFilter 操作筛选条件
 * @param string $dateFilter 日期筛选条件
 * @return string 分页HTML
 */
function generatePagination($currentPage, $totalPages, $actionFilter, $dateFilter) {
    if ($totalPages <= 1) {
        return '';
    }
    
    $pagination = '<div class="pagination">';
    $params = [];
    if ($actionFilter) $params['action'] = $actionFilter;
    if ($dateFilter) $params['date'] = $dateFilter;
    
    // 上一页
    $prevPage = $currentPage - 1;
    $prevUrl = $prevPage >= 1 ? buildUrl($prevPage, $params) : '#';
    $pagination .= '<a href="' . $prevUrl . '" class="page-btn ' . ($prevPage < 1 ? 'disabled' : '') . '">上一页</a>';
    
    // 页码（显示当前页前后2页 + 首尾页）
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    
    // 显示第一页（如果不是起始页）
    if ($startPage > 1) {
        $pagination .= '<a href="' . buildUrl(1, $params) . '" class="page-num">' . 1 . '</a>';
        if ($startPage > 2) {
            $pagination .= '<span class="ellipsis">...</span>';
        }
    }
    
    // 显示中间页码
    for ($i = $startPage; $i <= $endPage; $i++) {
        $pagination .= '<a href="' . buildUrl($i, $params) . '" class="page-num ' . ($i == $currentPage ? 'active' : '') . '">' . $i . '</a>';
    }
    
    // 显示最后一页（如果不是结束页）
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $pagination .= '<span class="ellipsis">...</span>';
        }
        $pagination .= '<a href="' . buildUrl($totalPages, $params) . '" class="page-num">' . $totalPages . '</a>';
    }
    
    // 下一页
    $nextPage = $currentPage + 1;
    $nextUrl = $nextPage <= $totalPages ? buildUrl($nextPage, $params) : '#';
    $pagination .= '<a href="' . $nextUrl . '" class="page-btn ' . ($nextPage > $totalPages ? 'disabled' : '') . '">下一页</a>';
    
    // 页码信息
    $pagination .= '<div class="page-info">
                        <span>共 <strong>' . $totalLogs . '</strong> 条记录 | </span>
                        <span>第 <strong>' . $currentPage . '</strong> 页 / 共 <strong>' . $totalPages . '</strong> 页</span>
                    </div>';
    $pagination .= '</div>';
    
    return $pagination;
}

/**
 * 构建分页URL
 * @param int $page 页码
 * @param array $params 其他参数
 * @return string URL
 */
function buildUrl($page, $params) {
    $params['page'] = $page;
    return $_SERVER['PHP_SELF'] . '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户操作日志</title>
    <style>
        /* 基础重置与全局样式 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Inter", "Microsoft YaHei", Arial, sans-serif;
        }
        
        html, body {
            height: 100%;
            width: 100%;
            margin: 0;
            padding: 0;
            background: #ffffff; /* 纯白背景 */
            scroll-behavior: smooth;
        }
        
        body {
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            padding: 20px;
        }
        
        /* 隐藏所有滚动条（保留滚动功能） */
        ::-webkit-scrollbar {
            display: none; /* 隐藏Chrome滚动条 */
        }
        
        /* 兼容Firefox */
        * {
            scrollbar-width: none; /* 隐藏Firefox滚动条 */
        }
        
        /* IE/Edge兼容 */
        body {
            -ms-overflow-style: none; /* 隐藏IE/Edge滚动条 */
        }
        
        /* 卡片通用样式 - 浅色风格 */
        .card {
            background: #ffffff;
            border: 1px solid #f0f2f5; /* 极浅边框 */
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02); /* 极淡阴影 */
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04); /* hover时轻微增强阴影 */
            border-color: #e8eaed;
        }
        
        /* 筛选栏样式 */
        .filter-bar {
            padding: 20px 24px;
            display: flex;
            gap: 24px;
            align-items: center;
            flex-wrap: wrap;
            background: #fafbfc; /* 极浅灰色背景 */
            border-color: #e8eaed;
        }
        
        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            margin-bottom: 16px;
        }
        
        .filter-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: #1d2129; /* 深灰文字 */
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-header h2::after {
            content: "";
            display: block;
            width: 30px;
            height: 3px;
            background: #e8eaed; /* 浅灰色下划线 */
            border-radius: 2px;
        }
        
        .user-label {
            font-size: 13px;
            color: #4e5969; /* 中灰色文字 */
            background: #ffffff;
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #e8eaed;
        }
        
        .user-label span {
            color: #1d2129;
            font-weight: 500;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            font-size: 13px;
            color: #4e5969;
            font-weight: 500;
            white-space: nowrap;
            min-width: 70px;
        }
        
        .filter-bar select, .filter-bar input {
            padding: 10px 14px;
            border: 1px solid #e8eaed;
            border-radius: 8px;
            width: 100%;
            font-size: 13px;
            transition: all 0.2s ease;
            background: #ffffff;
            appearance: none; /* 移除默认下拉样式 */
            position: relative;
            color: #1d2129;
        }
        
        /* 自定义下拉箭头 */
        .filter-group {
            position: relative;
        }
        
        .filter-group::after {
            content: "▼";
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 9px;
            color: #86909c;
            pointer-events: none;
        }
        
        .filter-bar select:focus, .filter-bar input:focus {
            outline: none;
            border-color: #007bff; /* 浅蓝色焦点边框 */
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.05);
        }
        
        .btn-group {
            margin-left: auto;
            display: flex;
            gap: 10px;
            flex-shrink: 0;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary {
            background: #007bff; /* 浅蓝色主按钮 */
            color: white;
        }
        
        .btn-primary:hover {
            background: #0069d9;
            box-shadow: 0 2px 6px rgba(0, 123, 255, 0.2);
        }
        
        .btn-danger {
            background: #dc3545; /* 浅红色危险按钮 */
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
            box-shadow: 0 2px 6px rgba(220, 53, 69, 0.2);
        }
        
        .icon {
            width: 16px;
            height: 16px;
            vertical-align: middle;
            filter: brightness(0) invert(1);
        }
        
        /* 日志卡片样式 */
        .log-card {
            flex: 1;
            display: flex;
            flex-direction: column;
            margin-bottom: 0;
        }
        
        /* 日志表格样式 */
        .log-table-wrapper {
            flex: 1;
            overflow: auto;
        }
        
        .log-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        .log-table thead {
            position: sticky;
            top: 0;
            background: #f8f9fa; /* 表头浅灰背景 */
        }
        
        .log-table th {
            padding: 12px 16px;
            text-align: left;
            font-weight: 500;
            position: relative;
            font-size: 12px;
            color: #4e5969;
            border-bottom: 1px solid #e8eaed;
        }
        
        .log-table td {
            padding: 12px 16px;
            text-align: left;
            color: #1d2129;
            border-bottom: 1px solid #f0f2f5;
            transition: background 0.2s ease;
        }
        
        .log-table tr:hover td {
            background: #fafbfc; /* 行hover浅灰背景 */
        }
        
        .log-table td:first-child {
            color: #86909c;
            font-family: monospace;
            font-size: 12px;
        }
        
        .log-table td:nth-child(3) {
            max-width: 700px;
            word-break: break-all;
            line-height: 1.5;
            color: #4e5969;
        }
        
        /* 操作类型颜色样式 */
        .action-tag {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            text-align: center;
            white-space: nowrap;
        }
        
        /* 定义不同操作类型的颜色 */
        .action-success { background: #f0f8fb; color: #28a745; border: 1px solid #e6f7ee; } /* 成功-绿色 */
        .action-info { background: #e8f4f8; color: #17a2b8; border: 1px solid #d1e7dd; } /* 信息-青色 */
        .action-primary { background: #e8f4f8; color: #007bff; border: 1px solid #cce5ff; } /* 主要-蓝色 */
        .action-warning { background: #fef7fb; color: #ffc107; border: 1px solid #fff3cd; } /* 警告-黄色 */
        .action-danger { background: #fef0f0; color: #dc3545; border: 1px solid #f8d7da; } /* 危险-红色 */
        .action-secondary { background: #f5f5f5; color: #6c757d; border: 1px solid #e2e3e5; } /* 次要-灰色 */
        .action-upload { background: #f5fafe; color: #3291ff; border: 1px solid #e1f5fe; } /* 上传-浅蓝 */
        .action-download { background: #f0f8fb; color: #00b8d9; border: 1px solid #e0f7fa; } /* 下载-天蓝 */
        .action-authorize { background: #fcf1f7; color: #e3342f; border: 1px solid #fdf2f8; } /* 授权-玫红 */
        .action-reject { background: #f8f8f8; color: #959da5; border: 1px solid #fafafa; } /* 拒绝-浅灰 */
        .action-default { background: #f8f9fa; color: #495057; border: 1px solid #e9ecef; } /* 默认-浅灰 */
        
        /* 空状态样式 */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 80px 20px;
            color: #86909c;
            text-align: center;
            background: #fafbfc;
            height: 100%;
        }
        
        .empty-state .empty-icon {
            font-size: 64px;
            color: #e8eaed;
            margin-bottom: 20px;
            opacity: 0.8;
        }
        
        .empty-state h3 {
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 8px;
            color: #4e5969;
        }
        
        .empty-state p {
            font-size: 13px;
            color: #86909c;
            max-width: 400px;
            line-height: 1.5;
        }
        
        /* 分页样式 - 浅色风格 */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            padding: 16px 20px;
            background: #f8f9fa;
            border-top: 1px solid #e8eaed;
            border-radius: 0 0 12px 12px;
            flex-wrap: wrap;
        }
        
        .page-btn, .page-num {
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .page-btn {
            background: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            min-width: 80px;
        }
        
        .page-btn:hover:not(.disabled) {
            background: #0069d9;
        }
        
        .page-btn.disabled {
            background: #e8eaed;
            color: #86909c;
            cursor: not-allowed;
        }
        
        .page-num {
            background: #ffffff;
            color: #4e5969;
            border: 1px solid #e8eaed;
            min-width: 36px;
            height: 36px;
            padding: 0;
        }
        
        .page-num:hover:not(.active) {
            border-color: #007bff;
            color: #007bff;
        }
        
        .page-num.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .ellipsis {
            padding: 0 8px;
            color: #86909c;
            font-weight: 500;
            font-size: 12px;
        }
        
        .page-info {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: 16px;
            font-size: 12px;
            color: #86909c;
            flex-wrap: wrap;
        }
        
        .page-info strong {
            color: #4e5969;
            font-weight: 500;
        }
        
        /* 错误提示样式 - 浅色风格 */
        .error-card {
            padding: 16px 20px;
            background: #fef0f0;
            border: 1px solid #f8d7da;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .error-msg {
            color: #dc3545;
            font-size: 13px;
            line-height: 1.5;
        }
        
        .error-msg strong {
            font-weight: 500;
        }
        
        /* 自定义弹窗样式 - 浅色风格 */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.08); /* 极淡的遮罩 */
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-container {
            background: white;
            border: 1px solid #e8eaed;
            border-radius: 12px;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            transform: translateY(20px) scale(0.98);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .modal-overlay.active .modal-container {
            transform: translateY(0) scale(1);
        }
        
        .modal-header {
            padding: 16px 20px;
            background: #f8f9fa;
            color: #dc3545;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e8eaed;
        }
        
        .modal-header h3 {
            font-size: 15px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: #86909c;
            font-size: 20px;
            cursor: pointer;
            opacity: 0.8;
            transition: all 0.2s ease;
        }
        
        .modal-close:hover {
            opacity: 1;
            color: #4e5969;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-body p {
            font-size: 14px;
            color: #4e5969;
            line-height: 1.6;
            margin-bottom: 0;
        }
        
        .modal-body .warning-icon {
            font-size: 36px;
            color: #dc3545;
            margin-bottom: 16px;
            display: block;
            text-align: center;
        }
        
        .modal-footer {
            padding: 16px 20px;
            background: #fafbfc;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            border-top: 1px solid #e8eaed;
        }
        
        .modal-btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }
        
        .modal-btn-cancel {
            background: #ffffff;
            color: #4e5969;
            border-color: #e8eaed;
        }
        
        .modal-btn-cancel:hover {
            background: #f8f9fa;
            border-color: #d1d7dc;
        }
        
        .modal-btn-confirm {
            background: #dc3545;
            color: white;
        }
        
        .modal-btn-confirm:hover {
            background: #c82333;
            box-shadow: 0 2px 6px rgba(220, 53, 69, 0.1);
        }
        
        /* 加载动画 */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .card {
            animation: fadeInUp 0.3s ease forwards;
        }
        
        .filter-bar {
            animation-delay: 0.1s;
        }
        
        .log-card {
            animation-delay: 0.2s;
        }
        
        /* 响应式适配 */
        @media (max-width: 992px) {
            body {
                padding: 12px;
            }
            
            .filter-bar {
                gap: 16px;
                padding: 16px;
            }
            
            .filter-group {
                flex: 100%;
            }
            
            .btn-group {
                margin-left: 0;
                width: 100%;
            }
            
            .btn {
                flex: 1;
                justify-content: center;
                padding: 10px;
            }
            
            .page-info {
                margin-left: 0;
                margin-top: 8px;
                width: 100%;
                justify-content: center;
            }
            
            .log-table td:nth-child(3) {
                max-width: 300px;
            }
        }
        
        @media (max-width: 576px) {
            .filter-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            
            .filter-bar {
                padding: 12px;
            }
            
            .log-table th, .log-table td {
                padding: 10px 12px;
                font-size: 12px;
            }
            
            .pagination {
                padding: 12px;
                gap: 6px;
            }
            
            .page-btn {
                min-width: 70px;
                padding: 6px 12px;
                font-size: 11px;
            }
            
            .page-num {
                min-width: 32px;
                height: 32px;
                font-size: 11px;
            }
            
            .modal-container {
                width: 95%;
            }
            
            .modal-body {
                padding: 16px;
            }
            
            .modal-footer {
                padding: 12px 16px;
            }
        }
    </style>
    <!-- 引入Inter字体提升美观度 -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap">
</head>
<body>
    <!-- 自定义弹窗 -->
    <div class="modal-overlay" id="clearModal">
        <div class="modal-container">
            <div class="modal-header">
                <h3>确认清除日志</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <span class="warning-icon">⚠️</span>
                <p>您确定要清除所有日志记录吗？<br><strong>此操作不可恢复</strong>，所有日志数据将被永久删除。</p>
            </div>
            <div class="modal-footer">
                <button class="modal-btn modal-btn-cancel" onclick="closeModal()">取消</button>
                <button class="modal-btn modal-btn-confirm" onclick="confirmClear()">确认清除</button>
            </div>
        </div>
    </div>

    <?php if (isset($errorMsg)): ?>
        <div class="error-card">
            <div class="error-msg"><strong>提示：</strong><?= htmlspecialchars($errorMsg) ?></div>
        </div>
    <?php elseif (!empty($userEmail)): ?>
        <!-- 筛选和操作栏 -->
        <div class="card filter-bar">
            <div class="filter-header">
                <h2>用户操作日志</h2>
                <div class="user-label">当前用户：<span><?= htmlspecialchars($userEmail) ?></span></div>
            </div>
            
            <div class="filter-group">
                <label>操作类型：</label>
                <select id="actionFilter" onchange="applyFilter()">
                    <option value="">所有操作类型</option>
                    <?php foreach ($allActions as $action): ?>
                        <option value="<?= htmlspecialchars($action) ?>" <?= $actionFilter === $action ? 'selected' : '' ?>>
                            <?= htmlspecialchars($action) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>操作日期：</label>
                <input type="date" id="dateFilter" value="<?= htmlspecialchars($dateFilter) ?>" onchange="applyFilter()">
            </div>
            
            <div class="btn-group">
                <button class="btn btn-primary" onclick="location.reload()">
                    <img src="../svg/刷新.svg" class="icon" alt="刷新"> 刷新日志
                </button>
                
                <button class="btn btn-danger" onclick="openModal()">
                    <img src="../svg/日志.svg" class="icon" alt="清除"> 清除日志
                </button>
            </div>
        </div>
        
        <!-- 日志卡片 -->
        <div class="card log-card">
            <!-- 日志列表 -->
            <?php if (empty($paginatedLogs)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📄</div>
                    <h3>暂无日志记录</h3>
                    <p>当前筛选条件下未找到任何操作日志，可调整筛选条件或等待新的操作记录生成</p>
                </div>
            <?php else: ?>
                <div class="log-table-wrapper">
                    <table class="log-table">
                        <thead>
                            <tr>
                                <th width="180">操作时间</th>
                                <th width="140">操作类型</th>
                                <th>操作详情</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paginatedLogs as $log): 
                                $colorClass = getActionColorClass($log['action']);
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($log['time']) ?></td>
                                    <td>
                                        <span class="action-tag <?= $colorClass ?>">
                                            <?= htmlspecialchars($log['action']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($log['detail']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- 分页控件 -->
                <?= generatePagination($currentPage, $totalPages, $actionFilter, $dateFilter) ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <script>
        // 模态框控制
        const modal = document.getElementById('clearModal');
        
        function openModal() {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden'; // 防止背景滚动
        }
        
        function closeModal() {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        function confirmClear() {
            // 执行清除操作
            window.location.href = `${window.location.pathname}?clear_confirm=yes`;
            closeModal();
        }
        
        // 点击模态框外部关闭
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal();
            }
        });
        
        // ESC键关闭模态框
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                closeModal();
            }
        });
        
        // 应用筛选条件（保留分页）
        function applyFilter() {
            const action = document.getElementById('actionFilter').value;
            const date = document.getElementById('dateFilter').value;
            
            let url = window.location.pathname;
            const params = new URLSearchParams();
            if (action) params.append('action', action);
            if (date) params.append('date', date);
            // 筛选时重置到第一页
            params.append('page', 1);
            
            const queryString = params.toString();
            window.location.href = queryString ? `${url}?${queryString}` : url;
        }
        
        // 页面加载完成后调整表格高度
        window.addEventListener('load', function() {
            adjustTableHeight();
        });
        
        window.addEventListener('resize', adjustTableHeight);
        
        function adjustTableHeight() {
            const filterBarHeight = document.querySelector('.filter-bar')?.offsetHeight || 0;
            const paginationHeight = document.querySelector('.pagination')?.offsetHeight || 0;
            const bodyPadding = 40; // 上下padding总和
            const availableHeight = window.innerHeight - filterBarHeight - paginationHeight - bodyPadding - 20;
            
            const logTableWrapper = document.querySelector('.log-table-wrapper');
            if (logTableWrapper) {
                logTableWrapper.style.maxHeight = `${availableHeight}px`;
            }
        }
    </script>
</body>
</html>