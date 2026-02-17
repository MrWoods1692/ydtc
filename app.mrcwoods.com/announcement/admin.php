<?php
// 定义JSON文件路径
define('NOTICE_FILE', './公告.json');

/**
 * 初始化JSON文件（如果不存在则创建空数组）
 */
function initNoticeFile() {
    if (!file_exists(NOTICE_FILE)) {
        file_put_contents(NOTICE_FILE, json_encode([], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        chmod(NOTICE_FILE, 0666); // 设置可读写权限
    }
}

/**
 * 读取公告数据
 * @return array 公告列表
 */
function getNotices(): array {
    initNoticeFile();
    $jsonContent = file_get_contents(NOTICE_FILE);
    $notices = json_decode($jsonContent, true);
    
    // 验证JSON格式是否正确
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [];
    }
    
    // 排序：置顶公告在前，按日期倒序
    usort($notices, function($a, $b) {
        if ($a['isSticky'] !== $b['isSticky']) {
            return $a['isSticky'] ? -1 : 1;
        }
        return strcmp($b['date'], $a['date']);
    });
    
    return $notices;
}

/**
 * 保存公告数据
 * @param array $notices 公告列表
 * @return bool 保存是否成功
 */
function saveNotices(array $notices): bool {
    $jsonContent = json_encode($notices, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return file_put_contents(NOTICE_FILE, $jsonContent) !== false;
}

/**
 * 计算文本字数（中文算1个，英文/数字算1个）
 * @param string $text 要计算的文本
 * @return int 字数
 */
function countWords(string $text): int {
    return mb_strlen(trim($text), 'UTF-8');
}

/**
 * 处理用户操作（添加/编辑/删除/置顶）
 */
function handleActions() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    
    $action = $_POST['action'] ?? '';
    $notices = getNotices();
    
    switch ($action) {
        // 添加公告
        case 'add':
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $isSticky = isset($_POST['isSticky']) && $_POST['isSticky'] === '1';
            
            if (empty($title) || empty($content)) {
                $_SESSION['error'] = '标题和内容不能为空！';
                break;
            }
            
            $newNotice = [
                'title' => $title,
                'content' => $content,
                'date' => date('Y-m-d'),
                'isSticky' => $isSticky,
                'wordCount' => countWords($content)
            ];
            
            $notices[] = $newNotice;
            saveNotices($notices);
            $_SESSION['success'] = '公告添加成功！';
            break;
        
        // 编辑公告
        case 'edit':
            $index = (int)($_POST['index'] ?? -1);
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $isSticky = isset($_POST['isSticky']) && $_POST['isSticky'] === '1';
            
            if ($index < 0 || $index >= count($notices) || empty($title) || empty($content)) {
                $_SESSION['error'] = '编辑失败：参数错误！';
                break;
            }
            
            $notices[$index]['title'] = $title;
            $notices[$index]['content'] = $content;
            $notices[$index]['isSticky'] = $isSticky;
            $notices[$index]['wordCount'] = countWords($content);
            
            saveNotices($notices);
            $_SESSION['success'] = '公告编辑成功！';
            break;
        
        // 删除公告
        case 'delete':
            $index = (int)($_POST['index'] ?? -1);
            if ($index >= 0 && $index < count($notices)) {
                array_splice($notices, $index, 1);
                saveNotices($notices);
                $_SESSION['success'] = '公告删除成功！';
            } else {
                $_SESSION['error'] = '删除失败：公告不存在！';
            }
            break;
        
        // 切换置顶状态
        case 'toggleSticky':
            $index = (int)($_POST['index'] ?? -1);
            if ($index >= 0 && $index < count($notices)) {
                $notices[$index]['isSticky'] = !$notices[$index]['isSticky'];
                saveNotices($notices);
                $_SESSION['success'] = '置顶状态已更新！';
            } else {
                $_SESSION['error'] = '操作失败：公告不存在！';
            }
            break;
    }
    
    // 重定向避免表单重复提交
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// 初始化会话存储提示信息
session_start();
// 处理用户操作
handleActions();
// 获取公告列表
$notices = getNotices();
// 获取编辑的公告数据（如果有）
$editNotice = null;
$editIndex = -1;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editIndex = (int)$_GET['edit'];
    if ($editIndex >= 0 && $editIndex < count($notices)) {
        $editNotice = $notices[$editIndex];
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>公告管理系统</title>
    <style>
        /* 基础重置 - 让页面铺满全屏 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Microsoft Yahei", "PingFang SC", sans-serif;
        }
        
        html, body {
            height: 100%;
            width: 100%;
            background-color: #f0f2f5;
            overflow-x: hidden;
        }
        
        /* 主容器 - 全屏适配 */
        .app-container {
            min-height: 100vh;
            width: 100%;
            padding: 20px;
            display: flex;
            flex-direction: column;
        }
        
        /* 头部样式 */
        .header {
            margin-bottom: 20px;
        }
        
        .header h1 {
            color: #1f2937;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .header .subtitle {
            color: #6b7280;
            font-size: 14px;
        }
        
        /* 卡片容器 - 统一视觉风格 */
        .card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            padding: 24px;
            margin-bottom: 24px;
            width: 100%;
        }
        
        /* 提示框样式优化 */
        .alert {
            padding: 16px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .alert-success {
            background-color: #ecfdf5;
            color: #065f46;
            border: 1px solid #d1fae5;
        }
        
        .alert-error {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fee2e2;
        }
        
        /* 表单样式优化 */
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
            font-size: 14px;
        }
        
        input[type="text"], textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s ease;
            background-color: #ffffff;
        }
        
        input[type="text"]:focus, textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        textarea {
            min-height: 120px;
            resize: vertical;
            line-height: 1.5;
        }
        
        /* 复选框样式优化 */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #2563eb;
            cursor: pointer;
        }
        
        /* 按钮样式优化 */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        
        .btn-primary {
            background-color: #2563eb;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #1d4ed8;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
        }
        
        .btn-success {
            background-color: #059669;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #047857;
            box-shadow: 0 4px 6px -1px rgba(5, 150, 105, 0.2);
        }
        
        .btn-danger {
            background-color: #dc2626;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #b91c1c;
            box-shadow: 0 4px 6px -1px rgba(220, 38, 38, 0.2);
        }
        
        .btn-warning {
            background-color: #f59e0b;
            color: #212529;
        }
        
        .btn-warning:hover {
            background-color: #d97706;
            box-shadow: 0 4px 6px -1px rgba(245, 158, 11, 0.2);
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        /* 表格样式优化 */
        .table-container {
            overflow-x: auto;
            margin-top: 8px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
        }
        
        th {
            background-color: #f9fafb;
            color: #1f2937;
            font-weight: 600;
            font-size: 14px;
            text-align: left;
            padding: 16px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        td {
            padding: 16px;
            font-size: 14px;
            color: #374151;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }
        
        tr:hover {
            background-color: #f9fafb;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        /* 状态标签样式 */
        .badge {
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-warning {
            background-color: #fffbeb;
            color: #92400e;
            border: 1px solid #fcd34d;
        }
        
        .badge-default {
            background-color: #f3f4f6;
            color: #4b5563;
            border: 1px solid #d1d5db;
        }
        
        /* 操作按钮组 */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        /* 响应式适配 */
        @media (max-width: 768px) {
            .app-container {
                padding: 10px;
            }
            
            .card {
                padding: 16px;
            }
            
            .btn {
                padding: 8px 16px;
            }
            
            td, th {
                padding: 12px 8px;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        
        <!-- 公告表单卡片 -->
        <div class="card">
            <h2 style="font-size: 18px; color: #1f2937; margin-bottom: 20px; font-weight: 600;">
                <?= $editNotice ? '编辑公告' : '添加新公告' ?>
            </h2>
            
            <!-- 提示信息 -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    ✔ <?= htmlspecialchars($_SESSION['success']) ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    ❌ <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <!-- 公告表单（添加/编辑） -->
            <form method="post">
                <input type="hidden" name="action" value="<?= $editNotice ? 'edit' : 'add' ?>">
                <?php if ($editNotice): ?>
                    <input type="hidden" name="index" value="<?= $editIndex ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="title">公告标题</label>
                    <input type="text" id="title" name="title" required 
                           value="<?= $editNotice ? htmlspecialchars($editNotice['title']) : '' ?>">
                </div>
                
                <div class="form-group">
                    <label for="content">公告内容</label>
                    <textarea id="content" name="content" required><?= $editNotice ? htmlspecialchars($editNotice['content']) : '' ?></textarea>
                </div>
                
                <div class="form-group checkbox-group">
                    <input type="checkbox" name="isSticky" value="1" id="isSticky"
                           <?= ($editNotice && $editNotice['isSticky']) || (!$editNotice && false) ? 'checked' : '' ?>>
                    <label for="isSticky">置顶公告</label>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn <?= $editNotice ? 'btn-success' : 'btn-primary' ?>">
                        <?= $editNotice ? '保存修改' : '添加公告' ?>
                    </button>
                    <?php if ($editNotice): ?>
                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-warning"> 取消编辑</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- 公告列表卡片 -->
        <div class="card" style="flex: 1;">
            <h2 style="font-size: 18px; color: #1f2937; margin-bottom: 20px; font-weight: 600;">公告列表</h2>
            
            <?php if (empty($notices)): ?>
                <div style="text-align: center; padding: 40px; color: #6b7280; font-size: 16px;">
                    📄 暂无公告数据，点击上方添加第一条公告吧！
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 30%;">标题</th>
                                <th style="width: 15%;">发布日期</th>
                                <th style="width: 10%;">字数</th>
                                <th style="width: 15%;">状态</th>
                                <th style="width: 30%;">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notices as $index => $notice): ?>
                                <tr>
                                    <td style="font-weight: 500;"><?= htmlspecialchars($notice['title']) ?></td>
                                    <td><?= htmlspecialchars($notice['date']) ?></td>
                                    <td><?= $notice['wordCount'] ?></td>
                                    <td>
                                        <?= $notice['isSticky'] 
                                            ? '<span class="badge badge-warning">📌 已置顶</span>' 
                                            : '<span class="badge badge-default">普通公告</span>' ?>
                                    </td>
                                    <td class="action-buttons">
                                        <!-- 编辑按钮 -->
                                        <a href="?edit=<?= $index ?>" class="btn btn-primary btn-sm">编辑</a>
                                        <!-- 置顶/取消置顶按钮 -->
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="action" value="toggleSticky">
                                            <input type="hidden" name="index" value="<?= $index ?>">
                                            <button type="submit" class="btn <?= $notice['isSticky'] ? 'btn-warning' : 'btn-success' ?> btn-sm">
                                                <?= $notice['isSticky'] ? ' 取消置顶' : ' 设为置顶' ?>
                                            </button>
                                        </form>
                                        <!-- 删除按钮 -->
                                        <form method="post" style="display: inline;" onsubmit="return confirm('确定要删除该公告吗？删除后无法恢复！');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="index" value="<?= $index ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">删除</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
