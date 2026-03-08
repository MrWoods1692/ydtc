<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>接口列表</title>
    <!-- 代码高亮样式 -->
    <link rel="stylesheet" href="atom-one-dark.min.css">
    <!-- 基础样式 -->
    <style>
        /* 全局样式 - macOS 风格 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        /* 隐藏所有滚动条 */
        ::-webkit-scrollbar {
            display: none;
        }
        * {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        body {
            background-color: #f5f5f7;
            /* 网格背景 */
            background-image: 
                linear-gradient(rgba(0, 0, 0, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 0, 0, 0.05) 1px, transparent 1px);
            background-size: 20px 20px;
            color: #1d1d1f;
            min-height: 100vh;
            padding-top: 60px;
        }

        /* 顶部导航栏 */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 50px;
            background-color: #ffffff;
            border-bottom: 1px solid #e6e6e6;
            display: flex;
            align-items: center;
            padding: 0 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            z-index: 100;
        }

        /* 左上角红黄绿三点菜单 - macOS 风格 */
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
            transition: transform 0.2s;
        }

        .menu-btn span:nth-child(1) {
            background-color: #ff5f57; /* 红 */
        }
        .menu-btn span:nth-child(2) {
            background-color: #ffbd2e; /* 黄 */
        }
        .menu-btn span:nth-child(3) {
            background-color: #28ca42; /* 绿 */
        }

        .menu-btn:hover span {
            transform: scale(1.1);
        }

        .navbar-title {
            margin-left: 15px;
            font-size: 18px;
            font-weight: 500;
        }

        /* API 列表容器 */
        .api-list {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        /* API 卡片样式 - 优化美化 */
        .api-card {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            cursor: pointer;
            border: 1px solid rgba(0,0,0,0.03);
        }

        .api-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            border-color: rgba(0, 113, 227, 0.1);
        }

        .api-card h3 {
            font-size: 19px;
            margin-bottom: 12px;
            color: #0071e3;
            font-weight: 500;
        }

        .api-card p {
            color: #86868b;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .api-card .api-method {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 8px;
            margin-bottom: 10px;
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

        /* 详情模态框 - 几乎铺满页面，仅4px边距 */
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
            padding: 10px; /* 全局边距4px */
            backdrop-filter: blur(8px);
        }

        .modal-content {
            background-color: #ffffff;
            border-radius: 18px;
            width: 100%;
            height: 100%;
            max-width: none;
            max-height: none;
            overflow-y: auto;
            padding: 36px;
            position: relative;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: #f5f5f7;
            border: none;
            cursor: pointer;
            font-size: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #86868b;
            transition: all 0.2s;
            z-index: 10;
        }

        .close-modal:hover {
            background-color: #e6e6e6;
            color: #1d1d1f;
            transform: scale(1.05);
        }

        .modal-content h2 {
            color: #1d1d1f;
            margin-bottom: 24px;
            font-size: 28px;
            font-weight: 600;
        }

        .modal-content h3 {
            color: #1d1d1f;
            margin: 30px 0 15px;
            font-size: 20px;
            font-weight: 500;
            border-bottom: 1px solid #e6e6e6;
            padding-bottom: 12px;
        }

        .modal-content p {
            line-height: 1.6;
            color: #424245;
            margin-bottom: 16px;
            font-size: 15px;
        }

        /* 基本信息样式 */
        .info-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin: 10px 0 20px;
        }

        .info-row p {
            background-color: #f5f5f7;
            padding: 10px 18px;
            border-radius: 8px;
            margin: 0;
            font-size: 14px;
        }

        .copyable-text {
            color: #0071e3;
            cursor: pointer;
            text-decoration: underline;
            text-decoration-style: dotted;
            text-underline-offset: 2px;
            transition: color 0.2s;
        }

        .copyable-text:hover {
            color: #0077ed;
            text-decoration-style: solid;
        }

        /* 参数表格 - 优化 */
        .params-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0 25px;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .params-table th, .params-table td {
            padding: 14px 18px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        .params-table th {
            background-color: #f5f5f7;
            font-weight: 500;
            color: #1d1d1f;
        }

        .params-table tr:last-child td {
            border-bottom: none;
        }

        /* macOS 风格代码容器 */
        .code-wrapper {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            margin: 20px 0 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        /* macOS 代码框头部 */
        .code-header {
            background-color: #282c34;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .code-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .code-dot.red {
            background-color: #ff5f57;
        }

        .code-dot.yellow {
            background-color: #ffbd2e;
        }

        .code-dot.green {
            background-color: #28ca42;
        }

        /* 代码复制按钮 - 白色背景 + SVG图标 */
        .code-copy-btn {
            position: absolute;
            top: 8px;
            right: 16px;
            background-color: #ffffff; /* 白色背景 */
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .code-copy-btn:hover {
            background-color: #f5f5f7; /* hover 浅灰色 */
            transform: scale(1.05);
        }

        .code-copy-btn img {
            width: 16px;
            height: 16px;
            opacity: 0.8;
        }

        .code-copy-btn:hover img {
            opacity: 1;
        }

        /* 代码高亮容器 */
        .code-container {
            background-color: #282c34;
            padding: 20px;
            overflow-x: auto;
        }

        pre {
            color: #abb2bf;
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
        }

        /* 复制提示 */
        .copy-tooltip {
            position: fixed;
            background-color: #1d1d1f;
            color: #ffffff;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13px;
            z-index: 1001;
            opacity: 0;
            transition: opacity 0.2s;
            pointer-events: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        /* 状态码样式 */
        .status-code-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0 25px;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .status-code-table th, .status-code-table td {
            padding: 14px 18px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        .status-code-table th {
            background-color: #f5f5f7;
            font-weight: 500;
            color: #1d1d1f;
        }

        .status-code-table tr:last-child td {
            border-bottom: none;
        }

        .status-code {
            color: #0071e3;
            font-weight: 500;
        }

        /* 响应式适配 */
        @media (max-width: 768px) {
            .api-list {
                grid-template-columns: 1fr;
                padding: 16px;
            }
            .modal-content {
                padding: 24px;
                border-radius: 16px;
            }
            .info-row {
                flex-direction: column;
                gap: 10px;
            }
            .params-table th, .params-table td,
            .status-code-table th, .status-code-table td {
                padding: 12px 14px;
            }
        }
    </style>
</head>
<body>
    <!-- 顶部导航栏 -->
    <div class="navbar">
        <button class="menu-btn">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <div class="navbar-title">接口列表</div>
    </div>

    <!-- API 列表容器 -->
    <div class="api-list" id="apiList">
        <!-- PHP 动态生成 API 卡片 -->
        <?php
        // 读取 API 列表文件
        $apisListFile = 'apis.json';
        if (!file_exists($apisListFile)) {
            echo '<div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #b91c1c; font-size: 16px;">未找到 APIs 列表文件</div>';
            exit;
        }

        $apisList = json_decode(file_get_contents($apisListFile), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($apisList)) {
            echo '<div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #b91c1c; font-size: 16px;">APIs 列表文件格式错误</div>';
            exit;
        }

        // 遍历生成 API 卡片
        foreach ($apisList as $api) {
            $apiId = isset($api['id']) ? $api['id'] : '';
            $apiName = isset($api['name']) ? $api['name'] : '未命名 API';
            $apiDesc = isset($api['description']) ? $api['description'] : '无描述';
            $apiMethod = isset($api['method']) ? strtoupper($api['method']) : 'GET';
            
            if (empty($apiId)) continue;
        ?>
        <div class="api-card" data-api-id="<?php echo htmlspecialchars($apiId); ?>">
            <span class="api-method method-<?php echo strtolower($apiMethod); ?>"><?php echo $apiMethod; ?></span>
            <h3><?php echo htmlspecialchars($apiName); ?></h3>
            <p><?php echo htmlspecialchars($apiDesc); ?></p>
        </div>
        <?php } ?>
    </div>

    <!-- API 详情模态框 -->
    <div class="modal" id="apiModal">
        <div class="modal-content">
            <button class="close-modal" id="closeModal">&times;</button>
            <div id="apiDetailContent"></div>
        </div>
    </div>

    <!-- 复制提示框 -->
    <div class="copy-tooltip" id="copyTooltip">复制成功！</div>

    <!-- 代码高亮脚本 -->
    <script src="highlight.min.js"></script>
    <!-- 加载所需语言包 -->
    <script src="languages/json.min.js"></script>
    <script src="languages/bash.min.js"></script>
    <script src="languages/php.min.js"></script>
    <script src="languages/javascript.min.js"></script>
    
    <script>
        // 初始化变量
        const apiModal = document.getElementById('apiModal');
        const apiDetailContent = document.getElementById('apiDetailContent');
        const closeModal = document.getElementById('closeModal');
        const apiCards = document.querySelectorAll('.api-card');
        const copyTooltip = document.getElementById('copyTooltip');

        // 点击 API 卡片显示详情
        apiCards.forEach(card => {
            card.addEventListener('click', async () => {
                const apiId = card.getAttribute('data-api-id');
                if (!apiId) return;

                try {
                    // 加载 API 详情文件
                    const response = await fetch(`./apis/${apiId}.json`);
                    if (!response.ok) throw new Error('暂未上架');
                    
                    const apiDetail = await response.json();
                    renderApiDetail(apiDetail);
                    
                    // 显示模态框
                    apiModal.style.display = 'flex';
                } catch (error) {
                    apiDetailContent.innerHTML = `<p style="color: #b91c1c; text-align: center; padding: 40px; font-size: 16px;">这个API ${error.message}</p>`;
                    apiModal.style.display = 'flex';
                }
            });
        });

        // 关闭模态框
        closeModal.addEventListener('click', () => {
            apiModal.style.display = 'none';
        });

        // 点击模态框外部关闭
        apiModal.addEventListener('click', (e) => {
            if (e.target === apiModal) {
                apiModal.style.display = 'none';
            }
        });

        // 显示复制提示
        function showCopyTooltip(x, y) {
            copyTooltip.style.left = `${x}px`;
            copyTooltip.style.top = `${y - 30}px`;
            copyTooltip.style.opacity = '1';
            
            setTimeout(() => {
                copyTooltip.style.opacity = '0';
            }, 1500);
        }

        // 复制到剪贴板函数
        function copyToClipboard(text, event) {
            navigator.clipboard.writeText(text).then(() => {
                // 获取鼠标位置显示提示
                const x = event.clientX;
                const y = event.clientY;
                showCopyTooltip(x, y);
            }).catch(() => {
                alert('复制失败，请手动复制');
            });
        }

        // HTML 转义函数
        function htmlEscape(str) {
            if (!str) return '';
            return str.toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // 渲染 API 详情
        function renderApiDetail(api) {
            let html = `
                <h2>${htmlEscape(api.name || '未命名 API')}</h2>
                <p>${htmlEscape(api.description || '无描述')}</p>
                
                <h3>基本信息</h3>
                <div class="info-row">
                    <p><strong>请求方法:</strong> ${htmlEscape(api.method ? api.method.toUpperCase() : 'GET')}</p><br>
                    <p><strong>请求地址:</strong> <span class="copyable-text" onclick="copyToClipboard('${htmlEscape(api.url || '')}', event)">${htmlEscape(api.url || '-')}</span></p><br>
                    ${api.responseFormat ? `<p><strong>返回格式:</strong> ${htmlEscape(api.responseFormat)}</p>` : ''}
                </div>
            `;

            // 渲染请求参数
            if (api.params && Object.keys(api.params).length > 0) {
                html += `
                    <h3>请求参数</h3>
                    <table class="params-table">
                        <thead>
                            <tr>
                                <th>参数名</th>
                                <th>类型</th>
                                <th>必填</th>
                                <th>描述</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                Object.entries(api.params).forEach(([key, param]) => {
                    html += `
                        <tr>
                            <td>
                                <span class="copyable-text" onclick="copyToClipboard('${htmlEscape(key)}', event)">${htmlEscape(key)}</span>
                            </td>
                            <td>${htmlEscape(param.type || 'string')}</td>
                            <td>${param.required ? '是' : '否'}</td>
                            <td>${htmlEscape(param.desc || '-')}</td>
                        </tr>
                    `;
                });

                html += `
                        </tbody>
                    </table>
                `;
            }

            // 渲染返回参数
            if (api.responseParams && Object.keys(api.responseParams).length > 0) {
                html += `
                    <h3>返回参数</h3>
                    <table class="params-table">
                        <thead>
                            <tr>
                                <th>参数名</th>
                                <th>类型</th>
                                <th>描述</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                Object.entries(api.responseParams).forEach(([key, param]) => {
                    html += `
                        <tr>
                            <td>
                                <span class="copyable-text" onclick="copyToClipboard('${htmlEscape(key)}', event)">${htmlEscape(key)}</span>
                            </td>
                            <td>${htmlEscape(param.type || 'string')}</td>
                            <td>${htmlEscape(param.desc || '-')}</td>
                        </tr>
                    `;
                });

                html += `
                        </tbody>
                    </table>
                `;
            }

            // 渲染状态码
            if (api.statusCodes && Object.keys(api.statusCodes).length > 0) {
                html += `
                    <h3>状态码说明</h3>
                    <table class="status-code-table">
                        <thead>
                            <tr>
                                <th>状态码</th>
                                <th>描述</th>
                                <th>说明</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                Object.entries(api.statusCodes).forEach(([code, status]) => {
                    html += `
                        <tr>
                            <td class="status-code">${htmlEscape(code)}</td>
                            <td>${htmlEscape(status.msg || '-')}</td>
                            <td>${htmlEscape(status.desc || '-')}</td>
                        </tr>
                    `;
                });

                html += `
                        </tbody>
                    </table>
                `;
            }

            // 渲染请求示例（macOS 风格代码框）
            if (api.example) {
                const exampleCode = htmlEscape(JSON.stringify(api.example, null, 2));
                const exampleLang = api.exampleLang || 'json';
                html += `
                    <h3>请求示例</h3>
                    <div class="code-wrapper">
                        <div class="code-header">
                            <span class="code-dot red"></span>
                            <span class="code-dot yellow"></span>
                            <span class="code-dot green"></span>
                        </div>
                        <button class="code-copy-btn" onclick="copyToClipboard('${exampleCode}', event)">
                            <img src="../svg/复制.svg" alt="复制代码">
                        </button>
                        <div class="code-container">
                            <pre><code class="language-${exampleLang}">${exampleCode}</code></pre>
                        </div>
                    </div>
                `;
            }

            // 渲染响应示例（macOS 风格代码框）
            if (api.response) {
                const responseCode = htmlEscape(JSON.stringify(api.response, null, 2));
                html += `
                    <h3>响应示例</h3>
                    <div class="code-wrapper">
                        <div class="code-header">
                            <span class="code-dot red"></span>
                            <span class="code-dot yellow"></span>
                            <span class="code-dot green"></span>
                        </div>
                        <button class="code-copy-btn" onclick="copyToClipboard('${responseCode}', event)">
                            <img src="../svg/复制.svg" alt="复制代码">
                        </button>
                        <div class="code-container">
                            <pre><code class="language-json">${responseCode}</code></pre>
                        </div>
                    </div>
                `;
            }

            apiDetailContent.innerHTML = html;
            
            // 高亮代码
            document.querySelectorAll('pre code').forEach((block) => {
                hljs.highlightElement(block);
            });
        }
    </script>
</body>
</html>