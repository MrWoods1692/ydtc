<?php
// 处理退出请求 - 必须放在所有输出之前
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // 开启 session 以便销毁
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // 删除所有 cookie（包括 HttpOnly）
    if (!empty($_COOKIE)) {
        foreach ($_COOKIE as $name => $value) {
            // 尝试多种 domain
            $domains = ['', '.mrcwoods.com', 'mrcwoods.com', $_SERVER['HTTP_HOST']];
            $paths = ['/', '/main', ''];
            
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
    
    // 清理 session
    $_SESSION = [];
    session_destroy();
    
    // 跳转登录页
    header('Location: ../in/');
    exit();
}
// 1. 处理OPTIONS预检请求（修复CORS+Cookie传递问题）
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

// 2. 全局CORS配置（解决字体/页面跨域）
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, Cookie, Set-Cookie");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400");

// 3. 字体文件CORS特殊配置（解决字体跨域）
if (strpos($_SERVER['REQUEST_URI'], '/font/') !== false) {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, HEAD");
    header("Access-Control-Allow-Headers: Range");
    header("Access-Control-Expose-Headers: Content-Length, Content-Range");
}

// 加载环境变量
static $env;
if (!isset($env)) {
    $envPath = '.env';
    $env = file_exists($envPath) ? parse_ini_file($envPath) : [];
}
if (empty($env)) {
    die("无法加载.env配置文件，请检查文件是否存在！");
}

// 数据库连接
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

// Cookie验证逻辑（优化登录状态失效问题）
$userToken = trim($_COOKIE['user_token'] ?? ''); // 去除首尾空格
$isValidLogin = false;
$loginPrompt = '';

if (empty($userToken)) {
    $loginPrompt = "您尚未登录，请点击下方按钮前往登录！";
} else {
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE token = :token LIMIT 1");
        $stmt->bindValue(':token', $userToken, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch();
        
        if (!$user) {
            $loginPrompt = "登录状态无效，请重新登录！";
        } else {
            $isValidLogin = true;
            // 优化Cookie配置：兼容HTTPS/HTTP，增加容错
            $cookieExpire = isset($env['COOKIE_EXPIRE']) ? (int)$env['COOKIE_EXPIRE'] : 86400 * 7; // 默认7天
            $secureFlag = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'); // 自动判断HTTPS
            
            setcookie(
                'user_token', 
                $userToken, 
                [
                    'expires' => time() + $cookieExpire,
                    'path' => '/',
                    'domain' => '.mrcwoods.com',
                    'secure' => $secureFlag, // 仅HTTPS时启用
                    'httponly' => true, // 开启HttpOnly防XSS
                    'samesite' => 'Lax' // 兼容更多场景，替代None
                ]
            );
        }
    } catch (PDOException $e) {
        $loginPrompt = "验证登录状态失败：" . $e->getMessage();
    }
}

// 导航映射（修复AI锚点+About显示问题）
$navMap = [
    'home' => ['page' => '../home/', 'name' => '主页'],
    'notice' => ['page' => '../announcement/', 'name' => '公告'],
    'album' => ['page' => '../album/', 'name' => '相册'],
    'tc' => ['page' => '../tc/', 'name' => '图床'],
    'aidraw' => ['page' => '../aidraw/', 'name' => 'AI绘画'],
    'uplevel' => ['page' => '../uplevel/', 'name' => '升级'],
    'tools' => ['page' => '../tools/', 'name' => '工具'],
    'open-platform' => ['page' => '../open-platform/', 'name' => '开放平台'],
    'log' => ['page' => '../log/', 'name' => '日志'],
    'about' => ['page' => '../about/', 'name' => '关于'],
    'setting' => ['page' => '../set/', 'name' => '设置']
];

// 网站基础信息
$siteInfo = [
    'title' => '云端图片储存',
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
    (function(c,l,a,r,i,t,y){
        c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
        t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
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

        /* 登录提示 */
        .login-prompt {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: #fff;
            padding: 40px;
            border-radius: <?php echo $siteInfo['borderRadius']; ?>;
            box-shadow: 0 16px 48px rgba(0,0,0,0.12);
            font-size: 18px;
            color: #1d1d1f;
            z-index: 9999;
            text-align: center;
            border: 1px solid #f0f0f0;
            width: 90%;
            max-width: 420px;
            opacity: 1;
            transition: all 0.3s ease;
        }

        @media (max-width: 1024px) {
            .login-prompt {
                display: none !important;
            }
            .mobile-warning {
                display: flex !important;
            }
            /* 新增：小屏幕下隐藏侧边栏和内容区 */
            .sidebar {
                display: none !important;
            }
            .content-area {
                display: none !important;
            }
        }

        .login-prompt-logo {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            margin: 0 auto 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            overflow: hidden;
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
        }

        .login-prompt-text {
            margin-bottom: 32px;
            line-height: 1.6;
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

        /* 侧边栏 */
        .sidebar {
            width: 280px;
            background-color: #ffffff;
            border-radius: 0 <?php echo $siteInfo['borderRadius']; ?> <?php echo $siteInfo['borderRadius']; ?> 0;
            box-shadow: 0 4px 30px rgba(0,0,0,0.06);
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
            box-shadow: 0 4px 30px rgba(0,0,0,0.06);
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
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
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
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
            transition: filter 0.2s ease;
        }

        .nav-link.active .nav-icon {
            filter: brightness(10);
        }

        .sidebar.collapsed .nav-text {
            display: none;
            opacity: 0;
            transition: opacity 0.2s ease;
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
            transition: filter 0.2s ease;
        }

        .action-btn.active .action-icon {
            filter: brightness(10);
        }

        .sidebar.collapsed .action-text {
            display: none;
            opacity: 0;
            transition: opacity 0.2s ease;
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
            <a href="../app/" >
                立即下载APP
            </a>
        <div class="lottie-container" id="lottie-animation"></div>
    </div>

    <?php if ($loginPrompt): ?>
        <div class="login-prompt">
            <div class="login-prompt-logo">
                <img src="<?php echo $siteInfo['logo']; ?>" alt="<?php echo $siteInfo['title']; ?>" loading="eager">
            </div>
            <h3 class="login-prompt-title"><?php echo $siteInfo['title']; ?></h3>
            <div class="login-prompt-text <?php echo empty($userToken) ? '' : 'error'; ?>">
                <?php echo $loginPrompt; ?>
            </div>
            <a href="<?php echo $env['LOGIN_PAGE']; ?>" class="login-btn">
                <img src="../svg/邮箱.svg" class="login-btn-icon" alt="登录" loading="eager">
                前往登录
            </a>
        </div>
    <?php endif; ?>

    <?php if ($isValidLogin): ?>
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-logo">
                <img src="<?php echo $siteInfo['logo']; ?>" alt="<?php echo $siteInfo['title']; ?>" loading="lazy">
            </div>

            <button class="sidebar-toggle" id="sidebar-toggle">
                <span class="collapse-icon" style="display: block;">
                    <svg t="1770122380402" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" width="20" height="20"><path d="M896 853.333333H128v-85.333333h768v85.333333zM341.76 226.901333L205.994667 362.666667l135.765333 135.765333-60.330667 60.330667L85.333333 362.666667l196.096-196.096L341.76 226.901333zM896 554.666667h-384v-85.333334h384v85.333334z m0-298.666667h-384V170.666667h384v85.333333z" fill="#303133"></path></svg>
                </span>
                <span class="expand-icon" style="display: none;">
                    <svg t="1770122411389" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" width="20" height="20"><path d="M896 853.333333H128v-85.333333h768v85.333333z m42.666667-490.666666l-196.096 196.096-60.330667-60.330667L818.005333 362.666667 682.24 226.901333l60.330667-60.330666L938.666667 362.666667zM512 554.666667H128v-85.333334h384v85.333334z m0-298.666667H128V170.666667h384v85.333333z" fill="#303133"></path></svg>
                </span>
            </button>

            <ul class="nav-list">
                <?php foreach ($navMap as $anchor => $item): ?>
                    <?php if ($anchor !== 'setting'): // 仅排除setting，其他都显示 ?>
                        <li class="nav-item">
                            <a href="javascript:;" 
                               class="nav-link" 
                               data-anchor="<?php echo $anchor; ?>"
                               data-target="<?php echo $env['BASE_URL'] . $item['page']; ?>"> <!-- 拼接完整URL -->
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
                <a href="javascript:;" 
                   class="action-btn" 
                   data-anchor="setting"
                   data-target="<?php echo $env['BASE_URL'] . $navMap['setting']['page']; ?>">
                    <img src="../svg/设置.svg" class="action-icon" alt="设置" loading="lazy">
                    <span class="action-text">设置</span>
                </a>
                <button class="action-btn" id="logout-btn">
                    <img src="../svg/退出.svg" class="action-icon" alt="退出登录" loading="lazy">
                    <span class="action-text">退出登录</span>
                </button>
            </div>
        </aside>

        <main class="content-area">
            <div class="content-box" id="content-box">
                <div class="placeholder">请点击左侧导航栏选择要访问的页面</div>
                <!-- 修复sandbox权限：替换allow-all为具体权限列表 -->
<iframe class="content-iframe" id="content-iframe" 
        sandbox="allow-same-origin allow-scripts allow-forms allow-popups allow-modals allow-orientation-lock allow-pointer-lock allow-presentation allow-top-navigation allow-top-navigation-by-user-activation allow-downloads allow-storage-access-by-user-activation"
        referrerpolicy="origin-when-cross-origin"
        frameborder="0"
        allowfullscreen
        loading="lazy"></iframe>
            </div>
        </main>
    <?php endif; ?>

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
                'lottie-animation', 'mobile-warning' // 新增：缓存移动端提示元素
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
        const baseUrl = '<?php echo $env['BASE_URL']; ?>';
        const borderRadius = '<?php echo $siteInfo['borderRadius']; ?>';
        const mouseCursor = '<?php echo $siteInfo['mouseCursor']; ?>';

        // ===== 新增：窗口尺寸检查函数 =====
        function checkScreenSize() {
            const isSmallScreen = window.innerWidth <= 1024;
            const sidebar = DOM.sidebar;
            const contentArea = document.querySelector('.content-area');
            
            // 如果是小屏幕，隐藏侧边栏和内容区
            if (sidebar) {
                sidebar.style.display = isSmallScreen ? 'none' : 'flex';
            }
            if (contentArea) {
                contentArea.style.display = isSmallScreen ? 'none' : 'block';
            }
            
            // 控制移动端提示显示
            if (DOM.mobilewarning) {
                DOM.mobilewarning.style.display = isSmallScreen ? 'flex' : 'none';
            }
        }

        // ===== 新增：监听窗口大小变化 =====
        // 使用节流优化性能
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                checkScreenSize();
            }, 200);
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

        // 侧边栏伸缩
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
                const currentHash = window.location.hash.replace('#', '') || 'home';
                const targetLink = document.querySelector(`[data-anchor="${currentHash}"]`);
                if (targetLink) {
                    targetLink.click();
                    setActiveNav(targetLink);
                }
            }, 50);
        }

        function setActiveNav(activeEl) {
            document.querySelectorAll('.nav-link.active, .action-btn.active').forEach(el => {
                el.classList.remove('active');
            });
            activeEl.classList.add('active');
        }

        window.addEventListener('hashchange', handleHashChange, { capture: true });
        document.addEventListener('DOMContentLoaded', handleHashChange, { once: true });

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
                DOM.contentiframe.timeout = 0; // 允许长时间加载
            }
        });

// 退出登录
if (DOM.logoutbtn) {
    DOM.logoutbtn.addEventListener('click', () => {
        // 第一步：JS 强制删除所有非 HttpOnly cookie
        const hostname = window.location.hostname;
        const isHttps = window.location.protocol === 'https:';
        
        // 所有可能的 domain 变体
        const domains = ['', hostname, '.' + hostname];
        const parts = hostname.split('.');
        if (parts.length > 2) {
            const rootDomain = parts.slice(-2).join('.');
            domains.push(rootDomain, '.' + rootDomain);
        }
        
        // 所有可能的 path
        const paths = ['/', '/main', '/in', '', window.location.pathname];
        
        // 获取并强制添加已知 cookie 名
        const cookieNames = new Set(['user_token', 'user_email', 'token', 'email', 'user', 'auth', 'session', 'PHPSESSID', 'uid', 'id']);
        document.cookie.split(';').forEach(c => {
            const name = c.split('=')[0].trim();
            if (name) cookieNames.add(name);
        });
        
        // 疯狂删除模式
        cookieNames.forEach(name => {
            domains.forEach(domain => {
                paths.forEach(path => {
                    // 多种组合
                    const configs = [
                        {secure: false, sameSite: ''},
                        {secure: isHttps, sameSite: 'Lax'},
                        {secure: isHttps, sameSite: 'Strict'},
                        {secure: isHttps, sameSite: 'None'}
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
        
        // 第二步：跳转 PHP 端删除 HttpOnly cookie 并清理服务端 session
        window.location.href = '?action=logout';
    });
}
        // 粒子特效（节流）
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

        // 首屏后配置
        document.addEventListener('DOMContentLoaded', () => {
            // ===== 新增：初始化时检查屏幕尺寸 =====
            checkScreenSize();
            
            if (DOM.contentiframe) {
                DOM.contentiframe.style.borderRadius = borderRadius;
                DOM.contentiframe.style.pointerEvents = 'auto';
                DOM.contentiframe.style.cursor = `url('${mouseCursor}'), auto !important`;
            }
        }, { once: true });
    </script>
</body>
</html>
