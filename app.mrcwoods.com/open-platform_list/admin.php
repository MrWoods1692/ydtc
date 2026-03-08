<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API 管理后台 - macOS 风格</title>
    <style>
        /* 全局样式 - macOS 风格 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        ::-webkit-scrollbar {
            display: none;
        }
        * {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        body {
            background-color: #f5f5f7;
            background-image: 
                linear-gradient(rgba(0, 0, 0, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 0, 0, 0.05) 1px, transparent 1px);
            background-size: 20px 20px;
            color: #1d1d1f;
            min-height: 100vh;
            padding-top: 70px;
        }

        /* 顶部导航 */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background-color: #ffffff;
            border-bottom: 1px solid #e6e6e6;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            z-index: 100;
        }

        .navbar-title {
            font-size: 20px;
            font-weight: 500;
            color: #1d1d1f;
        }

        .menu-btn {
            width: 40px;
            height: 30px;
            display: flex;
            flex-direction: row;
            justify-content: center;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            background: transparent;
            border: none;
        }

        .menu-btn span {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .menu-btn span:nth-child(1) {
            background-color: #ff5f57;
        }
        .menu-btn span:nth-child(2) {
            background-color: #ffbd2e;
        }
        .menu-btn span:nth-child(3) {
            background-color: #28ca42;
        }

        /* 容器 */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* 操作栏 */
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 16px 20px;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-primary {
            background-color: #0071e3;
            color: #ffffff;
        }

        .btn-primary:hover {
            background-color: #0077ed;
        }

        .btn-danger {
            background-color: #ff3b30;
            color: #ffffff;
        }

        .btn-danger:hover {
            background-color: #ff453a;
        }

        .btn-secondary {
            background-color: #f5f5f7;
            color: #1d1d1f;
        }

        .btn-secondary:hover {
            background-color: #e6e6e6;
        }

        /* API 列表表格 */
        .api-table {
            width: 100%;
            border-collapse: collapse;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .api-table th, .api-table td {
            padding: 14px 20px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        .api-table th {
            background-color: #f5f5f7;
            font-weight: 500;
        }

        .api-table tr:last-child td {
            border-bottom: none;
        }

        .api-table tr:hover {
            background-color: #f9f9f9;
        }

        .method-tag {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .method-get {
            background-color: #e6f4ea;
            color: #137333;
        }

        .method-post {
            background-color: #f0e7ff;
            color: #6020e0;
        }

        .method-put {
            background-color: #fff8e6;
            color: #d97706;
        }

        .method-delete {
            background-color: #feebea;
            color: #b91c1c;
        }

        /* 模态框 */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 20px;
            backdrop-filter: blur(8px);
        }

        .modal-content {
            background-color: #ffffff;
            border-radius: 18px;
            width: 100%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e6e6e6;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
        }

        .close-modal {
            background: transparent;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #86868b;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .close-modal:hover {
            background-color: #f5f5f7;
            color: #1d1d1f;
        }

        /* 表单样式 */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #1d1d1f;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e6e6e6;
            border-radius: 8px;
            font-size: 14px;
            background-color: #ffffff;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: #0071e3;
        }

        textarea.form-control {
            min-height: 80px;
            resize: vertical;
        }

        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }

        /* 参数表格 */
        .params-table-container {
            margin: 20px 0;
        }

        .params-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .params-table th, .params-table td {
            padding: 10px;
            border: 1px solid #e6e6e6;
            text-align: left;
        }

        .params-table th {
            background-color: #f5f5f7;
            font-weight: 500;
        }

        .param-input {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #e6e6e6;
            border-radius: 4px;
            font-size: 14px;
        }

        .param-input:focus {
            outline: none;
            border-color: #0071e3;
        }

        .tab-container {
            margin-bottom: 20px;
        }

        .tabs {
            display: flex;
            border-bottom: 1px solid #e6e6e6;
            margin-bottom: 20px;
        }

        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            font-weight: 500;
            color: #86868b;
        }

        .tab.active {
            border-bottom-color: #0071e3;
            color: #0071e3;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* 提示消息 */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background-color: #e6f4ea;
            color: #137333;
        }

        .alert-error {
            background-color: #feebea;
            color: #b91c1c;
        }

        .hidden {
            display: none;
        }

        /* 响应式 */
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 15px;
            }
            
            .api-table th, .api-table td {
                padding: 10px 12px;
            }
            
            .action-bar {
                flex-direction: column;
                gap: 10px;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <!-- 顶部导航 -->
    <div class="navbar">
        <div style="display: flex; align-items: center; gap: 15px;">
            <button class="menu-btn">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <div class="navbar-title">API 管理后台</div>
        </div>
        <div>
            <button class="btn btn-secondary" onclick="window.location.href='index.html'">查看展示页面</button>
        </div>
    </div>

    <!-- 主容器 -->
    <div class="container">
        <!-- 提示消息 -->
        <div id="alertMessage" class="alert hidden"></div>

        <!-- 操作栏 -->
        <div class="action-bar">
            <h2>API 列表管理</h2>
            <button class="btn btn-primary" id="addApiBtn">新增 API</button>
        </div>

        <!-- API 列表表格 -->
        <table class="api-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>名称</th>
                    <th>请求方法</th>
                    <th>描述</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody id="apiTableBody">
                <!-- PHP 动态生成 -->
                <?php
                // 读取 API 列表
                $apisListFile = 'apis.json';
                if (!file_exists($apisListFile)) {
                    file_put_contents($apisListFile, json_encode([], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                }
                
                $apisList = json_decode(file_get_contents($apisListFile), true);
                
                if (!empty($apisList)) {
                    foreach ($apisList as $api) {
                        $apiId = htmlspecialchars($api['id'] ?? '');
                        $apiName = htmlspecialchars($api['name'] ?? '未命名');
                        $apiMethod = strtoupper(htmlspecialchars($api['method'] ?? 'GET'));
                        $apiDesc = htmlspecialchars($api['description'] ?? '无描述');
                        $methodClass = 'method-' . strtolower($apiMethod);
                        
                        echo "
                        <tr data-api-id=\"{$apiId}\">
                            <td>{$apiId}</td>
                            <td>{$apiName}</td>
                            <td><span class=\"method-tag {$methodClass}\">{$apiMethod}</span></td>
                            <td>{$apiDesc}</td>
                            <td>
                                <button class=\"btn btn-secondary edit-api-btn\" data-api-id=\"{$apiId}\">编辑</button>
                                <button class=\"btn btn-danger delete-api-btn\" data-api-id=\"{$apiId}\">删除</button>
                            </td>
                        </tr>
                        ";
                    }
                } else {
                    echo "
                    <tr>
                        <td colspan=\"5\" style=\"text-align: center; color: #86868b;\">暂无 API 数据，请点击新增按钮添加</td>
                    </tr>
                    ";
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- API 编辑/新增模态框 -->
    <div class="modal" id="apiModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">新增 API</h3>
                <button class="close-modal" id="closeModalBtn">&times;</button>
            </div>
            
            <form id="apiForm">
                <input type="hidden" id="apiIdInput" name="apiId">
                
                <!-- 基础信息 -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="apiName">API 名称 <span style="color: red;">*</span></label>
                        <input type="text" class="form-control" id="apiName" name="apiName" required placeholder="如：用户列表接口">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="apiMethod">请求方法 <span style="color: red;">*</span></label>
                        <select class="form-control" id="apiMethod" name="apiMethod" required>
                            <option value="GET">GET</option>
                            <option value="POST">POST</option>
                            <option value="PUT">PUT</option>
                            <option value="DELETE">DELETE</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="apiId">API ID <span style="color: red;">*</span></label>
                        <input type="text" class="form-control" id="apiId" name="apiId" required placeholder="如：user_list（英文+下划线）">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="responseFormat">返回格式</label>
                        <input type="text" class="form-control" id="responseFormat" name="responseFormat" placeholder="如：JSON">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="apiUrl">请求地址</label>
                    <input type="text" class="form-control" id="apiUrl" name="apiUrl" placeholder="如：/api/v1/users">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="apiDescription">API 描述</label>
                    <textarea class="form-control" id="apiDescription" name="apiDescription" placeholder="请输入 API 的详细描述"></textarea>
                </div>
                
                <!-- 标签页 -->
                <div class="tab-container">
                    <div class="tabs">
                        <div class="tab active" data-tab="requestParams">请求参数</div>
                        <div class="tab" data-tab="responseParams">返回参数</div>
                        <div class="tab" data-tab="statusCodes">状态码</div>
                        <div class="tab" data-tab="examples">示例代码</div>
                    </div>
                    
                    <!-- 请求参数 -->
                    <div class="tab-content active" id="requestParams">
                        <div class="params-table-container">
                            <table class="params-table" id="requestParamsTable">
                                <thead>
                                    <tr>
                                        <th>参数名 <span style="color: red;">*</span></th>
                                        <th>类型</th>
                                        <th>必填</th>
                                        <th>描述</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="param-row">
                                        <td><input type="text" class="param-input param-name" name="paramName[]" placeholder="参数名"></td>
                                        <td><input type="text" class="param-input param-type" name="paramType[]" placeholder="如：string"></td>
                                        <td>
                                            <select class="param-input param-required" name="paramRequired[]">
                                                <option value="false">否</option>
                                                <option value="true">是</option>
                                            </select>
                                        </td>
                                        <td><input type="text" class="param-input param-desc" name="paramDesc[]" placeholder="参数描述"></td>
                                        <td><button type="button" class="btn btn-danger remove-param-btn">删除</button></td>
                                    </tr>
                                </tbody>
                            </table>
                            <button type="button" class="btn btn-secondary" id="addRequestParamBtn">添加参数</button>
                        </div>
                    </div>
                    
                    <!-- 返回参数 -->
                    <div class="tab-content" id="responseParams">
                        <div class="params-table-container">
                            <table class="params-table" id="responseParamsTable">
                                <thead>
                                    <tr>
                                        <th>参数名 <span style="color: red;">*</span></th>
                                        <th>类型</th>
                                        <th>描述</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="response-param-row">
                                        <td><input type="text" class="param-input resp-param-name" name="respParamName[]" placeholder="参数名"></td>
                                        <td><input type="text" class="param-input resp-param-type" name="respParamType[]" placeholder="如：string"></td>
                                        <td><input type="text" class="param-input resp-param-desc" name="respParamDesc[]" placeholder="参数描述"></td>
                                        <td><button type="button" class="btn btn-danger remove-resp-param-btn">删除</button></td>
                                    </tr>
                                </tbody>
                            </table>
                            <button type="button" class="btn btn-secondary" id="addResponseParamBtn">添加参数</button>
                        </div>
                    </div>
                    
                    <!-- 状态码 -->
                    <div class="tab-content" id="statusCodes">
                        <div class="params-table-container">
                            <table class="params-table" id="statusCodesTable">
                                <thead>
                                    <tr>
                                        <th>状态码 <span style="color: red;">*</span></th>
                                        <th>描述</th>
                                        <th>说明</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="status-code-row">
                                        <td><input type="text" class="param-input status-code" name="statusCode[]" placeholder="如：200"></td>
                                        <td><input type="text" class="param-input status-msg" name="statusMsg[]" placeholder="如：请求成功"></td>
                                        <td><input type="text" class="param-input status-desc" name="statusDesc[]" placeholder="详细说明"></td>
                                        <td><button type="button" class="btn btn-danger remove-status-code-btn">删除</button></td>
                                    </tr>
                                </tbody>
                            </table>
                            <button type="button" class="btn btn-secondary" id="addStatusCodeBtn">添加状态码</button>
                        </div>
                    </div>
                    
                    <!-- 示例代码 -->
                    <div class="tab-content" id="examples">
                        <div class="form-group">
                            <label class="form-label" for="requestExample">请求示例（JSON格式）</label>
                            <textarea class="form-control" id="requestExample" name="requestExample" placeholder="{\n  &quot;url&quot;: &quot;/api/v1/users?page=1&quot;,\n  &quot;headers&quot;: {\n    &quot;Authorization&quot;: &quot;Bearer token&quot;\n  }\n}"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="responseExample">响应示例（JSON格式）</label>
                            <textarea class="form-control" id="responseExample" name="responseExample" placeholder="{\n  &quot;code&quot;: 200,\n  &quot;msg&quot;: &quot;success&quot;,\n  &quot;data&quot;: {\n    &quot;list&quot;: []\n  }\n}"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="exampleLang">示例代码语言</label>
                            <select class="form-control" id="exampleLang" name="exampleLang">
                                <option value="json">JSON</option>
                                <option value="bash">Bash</option>
                                <option value="php">PHP</option>
                                <option value="javascript">JavaScript</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" id="cancelBtn">取消</button>
                    <button type="submit" class="btn btn-primary">保存 API</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // 全局变量
        let currentApiId = '';
        const apiModal = document.getElementById('apiModal');
        const alertMessage = document.getElementById('alertMessage');
        
        // 初始化
        document.addEventListener('DOMContentLoaded', function() {
            // 标签切换
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // 移除所有激活状态
                    tabs.forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                    
                    // 激活当前标签
                    this.classList.add('active');
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });
            
            // 添加请求参数行
            document.getElementById('addRequestParamBtn').addEventListener('click', function() {
                const table = document.getElementById('requestParamsTable').querySelector('tbody');
                const newRow = document.createElement('tr');
                newRow.className = 'param-row';
                newRow.innerHTML = `
                    <td><input type="text" class="param-input param-name" name="paramName[]" placeholder="参数名"></td>
                    <td><input type="text" class="param-input param-type" name="paramType[]" placeholder="如：string"></td>
                    <td>
                        <select class="param-input param-required" name="paramRequired[]">
                            <option value="false">否</option>
                            <option value="true">是</option>
                        </select>
                    </td>
                    <td><input type="text" class="param-input param-desc" name="paramDesc[]" placeholder="参数描述"></td>
                    <td><button type="button" class="btn btn-danger remove-param-btn">删除</button></td>
                `;
                table.appendChild(newRow);
                
                // 绑定删除事件
                newRow.querySelector('.remove-param-btn').addEventListener('click', function() {
                    newRow.remove();
                });
            });
            
            // 添加返回参数行
            document.getElementById('addResponseParamBtn').addEventListener('click', function() {
                const table = document.getElementById('responseParamsTable').querySelector('tbody');
                const newRow = document.createElement('tr');
                newRow.className = 'response-param-row';
                newRow.innerHTML = `
                    <td><input type="text" class="param-input resp-param-name" name="respParamName[]" placeholder="参数名"></td>
                    <td><input type="text" class="param-input resp-param-type" name="respParamType[]" placeholder="如：string"></td>
                    <td><input type="text" class="param-input resp-param-desc" name="respParamDesc[]" placeholder="参数描述"></td>
                    <td><button type="button" class="btn btn-danger remove-resp-param-btn">删除</button></td>
                `;
                table.appendChild(newRow);
                
                // 绑定删除事件
                newRow.querySelector('.remove-resp-param-btn').addEventListener('click', function() {
                    newRow.remove();
                });
            });
            
            // 添加状态码行
            document.getElementById('addStatusCodeBtn').addEventListener('click', function() {
                const table = document.getElementById('statusCodesTable').querySelector('tbody');
                const newRow = document.createElement('tr');
                newRow.className = 'status-code-row';
                newRow.innerHTML = `
                    <td><input type="text" class="param-input status-code" name="statusCode[]" placeholder="如：200"></td>
                    <td><input type="text" class="param-input status-msg" name="statusMsg[]" placeholder="如：请求成功"></td>
                    <td><input type="text" class="param-input status-desc" name="statusDesc[]" placeholder="详细说明"></td>
                    <td><button type="button" class="btn btn-danger remove-status-code-btn">删除</button></td>
                `;
                table.appendChild(newRow);
                
                // 绑定删除事件
                newRow.querySelector('.remove-status-code-btn').addEventListener('click', function() {
                    newRow.remove();
                });
            });
            
            // 删除参数行事件委托
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-param-btn')) {
                    e.target.closest('.param-row').remove();
                }
                if (e.target.classList.contains('remove-resp-param-btn')) {
                    e.target.closest('.response-param-row').remove();
                }
                if (e.target.classList.contains('remove-status-code-btn')) {
                    e.target.closest('.status-code-row').remove();
                }
            });
            
            // 新增 API 按钮
            document.getElementById('addApiBtn').addEventListener('click', function() {
                openModal('新增 API');
                resetForm();
                currentApiId = '';
            });
            
            // 编辑 API 按钮
            document.querySelectorAll('.edit-api-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const apiId = this.getAttribute('data-api-id');
                    currentApiId = apiId;
                    openModal('编辑 API');
                    loadApiData(apiId);
                });
            });
            
            // 删除 API 按钮
            document.querySelectorAll('.delete-api-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const apiId = this.getAttribute('data-api-id');
                    const apiName = this.closest('tr').querySelector('td:nth-child(2)').textContent;
                    
                    if (confirm(`确定要删除 API「${apiName}」吗？此操作不可恢复！`)) {
                        deleteApi(apiId);
                    }
                });
            });
            
            // 关闭模态框
            document.getElementById('closeModalBtn').addEventListener('click', closeModal);
            document.getElementById('cancelBtn').addEventListener('click', closeModal);
            
            // 点击模态框外部关闭
            apiModal.addEventListener('click', function(e) {
                if (e.target === apiModal) {
                    closeModal();
                }
            });
            
            // 表单提交
            document.getElementById('apiForm').addEventListener('submit', function(e) {
                e.preventDefault();
                saveApi();
            });
        });
        
        // 打开模态框
        function openModal(title) {
            document.getElementById('modalTitle').textContent = title;
            apiModal.style.display = 'flex';
        }
        
        // 关闭模态框
        function closeModal() {
            apiModal.style.display = 'none';
        }
        
        // 重置表单
        function resetForm() {
            document.getElementById('apiForm').reset();
            
            // 清空参数表格（保留一行）
            clearParamTable('requestParamsTable', '.param-row', 1);
            clearParamTable('responseParamsTable', '.response-param-row', 1);
            clearParamTable('statusCodesTable', '.status-code-row', 1);
        }
        
        // 清空参数表格
        function clearParamTable(tableId, rowClass, keepRows = 0) {
            const table = document.getElementById(tableId).querySelector('tbody');
            const rows = table.querySelectorAll(rowClass);
            
            // 保留指定行数
            Array.from(rows).slice(keepRows).forEach(row => row.remove());
            
            // 清空保留行的输入框
            Array.from(rows).slice(0, keepRows).forEach(row => {
                row.querySelectorAll('input, select').forEach(input => {
                    input.value = '';
                    if (input.tagName === 'SELECT') input.selectedIndex = 0;
                });
            });
        }
        
        // 加载 API 数据
        function loadApiData(apiId) {
            // 读取基础信息
            fetch(`load_api.php?apiId=${apiId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const api = data.data;
                        
                        // 填充基础信息
                        document.getElementById('apiIdInput').value = apiId;
                        document.getElementById('apiId').value = apiId;
                        document.getElementById('apiName').value = api.name || '';
                        document.getElementById('apiMethod').value = api.method || 'GET';
                        document.getElementById('apiUrl').value = api.url || '';
                        document.getElementById('responseFormat').value = api.responseFormat || '';
                        document.getElementById('apiDescription').value = api.description || '';
                        
                        // 填充请求参数
                        populateRequestParams(api.params || {});
                        
                        // 填充返回参数
                        populateResponseParams(api.responseParams || {});
                        
                        // 填充状态码
                        populateStatusCodes(api.statusCodes || {});
                        
                        // 填充示例代码
                        document.getElementById('requestExample').value = api.example ? JSON.stringify(api.example, null, 2) : '';
                        document.getElementById('responseExample').value = api.response ? JSON.stringify(api.response, null, 2) : '';
                        document.getElementById('exampleLang').value = api.exampleLang || 'json';
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    showAlert('加载 API 数据失败：' + error.message, 'error');
                });
        }
        
        // 填充请求参数
        function populateRequestParams(params) {
            const table = document.getElementById('requestParamsTable').querySelector('tbody');
            clearParamTable('requestParamsTable', '.param-row', 0);
            
            if (Object.keys(params).length === 0) {
                // 添加空行
                const newRow = document.createElement('tr');
                newRow.className = 'param-row';
                newRow.innerHTML = `
                    <td><input type="text" class="param-input param-name" name="paramName[]" placeholder="参数名"></td>
                    <td><input type="text" class="param-input param-type" name="paramType[]" placeholder="如：string"></td>
                    <td>
                        <select class="param-input param-required" name="paramRequired[]">
                            <option value="false">否</option>
                            <option value="true">是</option>
                        </select>
                    </td>
                    <td><input type="text" class="param-input param-desc" name="paramDesc[]" placeholder="参数描述"></td>
                    <td><button type="button" class="btn btn-danger remove-param-btn">删除</button></td>
                `;
                table.appendChild(newRow);
                return;
            }
            
            // 填充参数
            Object.entries(params).forEach(([name, param]) => {
                const newRow = document.createElement('tr');
                newRow.className = 'param-row';
                newRow.innerHTML = `
                    <td><input type="text" class="param-input param-name" name="paramName[]" value="${name}" placeholder="参数名"></td>
                    <td><input type="text" class="param-input param-type" name="paramType[]" value="${param.type || ''}" placeholder="如：string"></td>
                    <td>
                        <select class="param-input param-required" name="paramRequired[]">
                            <option value="false" ${!param.required ? 'selected' : ''}>否</option>
                            <option value="true" ${param.required ? 'selected' : ''}>是</option>
                        </select>
                    </td>
                    <td><input type="text" class="param-input param-desc" name="paramDesc[]" value="${param.desc || ''}" placeholder="参数描述"></td>
                    <td><button type="button" class="btn btn-danger remove-param-btn">删除</button></td>
                `;
                table.appendChild(newRow);
            });
        }
        
        // 填充返回参数
        function populateResponseParams(params) {
            const table = document.getElementById('responseParamsTable').querySelector('tbody');
            clearParamTable('responseParamsTable', '.response-param-row', 0);
            
            if (Object.keys(params).length === 0) {
                // 添加空行
                const newRow = document.createElement('tr');
                newRow.className = 'response-param-row';
                newRow.innerHTML = `
                    <td><input type="text" class="param-input resp-param-name" name="respParamName[]" placeholder="参数名"></td>
                    <td><input type="text" class="param-input resp-param-type" name="respParamType[]" placeholder="如：string"></td>
                    <td><input type="text" class="param-input resp-param-desc" name="respParamDesc[]" placeholder="参数描述"></td>
                    <td><button type="button" class="btn btn-danger remove-resp-param-btn">删除</button></td>
                `;
                table.appendChild(newRow);
                return;
            }
            
            // 填充参数
            Object.entries(params).forEach(([name, param]) => {
                const newRow = document.createElement('tr');
                newRow.className = 'response-param-row';
                newRow.innerHTML = `
                    <td><input type="text" class="param-input resp-param-name" name="respParamName[]" value="${name}" placeholder="参数名"></td>
                    <td><input type="text" class="param-input resp-param-type" name="respParamType[]" value="${param.type || ''}" placeholder="如：string"></td>
                    <td><input type="text" class="param-input resp-param-desc" name="respParamDesc[]" value="${param.desc || ''}" placeholder="参数描述"></td>
                    <td><button type="button" class="btn btn-danger remove-resp-param-btn">删除</button></td>
                `;
                table.appendChild(newRow);
            });
        }
        
        // 填充状态码
        function populateStatusCodes(statusCodes) {
            const table = document.getElementById('statusCodesTable').querySelector('tbody');
            clearParamTable('statusCodesTable', '.status-code-row', 0);
            
            if (Object.keys(statusCodes).length === 0) {
                // 添加空行
                const newRow = document.createElement('tr');
                newRow.className = 'status-code-row';
                newRow.innerHTML = `
                    <td><input type="text" class="param-input status-code" name="statusCode[]" placeholder="如：200"></td>
                    <td><input type="text" class="param-input status-msg" name="statusMsg[]" placeholder="如：请求成功"></td>
                    <td><input type="text" class="param-input status-desc" name="statusDesc[]" placeholder="详细说明"></td>
                    <td><button type="button" class="btn btn-danger remove-status-code-btn">删除</button></td>
                `;
                table.appendChild(newRow);
                return;
            }
            
            // 填充状态码
            Object.entries(statusCodes).forEach(([code, status]) => {
                const newRow = document.createElement('tr');
                newRow.className = 'status-code-row';
                newRow.innerHTML = `
                    <td><input type="text" class="param-input status-code" name="statusCode[]" value="${code}" placeholder="如：200"></td>
                    <td><input type="text" class="param-input status-msg" name="statusMsg[]" value="${status.msg || ''}" placeholder="如：请求成功"></td>
                    <td><input type="text" class="param-input status-desc" name="statusDesc[]" value="${status.desc || ''}" placeholder="详细说明"></td>
                    <td><button type="button" class="btn btn-danger remove-status-code-btn">删除</button></td>
                `;
                table.appendChild(newRow);
            });
        }
        
function saveApi() {
    // 手动获取表单数据（替代 FormData，避免兼容性问题）
    const apiIdInput = document.getElementById('apiId').value.trim();
    const apiNameInput = document.getElementById('apiName').value.trim();
    const apiMethodInput = document.getElementById('apiMethod').value.trim();
    const apiUrlInput = document.getElementById('apiUrl').value.trim();
    const apiDescriptionInput = document.getElementById('apiDescription').value.trim();
    const responseFormatInput = document.getElementById('responseFormat').value.trim();
    const requestExampleInput = document.getElementById('requestExample').value.trim();
    const responseExampleInput = document.getElementById('responseExample').value.trim();
    const exampleLangInput = document.getElementById('exampleLang').value.trim();

    // 第一步：直接校验 API ID（最直观的方式）
    if (!apiIdInput) {
        showAlert('API ID 不能为空！', 'error');
        // 聚焦到 API ID 输入框，提示用户
        document.getElementById('apiId').focus();
        return;
    }

    // 收集请求参数
    const params = {};
    const paramRows = document.querySelectorAll('#requestParamsTable .param-row');
    paramRows.forEach(row => {
        const name = row.querySelector('.param-name').value.trim();
        if (name) {
            const type = row.querySelector('.param-type').value.trim();
            const required = row.querySelector('.param-required').value === 'true';
            const desc = row.querySelector('.param-desc').value.trim();
            params[name] = { type, required, desc };
        }
    });

    // 收集返回参数
    const responseParams = {};
    const respParamRows = document.querySelectorAll('#responseParamsTable .response-param-row');
    respParamRows.forEach(row => {
        const name = row.querySelector('.resp-param-name').value.trim();
        if (name) {
            const type = row.querySelector('.resp-param-type').value.trim();
            const desc = row.querySelector('.resp-param-desc').value.trim();
            responseParams[name] = { type, desc };
        }
    });

    // 收集状态码
    const statusCodes = {};
    const statusRows = document.querySelectorAll('#statusCodesTable .status-code-row');
    statusRows.forEach(row => {
        const code = row.querySelector('.status-code').value.trim();
        if (code) {
            const msg = row.querySelector('.status-msg').value.trim();
            const desc = row.querySelector('.status-desc').value.trim();
            statusCodes[code] = { msg, desc };
        }
    });

    // 🔥 核心修复：不再强制转JSON，保留原始文本（解决test被转成[]的问题）
    // 逻辑：如果是合法JSON则解析，否则直接保存原始文本
    let requestExample = requestExampleInput;
    try {
        if (requestExampleInput) {
            requestExample = JSON.parse(requestExampleInput);
        }
    } catch (e) {
        // 解析失败，直接保留原始文本
        requestExample = requestExampleInput;
    }

    let responseExample = responseExampleInput;
    try {
        if (responseExampleInput) {
            responseExample = JSON.parse(responseExampleInput);
        }
    } catch (e) {
        // 解析失败，直接保留原始文本
        responseExample = responseExampleInput;
    }

    // 构建最终提交数据
    const apiData = {
        id: apiIdInput,
        name: apiNameInput,
        method: apiMethodInput || 'GET',
        url: apiUrlInput,
        description: apiDescriptionInput,
        responseFormat: responseFormatInput,
        params: params,
        responseParams: responseParams,
        statusCodes: statusCodes,
        example: requestExample, // 直接传文本/解析后的JSON，不再默认空数组
        response: responseExample, // 同上
        exampleLang: exampleLangInput || 'json'
    };

    // 提交数据
    fetch('save_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            apiData: apiData, 
            isEdit: !!currentApiId 
        })
    })
    .then(response => {
        if (!response.ok) throw new Error('网络请求失败');
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            closeModal();
            setTimeout(() => window.location.reload(), 800);
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(err => {
        console.error('保存失败详情：', err); // 控制台输出错误，方便排查
        showAlert('保存失败，请检查控制台或联系开发者', 'error');
    });
}
        
        // 删除 API
        function deleteApi(apiId) {
            fetch('delete_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ apiId: apiId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    // 刷新页面
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                showAlert('删除失败：' + error.message, 'error');
            });
        }
        
        // 显示提示消息
        function showAlert(message, type) {
            alertMessage.textContent = message;
            alertMessage.className = 'alert';
            
            if (type === 'success') {
                alertMessage.classList.add('alert-success');
            } else if (type === 'error') {
                alertMessage.classList.add('alert-error');
            }
            
            // 3秒后隐藏
            setTimeout(() => {
                alertMessage.classList.add('hidden');
            }, 3000);
        }
    </script>
</body>
</html>