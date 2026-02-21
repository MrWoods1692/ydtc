<?php
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '1');

// ===================== 核心配置与函数 =====================
function loadEnv(string $path = '../in/.env'): array
{
    if (!file_exists($path)) {
        die("配置文件 .env 不存在");
    }
    
    $env = parse_ini_file($path);
    if (!$env) {
        die("配置文件 .env 解析失败");
    }
    return $env;
}

function safeRealpath(string $baseDir, string $targetPath): ?string
{
    $realBase = realpath($baseDir);
    $realTarget = realpath($targetPath);
    
    if (!$realBase || !$realTarget) {
        return null;
    }
    
    if (str_starts_with($realTarget, $realBase . DIRECTORY_SEPARATOR)) {
        return $realTarget;
    }
    return null;
}

function groupLogsByDate(array $logs): array
{
    $groupedLogs = [];
    foreach ($logs as $log) {
        preg_match('/^\[(\d{4}-\d{2}-\d{2})\s+\d{2}:\d{2}:\d{2}\]/', $log, $matches);
        $date = $matches[1] ?? '未知日期';
        
        if (!isset($groupedLogs[$date])) {
            $groupedLogs[$date] = [];
        }
        $groupedLogs[$date][] = $log;
    }
    return $groupedLogs;
}

// ===================== 初始化变量 =====================
$env = loadEnv();
$errorMsg = '';
$groupedLogs = [];
$tokenValid = false;

// ===================== 验证Token并查询用户 =====================
$userToken = $_COOKIE['user_token'] ?? '';

if (strlen($userToken) !== 64) {
    $errorMsg = "无效的登录令牌，请重新登录";
} else {
    try {
        $dsn = "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset=utf8mb4";
        $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        $stmt = $pdo->prepare("SELECT 1 FROM users WHERE token = :token LIMIT 1");
        $stmt->bindParam(':token', $userToken);
        $stmt->execute();
        $user = $stmt->fetch();
        
        if (!$user) {
            $errorMsg = "未找到该令牌对应的用户";
        } else {
            $tokenValid = true;
            $stmt = $pdo->prepare("SELECT email FROM users WHERE token = :token LIMIT 1");
            $stmt->bindParam(':token', $userToken);
            $stmt->execute();
            $userData = $stmt->fetch();
            $userEmail = $userData['email'];
            
            $encodedEmail = urlencode($userEmail);
            $logDir = "../users/{$encodedEmail}";
            $logFile = "{$logDir}/log.txt";
            
            $safeLogFile = safeRealpath('../users', $logFile);
            if (!$safeLogFile) {
                $errorMsg = "日志文件路径非法";
            } elseif (!file_exists($safeLogFile)) {
                $errorMsg = "暂无日志记录";
            } else {
                $logContent = file_get_contents($safeLogFile);
                $logs = explode("\n", trim($logContent));
                $logs = array_filter($logs, fn($line) => !empty(trim($line)));
                $logs = array_reverse($logs);
                $groupedLogs = groupLogsByDate($logs);
            }
        }
    } catch (PDOException $e) {
        $errorMsg = "数据库错误：" . $e->getMessage();
    } catch (Exception $e) {
        $errorMsg = "系统错误：" . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户操作日志 | 苹果风格</title>
    <style>
        /* 基础重置与全局样式 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Helvetica Neue", Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        html {
            scroll-behavior: smooth; /* 平滑滚动 */
        }
        
        body {
            background: linear-gradient(180deg, #f5f5f7 0%, #e8e8ed 100%);
            color: #1d1d1f;
            min-height: 100vh;
            padding-bottom: 60px; /* 为返回顶部按钮预留空间 */
        }
        
        /* 加载动画容器 - 优化尺寸和显示 */
        #lottie-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(180deg, #f5f5f7 0%, #e8e8ed 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.8s cubic-bezier(0.25, 0.1, 0.25, 1);
        }
        
        /* 加载动画容器固定尺寸，避免变形 */
        #lottie-loader .lottie-wrapper {
            width: 80px;  /* 优化后的固定尺寸 */
            height: 80px; /* 优化后的固定尺寸 */
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        /* 主容器 - 优化间距和阴影 */
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 40px 24px;
            display: none;
            opacity: 0; /* 初始透明，用于入场动画 */
        }
        
        /* 头部样式 - 毛玻璃效果优化 */
        .header {
            display: flex;
            align-items: center;
            margin-bottom: 40px;
            padding: 20px 24px;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.9);
        }
        
        .header-icon {
            width: 36px;
            height: 36px;
            margin-right: 18px;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        }
        
        .header-title {
            font-size: 30px;
            font-weight: 700;
            color: #1d1d1f;
            letter-spacing: -0.8px;
            background: linear-gradient(90deg, #1d1d1f 0%, #86868b 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        /* 日期分隔栏 - 增强视觉层次 */
        .date-separator {
            margin: 32px 0 20px;
            display: 32px 0 20px;
            display: flex;
            align-items: center;
            width: 100%;
            position: relative;
        }
        
        .date-line {
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, transparent 0%, #e6e6e8 50%, transparent 100%);
        }
        
        .date-text {
            padding: 8px 20px;
            font-size: 15px;
            color: #0071e3;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 50px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid rgba(255, 255, 255, 0.9);
        }
        
        /* 日志卡片 - 移除顶部蓝线，优化样式 */
        .log-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.04);
            padding: 28px;
            margin-bottom: 24px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 1px solid rgba(255, 255, 255, 0.95);
            position: relative;
            overflow: hidden;
        }
        
        .log-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.08);
        }
        
        /* 日志条目 - 移除emoji，优化排版 */
        .log-item {
            padding: 18px 12px;
            border-bottom: 1px solid rgba(242, 242, 247, 0.8);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            transition: background 0.3s ease;
            border-radius: 16px;
            margin: 4px 0;
        }
        
        .log-item:hover {
            background: rgba(245, 245, 247, 0.6);
        }
        
        .log-item:last-child {
            border-bottom: none;
        }
        
        .log-time {
            color: #0071e3;
            font-weight: 600;
            margin-right: 16px;
            min-width: 110px;
            font-size: 14px;
            letter-spacing: 0.5px;
        }
        
        .log-content {
            color: #1d1d1f;
            line-height: 1.7;
            font-size: 15px;
            flex: 1;
            padding: 4px 0;
        }
        
        /* 错误提示 - 苹果风格警告 */
        .error-box {
            background: linear-gradient(135deg, #ff3b30 0%, #ff6b6b 100%);
            color: white;
            padding: 24px 30px;
            border-radius: 24px;
            margin: 20px 0;
            text-align: center;
            font-size: 16px;
            box-shadow: 0 8px 24px rgba(255, 59, 48, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        /* 空日志提示 - 精致化 */
        .empty-log {
            text-align: center;
            padding: 100px 20px;
            color: #86868b;
            font-size: 17px;
            line-height: 2;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.95);
        }
        
        .empty-log::before {
            content: "";
            display: block;
            width: 80px;
            height: 80px;
            margin: 0 auto 30px;
            background: linear-gradient(135deg, #f2f2f7 0%, #e6e6e8 100%);
            border-radius: 50%;
            background-image: url('../svg/日志.svg');
            background-size: 40px;
            background-repeat: no-repeat;
            background-position: center;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }

        /* 返回顶部按钮 - 苹果风格设计 */
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 56px;
            height: 56px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 50%;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.95);
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.4s ease;
            z-index: 999;
        }
        
        .back-to-top.visible {
            opacity: 1;
            visibility: visible;
        }
        
        .back-to-top:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.15);
            background: rgba(255, 255, 255, 1);
        }
        
        .back-to-top img {
            width: 24px;
            height: 24px;
            transition: transform 0.3s ease;
        }
        
        .back-to-top:hover img {
            transform: translateY(-2px);
        }

        /* 响应式适配 - 全尺寸优化 */
        @media (max-width: 768px) {
            .container {
                padding: 24px 16px;
            }
            
            .header {
                padding: 16px 20px;
                margin-bottom: 28px;
            }
            
            .header-title {
                font-size: 24px;
            }
            
            .header-icon {
                width: 30px;
                height: 30px;
            }
            
            .log-time {
                min-width: 100%;
                margin-bottom: 10px;
            }
            
            .log-item {
                padding: 14px 8px;
            }
            
            .log-card {
                padding: 20px;
                border-radius: 20px;
            }
            
            .date-separator {
                margin: 24px 0 16px;
            }
            
            /* 移动端返回顶部按钮优化 */
            .back-to-top {
                width: 48px;
                height: 48px;
                bottom: 20px;
                right: 20px;
            }
            
            .back-to-top img {
                width: 20px;
                height: 20px;
            }
        }

        @media (max-width: 480px) {
            .header-title {
                font-size: 20px;
            }
            
            .date-text {
                padding: 6px 16px;
                font-size: 14px;
            }
            
            .log-content {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <!-- Lottie加载动画 - 优化容器结构 -->
    <div id="lottie-loader">
        <div class="lottie-wrapper"></div>
    </div>

    <!-- 主内容容器 -->
    <div class="container">
        <!-- 头部 -->
        <div class="header">
            <img src="../svg/日志.svg" alt="日志图标" class="header-icon">
            <h1 class="header-title">用户操作日志</h1>
        </div>

        <!-- 错误提示 -->
        <?php if ($errorMsg): ?>
            <div class="error-box"><?= htmlspecialchars($errorMsg) ?></div>
        <?php endif; ?>

        <!-- 日志内容 -->
        <?php if (empty($groupedLogs) && !$errorMsg): ?>
            <div class="empty-log">暂无操作日志<br>你的所有操作记录将在这里显示</div>
        <?php elseif (!empty($groupedLogs)): ?>
            <?php foreach ($groupedLogs as $date => $logs): ?>
                <!-- 日期分隔栏 -->
                <div class="date-separator">
                    <div class="date-line"></div>
                    <div class="date-text"><?= htmlspecialchars($date) ?></div>
                    <div class="date-line"></div>
                </div>
                
                <!-- 当日日志卡片 -->
                <div class="log-card">
                    <?php foreach ($logs as $log): ?>
                        <?php
                        preg_match('/^\[(.*?)\]\s+(.*)$/', $log, $matches);
                        $logTime = $matches[1] ?? '';
                        $logContent = $matches[2] ?? $log;
                        $logTimeOnly = preg_replace('/^\d{4}-\d{2}-\d{2}\s+/', '', $logTime);
                        ?>
                        <div class="log-item">
                            <span class="log-time"><?= htmlspecialchars($logTimeOnly) ?></span>
                            <span class="log-content"><?= htmlspecialchars($logContent) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- 返回顶部按钮 -->
    <div class="back-to-top" id="backToTop">
        <img src="../svg/返回顶部.svg" alt="返回顶部">
    </div>

    <!-- Lottie JS -->
    <script src="../main/js/lottie.min.js"></script>
    <script>
        // 1. 初始化Lottie加载动画（优化尺寸和显示）
        const loaderContainer = document.getElementById('lottie-loader');
        const lottieWrapper = loaderContainer.querySelector('.lottie-wrapper');
        
        const loader = lottie.loadAnimation({
            container: lottieWrapper,
            renderer: 'svg',
            loop: true,
            autoplay: true,
            path: '../lottie/整理.json',
            rendererSettings: {
                preserveAspectRatio: 'xMidYMid meet',
                clearCanvas: true
            }
        });

        // 2. 页面加载完成后平滑过渡
        window.addEventListener('load', () => {
            setTimeout(() => {
                loaderContainer.style.opacity = 0;
                setTimeout(() => {
                    loaderContainer.style.display = 'none';
                    const mainContainer = document.querySelector('.container');
                    mainContainer.style.display = 'block';
                    // 主容器入场动画
                    mainContainer.animate([
                        { opacity: 0, transform: 'translateY(20px)' },
                        { opacity: 1, transform: 'translateY(0)' }
                    ], {
                        duration: 800,
                        easing: 'cubic-bezier(0.25, 0.1, 0.25, 1)',
                        fill: 'forwards'
                    });
                }, 800);
            }, 800);
        });

        // 3. 返回顶部按钮逻辑
        const backToTopBtn = document.getElementById('backToTop');
        
        // 滚动监听，控制按钮显示/隐藏
        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) { // 滚动超过300px显示
                backToTopBtn.classList.add('visible');
            } else {
                backToTopBtn.classList.remove('visible');
            }
        });
        
        // 点击返回顶部
        backToTopBtn.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // 4. 优化动画性能
        loader.addEventListener('DOMLoaded', () => {
            // 确保动画尺寸适配容器
            lottieWrapper.style.width = '80px';
            lottieWrapper.style.height = '80px';
        });
        
        // 页面隐藏时暂停动画，节省性能
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                loader.pause();
            } else {
                loader.play();
            }
        });
    </script>
</body>
</html>
