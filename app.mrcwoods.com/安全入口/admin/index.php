<?php
session_start();
require_once 'security_headers.php';
require_once 'EnvHelper.php';
require_once 'JwtHelper.php';

// 验证登录（强化）
function checkLogin(): void {
    $token = $_COOKIE['admin_token'] ?? '';
    if (empty($token) || !JwtHelper::verifyToken($token)) {
        header('Location: login.php');
        exit;
    }
}
checkLogin();

// XSS过滤
function xssFilter(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// 生成CSRF Token
$csrfToken = bin2hex(random_bytes(16)) . '_' . time();
$_SESSION['csrf_token'] = $csrfToken;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>云端图片储存 - 管理后台</title>
    <style>
        /* 苹果风格深度美化 + 全动画支持 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif;
        }
        body {
            background-color: #f5f5f7;
            color: #1d1d1f;
            overflow: hidden;
        }
        /* 顶部导航栏（毛玻璃+动画） */
        .header {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            box-shadow: 0 1px 8px rgba(0, 0, 0, 0.05);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        .logo-area {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .logo-area img {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            transition: transform 0.2s ease;
        }
        .logo-area img:hover {
            transform: scale(1.1);
        }
        .logo-area h2 {
            font-size: 18px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        .user-actions {
            display: flex;
            gap: 16px;
        }
        .user-actions a {
            color: #0071e3;
            text-decoration: none;
            font-size: 14px;
            padding: 8px 12px;
            border-radius: 8px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .user-actions a:hover {
            background-color: rgba(0, 113, 227, 0.1);
            transform: translateY(-1px);
        }
        .user-actions .logout {
            color: #ff3b30;
        }
        .user-actions .logout:hover {
            background-color: rgba(255, 59, 48, 0.1);
        }

        /* 侧边菜单（美化+动画） */
        .sidebar {
            width: 220px;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            position: fixed;
            top: 56px;
            left: 0;
            bottom: 0;
            padding-top: 20px;
            box-shadow: 1px 0 8px rgba(0, 0, 0, 0.05);
            border-right: 1px solid rgba(0, 0, 0, 0.05);
            overflow-y: auto;
        }
        .menu-item {
            padding: 14px 24px;
            color: #1d1d1f;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }
        .menu-item:hover {
            background-color: rgba(0, 0, 0, 0.03);
            border-left-color: #0071e3;
            transform: translateX(2px);
        }
        .menu-item.active {
            background-color: rgba(0, 113, 227, 0.1);
            border-left-color: #0071e3;
            color: #0071e3;
        }

        /* 标签页容器（优化+滚动+动画+刷新功能） */
        .tabs-container {
            margin-left: 220px;
            margin-top: 56px;
            height: calc(100vh - 56px);
            display: flex;
            flex-direction: column;
        }
        .tabs-header {
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            padding: 0 10px;
            border-bottom: 1px solid #e0e0e5;
            height: 48px;
            overflow-x: auto;
            overflow-y: hidden;
            scrollbar-width: none; /* 隐藏滚动条（Firefox） */
        }
        .tabs-header::-webkit-scrollbar {
            display: none; /* 隐藏滚动条（Chrome/Safari） */
        }
        .tab-item {
            display: flex;
            align-items: center;
            padding: 0 12px;
            height: 100%;
            cursor: pointer;
            position: relative;
            white-space: nowrap;
            max-width: 220px;
            transition: all 0.2s ease;
            border-bottom: 2px solid transparent;
            margin-right: 2px;
        }
        .tab-item:hover {
            background-color: rgba(0, 0, 0, 0.03);
        }
        .tab-item.active {
            border-bottom: 2px solid #0071e3;
            color: #0071e3;
        }
        .tab-item.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, #0071e3 0%, #0099ff 100%);
        }
        .tab-text {
            overflow: hidden;
            text-overflow: ellipsis;
            margin-right: 6px;
        }
        /* 刷新按钮样式 */
        .tab-refresh {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #86868b;
            transition: all 0.2s ease;
            margin-right: 6px;
        }
        .tab-refresh:hover {
            color: #0071e3;
            transform: rotate(180deg);
        }
        /* 关闭按钮样式 */
        .tab-close {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #86868b;
            transition: all 0.2s ease;
        }
        .tab-close:hover {
            background-color: #ff3b30;
            color: white;
            transform: scale(1.1);
        }

        /* 内容区域（动画+安全+加载效果） */
        .tab-content {
            flex: 1;
            height: calc(100% - 48px);
            overflow: hidden;
            position: relative;
        }
        .tab-panel {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        .tab-panel.active {
            opacity: 1;
            visibility: visible;
        }
        .tab-iframe {
            width: 100%;
            height: 100%;
            border: none;
            /* 核心：允许同域跳转，禁止跨域/嵌入 */
            sandbox="allow-same-origin allow-scripts allow-top-navigation-by-user-activation"
        }
        .empty-content {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100%;
            color: #86868b;
            font-size: 16px;
            gap: 10px;
        }
        .empty-content i {
            font-size: 48px;
            color: #d2d2d7;
        }

        /* 加载动画 */
        .loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 40px;
            height: 40px;
            border: 4px solid rgba(0, 113, 227, 0.1);
            border-radius: 50%;
            border-top-color: #0071e3;
            animation: spin 1s ease-in-out infinite;
            z-index: 10;
        }
        @keyframes spin {
            to { transform: translate(-50%, -50%) rotate(360deg); }
        }

        /* 动画效果 */
        @keyframes tabFadeIn {
            from { opacity: 0; transform: translateX(10px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes tabFadeOut {
            from { opacity: 1; transform: translateX(0); }
            to { opacity: 0; transform: translateX(-10px); }
        }
        .tab-panel.fade-in {
            animation: tabFadeIn 0.3s ease-out;
        }
        .tab-panel.fade-out {
            animation: tabFadeOut 0.3s ease-out;
        }
    </style>
    <!-- 图标库 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- 顶部导航栏 -->
    <div class="header">
        <div class="logo-area">
            <img src="../../images/logo.png" alt="logo">
            <h2>云端图片储存管理后台</h2>
        </div>
        <div class="user-actions">
            <a href="javascript:openTab('修改密码', 'change_password')"><i class="fas fa-key"></i> 修改密码</a>
            <a href="javascript:refreshActiveTab()"><i class="fas fa-sync-alt"></i> 刷新</a>
            <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> 退出登录</a>
        </div>
    </div>

    <!-- 侧边菜单 -->
    <div class="sidebar">
        <a href="javascript:openTab('前台预览', 'frontend')" class="menu-item">
            <i class="fas fa-desktop"></i> 前台预览
        </a>
        <a href="javascript:openTab('网站配置', 'config')" class="menu-item">
            <i class="fas fa-cog"></i> 网站配置
        </a>
        <a href="javascript:openTab('工具管理', 'tools')" class="menu-item">
            <i class="fas fa-wrench"></i> 工具管理
        </a>
        <a href="javascript:openTab('网站日志', 'logs')" class="menu-item">
            <i class="fas fa-file-alt"></i> 网站日志
        </a>
        <a href="javascript:openTab('用户管理', 'users')" class="menu-item">
            <i class="fas fa-users"></i> 用户管理
        </a>
        <a href="javascript:openTab('公告管理', 'announcement')" class="menu-item">
            <i class="fas fa-bullhorn"></i> 公告管理
        </a>
        <a href="javascript:openTab('卡密管理', 'mall')" class="menu-item">
            <i class="fas fa-ticket-alt"></i> 卡密管理
        </a>
    </div>

    <!-- 标签页区域 -->
    <div class="tabs-container">
        <div class="tabs-header" id="tabsHeader"></div>
        <div class="tab-content" id="tabContent">
            <div class="empty-content">
                <i class="fas fa-folder-open"></i>
            </div>
        </div>
    </div>

    <script>
        // 核心：标签页管理（优化版+刷新功能）
        const tabs = new Map(); // 存储标签：key=标题，value=别名
        let activeTab = '';
        const tabContent = document.getElementById('tabContent');
        const tabsHeader = document.getElementById('tabsHeader');

        // 打开标签页（带动画+URL映射）
        function openTab(title, alias) {
            // 防止重复打开
            if (tabs.has(title)) {
                switchTab(title);
                return;
            }

            // 存储标签（仅存别名，不暴露真实URL）
            tabs.set(title, alias);
            
            // 创建标签元素（美化+动画+刷新按钮）
            const tabItem = document.createElement('div');
            tabItem.className = 'tab-item';
            tabItem.dataset.title = title;
            tabItem.innerHTML = `
                <span class="tab-text">${title}</span>
                <span class="tab-refresh" onclick="refreshTab('${title}', event)"><i class="fas fa-sync-alt"></i></span>
                <span class="tab-close" onclick="closeTab('${title}', event)">&times;</span>
            `;
            tabItem.onclick = () => switchTab(title);
            // 标签添加动画
            tabItem.style.opacity = '0';
            tabItem.style.transform = 'translateY(5px)';
            tabsHeader.appendChild(tabItem);
            setTimeout(() => {
                tabItem.style.opacity = '1';
                tabItem.style.transform = 'translateY(0)';
            }, 10);

            // 创建内容面板（带动画+加载效果）
            const panel = document.createElement('div');
            panel.className = 'tab-panel';
            panel.id = `panel_${title.replace(/\s+/g, '_')}`;
            // 先显示加载动画
            panel.innerHTML = '<div class="loading"></div>';
            tabContent.appendChild(panel);

            // 核心：通过别名获取真实URL（前端不暴露）
            fetch('get_real_url.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': '<?= xssFilter($csrfToken) ?>'
                },
                body: `alias=${encodeURIComponent(alias)}`
            }).then(res => res.text()).then(realUrl => {
                if (realUrl) {
                    panel.innerHTML = `<iframe src="${realUrl}" class="tab-iframe"></iframe>`;
                } else {
                    panel.innerHTML = '<div class="empty-content"><i class="fas fa-exclamation-triangle"></i><span>页面路径错误</span></div>';
                }
                // 切换到新标签
                switchTab(title);
            }).catch(err => {
                panel.innerHTML = '<div class="empty-content"><i class="fas fa-exclamation-triangle"></i><span>加载失败</span></div>';
                switchTab(title);
            });
        }

        // 切换标签页（动画+激活状态）
        function switchTab(title) {
            if (!tabs.has(title)) return;

            // 隐藏当前激活面板
            if (activeTab) {
                const oldPanel = document.getElementById(`panel_${activeTab.replace(/\s+/g, '_')}`);
                if (oldPanel) {
                    oldPanel.classList.remove('active', 'fade-in');
                    oldPanel.classList.add('fade-out');
                    setTimeout(() => oldPanel.classList.remove('fade-out'), 300);
                }
                // 移除旧标签激活状态
                document.querySelector(`.tab-item[data-title="${activeTab}"]`)?.classList.remove('active');
            }

            // 激活新标签
            activeTab = title;
            const newPanel = document.getElementById(`panel_${title.replace(/\s+/g, '_')}`);
            if (newPanel) {
                newPanel.classList.add('active', 'fade-in');
            }
            // 添加新标签激活状态
            const newTabItem = document.querySelector(`.tab-item[data-title="${title}"]`);
            if (newTabItem) {
                newTabItem.classList.add('active');
                // 滚动到可见区域
                newTabItem.scrollIntoView({ behavior: 'smooth', inline: 'nearest' });
            }
        }

        // 关闭标签页（动画+清理）
        function closeTab(title, event) {
            event.stopPropagation();
            if (!tabs.has(title)) return;

            // 标签淡出动画
            const tabItem = document.querySelector(`.tab-item[data-title="${title}"]`);
            tabItem.style.opacity = '0';
            tabItem.style.transform = 'translateY(5px)';

            // 面板淡出动画
            const panel = document.getElementById(`panel_${title.replace(/\s+/g, '_')}`);
            panel.classList.remove('active', 'fade-in');
            panel.classList.add('fade-out');

            // 延迟移除DOM
            setTimeout(() => {
                tabItem.remove();
                panel.remove();
                tabs.delete(title);

                // 切换到第一个标签（如果关闭的是激活标签）
                if (title === activeTab) {
                    const firstTitle = tabs.keys().next().value;
                    if (firstTitle) {
                        switchTab(firstTitle);
                    } else {
                        // 无标签时显示空内容
                        tabContent.innerHTML = `
                            <div class="empty-content">
                                <i class="fas fa-folder-open"></i>
                                <span>请选择左侧菜单打开对应功能</span>
                            </div>
                        `;
                        activeTab = '';
                    }
                }
            }, 300);
        }

        // 刷新指定标签
        function refreshTab(title, event) {
            event.stopPropagation();
            if (!tabs.has(title)) return;

            const panel = document.getElementById(`panel_${title.replace(/\s+/g, '_')}`);
            // 显示加载动画
            panel.innerHTML = '<div class="loading"></div>';

            // 重新获取URL并刷新
            const alias = tabs.get(title);
            fetch('get_real_url.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': '<?= xssFilter($csrfToken) ?>'
                },
                body: `alias=${encodeURIComponent(alias)}`
            }).then(res => res.text()).then(realUrl => {
                if (realUrl) {
                    panel.innerHTML = `<iframe src="${realUrl}" class="tab-iframe"></iframe>`;
                } else {
                    panel.innerHTML = '<div class="empty-content"><i class="fas fa-exclamation-triangle"></i><span>页面路径错误</span></div>';
                }
            }).catch(err => {
                panel.innerHTML = '<div class="empty-content"><i class="fas fa-exclamation-triangle"></i><span>刷新失败</span></div>';
            });
        }

        // 刷新当前激活标签
        function refreshActiveTab() {
            if (activeTab) {
                refreshTab(activeTab, { stopPropagation: () => {} });
            } else {
                alert('请先打开一个标签页');
            }
        }

        // 监听窗口大小变化，优化布局
        window.addEventListener('resize', () => {
            if (activeTab) {
                const panel = document.getElementById(`panel_${activeTab.replace(/\s+/g, '_')}`);
                if (panel) panel.style.height = `${tabContent.offsetHeight}px`;
            }
        });
    </script>
</body>
</html>
