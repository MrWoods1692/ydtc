<?php
// 获取接口ID
$apiId = $_GET['id'] ?? '';
if (empty($apiId)) {
    die('
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>参数错误</title>
        <style>
            * {font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;}
            body {background: #f5f5f7; display: flex; align-items: center; justify-content: center; min-height: 100vh;}
            .error-box {background: #fff; padding: 40px; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.07); text-align: center;}
            .error-box i {font-size: 60px; color: #ff3b30; margin-bottom: 20px; display: block;}
            .error-box h2 {color: #1d1d1f; font-size: 24px; margin-bottom: 10px;}
            .error-box p {color: #6e6e73; font-size: 16px; margin-bottom: 20px;}
            .error-box a {color: #0071e3; text-decoration: none; padding: 8px 20px; border-radius: 20px; border: 1px solid #0071e3; transition: all 0.3s ease;}
            .error-box a:hover {background: #0071e3; color: #fff;}
        </style>
    </head>
    <body>
        <div class="error-box">
            <i>⚠️</i>
            <h2>Nano</h2>
            <p>请从接口列表页进入详情页</p>
            <a href="index.php">返回接口列表</a>
        </div>
    </body>
    </html>');
}

// JSON文件路径
$jsonFile = __DIR__ . '/api_json/' . $apiId . '.json';
if (!file_exists($jsonFile)) {
    die('
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>接口不存在</title>
        <style>
            * {font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;}
            body {background: #f5f5f7; display: flex; align-items: center; justify-content: center; min-height: 100vh;}
            .error-box {background: #fff; padding: 40px; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.07); text-align: center;}
            .error-box i {font-size: 60px; color: #ff9500; margin-bottom: 20px; display: block;}
            .error-box h2 {color: #1d1d1f; font-size: 24px; margin-bottom: 10px;}
            .error-box p {color: #6e6e73; font-size: 16px; margin-bottom: 20px;}
            .error-box a {color: #0071e3; text-decoration: none; padding: 8px 20px; border-radius: 20px; border: 1px solid #0071e3; transition: all 0.3s ease;}
            .error-box a:hover {background: #0071e3; color: #fff;}
        </style>
    </head>
    <body>
        <div class="error-box">
            <i>📄</i>
            <h2>接口文件不存在</h2>
            <p>未找到ID为 "' . htmlspecialchars($apiId) . '" 的接口</p>
            <a href="index.php">返回接口列表</a>
        </div>
    </body>
    </html>');
}

// 读取并解析JSON
$jsonContent = file_get_contents($jsonFile);
$apiData = json_decode($jsonContent, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die('
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>解析失败</title>
        <style>
            * {font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;}
            body {background: #f5f5f7; display: flex; align-items: center; justify-content: center; min-height: 100vh;}
            .error-box {background: #fff; padding: 40px; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.07); text-align: center;}
            .error-box i {font-size: 60px; color: #ff3b30; margin-bottom: 20px; display: block;}
            .error-box h2 {color: #1d1d1f; font-size: 24px; margin-bottom: 10px;}
            .error-box p {color: #6e6e73; font-size: 16px; margin-bottom: 20px;}
            .error-box a {color: #0071e3; text-decoration: none; padding: 8px 20px; border-radius: 20px; border: 1px solid #0071e3; transition: all 0.3s ease;}
            .error-box a:hover {background: #0071e3; color: #fff;}
        </style>
    </head>
    <body>
        <div class="error-box">
            <i>🔧</i>
            <h2>文件解析失败</h2>
            <p>接口格式错误，请检查</p>
            <a href="index.php">返回接口列表</a>
        </div>
    </body>
    </html>');
}

// 提取SDK语言列表（从JSON的sdk字段）
$sdkLanguages = isset($apiData['sdk']) ? array_keys($apiData['sdk']) : [];
$defaultLang = $sdkLanguages ? $sdkLanguages[0] : '';

// 统一前后端语言映射（关键！和前端保持一致）
$langMap = [
    'PHP' => 'php',
    'Java' => 'java',
    'JavaScript' => 'javascript',
    'Python' => 'python',
    'Go' => 'go',
    'C' => 'c',
    'C++' => 'cpp',
    'Shell' => 'bash',
    'Bash' => 'bash',
    'NodeJS' => 'javascript',
    'Rust' => 'rust',
    'Wget' => 'bash',
    'JSON' => 'json'
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($apiData['name'] ?? 'API详情'); ?></title>
    
    <!-- 只保留CDN引入，避免冲突（核心修复1） -->
    <link rel="stylesheet" href="./atom-one-dark.min.css">
    <script src="./highlight.min.js"></script>
    
    <!-- 批量加载语言文件（按highlight.js官方命名） -->
    <script src="./languages/bash.min.js"></script>
    <script src="./languages/c.min.js"></script>
    <script src="./languages/cpp.min.js"></script>
    <script src="./languages/go.min.js"></script>
    <script src="./languages/java.min.js"></script>
    <script src="./languages/javascript.min.js"></script>
    <script src="./languages/json.min.js"></script>
    <script src="./languages/php.min.js"></script>
    <script src="./languages/python.min.js"></script>
    <script src="./languages/rust.min.js"></script>
    <script src="./languages/shell.min.js"></script>

    <style>
        /* 苹果风格全局样式 - 深度美化版 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        body {
            background-color: #f5f5f7;
            padding: 20px;
            line-height: 1.6;
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.07);
            padding: 30px;
            margin-bottom: 30px;
            /* 苹果毛玻璃效果 */
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.8);
        }
        /* 头部样式美化 */
        .header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 1px solid #e6e6e8;
            padding-bottom: 20px;
            flex-wrap: wrap;
        }
        .back-btn {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: #0071e3;
            margin-right: 20px;
            font-size: 16px;
            padding: 8px 12px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .back-btn:hover {
            background-color: rgba(0,113,227,0.05);
        }
        .back-btn img {
            width: 18px;
            height: 18px;
            margin-right: 8px;
        }
        h1 {
            color: #1d1d1f;
            font-size: 32px;
            font-weight: 600;
            flex: 1;
        }
        /* 章节样式美化 */
        .section {
            margin-bottom: 35px;
            padding: 20px;
            border-radius: 12px;
            background-color: #fafafa;
            border: 1px solid #f0f0f0;
        }
        .section h2 {
            color: #1d1d1f;
            font-size: 22px;
            margin-bottom: 20px;
            padding-left: 12px;
            border-left: 4px solid #0071e3;
            font-weight: 500;
        }
        .section .info-item {
            margin-bottom: 12px;
            color: #6e6e73;
            font-size: 15px;
            line-height: 1.7;
        }
        .section .info-item strong {
            color: #1d1d1f;
            margin-right: 8px;
            font-weight: 500;
        }
        /* 苹果风格代码框 - 深度美化 */
        .code-box {
            background-color: #1a1a1a;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
            margin: 20px 0;
            overflow: hidden;
            position: relative;
            font-family: Menlo, Monaco, Consolas, "Courier New", monospace;
        }
        /* 代码框顶部（仿Mac窗口） */
        .code-box-header {
            background-color: #2d2d2d;
            padding: 10px 18px;
            display: flex;
            align-items: center;
        }
        .code-dots {
            display: flex;
            gap: 8px;
        }
        .dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            transition: all 0.2s ease;
        }
        .dot-red {
            background-color: #ff5f56;
        }
        .dot-red:hover {
            background-color: #ff3b30;
        }
        .dot-yellow {
            background-color: #ffbd2e;
        }
        .dot-yellow:hover {
            background-color: #ff9500;
        }
        .dot-green {
            background-color: #27c93f;
        }
        .dot-green:hover {
            background-color: #34c759;
        }
        .code-lang {
            color: #ccc;
            font-size: 14px;
            margin-left: 18px;
            font-weight: 400;
        }
        /* 代码内容区域 - 优化高亮显示 */
        .code-content {
            padding: 20px;
            color: #fff;
            font-family: Menlo, Monaco, Consolas, "Courier New", monospace;
            font-size: 14px;
            line-height: 1.8;
            overflow-x: auto;
            scrollbar-width: thin;
            scrollbar-color: #6e6e73 #2d2d2d;
        }
        .code-content::-webkit-scrollbar {
            height: 6px;
        }
        .code-content::-webkit-scrollbar-thumb {
            background-color: #6e6e73;
            border-radius: 3px;
        }
        /* 复制按钮通用样式 - 美化 */
        .copy-btn {
            position: absolute;
            top: 10px;
            right: 18px;
            width: 24px;
            height: 24px;
            cursor: pointer;
            opacity: 0.7;
            transition: all 0.3s ease;
            filter: invert(1);
        }
        .copy-btn:hover {
            opacity: 1;
            transform: scale(1.1);
        }
        /* 表格样式 - 美化 */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            background-color: #fff;
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
            position: sticky;
            top: 0;
        }
        td {
            color: #333;
            position: relative;
            transition: background-color 0.2s ease;
        }
        tr:hover td {
            background-color: rgba(0,113,227,0.03);
        }
        /* 单元格内的复制按钮 - 美化 */
        .cell-copy-btn {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            cursor: pointer;
            opacity: 0;
            transition: all 0.2s ease;
        }
        tr:hover .cell-copy-btn {
            opacity: 0.7;
        }
        .cell-copy-btn:hover {
            opacity: 1;
            transform: translateY(-50%) scale(1.1);
        }
        /* SDK切换按钮 - 苹果风格 */
        .sdk-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .sdk-tab {
            padding: 8px 16px;
            background-color: #f0f0f0;
            border: none;
            border-radius: 6px;  /* 方形圆角，比原来小 */
            color: #1d1d1f;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
            font-weight: 500;
        }
        .sdk-tab.active {
            background-color: #0071e3;
            color: #fff;
            box-shadow: 0 4px 10px rgba(0,113,227,0.2);
        }
        .sdk-tab:hover {
            background-color: #e0e0e0;
            transform: translateY(-2px);
        }
        .sdk-tab.active:hover {
            background-color: #0077ed;
            box-shadow: 0 6px 12px rgba(0,113,227,0.3);
        }
        /* 返回顶部按钮 - 美化 */
        .back-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 48px;
            height: 48px;
            background-color: rgba(255,255,255,0.8);
            border-radius: 50%;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.9);
            opacity: 0.7;
            z-index: 999;
        }
        .back-top:hover {
            background-color: #fff;
            opacity: 1;
            transform: scale(1.05);
        }
        .back-top img {
            width: 22px;
            height: 22px;
        }
        /* 苹果风格复制提示 - 核心美化 */
        .copy-toast {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(-20px);
            background-color: rgba(0,0,0,0.8);
            color: #fff;
            padding: 10px 24px;
            border-radius: 25px;
            font-size: 15px;
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            z-index: 9999;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .copy-toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
        /* 空内容提示 */
        .empty-content {
            text-align: center;
            padding: 40px 20px;
            color: #86868b;
            font-size: 15px;
        }
        /* 响应式适配 */
        @media (max-width: 768px) {
            .container {
                padding: 20px;
                margin: 10px;
            }
            h1 {
                font-size: 24px;
                margin-top: 10px;
                width: 100%;
            }
            .section {
                padding: 15px;
                margin-bottom: 25px;
            }
            .section h2 {
                font-size: 18px;
            }
            table {
                font-size: 14px;
            }
            th, td {
                padding: 12px 10px;
            }
            .code-content {
                padding: 15px;
                font-size: 13px;
            }
            .sdk-tab {
                padding: 8px 16px;
                font-size: 13px;
            }
            .back-top {
                width: 44px;
                height: 44px;
                bottom: 20px;
                right: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="copy-toast" id="copyToast">复制成功</div>

    <div class="container">
        <!-- 头部（返回按钮+标题） -->
        <div class="header">
            <a href="index.php" class="back-btn">
                <img src="../svg/返回.svg" alt="返回">
                返回列表
            </a>
            <h1><?php echo htmlspecialchars($apiData['name'] ?? '未命名接口'); ?></h1>
        </div>

        <!-- 接口基本信息 -->
        <div class="section">
            <h2>接口基本信息</h2>
            <div class="info-item"><strong>接口名称：</strong><?php echo htmlspecialchars($apiData['name'] ?? '无'); ?></div>
            <div class="info-item"><strong>简介：</strong><?php echo htmlspecialchars($apiData['brief'] ?? '无'); ?></div>
            <div class="info-item"><strong>请求说明：</strong><?php echo htmlspecialchars($apiData['request_desc'] ?? '无'); ?></div>
            <div class="info-item">
                <strong>接口地址：</strong>
                <span id="apiUrl"><?php echo htmlspecialchars($apiData['api_url'] ?? '无'); ?></span>
                <img src="../svg/复制.svg" class="copy-btn" style="position: static; display: inline-block; vertical-align: middle; margin-left: 8px; filter: invert(0);" onclick="copyContent('apiUrl')">
            </div>
            <div class="info-item"><strong>请求方式：</strong><?php echo htmlspecialchars($apiData['request_method'] ?? '无'); ?></div>
            <div class="info-item"><strong>返回格式：</strong><?php echo htmlspecialchars($apiData['return_format'] ?? '无'); ?></div>
        </div>

        <!-- 请求示例 -->
        <?php if (!empty($apiData['request_example'])): ?>
        <div class="section">
            <h2>请求示例</h2>
            <div class="code-box">
                <div class="code-box-header">
                    <div class="code-dots">
                        <div class="dot dot-red"></div>
                        <div class="dot dot-yellow"></div>
                        <div class="dot dot-green"></div>
                    </div>
                    <div class="code-lang">请求示例</div>
                    <img src="../svg/复制.svg" class="copy-btn" onclick="copyContent('requestExample')">
                </div>
                <pre class="code-content"><code id="requestExample" class="language-bash"><?php echo htmlspecialchars($apiData['request_example']); ?></code></pre>
            </div>
        </div>
        <?php endif; ?>

        <!-- 请求参数 -->
        <?php if (!empty($apiData['request_params'])): ?>
        <div class="section">
            <h2>请求参数</h2>
            <table>
                <thead>
                    <tr>
                        <th>参数名</th>
                        <th>类型</th>
                        <th>必填</th>
                        <th>描述</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($apiData['request_params'] as $param): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($param['name'] ?? ''); ?>
                            <img src="../svg/复制.svg" class="cell-copy-btn" onclick="copyText('<?php echo htmlspecialchars($param['name'] ?? ''); ?>')">
                        </td>
                        <td>
                            <?php echo htmlspecialchars($param['type'] ?? ''); ?>
                            <img src="../svg/复制.svg" class="cell-copy-btn" onclick="copyText('<?php echo htmlspecialchars($param['type'] ?? ''); ?>')">
                        </td>
                        <td>
                            <?php echo htmlspecialchars($param['required'] ?? ''); ?>
                            <img src="../svg/复制.svg" class="cell-copy-btn" onclick="copyText('<?php echo htmlspecialchars($param['required'] ?? ''); ?>')">
                        </td>
                        <td>
                            <?php echo htmlspecialchars($param['desc'] ?? ''); ?>
                            <img src="../svg/复制.svg" class="cell-copy-btn" onclick="copyText('<?php echo htmlspecialchars($param['desc'] ?? ''); ?>')">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- 返回参数 -->
        <?php if (!empty($apiData['return_params'])): ?>
        <div class="section">
            <h2>返回参数</h2>
            <table>
                <thead>
                    <tr>
                        <th>参数字段</th>
                        <th>类型</th>
                        <th>说明</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($apiData['return_params'] as $param): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($param['field'] ?? ''); ?>
                            <img src="../svg/复制.svg" class="cell-copy-btn" onclick="copyText('<?php echo htmlspecialchars($param['field'] ?? ''); ?>')">
                        </td>
                        <td>
                            <?php echo htmlspecialchars($param['type'] ?? ''); ?>
                            <img src="../svg/复制.svg" class="cell-copy-btn" onclick="copyText('<?php echo htmlspecialchars($param['type'] ?? ''); ?>')">
                        </td>
                        <td>
                            <?php echo htmlspecialchars($param['desc'] ?? ''); ?>
                            <img src="../svg/复制.svg" class="cell-copy-btn" onclick="copyText('<?php echo htmlspecialchars($param['desc'] ?? ''); ?>')">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- 状态码说明 -->
        <?php if (!empty($apiData['status_code'])): ?>
        <div class="section">
            <h2>状态码说明</h2>
            <table>
                <thead>
                    <tr>
                        <th>状态码</th>
                        <th>说明</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($apiData['status_code'] as $code): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($code['code'] ?? ''); ?>
                            <img src="../svg/复制.svg" class="cell-copy-btn" onclick="copyText('<?php echo htmlspecialchars($code['code'] ?? ''); ?>')">
                        </td>
                        <td>
                            <?php echo htmlspecialchars($code['desc'] ?? ''); ?>
                            <img src="../svg/复制.svg" class="cell-copy-btn" onclick="copyText('<?php echo htmlspecialchars($code['desc'] ?? ''); ?>')">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- 返回示例 -->
        <?php if (!empty($apiData['return_example'])): ?>
        <div class="section">
            <h2>返回示例</h2>
            <div class="code-box">
                <div class="code-box-header">
                    <div class="code-dots">
                        <div class="dot dot-red"></div>
                        <div class="dot dot-yellow"></div>
                        <div class="dot dot-green"></div>
                    </div>
                    <div class="code-lang">返回示例</div>
                    <img src="../svg/复制.svg" class="copy-btn" onclick="copyContent('returnExample')">
                </div>
                <pre class="code-content"><code id="returnExample" class="language-json"><?php echo htmlspecialchars($apiData['return_example']); ?></code></pre>
            </div>
        </div>
        <?php endif; ?>

        <!-- 多语言SDK -->
        <?php if (!empty($apiData['sdk']) && !empty($sdkLanguages)): ?>
        <div class="section">
            <h2>SDK</h2>
            <!-- SDK语言切换按钮 -->
            <div class="sdk-tabs">
                <?php foreach ($sdkLanguages as $lang): ?>
                <button class="sdk-tab <?php echo $lang === $defaultLang ? 'active' : ''; ?>" 
                        onclick="switchSdk('<?php echo htmlspecialchars($lang); ?>')">
                    <?php echo htmlspecialchars($lang); ?>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- SDK代码框 -->
            <div class="code-box">
                <div class="code-box-header">
                    <div class="code-dots">
                        <div class="dot dot-red"></div>
                        <div class="dot dot-yellow"></div>
                        <div class="dot dot-green"></div>
                    </div>
                    <div class="code-lang" id="sdkLangText"><?php echo htmlspecialchars($defaultLang); ?></div>
                    <img src="../svg/复制.svg" class="copy-btn" onclick="copyContent('sdkCode')">
                </div>
<pre class="code-content"><code id="sdkCode" class="language-<?php echo isset($langMap[$defaultLang]) ? $langMap[$defaultLang] : 'plaintext'; ?>"><?php 
    // 先解码 HTML 实体，然后再转义特殊字符用于显示
    $decodedCode = html_entity_decode($apiData['sdk'][$defaultLang] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    echo htmlspecialchars($decodedCode, ENT_QUOTES | ENT_HTML5, 'UTF-8'); 
?></code></pre>
            </div>
        </div>
        <?php elseif (!empty($sdkLanguages)): ?>
            <div class="section">
                <h2>SDK</h2>
                <div class="empty-content">暂无SDK代码示例</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- 返回顶部按钮 -->
    <div class="back-top" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">
        <img src="../svg/返回顶部.svg" alt="返回顶部">
    </div>

<script>
    // 全局存储SDK数据，避免重复解析
    const sdkData = <?php echo json_encode($apiData['sdk'] ?? []); ?>;
    const langMap = <?php echo json_encode($langMap); ?>;

    // 等待highlight.js完全加载
    window.addEventListener('load', function() {
        if (window.hljs) {
            hljs.highlightAll();
            console.log('高亮库初始化成功');
        } else {
            console.error('高亮库加载失败');
        }
    });

    // 复制功能
    function copyContent(elementId) {
        const element = document.getElementById(elementId);
        const text = element.textContent.trim();
        copyText(text);
    }

    function copyText(text) {
        if (!text) {
            showToast('无内容可复制', 'error');
            return;
        }
        navigator.clipboard.writeText(text).then(() => {
            showToast('复制成功');
        }).catch(() => {
            showToast('复制失败，请手动复制', 'error');
        });
    }

    // 提示框
    function showToast(message, type = 'success') {
        const toast = document.getElementById('copyToast');
        toast.textContent = message;
        toast.style.backgroundColor = type === 'error' ? 'rgba(255, 59, 48, 0.9)' : 'rgba(0, 0, 0, 0.8)';
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
        }, 2000);
    }

    // HTML 实体解码函数
    function decodeHtmlEntities(text) {
        const textarea = document.createElement('textarea');
        textarea.innerHTML = text;
        return textarea.value;
    }

    // 切换SDK语言 - 使用 highlight API 避免重复高亮限制
    function switchSdk(lang) {
        if (!window.hljs) {
            showToast('无法切换', 'error');
            return;
        }

        // 更新按钮状态
        document.querySelectorAll('.sdk-tab').forEach(tab => {
            tab.classList.toggle('active', tab.textContent.trim() === lang);
        });

        const sdkCode = document.getElementById('sdkCode');
        const sdkLangText = document.getElementById('sdkLangText');
        
        const langClass = langMap[lang] || 'plaintext';
        
        // 关键修复：解码 HTML 实体（&lt; 转为 <）
        let codeContent = sdkData[lang] ? sdkData[lang].trim() : '';
        codeContent = decodeHtmlEntities(codeContent);
        
        sdkLangText.textContent = lang;
        
        // 使用 highlight API 直接获取高亮后的 HTML
        try {
            const result = hljs.highlight(codeContent, {
                language: langClass,
                ignoreIllegals: true
            });
            
            sdkCode.className = 'code-content language-' + langClass;
            sdkCode.innerHTML = result.value;
            
            console.log(`切换到 ${lang}，高亮类名：${langClass}`);
        } catch (e) {
            console.error('高亮失败:', e);
            sdkCode.className = 'code-content language-' + langClass;
            sdkCode.textContent = codeContent;
        }
    }
</script>
</body>
</html>