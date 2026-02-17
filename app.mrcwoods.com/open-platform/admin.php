<?php
session_start();

// ===================== 基础配置 =====================
// JSON存储目录
$jsonDir = __DIR__ . '/api_json/';
// 管理员密码（生产环境建议加密存储）
$adminPwd = 'admin123';
// 默认操作
$action = $_GET['action'] ?? 'login';
// 全局消息
$msg = ['type' => '', 'content' => ''];

// ===================== 目录初始化 =====================
if (!is_dir($jsonDir)) {
    mkdir($jsonDir, 0755, true);
}

// ===================== 权限验证 =====================
// 非登录页面需要验证登录
if ($action !== 'login' && (!isset($_SESSION['admin_login']) || !$_SESSION['admin_login'])) {
    header('Location: admin.php?action=login');
    exit;
}

// ===================== 操作处理 =====================
// 1. 登录处理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    $inputPwd = trim($_POST['password'] ?? '');
    if ($inputPwd === $adminPwd) {
        $_SESSION['admin_login'] = true;
        header('Location: admin.php?action=list');
        exit;
    } else {
        $msg = ['type' => 'error', 'content' => '密码错误，请重试'];
    }
}

// 2. 退出登录
if ($action === 'logout') {
    unset($_SESSION['admin_login']);
    header('Location: admin.php?action=login');
    exit;
}

// 3. 删除接口处理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $apiId = trim($_POST['api_id'] ?? '');
    if (!empty($apiId)) {
        $jsonFile = $jsonDir . $apiId . '.json';
        if (file_exists($jsonFile)) {
            unlink($jsonFile);
            $msg = ['type' => 'success', 'content' => '删除成功'];
        } else {
            $msg = ['type' => 'error', 'content' => '接口文件不存在'];
        }
    } else {
        $msg = ['type' => 'error', 'content' => '缺少接口ID'];
    }
    // 刷新列表页
    header('Location: admin.php?action=list&msg=' . $msg['type'] . '&content=' . urlencode($msg['content']));
    exit;
}

// 4. 保存/编辑接口处理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'])) {
    // 收集基础信息
    $submitData = [
        'name' => trim($_POST['name'] ?? ''),
        'brief' => trim($_POST['brief'] ?? ''),
        'request_desc' => trim($_POST['request_desc'] ?? ''),
        'api_url' => trim($_POST['api_url'] ?? ''),
        'request_method' => trim($_POST['request_method'] ?? 'GET'),
        'return_format' => trim($_POST['return_format'] ?? 'JSON'),
        'request_example' => trim($_POST['request_example'] ?? ''),
        'request_params' => [],
        'return_params' => [],
        'status_code' => [],
        'return_example' => trim($_POST['return_example'] ?? ''),
        'sdk' => []
    ];

    // 验证必填字段
    if (empty($submitData['name']) || empty($submitData['api_url'])) {
        $msg = ['type' => 'error', 'content' => '接口名称和接口地址为必填项'];
    } else {
        // 处理请求参数
        if (isset($_POST['param_name']) && is_array($_POST['param_name'])) {
            foreach ($_POST['param_name'] as $index => $name) {
                if (!empty(trim($name))) {
                    $submitData['request_params'][] = [
                        'name' => trim($name),
                        'type' => trim($_POST['param_type'][$index] ?? ''),
                        'required' => trim($_POST['param_required'][$index] ?? ''),
                        'desc' => trim($_POST['param_desc'][$index] ?? '')
                    ];
                }
            }
        }

        // 处理返回参数
        if (isset($_POST['return_field']) && is_array($_POST['return_field'])) {
            foreach ($_POST['return_field'] as $index => $field) {
                if (!empty(trim($field))) {
                    $submitData['return_params'][] = [
                        'field' => trim($field),
                        'type' => trim($_POST['return_type'][$index] ?? ''),
                        'desc' => trim($_POST['return_desc'][$index] ?? '')
                    ];
                }
            }
        }

        // 处理状态码
        if (isset($_POST['code_num']) && is_array($_POST['code_num'])) {
            foreach ($_POST['code_num'] as $index => $num) {
                if (!empty(trim($num))) {
                    $submitData['status_code'][] = [
                        'code' => trim($num),
                        'desc' => trim($_POST['code_desc'][$index] ?? '')
                    ];
                }
            }
        }

        // 处理SDK（核心：转义处理）
        if (isset($_POST['sdk_lang']) && is_array($_POST['sdk_lang'])) {
            foreach ($_POST['sdk_lang'] as $index => $lang) {
                $code = trim($_POST['sdk_code'][$index] ?? '');
                if (!empty($lang) && !empty($code)) {
                    // SDK代码转义：保留换行/特殊字符，防止JSON解析错误
                    $submitData['sdk'][$lang] = htmlspecialchars($code, ENT_QUOTES | ENT_HTML5);
                }
            }
        }

        // 生成/使用API ID
        $saveId = $action === 'edit' ? trim($_POST['api_id'] ?? '') : uniqid('api_');
        $saveFile = $jsonDir . $saveId . '.json';

        // 存储JSON（正确转义参数）
        $jsonContent = json_encode($submitData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (file_put_contents($saveFile, $jsonContent)) {
            $msg = ['type' => 'success', 'content' => $action === 'edit' ? '更新成功' : '添加成功'];
            header('Location: admin.php?action=list&msg=' . $msg['type'] . '&content=' . urlencode($msg['content']));
            exit;
        } else {
            $msg = ['type' => 'error', 'content' => '保存失败，请检查目录权限'];
        }
    }
}

// 5. 编辑接口：加载现有数据
$apiData = [
    'id' => '',
    'name' => '',
    'brief' => '',
    'request_desc' => '',
    'api_url' => '',
    'request_method' => 'GET',
    'return_format' => 'JSON',
    'request_example' => '',
    'request_params' => [],
    'return_params' => [],
    'status_code' => [],
    'return_example' => '',
    'sdk' => []
];
if ($action === 'edit' && isset($_GET['id']) && !empty($_GET['id'])) {
    $apiId = trim($_GET['id']);
    $jsonFile = $jsonDir . $apiId . '.json';
    if (file_exists($jsonFile)) {
        $content = file_get_contents($jsonFile);
        $loadedData = json_decode($content, true);
        if ($loadedData) {
            $apiData['id'] = $apiId;
            $apiData = array_merge($apiData, $loadedData);
            // SDK代码还原转义（编辑时展示原始代码）
            foreach ($apiData['sdk'] as $lang => $code) {
                $apiData['sdk'][$lang] = htmlspecialchars_decode($code);
            }
        } else {
            $msg = ['type' => 'error', 'content' => '接口数据解析失败'];
            header('Location: admin.php?action=list');
            exit;
        }
    } else {
        $msg = ['type' => 'error', 'content' => '接口文件不存在'];
        header('Location: admin.php?action=list');
        exit;
    }
}

// 6. 获取接口列表（用于列表页）
$apiList = [];
if ($action === 'list') {
    // 接收跳转消息
    if (isset($_GET['msg']) && isset($_GET['content'])) {
        $msg = ['type' => $_GET['msg'], 'content' => urldecode($_GET['content'])];
    }
    // 加载所有接口
    $jsonFiles = glob($jsonDir . '*.json');
    foreach ($jsonFiles as $file) {
        $id = basename($file, '.json');
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        if ($data) {
            $apiList[] = [
                'id' => $id,
                'name' => $data['name'] ?? '未命名接口',
                'brief' => $data['brief'] ?? '无简介',
                'api_url' => $data['api_url'] ?? '无'
            ];
        }
    }
}

// ===================== 页面渲染 =====================
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php 
        $titles = [
            'login' => '后台登录',
            'list' => 'API接口列表',
            'add' => '添加API接口',
            'edit' => '编辑API接口'
        ];
        echo $titles[$action] ?? 'API后台管理';
        ?>
    </title>
    <style>
        /* 全局样式 - 苹果风格 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        body {
            background-color: #f5f5f7;
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.07);
            padding: 30px;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.8);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 1px solid #e6e6e8;
            padding-bottom: 20px;
        }
        h1 {
            color: #1d1d1f;
            font-size: 32px;
            font-weight: 600;
        }
        /* 按钮样式 */
        .btn {
            padding: 8px 20px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            border: none;
        }
        .btn-primary {
            background-color: #0071e3;
            color: #fff;
        }
        .btn-primary:hover {
            background-color: #0077ed;
            box-shadow: 0 4px 15px rgba(0,113,227,0.2);
        }
        .btn-outline {
            background-color: transparent;
            color: #0071e3;
            border: 1px solid #0071e3 !important;
        }
        .btn-outline:hover {
            background-color: rgba(0,113,227,0.05);
        }
        .btn-success {
            background-color: #34c759;
            color: #fff;
        }
        .btn-success:hover {
            background-color: #30d158;
        }
        .btn-danger {
            background-color: #ff3b30;
            color: #fff;
        }
        .btn-danger:hover {
            background-color: #ff2d20;
        }
        /* 消息提示 */
        .msg-tip {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 15px;
            text-align: center;
        }
        .success-tip {
            background-color: rgba(52,199,89,0.05);
            color: #34c759;
        }
        .error-tip {
            background-color: rgba(255,59,48,0.05);
            color: #ff3b30;
        }
        /* 登录页样式 */
        .login-box {
            max-width: 400px;
            margin: 50px auto;
            padding: 40px;
        }
        .login-box h1 {
            text-align: center;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            color: #1d1d1f;
            font-size: 15px;
            margin-bottom: 8px;
            font-weight: 500;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e6e6e8;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background-color: #f9f9fb;
            font-family: inherit;
        }
        .form-control:focus {
            outline: none;
            border-color: #0071e3;
            box-shadow: 0 0 0 4px rgba(0,113,227,0.1);
            background-color: #fff;
        }
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
            line-height: 1.6;
        }
        .code-editor {
            font-family: Menlo, Monaco, Consolas, "Courier New", monospace !important;
            font-size: 14px !important;
        }
        /* 列表页样式 */
        table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        th, td {
            padding: 15px 18px;
            text-align: left;
            border-bottom: 1px solid #e6e6e8;
            font-size: 15px;
        }
        th {
            background-color: #f9f9fb;
            color: #1d1d1f;
            font-weight: 500;
        }
        td {
            color: #333;
        }
        tr:hover td {
            background-color: rgba(0,113,227,0.03);
        }
        .action-btns {
            display: flex;
            gap: 8px;
        }
        .empty-tip {
            text-align: center;
            padding: 60px 20px;
            color: #86868b;
            font-size: 16px;
        }
        .empty-tip i {
            font-size: 60px;
            margin-bottom: 20px;
            display: block;
            color: #e6e6e8;
        }
        /* 表单页样式 */
        .form-section {
            margin-bottom: 35px;
            padding: 20px;
            border-radius: 12px;
            background-color: #fafafa;
            border: 1px solid #f0f0f0;
        }
        .form-section h2 {
            color: #1d1d1f;
            font-size: 22px;
            margin-bottom: 20px;
            padding-left: 12px;
            border-left: 4px solid #0071e3;
            font-weight: 500;
        }
        .dynamic-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
            padding: 10px;
            border-radius: 8px;
            background-color: #fff;
            border: 1px solid #e6e6e8;
        }
        .dynamic-row .form-control {
            flex: 1;
            padding: 8px 12px;
            font-size: 14px;
        }
        .dynamic-row .btn-danger {
            width: 40px;
            height: 40px;
            padding: 0;
            border-radius: 8px;
        }
        .sdk-item {
            margin-bottom: 15px;
            padding: 15px;
            border-radius: 8px;
            background-color: #fff;
            border: 1px solid #e6e6e8;
        }
        .sdk-item select {
            margin-bottom: 10px;
        }
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 30px;
        }
        /* 模态框样式 */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background-color: #fff;
            border-radius: 16px;
            padding: 30px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
        }
        .modal h3 {
            color: #1d1d1f;
            font-size: 20px;
            margin-bottom: 20px;
            text-align: center;
        }
        .modal-btns {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .modal-btns button {
            flex: 1;
            padding: 10px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
        }
        /* 响应式适配 */
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            h1 {
                font-size: 24px;
            }
            .dynamic-row {
                flex-wrap: wrap;
            }
            .dynamic-row .form-control {
                flex: 100%;
                margin-bottom: 8px;
            }
            .form-actions {
                flex-direction: column;
            }
            th, td {
                padding: 12px 10px;
                font-size: 14px;
            }
            .action-btns {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php if (!empty($msg['content'])): ?>
        <div class="msg-tip <?php echo $msg['type'] === 'success' ? 'success-tip' : 'error-tip'; ?>">
            <?php echo htmlspecialchars($msg['content']); ?>
        </div>
    <?php endif; ?>

    <?php if ($action === 'login'): ?>
        <!-- ===================== 登录页面 ===================== -->
        <div class="container login-box">
            <h1>API后台管理 - 登录</h1>
            <form method="POST" action="admin.php?action=login">
                <div class="form-group">
                    <label for="password">管理员密码</label>
                    <input type="password" id="password" name="password" class="form-control" required placeholder="请输入登录密码">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">登录</button>
            </form>
        </div>

    <?php elseif ($action === 'list'): ?>
        <!-- ===================== 列表页面 ===================== -->
        <div class="container">
            <div class="header">
                <h1>API接口管理</h1>
                <div class="btn-group">
                    <a href="admin.php?action=add" class="btn btn-primary">添加新接口</a>
                    <a href="admin.php?action=logout" class="btn btn-outline" onclick="return confirm('确定退出登录？')">退出登录</a>
                </div>
            </div>

            <?php if (empty($apiList)): ?>
                <div class="empty-tip">
                    <i>📄</i>
                    <p>暂无接口数据，点击"添加新接口"创建第一个接口</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>接口名称</th>
                            <th>简介</th>
                            <th>接口地址</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($apiList as $api): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($api['id']); ?></td>
                            <td><?php echo htmlspecialchars($api['name']); ?></td>
                            <td><?php echo htmlspecialchars($api['brief']); ?></td>
                            <td><?php echo htmlspecialchars($api['api_url']); ?></td>
                            <td class="action-btns">
                                <a href="admin.php?action=edit&id=<?php echo htmlspecialchars($api['id']); ?>" class="btn btn-outline">编辑</a>
                                <button class="btn btn-danger" onclick="showDeleteModal('<?php echo htmlspecialchars($api['id']); ?>', '<?php echo htmlspecialchars($api['name']); ?>')">删除</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- 删除确认模态框 -->
        <div class="modal" id="deleteModal">
            <div class="modal-content">
                <h3>确认删除</h3>
                <p>您确定要删除接口 <strong id="apiName"></strong> 吗？此操作不可恢复。</p>
                <form method="POST" action="admin.php?action=delete">
                    <input type="hidden" name="api_id" id="deleteApiId" value="">
                    <div class="modal-btns">
                        <button type="button" class="btn-outline" onclick="hideDeleteModal()">取消</button>
                        <button type="submit" class="btn-danger">确认删除</button>
                    </div>
                </form>
            </div>
        </div>

    <?php elseif (in_array($action, ['add', 'edit'])): ?>
        <!-- ===================== 添加/编辑页面 ===================== -->
        <div class="container">
            <div class="header">
                <h1><?php echo $action === 'add' ? '添加新API接口' : '编辑API接口'; ?></h1>
                <a href="admin.php?action=list" class="btn btn-outline">返回列表</a>
            </div>

            <form method="POST" action="admin.php?action=<?php echo $action; ?>" id="apiForm">
                <!-- 编辑模式隐藏ID -->
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="api_id" value="<?php echo htmlspecialchars($apiData['id']); ?>">
                <?php endif; ?>

                <!-- 基础信息 -->
                <div class="form-section">
                    <h2>基础信息</h2>
                    <div class="form-group">
                        <label for="name">接口名称 <span style="color: #ff3b30">*</span></label>
                        <input type="text" id="name" name="name" class="form-control" required value="<?php echo htmlspecialchars($apiData['name']); ?>" placeholder="如：用户信息查询接口">
                    </div>
                    <div class="form-group">
                        <label for="brief">接口简介</label>
                        <textarea id="brief" name="brief" class="form-control" placeholder="简要描述接口功能"><?php echo htmlspecialchars($apiData['brief']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="request_desc">请求说明</label>
                        <textarea id="request_desc" name="request_desc" class="form-control" placeholder="描述请求注意事项、权限要求等"><?php echo htmlspecialchars($apiData['request_desc']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="api_url">接口地址 <span style="color: #ff3b30">*</span></label>
                        <input type="url" id="api_url" name="api_url" class="form-control" required value="<?php echo htmlspecialchars($apiData['api_url']); ?>" placeholder="如：https://api.example.com/user/info">
                    </div>
                    <div class="form-group" style="display: flex; gap: 20px;">
                        <div style="flex: 1;">
                            <label for="request_method">请求方式</label>
                            <select id="request_method" name="request_method" class="form-control">
                                <option value="GET" <?php echo $apiData['request_method'] === 'GET' ? 'selected' : ''; ?>>GET</option>
                                <option value="POST" <?php echo $apiData['request_method'] === 'POST' ? 'selected' : ''; ?>>POST</option>
                                <option value="PUT" <?php echo $apiData['request_method'] === 'PUT' ? 'selected' : ''; ?>>PUT</option>
                                <option value="DELETE" <?php echo $apiData['request_method'] === 'DELETE' ? 'selected' : ''; ?>>DELETE</option>
                            </select>
                        </div>
                        <div style="flex: 1;">
                            <label for="return_format">返回格式</label>
                            <select id="return_format" name="return_format" class="form-control">
                                <option value="JSON" <?php echo $apiData['return_format'] === 'JSON' ? 'selected' : ''; ?>>JSON</option>
                                <option value="XML" <?php echo $apiData['return_format'] === 'XML' ? 'selected' : ''; ?>>XML</option>
                                <option value="TEXT" <?php echo $apiData['return_format'] === 'TEXT' ? 'selected' : ''; ?>>TEXT</option>
                                <option value="IMAGE" <?php echo $apiData['return_format'] === 'IMAGE' ? 'selected' : ''; ?>>IMAGE</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 请求示例 -->
                <div class="form-section">
                    <h2>请求示例</h2>
                    <div class="form-group">
                        <label for="request_example">请求示例代码</label>
                        <textarea id="request_example" name="request_example" class="form-control code-editor" placeholder="如curl命令、HTTP请求示例等"><?php echo htmlspecialchars($apiData['request_example']); ?></textarea>
                    </div>
                </div>

                <!-- 请求参数 -->
                <div class="form-section">
                    <h2>请求参数</h2>
                    <div id="paramRows">
                        <?php if (!empty($apiData['request_params'])): ?>
                            <?php foreach ($apiData['request_params'] as $param): ?>
                                <div class="dynamic-row">
                                    <input type="text" name="param_name[]" class="form-control" placeholder="参数名" value="<?php echo htmlspecialchars($param['name']); ?>">
                                    <input type="text" name="param_type[]" class="form-control" placeholder="类型" value="<?php echo htmlspecialchars($param['type']); ?>">
                                    <input type="text" name="param_required[]" class="form-control" placeholder="必填（是/否）" value="<?php echo htmlspecialchars($param['required']); ?>">
                                    <input type="text" name="param_desc[]" class="form-control" placeholder="描述" value="<?php echo htmlspecialchars($param['desc']); ?>">
                                    <button type="button" class="btn btn-danger" onclick="removeRow(this)">×</button>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="dynamic-row">
                                <input type="text" name="param_name[]" class="form-control" placeholder="参数名">
                                <input type="text" name="param_type[]" class="form-control" placeholder="类型">
                                <input type="text" name="param_required[]" class="form-control" placeholder="必填（是/否）">
                                <input type="text" name="param_desc[]" class="form-control" placeholder="描述">
                                <button type="button" class="btn btn-danger" onclick="removeRow(this)">×</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-success" onclick="addParamRow()">添加参数行</button>
                </div>

                <!-- 返回参数 -->
                <div class="form-section">
                    <h2>返回参数</h2>
                    <div id="returnRows">
                        <?php if (!empty($apiData['return_params'])): ?>
                            <?php foreach ($apiData['return_params'] as $param): ?>
                                <div class="dynamic-row">
                                    <input type="text" name="return_field[]" class="form-control" placeholder="参数字段" value="<?php echo htmlspecialchars($param['field']); ?>">
                                    <input type="text" name="return_type[]" class="form-control" placeholder="类型" value="<?php echo htmlspecialchars($param['type']); ?>">
                                    <input type="text" name="return_desc[]" class="form-control" placeholder="说明" value="<?php echo htmlspecialchars($param['desc']); ?>">
                                    <button type="button" class="btn btn-danger" onclick="removeRow(this)">×</button>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="dynamic-row">
                                <input type="text" name="return_field[]" class="form-control" placeholder="参数字段">
                                <input type="text" name="return_type[]" class="form-control" placeholder="类型">
                                <input type="text" name="return_desc[]" class="form-control" placeholder="说明">
                                <button type="button" class="btn btn-danger" onclick="removeRow(this)">×</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-success" onclick="addReturnRow()">添加参数行</button>
                </div>

                <!-- 状态码说明 -->
                <div class="form-section">
                    <h2>状态码说明</h2>
                    <div id="codeRows">
                        <?php if (!empty($apiData['status_code'])): ?>
                            <?php foreach ($apiData['status_code'] as $code): ?>
                                <div class="dynamic-row">
                                    <input type="text" name="code_num[]" class="form-control" placeholder="状态码" value="<?php echo htmlspecialchars($code['code']); ?>">
                                    <input type="text" name="code_desc[]" class="form-control" placeholder="说明" value="<?php echo htmlspecialchars($code['desc']); ?>">
                                    <button type="button" class="btn btn-danger" onclick="removeRow(this)">×</button>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="dynamic-row">
                                <input type="text" name="code_num[]" class="form-control" placeholder="状态码">
                                <input type="text" name="code_desc[]" class="form-control" placeholder="说明">
                                <button type="button" class="btn btn-danger" onclick="removeRow(this)">×</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-success" onclick="addCodeRow()">添加状态码行</button>
                </div>

                <!-- 返回示例 -->
                <div class="form-section">
                    <h2>返回示例</h2>
                    <div class="form-group">
                        <label for="return_example">返回示例代码</label>
                        <textarea id="return_example" name="return_example" class="form-control code-editor" placeholder="JSON/XML等返回示例"><?php echo htmlspecialchars($apiData['return_example']); ?></textarea>
                    </div>
                </div>

                <!-- 多语言SDK（核心转义） -->
                <div class="form-section">
                    <h2>SDK</h2>
                    <div id="sdkItems">
                        <?php if (!empty($apiData['sdk'])): ?>
                            <?php foreach ($apiData['sdk'] as $lang => $code): ?>
                                <div class="sdk-item">
                                    <select name="sdk_lang[]" class="form-control">
                                        <option value="PHP" <?php echo $lang === 'PHP' ? 'selected' : ''; ?>>PHP</option>
                                        <option value="Java" <?php echo $lang === 'Java' ? 'selected' : ''; ?>>Java</option>
                                        <option value="JavaScript" <?php echo $lang === 'JavaScript' ? 'selected' : ''; ?>>JavaScript</option>
                                        <option value="Python" <?php echo $lang === 'Python' ? 'selected' : ''; ?>>Python</option>
                                        <option value="Go" <?php echo $lang === 'Go' ? 'selected' : ''; ?>>Go</option>
                                        <option value="Shell" <?php echo $lang === 'Shell' ? 'selected' : ''; ?>>Shell</option>
                                        <option value="NodeJS" <?php echo $lang === 'NodeJS' ? 'selected' : ''; ?>>NodeJS</option>
                                        <option value="C++" <?php echo $lang === 'C++' ? 'selected' : ''; ?>>C++</option>
                                        <option value="C" <?php echo $lang === 'C' ? 'selected' : ''; ?>>C</option>
                                        <option value="Rust" <?php echo $lang === 'Rust' ? 'selected' : ''; ?>>Rust</option>
                                        <option value="Other" <?php echo $lang === 'Other' ? 'selected' : ''; ?>>其他</option>
                                    </select>
                                    <textarea name="sdk_code[]" class="form-control code-editor" placeholder="SDK代码内容"><?php echo htmlspecialchars($code); ?></textarea>
                                    <button type="button" class="btn btn-danger" style="margin-top: 10px;" onclick="removeSdkItem(this)">删除该语言SDK</button>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="sdk-item">
                                <select name="sdk_lang[]" class="form-control">
                                    <option value="PHP">PHP</option>
                                    <option value="Java">Java</option>
                                    <option value="JavaScript">JavaScript</option>
                                    <option value="Python">Python</option>
                                    <option value="Go">Go</option>
                                    <option value="Shell">Shell</option>
                                    <option value="NodeJS">NodeJS</option>
                                    <option value="C++">C++</option>
                                    <option value="C">C</option>
                                    <option value="Rust">Rust</option>
                                    <option value="Other">其他</option>
                                </select>
                                <textarea name="sdk_code[]" class="form-control code-editor" placeholder="SDK代码内容"></textarea>
                                <button type="button" class="btn btn-danger" style="margin-top: 10px;" onclick="removeSdkItem(this)">删除该语言SDK</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-success" onclick="addSdkItem()">添加SDK语言</button>
                </div>

                <!-- 表单操作 -->
                <div class="form-actions">
                    <a href="admin.php?action=list" class="btn btn-outline">取消</a>
                    <button type="submit" class="btn btn-primary"><?php echo $action === 'edit' ? '更新接口' : '保存接口'; ?></button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <script>
        // ===================== 通用交互函数 =====================
        // 显示删除模态框
        function showDeleteModal(apiId, apiName) {
            document.getElementById('deleteApiId').value = apiId;
            document.getElementById('apiName').textContent = apiName;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        // 隐藏删除模态框
        function hideDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // 点击模态框外部关闭
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (modal && event.target === modal) {
                hideDeleteModal();
            }
        }

        // 添加请求参数行
        function addParamRow() {
            const container = document.getElementById('paramRows');
            const row = document.createElement('div');
            row.className = 'dynamic-row';
            row.innerHTML = `
                <input type="text" name="param_name[]" class="form-control" placeholder="参数名">
                <input type="text" name="param_type[]" class="form-control" placeholder="类型">
                <input type="text" name="param_required[]" class="form-control" placeholder="必填（是/否）">
                <input type="text" name="param_desc[]" class="form-control" placeholder="描述">
                <button type="button" class="btn btn-danger" onclick="removeRow(this)">×</button>
            `;
            container.appendChild(row);
        }

        // 添加返回参数行
        function addReturnRow() {
            const container = document.getElementById('returnRows');
            const row = document.createElement('div');
            row.className = 'dynamic-row';
            row.innerHTML = `
                <input type="text" name="return_field[]" class="form-control" placeholder="参数字段">
                <input type="text" name="return_type[]" class="form-control" placeholder="类型">
                <input type="text" name="return_desc[]" class="form-control" placeholder="说明">
                <button type="button" class="btn btn-danger" onclick="removeRow(this)">×</button>
            `;
            container.appendChild(row);
        }

        // 添加状态码行
        function addCodeRow() {
            const container = document.getElementById('codeRows');
            const row = document.createElement('div');
            row.className = 'dynamic-row';
            row.innerHTML = `
                <input type="text" name="code_num[]" class="form-control" placeholder="状态码">
                <input type="text" name="code_desc[]" class="form-control" placeholder="说明">
                <button type="button" class="btn btn-danger" onclick="removeRow(this)">×</button>
            `;
            container.appendChild(row);
        }

        // 添加SDK语言项
        function addSdkItem() {
            const container = document.getElementById('sdkItems');
            const item = document.createElement('div');
            item.className = 'sdk-item';
            item.innerHTML = `
                <select name="sdk_lang[]" class="form-control">
                    <option value="PHP">PHP</option>
                    <option value="Java">Java</option>
                    <option value="JavaScript">JavaScript</option>
                    <option value="Python">Python</option>
                    <option value="Go">Go</option>
                    <option value="Shell">Shell</option>
                    <option value="NodeJS">NodeJS</option>
                    <option value="Rust">Rust</option>
                    <option value="C++">C++</option>
                    <option value="C">C</option>
                    <option value="Other">其他</option>
                </select>
                <textarea name="sdk_code[]" class="form-control code-editor" placeholder="SDK代码内容"></textarea>
                <button type="button" class="btn btn-danger" style="margin-top: 10px;" onclick="removeSdkItem(this)">删除该语言SDK</button>
            `;
            container.appendChild(item);
        }

        // 删除动态行
        function removeRow(el) {
            const row = el.parentElement;
            if (row.parentElement.children.length > 1) {
                row.remove();
            } else {
                row.querySelectorAll('input').forEach(input => input.value = '');
            }
        }

        // 删除SDK项
        function removeSdkItem(el) {
            const item = el.parentElement;
            if (item.parentElement.children.length > 1) {
                item.remove();
            } else {
                item.querySelector('textarea').value = '';
            }
        }

        // 表单提交验证
        const apiForm = document.getElementById('apiForm');
        if (apiForm) {
            apiForm.addEventListener('submit', function(e) {
                const name = document.getElementById('name').value.trim();
                const apiUrl = document.getElementById('api_url').value.trim();
                
                if (!name) {
                    alert('请填写接口名称');
                    e.preventDefault();
                    return false;
                }
                
                if (!apiUrl) {
                    alert('请填写接口地址');
                    e.preventDefault();
                    return false;
                }
                
                if (!confirm('确定要' + (<?php echo $action === 'edit' ? '更新' : '保存'; ?>) + '该接口吗？')) {
                    e.preventDefault();
                    return false;
                }
            });
        }
    </script>
</body>
</html>