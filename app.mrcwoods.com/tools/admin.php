<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

const JSON_FILE = __DIR__ . '/tools.json';

// 读取工具数据
function readTools(): array {
    if (!file_exists(JSON_FILE)) {
        file_put_contents(JSON_FILE, json_encode(['tools' => []], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    $data = json_decode(file_get_contents(JSON_FILE), true);
    return is_array($data) && isset($data['tools']) ? $data : ['tools' => []];
}

// 保存工具数据
function saveTools(array $data): bool {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return file_put_contents(JSON_FILE, $json) !== false;
}

// 初始化变量
$data = readTools();
$tools = $data['tools'] ?? [];
$editTool = null;
$message = '';
$messageType = '';

// 处理POST操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // 删除工具
    if ($action === 'delete' && isset($_POST['index'])) {
        $index = (int)$_POST['index'];
        if (isset($tools[$index])) {
            array_splice($tools, $index, 1);
            saveTools(['tools' => $tools]);
            $message = '工具删除成功！';
            $messageType = 'success';
        } else {
            $message = '无效的工具索引！';
            $messageType = 'error';
        }
    }
    
    // 加载编辑数据
    elseif ($action === 'edit' && isset($_POST['index'])) {
        $index = (int)$_POST['index'];
        if (isset($tools[$index])) {
            $editTool = $tools[$index];
            $editTool['_index'] = $index;
        } else {
            $message = '无效的工具索引！';
            $messageType = 'error';
        }
    }
    
    // 保存工具（新增/编辑）
    elseif ($action === 'save') {
        // 基础验证
        $required = ['name', 'category', 'description', 'icon', 'type'];
        $errors = [];
        foreach ($required as $field) {
            if (empty(trim($_POST[$field] ?? ''))) {
                $errors[] = "请填写{$field}字段";
            }
        }
        
        if (empty($errors)) {
            $toolData = [
                'name' => trim($_POST['name']),
                'category' => trim($_POST['category']),
                'description' => trim($_POST['description']),
                'icon' => trim($_POST['icon']),
                'type' => trim($_POST['type']),
                'usage' => isset($_POST['usage']) ? (int)$_POST['usage'] : 0, // 初始值0，支持修改
                'adapted_systems' => isset($_POST['adapted_systems']) ? (array)$_POST['adapted_systems'] : [],
                'links' => []
            ];
            
            // 根据类型组装链接
            $type = $toolData['type'];
            if ($type === 'web' && !empty($_POST['link_web'])) {
                $toolData['links']['web'] = trim($_POST['link_web']);
            } elseif ($type === 'offweb' && !empty($_POST['link_offweb'])) {
                $toolData['links']['offweb'] = trim($_POST['link_offweb']);
            } elseif ($type === 'app') {
                $sysLinks = ['ios', 'android', 'windows', 'macos', 'linux'];
                foreach ($sysLinks as $sys) {
                    if (!empty($_POST["link_{$sys}"])) {
                        $toolData['links'][$sys] = trim($_POST["link_{$sys}"]);
                    }
                }
            }
            
            // 编辑模式：更新
            if (isset($_POST['_index']) && is_numeric($_POST['_index'])) {
                $index = (int)$_POST['_index'];
                if (isset($tools[$index])) {
                    $tools[$index] = $toolData;
                    $message = '工具修改成功！';
                }
            } 
            // 新增模式：添加
            else {
                $tools[] = $toolData;
                $message = '工具添加成功！';
            }
            
            saveTools(['tools' => $tools]);
            $messageType = 'success';
            $editTool = null; // 清空编辑状态
        } else {
            $message = implode('，', $errors);
            $messageType = 'error';
        }
    }
    
    // 取消编辑
    elseif ($action === 'cancel') {
        $editTool = null;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>工具管理系统</title>
    <style>
        :root {
            --primary: #165DFF;
            --success: #00B42A;
            --danger: #F53F3F;
            --warning: #FF7D00;
            --gray-50: #F9FAFB;
            --gray-100: #F2F3F5;
            --gray-200: #E5E6EB;
            --gray-300: #C9CDD4;
            --gray-400: #86909C;
            --gray-500: #4E5969;
            --gray-600: #272E3B;
            --gray-700: #1D2129;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.08);
            --radius-sm: 4px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --transition: all 0.2s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'PingFang SC', 'Microsoft YaHei', sans-serif;
        }

        html, body {
            height: 100%;
            background-color: var(--gray-50);
            color: var(--gray-700);
            line-height: 1.5;
        }

        .container {
            width: 100%;
            min-height: 100vh;
            padding: 24px;
        }

        /* 消息提示 */
        .message {
            padding: 12px 16px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: fadeIn 0.3s ease;
        }

        .message.success {
            background-color: #E8F8EB;
            color: var(--success);
            border: 1px solid #B7F0C1;
        }

        .message.error {
            background-color: #FFF1F0;
            color: var(--danger);
            border: 1px solid #FFCCC7;
        }

        /* 头部 */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--gray-200);
        }

        .header h1 {
            font-size: 24px;
            font-weight: 600;
            color: var(--gray-700);
        }

        /* 表单区域 */
        .form-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            padding: 28px;
            margin-bottom: 32px;
            transition: var(--transition);
        }

        .form-card:hover {
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.1);
        }

        .form-card h2 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 24px;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-card h2::before {
            content: '';
            width: 4px;
            height: 20px;
            background-color: var(--primary);
            border-radius: var(--radius-sm);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-size: 14px;
            font-weight: 500;
            color: var(--gray-600);
        }

        .form-group label.required::after {
            content: '*';
            color: var(--danger);
            margin-left: 4px;
        }

        .form-control {
            padding: 12px 16px;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            font-size: 14px;
            color: var(--gray-700);
            transition: var(--transition);
            outline: none;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(22, 93, 255, 0.1);
        }

        .form-control::placeholder {
            color: var(--gray-400);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
            line-height: 1.5;
        }

        /* 适配系统选择 */
        .system-group {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 8px;
        }

        .system-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
            color: var(--gray-600);
        }

        .system-item input {
            width: 16px;
            height: 16px;
            accent-color: var(--primary);
        }

        /* 链接区域 */
        .link-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px dashed var(--gray-200);
            display: none;
        }

        .link-section.show {
            display: block;
            animation: slideDown 0.3s ease;
        }

        .link-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 12px;
        }

        /* 按钮样式 */
        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 28px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: #0E51D9;
            box-shadow: 0 4px 12px rgba(22, 93, 255, 0.3);
        }

        .btn-default {
            background-color: white;
            color: var(--gray-600);
            border: 1px solid var(--gray-200);
        }

        .btn-default:hover {
            background-color: var(--gray-50);
            border-color: var(--gray-300);
        }

        .btn-danger {
            background-color: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background-color: #E03030;
            box-shadow: 0 4px 12px rgba(245, 63, 63, 0.3);
        }

        /* 列表区域 */
        .list-header {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .list-header::before {
            content: '';
            width: 4px;
            height: 20px;
            background-color: var(--primary);
            border-radius: var(--radius-sm);
        }

        .table-container {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .tool-table {
            width: 100%;
            border-collapse: collapse;
        }

        .tool-table th {
            background-color: var(--gray-50);
            padding: 16px 20px;
            text-align: left;
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-600);
            border-bottom: 1px solid var(--gray-200);
        }

        .tool-table td {
            padding: 16px 20px;
            font-size: 14px;
            color: var(--gray-700);
            border-bottom: 1px solid var(--gray-100);
        }

        .tool-table tr:hover {
            background-color: rgba(22, 93, 255, 0.02);
        }

        .tool-table tr:last-child td {
            border-bottom: none;
        }

        .tool-name {
            font-weight: 500;
            color: var(--primary);
        }

        .tool-category {
            font-size: 13px;
            color: var(--gray-400);
            margin-top: 4px;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            padding: 6px 12px;
            font-size: 13px;
            border-radius: var(--radius-sm);
        }

        /* 空状态 */
        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: var(--gray-400);
            font-size: 14px;
        }

        /* 动画 */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* 响应式 */
        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-card {
                padding: 20px;
            }

            .btn-group {
                flex-wrap: wrap;
            }

            .tool-table th, .tool-table td {
                padding: 12px 16px;
            }

            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">

        <!-- 消息提示 -->
        <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <!-- 表单卡片 -->
        <div class="form-card">
            <h2><?php echo $editTool ? '编辑工具' : '添加新工具'; ?></h2>
            <form method="POST" id="toolForm">
                <input type="hidden" name="action" value="save">
                <?php if ($editTool): ?>
                <input type="hidden" name="_index" value="<?php echo htmlspecialchars((string)$editTool['_index']); ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <!-- 工具名称 -->
                    <div class="form-group">
                        <label class="required">工具名称</label>
                        <input type="text" name="name" class="form-control" required 
                               value="<?php echo $editTool ? htmlspecialchars($editTool['name']) : ''; ?>"
                               placeholder="请输入工具名称">
                    </div>

                    <!-- 分类 -->
                    <div class="form-group">
                        <label class="required">分类</label>
                        <input type="text" name="category" class="form-control" required 
                               value="<?php echo $editTool ? htmlspecialchars($editTool['category']) : ''; ?>"
                               placeholder="请输入工具分类（如：开发工具、办公工具）">
                    </div>

                    <!-- 图标路径 -->
                    <div class="form-group">
                        <label class="required">图标路径</label>
                        <input type="text" name="icon" class="form-control" required 
                               value="<?php echo $editTool ? htmlspecialchars($editTool['icon']) : ''; ?>"
                               placeholder="如：../svg/wangye.svg">
                    </div>

                    <!-- 使用次数 -->
                    <div class="form-group">
                        <label>使用次数</label>
                        <input type="number" name="usage" class="form-control" min="0" 
                               value="<?php echo $editTool ? (int)$editTool['usage'] : 0; ?>"
                               placeholder="初始值为0，可手动修改">
                    </div>

                    <!-- 工具类型 -->
                    <div class="form-group">
                        <label class="required">工具类型</label>
                        <select name="type" id="toolType" class="form-control" required>
                            <option value="web" <?php echo ($editTool && $editTool['type'] === 'web') ? 'selected' : ''; ?>>web</option>
                            <option value="app" <?php echo ($editTool && $editTool['type'] === 'app') ? 'selected' : ''; ?>>app</option>
                            <option value="offweb" <?php echo ($editTool && $editTool['type'] === 'offweb') ? 'selected' : ''; ?>>offweb</option>
                        </select>
                    </div>

                    <!-- 描述 -->
                    <div class="form-group full">
                        <label class="required">工具描述</label>
                        <textarea name="description" class="form-control" required 
                                  placeholder="请输入工具详细描述"><?php echo $editTool ? htmlspecialchars($editTool['description']) : ''; ?></textarea>
                    </div>

                    <!-- 适配系统 -->
                    <div class="form-group full">
                        <label class="required">适配系统</label>
                        <div class="system-group">
                            <?php
                            $systems = ['windows', 'macos', 'linux', 'ios', 'android'];
                            foreach ($systems as $sys):
                                $checked = $editTool && in_array($sys, $editTool['adapted_systems']) ? 'checked' : '';
                            ?>
                            <label class="system-item">
                                <input type="checkbox" name="adapted_systems[]" value="<?php echo $sys; ?>" <?php echo $checked; ?>>
                                <?php echo $sys; ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- 链接配置区域 -->
                <div class="link-section" id="linkSection">
                    <label>链接配置</label>
                    <div class="link-group">
                        <!-- Web链接 -->
                        <div id="linkWeb" style="display: none;">
                            <input type="url" name="link_web" class="form-control" 
                                   value="<?php echo $editTool ? htmlspecialchars($editTool['links']['web'] ?? '') : ''; ?>"
                                   placeholder="请输入web工具链接（https://）">
                        </div>

                        <!-- Offweb链接 -->
                        <div id="linkOffweb" style="display: none;">
                            <input type="url" name="link_offweb" class="form-control" 
                                   value="<?php echo $editTool ? htmlspecialchars($editTool['links']['offweb'] ?? '') : ''; ?>"
                                   placeholder="请输入offweb工具链接（https://）">
                        </div>

                        <!-- App链接 -->
                        <div id="linkApp" style="display: none;">
                            <?php foreach (['ios', 'android', 'windows', 'macos', 'linux'] as $sys): ?>
                            <div style="margin-top: 8px;">
                                <label style="font-size: 13px; color: var(--gray-500);"><?php echo $sys; ?>链接</label>
                                <input type="url" name="link_<?php echo $sys; ?>" class="form-control" 
                                       value="<?php echo $editTool ? htmlspecialchars($editTool['links'][$sys] ?? '') : ''; ?>"
                                       placeholder="请输入<?php echo $sys; ?>平台链接（https://）">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- 按钮组 -->
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">保存</button>
                    <?php if ($editTool): ?>
                    <button type="submit" name="action" value="cancel" class="btn btn-default">取消编辑</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- 工具列表 -->
        <div class="list-header">工具列表</div>
        <div class="table-container">
            <table class="tool-table">
                <thead>
                    <tr>
                        <th>工具名称</th>
                        <th>分类</th>
                        <th>类型</th>
                        <th>适配系统</th>
                        <th>使用次数</th>
                        <th style="width: 160px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tools)): ?>
                    <tr>
                        <td colspan="6" class="empty-state">暂无工具数据，点击上方"添加新工具"创建</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($tools as $index => $tool): ?>
                    <tr>
                        <td>
                            <div class="tool-name"><?php echo htmlspecialchars($tool['name']); ?></div>
                            <div class="tool-category"><?php echo htmlspecialchars($tool['description']); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($tool['category']); ?></td>
                        <td><?php echo htmlspecialchars($tool['type']); ?></td>
                        <td><?php echo implode(', ', $tool['adapted_systems']); ?></td>
                        <td><?php echo (int)$tool['usage']; ?></td>
                        <td>
                            <div class="action-buttons">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="index" value="<?php echo $index; ?>">
                                    <button type="submit" class="btn btn-default action-btn">编辑</button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除该工具吗？');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="index" value="<?php echo $index; ?>">
                                    <button type="submit" class="btn btn-danger action-btn">删除</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // 类型切换联动链接区域
        const toolType = document.getElementById('toolType');
        const linkSection = document.getElementById('linkSection');
        const linkWeb = document.getElementById('linkWeb');
        const linkOffweb = document.getElementById('linkOffweb');
        const linkApp = document.getElementById('linkApp');

        // 初始化链接区域显示
        function updateLinkArea() {
            const type = toolType.value;
            
            // 隐藏所有链接区域
            linkWeb.style.display = 'none';
            linkOffweb.style.display = 'none';
            linkApp.style.display = 'none';
            
            // 显示对应类型的链接区域
            if (type) {
                linkSection.classList.add('show');
                if (type === 'web') {
                    linkWeb.style.display = 'block';
                } else if (type === 'offweb') {
                    linkOffweb.style.display = 'block';
                } else if (type === 'app') {
                    linkApp.style.display = 'block';
                }
            } else {
                linkSection.classList.remove('show');
            }
        }

        // 页面加载时初始化
        document.addEventListener('DOMContentLoaded', updateLinkArea);
        // 类型变化时更新
        toolType.addEventListener('change', updateLinkArea);

        // 消息提示自动消失
        setTimeout(() => {
            const message = document.querySelector('.message');
            if (message) {
                message.style.opacity = '0';
                message.style.transition = 'opacity 0.5s ease';
                setTimeout(() => message.remove(), 500);
            }
        }, 3000);
    </script>
</body>
</html>
