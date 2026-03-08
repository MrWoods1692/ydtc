<?php
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!empty($_COOKIE)) {
        foreach ($_COOKIE as $name => $value) {
            $domains = ['', '.mrcwoods.com', 'mrcwoods.com', $_SERVER['HTTP_HOST']];
            $paths = ['/', '/open-platform', ''];
            foreach ($domains as $domain) {
                foreach ($paths as $path) {
                    setcookie($name, '', [
                        'expires' => 1,
                        'path' => $path,
                        'domain' => $domain,
                        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                }
            }
        }
    }
    $_SESSION = [];
    session_destroy();
    // 清除验证缓存
    setcookie('captcha_verified', '', [
        'expires' => 1,
        'path' => '/',
        'domain' => '.mrcwoods.com',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    header('Location: ../in/');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, Cookie, Set-Cookie");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Max-Age: 86400");
    header("Content-Length: 0");
    header("Content-Type: text/plain");
    exit();
}
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, Cookie, Set-Cookie");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400");
if (strpos($_SERVER['REQUEST_URI'], '/font/') !== false) {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, HEAD");
    header("Access-Control-Allow-Headers: Range");
    header("Access-Control-Expose-Headers: Content-Length, Content-Range");
}
static $env;
if (!isset($env)) {
    $envPath = '../in/.env';
    $env = file_exists($envPath) ? parse_ini_file($envPath) : [];
}
if (empty($env)) {
    die("无法加载.env配置文件，请检查文件是否存在！");
}
static $pdo;
if (!isset($pdo)) {
    try {
        $dsn = "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset={$env['DB_CHARSET']};connect_timeout=3";
        $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]);
    } catch (PDOException $e) {
        die("数据库连接失败：" . $e->getMessage());
    }
}
// 登录状态仅内部判断，不影响人机验证
$userToken = trim($_COOKIE['user_token'] ?? '');
$isValidLogin = false;
if (!empty($userToken)) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE token = :token LIMIT 1");
        $stmt->bindValue(':token', $userToken, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch();
        if ($user) {
            $isValidLogin = true;
            $cookieExpire = isset($env['COOKIE_EXPIRE']) ? (int)$env['COOKIE_EXPIRE'] : 86400;
            $secureFlag = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
            setcookie(
                'user_token',
                $userToken,
                [
                    'expires' => time() + $cookieExpire,
                    'path' => '/',
                    'domain' => '.mrcwoods.com',
                    'secure' => $secureFlag,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );
        }
    } catch (PDOException $e) {
        // 登录状态验证失败不影响主流程
    }
}
$navMap = [
    'Access_management' => ['page' => '../open-platform_manage/', 'name' => '接入管理'],
    'list' => ['page' => '../open-platform_list/', 'name' => '接口列表'],
    'Dosage_information' => ['page' => '../open-platform_dosage/', 'name' => '用量信息'],
    'Operation_log' => ['page' => '../open-platform_log/', 'name' => '操作日志'],
    'OAuth' => ['page' => '../OAuth/public/console.php', 'name' => 'OAuth 2'],
    'ydtc' => ['page' => '../ydtc/', 'name' => '图片储存'],
];
// 网站基础信息
$siteInfo = [
    'title' => '开放平台控制台',
    'logo' => '../images/logo.png',
    'borderRadius' => '24px',
    'mouseCursor' => '../svg/鼠标.svg',
    'fontPath' => '/font/' // 字体绝对路径
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <script type="text/javascript">
        (function(c, l, a, r, i, t, y) {
            c[a] = c[a] || function() {
                (c[a].q = c[a].q || []).push(arguments)
            };
            t = l.createElement(r);
            t.async = 1;
            t.src = "https://www.clarity.ms/tag/" + i;
            y = l.getElementsByTagName(r)[0];
            y.parentNode.insertBefore(t, y);
        })(window, document, "clarity", "script", "vhumct5cuc");
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Access-Control-Allow-Origin" content="*">
    <meta http-equiv="Access-Control-Allow-Credentials" content="true">
    <meta name="description" content="安全稳定的云端图片储存服务，支持原图备份、分类、多端同步。免费起步，TB级空间任选，一键分享外链，摄影师与设计师的首选云相册。">
    <meta name="description" content="全球CDN加速的云端图片储存，秒开预览不卡顿。自动压缩省流量，支持多种格式托管，外链直传论坛与电商，新用户送2GB空间。">
    <meta name="description" content="端到端加密的私密云端图库，本地密钥掌控数据主权。防误删回收站、异地容灾备份，家庭照片与企业素材的安全保险箱。">
    <meta name="description" content="摄影师专属云端图片仓库，EXIF信息完整保留，AI智能体找图快。">
    <meta name="description" content="免费好用的云端图片储存，储存空间免费送。API接口丰富，5分钟接入网站图床，支持防盗链。">
    <!-- 标准权限策略 -->
    <meta http-equiv="Permissions-Policy" content="fullscreen=*, geolocation=*, microphone=*, camera=*, clipboard-read=*, clipboard-write=*">
    <title><?php echo $siteInfo['title']; ?></title>
    <!-- 字体引入（修复CORS + 跨域属性） -->
    <style>
        @font-face {
            font-family: 'Alimama Agile';
            src: url('<?php echo $siteInfo['fontPath']; ?>AlimamaAgileVF-Thin.woff2') format('woff2'),
                url('<?php echo $siteInfo['fontPath']; ?>AlimamaAgileVF-Thin.woff') format('woff'),
                url('<?php echo $siteInfo['fontPath']; ?>AlimamaAgileVF-Thin.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
            unicode-range: U+4E00-9FFF, U+0020-007E;
        }
        /* 全局样式 + 字体 + 鼠标样式 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Alimama Agile', -apple-system, BlinkMacSystemFont, sans-serif !important;
            cursor: url('<?php echo $siteInfo['mouseCursor']; ?>'), auto !important;
            transition: none;
            will-change: auto;
        }
        body {
            background-color: #f5f5f7;
            display: flex;
            height: 100vh;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }
        /* 移除所有链接和元素的默认下划线 */
        a {
            text-decoration: none !important;
        }
        /* 粒子容器 */
        #particle-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 9999;
        }
        /* 移动端提示 */
        .mobile-warning {
            display: none;
            width: 100%;
            height: 100vh;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 20px;
            color: #1d1d1f;
            background: linear-gradient(135deg, #f5f5f7 0%, #e8e8ed 100%);
            z-index: 9998;
        }
        .mobile-warning p {
            font-size: 20px;
            margin-bottom: 30px;
            max-width: 600px;
            line-height: 1.5;
        }
        .lottie-container {
            width: 300px;
            height: 300px;
        }
        /* 全局人机验证样式（强化视觉层级） */
        .login-prompt {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: #fff;
            padding: 40px;
            border-radius: <?php echo $siteInfo['borderRadius']; ?>;
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.18);
            font-size: 18px;
            color: #1d1d1f;
            z-index: 9999;
            text-align: center;
            border: 1px solid #f0f0f0;
            width: 90%;
            max-width: 420px;
            opacity: 1;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(8px);
            background: rgba(255, 255, 255, 0.95);
        }
        @media (max-width: 1024px) {
            .login-prompt {
                display: flex !important;
                flex-direction: column;
                justify-content: center;
                height: 100vh;
                border-radius: 0;
                max-width: 100%;
                padding: 30px 20px;
            }
            .mobile-warning {
                display: flex !important;
            }
            /* 小屏幕下隐藏所有内容 */
            .sidebar, .content-area, #main-content {
                display: none !important;
            }
        }
        .login-prompt-logo {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            margin: 0 auto 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            border: 2px solid #f0f0f0;
        }
        .login-prompt-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .login-prompt-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #0071e3;
        }
        .login-prompt-text {
            margin-bottom: 32px;
            line-height: 1.6;
            color: #6e6e73;
        }
        .login-prompt-text.error {
            color: #ff3b30;
        }
        .login-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 32px;
            background-color: #0071e3;
            color: #fff;
            text-decoration: none;
            border-radius: 28px;
            transition: all 0.25s ease-in-out;
            font-size: 16px;
            font-weight: 500;
            width: 100%;
            max-width: 280px;
            gap: 8px;
            border: none;
            cursor: pointer;
        }
        .login-btn:hover {
            background-color: #0077ed;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 113, 227, 0.3);
        }
        .login-btn-icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }
        /* 强化人机验证样式 */
        .captcha-container {
            margin: 0 auto 24px;
            display: flex;
            justify-content: center;
        }
        .captcha-box {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            background-color: #f5f5f7;
            border-radius: 16px;
            border: 2px solid #d1d1d6;
            cursor: pointer;
            transition: all 0.3s ease;
            user-select: none;
            width: 100%;
            max-width: 300px;
        }
        .captcha-box:hover {
            background-color: #e8e8ed;
            border-color: #0071e3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 113, 227, 0.1);
        }
        .captcha-box.verified {
            background-color: #e3f2e3;
            border-color: #34c759;
            box-shadow: 0 4px 12px rgba(52, 199, 89, 0.15);
        }
        .captcha-checkbox {
            width: 28px;
            height: 28px;
            border: 2px solid #86868b;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            background-color: #fff;
        }
        .captcha-box.verified .captcha-checkbox {
            background-color: #34c759;
            border-color: #34c759;
            transform: scale(1.05);
        }
        .captcha-checkbox svg {
            width: 18px;
            height: 18px;
            opacity: 0;
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        .captcha-box.verified .captcha-checkbox svg {
            opacity: 1;
            transform: scale(1.1);
        }
        .captcha-label {
            font-size: 16px;
            color: #1d1d1f;
            font-weight: 500;
            flex: 1;
            text-align: left;
        }
        .captcha-box.verified .captcha-label {
            color: #34c759;
            font-weight: 600;
        }
        /* 侧边栏 */
        .sidebar {
            width: 280px;
            background-color: #ffffff;
            border-radius: 0 <?php echo $siteInfo['borderRadius']; ?> <?php echo $siteInfo['borderRadius']; ?> 0;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.06);
            padding: 24px;
            display: flex;
            flex-direction: column;
            transition: width 0.35s cubic-bezier(0.4, 0, 0.2, 1), padding 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            z-index: 10;
        }
        /* 内容区 */
        .content-area {
            flex: 1;
            padding: 16px;
            overflow: auto;
            transform: translateZ(0);
            height: 100vh;
            scrollbar-width: thin;
        }
        /* 网页显示框 */
        .content-box {
            width: 100%;
            height: 100%;
            background-color: #ffffff;
            border-radius: <?php echo $siteInfo['borderRadius']; ?>;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.06);
            padding: 0;
            display: block;
            position: relative;
            overflow: hidden;
        }
        /* 滚动条美化 */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f5f5f7;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb {
            background: #d1d1d6;
            border-radius: 4px;
        }
        /* 主内容容器（默认隐藏） */
        #main-content {
            display: none;
            width: 100%;
            height: 100vh;
        }
    </style>
    <!-- 非关键样式 -->
    <style id="deferred-styles">
        .sidebar.collapsed {
            width: 88px;
            padding: 24px 16px;
        }
        .sidebar-logo {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            overflow: hidden;
            margin: 0 auto 24px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }
        .sidebar-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .sidebar.collapsed .sidebar-logo {
            width: 56px;
            height: 56px;
        }
        .sidebar-toggle {
            background-color: #f5f5f7;
            border: none;
            border-radius: 16px;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            cursor: url('<?php echo $siteInfo['mouseCursor']; ?>'), pointer !important;
            transition: all 0.25s ease-in-out;
        }
        .sidebar-toggle:hover {
            background-color: #e8e8ed;
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        .sidebar-toggle:active {
            transform: scale(0.98);
        }
        .nav-list {
            list-style: none;
            flex: 1;
            margin-bottom: 24px;
        }
        .nav-item {
            margin-bottom: 4px;
            border-radius: 12px;
            overflow: hidden;
        }
        .nav-link {
            display: flex;
            align-items: center;
            padding: 14px 18px;
            border-radius: 12px;
            color: #1d1d1f;
            text-decoration: none;
            transition: all 0.2s ease-in-out;
            font-size: 16px;
            font-weight: 400;
            cursor: url('<?php echo $siteInfo['mouseCursor']; ?>'), pointer !important;
        }
        .nav-link.active {
            background-color: #0071e3;
            color: #ffffff;
        }
        .nav-link:hover:not(.active) {
            background-color: #f5f5f7;
        }
        .nav-icon {
            width: 24px;
            height: 24px;
            margin-right: 18px;
            flex-shrink: 0;
            filter: brightness(1);
            transition: filter 0.2s ease, margin-right 0.3s ease;
        }
        .nav-link.active .nav-icon {
            filter: brightness(10);
        }
        .nav-text {
            white-space: nowrap;
            opacity: 1;
            max-width: 150px;
            overflow: hidden;
            transition: opacity 0.3s ease 0.1s, max-width 0.3s ease;
        }
        .sidebar.collapsed .nav-text {
            opacity: 0;
            max-width: 0;
            transition: opacity 0.1s ease, max-width 0.3s ease;
        }
        .sidebar.collapsed .nav-icon {
            margin-right: 0;
        }
        .sidebar-actions {
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-top: auto;
        }
        .action-btn {
            display: flex;
            align-items: center;
            padding: 14px 18px;
            border-radius: 12px;
            color: #1d1d1f;
            text-decoration: none;
            border: none;
            background: transparent;
            width: 100%;
            cursor: url('<?php echo $siteInfo['mouseCursor']; ?>'), pointer !important;
            transition: all 0.2s ease-in-out;
            font-size: 16px;
            font-weight: 400;
        }
        .action-btn.active {
            background-color: #0071e3;
            color: #ffffff;
        }
        .action-btn:hover:not(.active) {
            background-color: #f5f5f7;
        }
        .action-icon {
            width: 24px;
            height: 24px;
            margin-right: 18px;
            flex-shrink: 0;
            filter: brightness(1);
            transition: filter 0.2s ease, margin-right 0.3s ease;
        }
        .action-btn.active .action-icon {
            filter: brightness(10);
        }
        .action-text {
            white-space: nowrap;
            opacity: 1;
            max-width: 150px;
            overflow: hidden;
            transition: opacity 0.3s ease 0.1s, max-width 0.3s ease;
        }
        .sidebar.collapsed .action-text {
            opacity: 0;
            max-width: 0;
            transition: opacity 0.1s ease, max-width 0.3s ease;
        }
        .sidebar.collapsed .action-icon {
            margin-right: 0;
        }
        /* 占位符 */
        .content-box .placeholder {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #86868b;
            font-size: 18px;
            z-index: 1;
        }
        /* iframe样式（修复sandbox权限） */
        .content-iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
            border-radius: <?php echo $siteInfo['borderRadius']; ?>;
            display: none;
            z-index: 2;
            transform: translateZ(0);
            -webkit-backface-visibility: hidden;
            backface-visibility: hidden;
            pointer-events: auto;
            cursor: url('<?php echo $siteInfo['mouseCursor']; ?>'), auto !important;
        }
    </style>
    <!-- 预加载优化（移除未使用的，修复字体跨域） -->
    <link rel="preload" href="<?php echo $siteInfo['logo']; ?>" as="image" fetchpriority="high">
    <link rel="preload" href="<?php echo $siteInfo['fontPath']; ?>AlimamaAgileVF-Thin.woff2" as="font" type="font/woff2" crossorigin="anonymous">
    <!-- 网站图标 -->
    <link rel="icon" href="<?php echo $siteInfo['logo']; ?>" type="image/png">
</head>
<body>
    <div id="particle-container"></div>
    <div class="mobile-warning">
        <p>抱歉，本页面仅支持大屏幕设备访问，请放大浏览器窗口或使用更大尺寸的设备！</p>
        <div class="lottie-container" id="lottie-animation"></div>
    </div>

    <!-- 全局强制人机验证（所有用户必须验证） -->
    <div class="login-prompt" id="global-captcha">
        <div class="login-prompt-logo">
            <img src="<?php echo $siteInfo['logo']; ?>" alt="<?php echo $siteInfo['title']; ?>" loading="eager">
        </div>
        <h3 class="login-prompt-title">安全验证</h3>
        <div class="login-prompt-text">
            为保障平台安全，请完成人机验证后访问
        </div>
        <div class="captcha-container">
            <div class="captcha-box" id="captcha-box">
                <div class="captcha-checkbox">
                    <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </div>
                <span class="captcha-label">我是人类，点击验证</span>
            </div>
        </div>
    </div>

    <!-- 主内容容器（验证通过后显示） -->
    <div id="main-content">
        <?php if ($isValidLogin): ?>
            <aside class="sidebar" id="sidebar">
                <div class="sidebar-logo">
                    <img src="<?php echo $siteInfo['logo']; ?>" alt="<?php echo $siteInfo['title']; ?>" loading="lazy">
                </div>
                <button class="sidebar-toggle" id="sidebar-toggle">
                    <span class="collapse-icon" style="display: block;">
                        <svg t="1770122380402" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" width="20" height="20">
                            <path d="M896 853.333333H128v-85.333333h768v85.333333zM341.76 226.901333L205.994667 362.666667l135.765333 135.765333-60.330667 60.330667L85.333333 362.666667l196.096-196.096L341.76 226.901333zM896 554.666667h-384v-85.333334h384v85.333334z m0-298.666667h-384V170.666667h384v85.333333z" fill="#303133"></path>
                        </svg>
                    </span>
                    <span class="expand-icon" style="display: none;">
                        <svg t="1770122411389" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" width="20" height="20">
                            <path d="M896 853.333333H128v-85.333333h768v85.333333z m42.666667-490.666666l-196.096 196.096-60.330667-60.330667L818.005333 362.666667 682.24 226.901333l60.330667-60.330666L938.666667 362.666667zM512 554.666667H128v-85.333334h384v85.333334z m0-298.666667H128V170.666667h384v85.333333z" fill="#303133"></path>
                        </svg>
                    </span>
                </button>
                <ul class="nav-list">
                    <?php foreach ($navMap as $anchor => $item): ?>
                        <?php if ($anchor !== 'setting'): ?>
                            <li class="nav-item">
                                <a href="javascript:;"
                                    class="nav-link"
                                    data-anchor="<?php echo $anchor; ?>"
                                    data-target="/open-platform/<?php echo $item['page']; ?>">
                                    <img src="../svg/<?php echo $item['name']; ?>.svg"
                                        class="nav-icon"
                                        alt="<?php echo $item['name']; ?>"
                                        loading="lazy">
                                    <span class="nav-text"><?php echo $item['name']; ?></span>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
                <div class="sidebar-actions">
                    <button class="action-btn" id="logout-btn">
                        <img src="../svg/退出.svg" class="action-icon" alt="退出登录" loading="lazy">
                        <span class="action-text">退出登录</span>
                    </button>
                </div>
            </aside>
            <main class="content-area">
                <div class="content-box" id="content-box">
                    <div class="placeholder"></div>
                    <iframe class="content-iframe" id="content-iframe"
                        sandbox="allow-same-origin allow-scripts allow-forms allow-popups allow-modals allow-orientation-lock allow-pointer-lock allow-presentation allow-top-navigation allow-top-navigation-by-user-activation allow-downloads allow-storage-access-by-user-activation"
                        referrerpolicy="origin-when-cross-origin"
                        frameborder="0"
                        allowfullscreen
                        loading="lazy"></iframe>
                </div>
            </main>
        <?php else: ?>
            <!-- 未登录用户验证后显示的登录提示 -->
            <div class="login-prompt" style="z-index: 9998; margin-top: 20px;">
                <div class="login-prompt-logo">
                    <img src="<?php echo $siteInfo['logo']; ?>" alt="<?php echo $siteInfo['title']; ?>" loading="eager">
                </div>
                <h3 class="login-prompt-title"><?php echo $siteInfo['title']; ?></h3>
                <div class="login-prompt-text">
                    您尚未登录，请点击下方按钮前往登录！
                </div>
                <a href="<?php echo $env['LOGIN_PAGE']; ?>" class="login-btn">
                    <img src="../svg/邮箱.svg" class="login-btn-icon" alt="登录" loading="eager">
                    前往登录
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
   

    // 加载非关键样式
    (function() {
        const deferredStyles = document.getElementById('deferred-styles');
        if (deferredStyles) {
            const head = document.querySelector('head');
            head.appendChild(deferredStyles);
            deferredStyles.removeAttribute('id');
        }
    })();

    // DOM缓存
    const DOM = (() => {
        const el = {};
        [
            'sidebar', 'sidebar-toggle', 'content-iframe',
            'content-box', 'logout-btn', 'particle-container',
            'lottie-animation', 'mobile-warning', 'global-captcha',
            'captcha-box', 'main-content'
        ].forEach(id => {
            el[id.replace(/-/g, '')] = document.getElementById(id);
        });
        el.collapseIcon = document.querySelector('.collapse-icon');
        el.expandIcon = document.querySelector('.expand-icon');
        el.placeholder = document.querySelector('.content-box .placeholder');
        return el;
    })();

    // 基础配置
    const navMap = <?php echo json_encode($navMap); ?>;
    const baseUrl = '/open-platform/';
    const borderRadius = '<?php echo $siteInfo['borderRadius']; ?>';
    const mouseCursor = '<?php echo $siteInfo['mouseCursor']; ?>';

    // ===== 强化全局人机验证核心逻辑 =====
    const CAPTCHA_KEY = 'global_platform_captcha_verified_v2';
    const CAPTCHA_EXPIRE = 60 * 60 * 1000; // 1小时有效期
    let isCaptchaVerified = false;
    let mouseMovements = [];
    let keyboardEvents = 0;
    let scrollEvents = 0;
    let hasHumanInteraction = false;

    // 检查验证状态（含设备指纹校验）
    function checkCaptchaStatus() {
        const stored = localStorage.getItem(CAPTCHA_KEY);
        if (!stored) return false;
        try {
            const data = JSON.parse(stored);
            const now = Date.now();
            const isExpired = now - data.timestamp > CAPTCHA_EXPIRE;
            const isVerified = data.verified === true;
            const isSameDevice = (
                data.userAgent === navigator.userAgent.slice(0, 100) &&
                data.screenSize === `${window.screen.width}x${window.screen.height}` &&
                data.language === navigator.language
            );
            if (isExpired || !isVerified || !isSameDevice) {
                localStorage.removeItem(CAPTCHA_KEY);
                return false;
            }
            return true;
        } catch (e) {
            localStorage.removeItem(CAPTCHA_KEY);
            return false;
        }
    }

    // 保存验证状态（存储设备指纹）
    function saveCaptchaStatus() {
        const data = {
            verified: true,
            timestamp: Date.now(),
            userAgent: navigator.userAgent.slice(0, 100),
            screenSize: `${window.screen.width}x${window.screen.height}`,
            language: navigator.language,
            browserType: getBrowserType()
        };
        localStorage.setItem(CAPTCHA_KEY, JSON.stringify(data));
    }

    // 获取浏览器类型（辅助指纹校验）
    function getBrowserType() {
        if (navigator.userAgent.includes('Chrome')) return 'chrome';
        if (navigator.userAgent.includes('Firefox')) return 'firefox';
        if (navigator.userAgent.includes('Safari')) return 'safari';
        if (navigator.userAgent.includes('Edge')) return 'edge';
        return 'other';
    }

    // 跟踪多维度人类行为
    function trackHumanBehavior() {
        document.addEventListener('mousemove', (e) => {
            mouseMovements.push({
                x: e.clientX,
                y: e.clientY,
                time: Date.now(),
                movementX: e.movementX,
                movementY: e.movementY
            });
            if (mouseMovements.length > 30) mouseMovements.shift();
        }, { passive: true });

        document.addEventListener('keydown', () => {
            keyboardEvents++;
            hasHumanInteraction = true;
        }, { passive: true });

        document.addEventListener('scroll', () => {
            scrollEvents++;
            hasHumanInteraction = true;
        }, { passive: true });

        document.addEventListener('touchmove', () => {
            hasHumanInteraction = true;
        }, { passive: true });
    }

    // 验证鼠标移动是否自然
    function isNaturalMouseMovement() {
        if (mouseMovements.length < 8) return false;
        const speeds = [];
        for (let i = 1; i < mouseMovements.length; i++) {
            const timeDiff = mouseMovements[i].time - mouseMovements[i-1].time;
            if (timeDiff <= 0) continue;
            const distance = Math.sqrt(
                Math.pow(mouseMovements[i].x - mouseMovements[i-1].x, 2) +
                Math.pow(mouseMovements[i].y - mouseMovements[i-1].y, 2)
            );
            speeds.push(distance / timeDiff);
        }
        if (speeds.length < 5) return false;
        const avgSpeed = speeds.reduce((a, b) => a + b, 0) / speeds.length;
        const variance = speeds.reduce((sum, s) => sum + Math.pow(s - avgSpeed, 2), 0) / speeds.length;
        const hasPauses = mouseMovements.some((move, index) => {
            return index > 0 && (move.time - mouseMovements[index-1].time) > 100;
        });
        return variance > 0.8 && hasPauses;
    }

    // 唯一的验证执行函数（无重复）
    function performVerification() {
        if (isCaptchaVerified) return;
        const captchaBox = DOM.captchabox;
        const captchaLabel = captchaBox.querySelector('.captcha-label');
        
        captchaBox.style.pointerEvents = 'none';
        captchaLabel.textContent = '验证中...';
        const delay = 800 + Math.floor(Math.random() * 700);

        setTimeout(() => {
            const hasNaturalMouse = isNaturalMouseMovement();
            const hasKeyOrScroll = keyboardEvents > 0 || scrollEvents > 0;
            const hasInteraction = hasHumanInteraction || hasNaturalMouse || hasKeyOrScroll;
            const isHuman = hasInteraction || mouseMovements.length > 15;

            if (isHuman) {
                isCaptchaVerified = true;
                captchaBox.classList.add('verified');
                captchaLabel.textContent = '验证通过！';
                saveCaptchaStatus();

                setTimeout(() => {
                    DOM.globalcaptcha.style.opacity = '0';
                    DOM.globalcaptcha.style.transform = 'translate(-50%, -50%) scale(0.9)';
                    setTimeout(() => {
                        DOM.globalcaptcha.style.display = 'none';
                        // 动态设置布局（登录=flex，未登录=block）
                        DOM.maincontent.style.display = <?php echo $isValidLogin ? "'flex'" : "'block'"; ?>;
                        checkScreenSize();
                    }, 300);
                }, 600);
            } else {
                captchaLabel.textContent = '验证失败，请重试';
                setTimeout(() => {
                    captchaBox.classList.remove('verified');
                    captchaLabel.textContent = '我是人类，点击验证';
                    captchaBox.style.pointerEvents = 'auto';
                    // 重新绑定事件，避免点击无反应
                    captchaBox.removeEventListener('click', performVerification);
                    captchaBox.addEventListener('click', performVerification);
                }, 1500);
            }
        }, delay);
    }

    // 窗口尺寸检查函数
    function checkScreenSize() {
        const isSmallScreen = window.innerWidth <= 1024;
        const sidebar = DOM.sidebar;
        const contentArea = document.querySelector('.content-area');
        if (sidebar) sidebar.style.display = isSmallScreen ? 'none' : 'flex';
        if (contentArea) contentArea.style.display = isSmallScreen ? 'none' : 'block';
        if (DOM.mobilewarning) DOM.mobilewarning.style.display = isSmallScreen ? 'flex' : 'none';
    }

    // 侧边栏伸缩逻辑
    if (DOM.sidebartoggle) {
        let isCollapsed = false;
        DOM.sidebartoggle.addEventListener('click', () => {
            isCollapsed = !isCollapsed;
            DOM.sidebar.classList.toggle('collapsed', isCollapsed);
            DOM.collapseIcon.style.display = isCollapsed ? 'none' : 'block';
            DOM.expandIcon.style.display = isCollapsed ? 'block' : 'none';
            DOM.sidebar.style.transform = 'translateZ(0)';
        });
    }

    // 锚点匹配（防抖）
    let hashChangeTimer = null;
    function handleHashChange() {
        clearTimeout(hashChangeTimer);
        hashChangeTimer = setTimeout(() => {
            const currentHash = window.location.hash.replace('#', '') || 'Access_management';
            const targetLink = document.querySelector(`[data-anchor="${currentHash}"]`);
            if (targetLink) {
                targetLink.click();
                setActiveNav(targetLink);
            }
        }, 50);
    }

    function setActiveNav(activeEl) {
        document.querySelectorAll('.nav-link.active, .action-btn.active').forEach(el => el.classList.remove('active'));
        activeEl.classList.add('active');
    }

    // 导航点击逻辑
    document.addEventListener('click', (e) => {
        const target = e.target.closest('[data-target]');
        if (!target || !DOM.contentiframe) return;
        e.preventDefault();
        setActiveNav(target);
        const targetAnchor = target.getAttribute('data-anchor');
        window.history.pushState({}, '', `${baseUrl}#${targetAnchor}`);
        DOM.placeholder.style.display = 'none';
        DOM.contentiframe.style.display = 'block';
        const targetPage = target.getAttribute('data-target');
        if (DOM.contentiframe.src !== targetPage) {
            DOM.contentiframe.src = targetPage;
            DOM.contentiframe.onload = function() {
                this.style.display = 'block';
                this.onload = null;
            };
            DOM.contentiframe.timeout = 0;
        }
    });

    // 退出登录逻辑
    if (DOM.logoutbtn) {
        DOM.logoutbtn.addEventListener('click', () => {
            const hostname = window.location.hostname;
            const isHttps = window.location.protocol === 'https:';
            const domains = ['', hostname, '.' + hostname];
            const parts = hostname.split('.');
            if (parts.length > 2) {
                const rootDomain = parts.slice(-2).join('.');
                domains.push(rootDomain, '.' + rootDomain);
            }
            const paths = ['/', '/open-platform', '/in', '', window.location.pathname];
            const cookieNames = new Set(['user_token', 'user_email', 'token', 'email', 'user', 'auth', 'session', 'PHPSESSID', 'uid', 'id']);
            
            document.cookie.split(';').forEach(c => {
                const name = c.split('=')[0].trim();
                if (name) cookieNames.add(name);
            });

            cookieNames.forEach(name => {
                domains.forEach(domain => {
                    paths.forEach(path => {
                        const configs = [
                            { secure: false, sameSite: '' },
                            { secure: isHttps, sameSite: 'Lax' },
                            { secure: isHttps, sameSite: 'Strict' },
                            { secure: isHttps, sameSite: 'None' }
                        ];
                        configs.forEach(cfg => {
                            let str = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=-99999999;`;
                            if (path) str += ` path=${path};`;
                            if (domain) str += ` domain=${domain};`;
                            if (cfg.secure) str += ` secure;`;
                            if (cfg.sameSite) str += ` SameSite=${cfg.sameSite};`;
                            document.cookie = str;
                        });
                    });
                });
            });

            localStorage.removeItem(CAPTCHA_KEY);
            window.location.href = '?action=logout';
        });
    }

    // 粒子特效
    let lastParticleTime = 0;
    function createParticles(x, y) {
        const now = Date.now();
        if (now - lastParticleTime < 100 || window.innerWidth <= 1024) return;
        lastParticleTime = now;
        const count = 8;
        const colors = ['#0071e3', '#86868b', '#1d1d1f', '#f5f5f7'];
        const container = DOM.particlecontainer;
        const fragment = document.createDocumentFragment();

        for (let i = 0; i < count; i++) {
            const particle = document.createElement('div');
            const size = Math.random() * 5 + 2;
            Object.assign(particle.style, {
                position: 'absolute',
                width: `${size}px`,
                height: `${size}px`,
                borderRadius: '50%',
                backgroundColor: colors[Math.floor(Math.random() * colors.length)],
                left: `${x}px`,
                top: `${y}px`,
                opacity: '0.8',
                pointerEvents: 'none',
                zIndex: '9999',
                transition: 'all 0.4s cubic-bezier(0.16, 1, 0.3, 1)',
                transform: 'translateZ(0)',
                cursor: `url('${mouseCursor}'), auto !important`
            });
            fragment.appendChild(particle);
            requestAnimationFrame(() => {
                const angle = Math.random() * Math.PI * 2;
                const distance = Math.random() * 40 + 10;
                particle.style.transform = `translate(${Math.cos(angle) * distance}px, ${Math.sin(angle) * distance}px) translateZ(0)`;
                particle.style.opacity = '0';
                setTimeout(() => particle.remove(), 400);
            });
        }
        container.appendChild(fragment);
    }

    document.addEventListener('click', (e) => {
        createParticles(e.clientX, e.clientY);
    });

    // 页面卸载清理
    window.addEventListener('beforeunload', () => {
        if (DOM.particlecontainer) DOM.particlecontainer.innerHTML = '';
        if (DOM.contentiframe) DOM.contentiframe.src = 'about:blank';
    }, { once: true });

    // 窗口大小变化监听（节流）
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => checkScreenSize(), 200);
    });

    // Lottie按需加载
    if (window.innerWidth <= 1024 && DOM.lottieanimation) {
        const script = document.createElement('script');
        script.src = './js/lottie.min.js';
        script.async = true;
        script.onload = () => {
            lottie.loadAnimation({
                container: DOM.lottieanimation,
                renderer: 'svg',
                loop: true,
                autoplay: true,
                path: '../lottie/数据.json',
                rendererSettings: { preserveAspectRatio: 'xMidYMid meet' }
            });
        };
        document.body.appendChild(script);
    }

    // 锚点变化监听
    window.addEventListener('hashchange', handleHashChange, { capture: true });

    // 唯一的DOM初始化事件（无嵌套）
    document.addEventListener('DOMContentLoaded', () => {
        trackHumanBehavior();

        if (checkCaptchaStatus()) {
            DOM.globalcaptcha.style.display = 'none';
            DOM.maincontent.style.display = <?php echo $isValidLogin ? "'flex'" : "'block'"; ?>;
            checkScreenSize();
            handleHashChange();
        } else {
            // 绑定验证事件（防止重复）
            const bindVerifyEvent = () => {
                DOM.captchabox.removeEventListener('click', performVerification);
                DOM.captchabox.addEventListener('click', performVerification);
            };
            bindVerifyEvent();

            document.addEventListener('click', () => {
                hasHumanInteraction = true;
            }, { once: true });
        }

        // 初始化iframe样式
        if (DOM.contentiframe) {
            DOM.contentiframe.style.borderRadius = borderRadius;
            DOM.contentiframe.style.pointerEvents = 'auto';
            DOM.contentiframe.style.cursor = `url('${mouseCursor}'), auto !important`;
        }
    }, { once: true });
</script> <!-- 闭合 script 标签 -->
</body>
</html> <!-- 闭合 html 标签 -->
