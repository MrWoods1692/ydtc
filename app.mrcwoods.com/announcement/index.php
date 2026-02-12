<?php
// ========== PHP 跨域 & 嵌入配置（核心） ==========
// 允许跨域访问
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
// 允许页面被其他网页iframe嵌入
header("X-Frame-Options: ALLOWALL");
// 性能优化：设置缓存（公告内容更新频率可调整缓存时间）
header("Cache-Control: public, max-age=3600");
// 定义JSON文件路径（适配PHP路径解析）
$am_ann_json_path = __DIR__ . '/announcement/公告.json';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>公告中心</title>
    <!-- ========== 性能优化：预加载关键资源 ========== -->
    <link rel="preload" href="../font/AlimamaAgileVF-Thin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="../svg/公告.svg" as="image">
    <!-- ========== 字体定义（阿里妈妈灵动体） ========== -->
    <style>
        @font-face {
            font-family: 'AlimamaAgile-VIP'; /* 特殊命名避免冲突 */
            src: url('../font/AlimamaAgileVF-Thin.woff2') format('woff2'),
                 url('../font/AlimamaAgileVF-Thin.woff') format('woff'),
                 url('../font/AlimamaAgileVF-Thin.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap; /* 性能优化：字体加载时不阻塞文本渲染 */
        }

        /* ========== 全局样式 & 深度美化（特殊命名前缀am-ann-） ========== */
        .am-ann-global-reset {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'AlimamaAgile-VIP', sans-serif;
            -webkit-font-smoothing: antialiased; /* 美化：文字抗锯齿 */
            -moz-osx-font-smoothing: grayscale;
        }

        .am-ann-body {
            background: linear-gradient(120deg, #f0f8fb 0%, #e8f4f8 50%, #f5fafe 100%); /* 更柔和的渐变背景 */
            color: #2d3748;
            line-height: 1.7;
            padding-bottom: 60px;
            min-height: 100vh;
            /* 隐藏滚动条但保留滚动功能 */
            overflow-y: auto;
            scrollbar-width: none; /* 火狐隐藏滚动条 */
        }
        
        /* 隐藏Chrome/Safari等webkit内核浏览器的滚动条 */
        .am-ann-body::-webkit-scrollbar {
            display: none;
        }

        /* ========== 页面头部（美化升级+特殊命名） ========== */
        .am-ann-page-header {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
        }

        .am-ann-page-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 20px;
            width: 100px;
            height: 4px;
            background: linear-gradient(90deg, #4299e1, #38b2ac, #48bb78); /* 多色渐变 */
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(66, 153, 225, 0.2);
        }

        .am-ann-ann-icon {
            width: 40px;
            height: 40px;
            filter: drop-shadow(0 3px 6px rgba(66, 153, 225, 0.25)); /* 更柔和的图标阴影 */
            transition: transform 0.4s ease;
        }

        .am-ann-page-header:hover .am-ann-ann-icon {
            transform: rotate(8deg) scale(1.1); /* 更灵动的hover动效 */
        }

        .am-ann-page-title {
            font-size: 32px;
            font-weight: 600;
            color: #2d3748;
            letter-spacing: 0.8px; /* 更舒适的字间距 */
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.03);
            background: linear-gradient(90deg, #2d3748, #4a5568);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent; /* 文字渐变效果 */
        }

        /* ========== 加载动画容器（lottie+美化升级） ========== */
        .am-ann-loading-wrap {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.98);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 99998;
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(5px); /* 更柔和的毛玻璃 */
        }

        #am-ann-lottie-loading {
            width: 240px;
            height: 240px;
            filter: drop-shadow(0 8px 20px rgba(66, 153, 225, 0.2)); /* 更精致的动画阴影 */
        }

        /* ========== 公告统计 & 列表容器（美化+特殊命名） ========== */
        .am-ann-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .am-ann-stats-card {
            background: rgba(255, 255, 255, 0.95); /* 更通透的背景 */
            padding: 22px 30px;
            border-radius: 16px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            font-size: 18px;
            color: #4a5568;
            border: 1px solid rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(8px); /* 增强毛玻璃效果 */
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            overflow: hidden;
        }

        .am-ann-stats-count {
            color: #4299e1;
            font-weight: 700;
            font-size: 20px;
            text-shadow: 0 1px 2px rgba(66, 153, 225, 0.1);
        }

        .am-ann-list-wrap {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 25px;
        }

        /* ========== 公告卡片样式（深度美化+特殊命名） ========== */
        .am-ann-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 18px;
            padding: 28px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.06);
            cursor: pointer;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            border-left: 6px solid #4299e1;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(8px);
        }

        /* 卡片装饰元素（增强美化） */
        .am-ann-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, rgba(66, 153, 225, 0.08) 0%, transparent 60%);
            border-radius: 0 18px 0 100%;
            z-index: 0;
        }
        
        /* 卡片底部渐变装饰 */
        .am-ann-card::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, rgba(66, 153, 225, 0.8), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .am-ann-card:hover::after {
            opacity: 1;
        }

        .am-ann-card.am-ann-sticky {
            border-left-color: #e53e3e; /* 置顶公告特殊标识 */
        }

        .am-ann-card.am-ann-sticky::before {
            background: linear-gradient(135deg, rgba(229, 62, 62, 0.08) 0%, transparent 60%);
        }
        
        .am-ann-card.am-ann-sticky::after {
            background: linear-gradient(90deg, rgba(229, 62, 62, 0.8), transparent);
        }

        .am-ann-card:hover {
            transform: translateY(-10px) scale(1.01); /* 更自然的hover缩放 */
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            /* 移除左侧边框变粗的效果 */
            border-left-width: 0px;
        }

        .am-ann-card-inner {
            position: relative;
            z-index: 1;
        }

        .am-ann-card-title {
            font-size: 22px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            letter-spacing: 0.5px; /* 更舒适的字间距 */
            background: linear-gradient(90deg, #2d3748, #4a5568);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent; /* 标题渐变效果 */
        }

        .am-ann-sticky-tag {
            font-size: 14px;
            color: #e53e3e;
            background-color: rgba(229, 62, 62, 0.12);
            padding: 4px 10px;
            border-radius: 8px;
            font-weight: 600;
            box-shadow: 0 2px 6px rgba(229, 62, 62, 0.1);
        }

        .am-ann-card-meta {
            font-size: 16px;
            color: #718096;
            margin-bottom: 15px;
            display: flex;
            gap: 25px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .am-ann-card-preview {
            font-size: 17px;
            color: #4a5568;
            display: -webkit-box;
            -webkit-line-clamp: 2; /* 只显示2行预览 */
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.8;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
        }

        /* ========== 公告详情弹窗（深度美化+特殊命名） ========== */
        .am-ann-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 100000; /* 超高z-index */
            backdrop-filter: blur(10px); /* 更强的毛玻璃效果 */
            padding: 20px;
        }

        .am-ann-modal-content {
            background: rgba(255, 255, 255, 0.99);
            width: 90%;
            max-width: 850px;
            border-radius: 24px;
            padding: 45px;
            position: relative;
            max-height: 85vh;
            overflow-y: auto;
            animation: am-ann-modal-fade-in 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.99);
            /* 隐藏弹窗内部滚动条 */
            scrollbar-width: none;
        }
        
        /* 隐藏弹窗内部webkit滚动条 */
        .am-ann-modal-content::-webkit-scrollbar {
            display: none;
        }

        @keyframes am-ann-modal-fade-in {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1.02); /* 轻微放大更有层次感 */
            }
        }

        .am-ann-close-modal {
            position: absolute;
            top: 30px;
            right: 30px;
            font-size: 30px;
            cursor: pointer;
            color: #718096;
            transition: all 0.4s ease;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .am-ann-close-modal:hover {
            color: #e53e3e;
            background: rgba(229, 62, 62, 0.15);
            transform: rotate(180deg) scale(1.1); /* 更炫酷的旋转动效 */
            box-shadow: 0 4px 12px rgba(229, 62, 62, 0.15);
        }

        .am-ann-modal-title {
            font-size: 28px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(66, 153, 225, 0.15);
            letter-spacing: 0.8px;
            background: linear-gradient(90deg, #2d3748, #4a5568);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent; /* 弹窗标题渐变 */
        }

        .am-ann-modal-meta {
            font-size: 16px;
            color: #718096;
            margin-bottom: 30px;
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }

        .am-ann-modal-body {
            font-size: 18px;
            color: #4a5568;
            line-height: 2;
            letter-spacing: 0.5px;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
        }

        /* ========== 响应式适配（深度优化+特殊命名） ========== */
        @media (max-width: 768px) {
            .am-ann-list-wrap {
                grid-template-columns: 1fr;
            }
            .am-ann-page-title {
                font-size: 28px;
            }
            .am-ann-modal-content {
                padding: 35px 25px;
                border-radius: 20px;
            }
            .am-ann-card {
                padding: 22px;
                border-radius: 16px;
            }
            .am-ann-stats-card {
                padding: 18px 25px;
                border-radius: 14px;
            }
            .am-ann-close-modal {
                width: 40px;
                height: 40px;
                font-size: 26px;
            }
        }
    </style>
</head>
<body class="am-ann-global-reset am-ann-body">
    <!-- 加载动画容器（特殊ID） -->
    <div class="am-ann-loading-wrap" id="am-ann-loading-wrap">
        <div id="am-ann-lottie-loading"></div>
    </div>

    <!-- 页面头部 -->
    <div class="am-ann-page-header">
        <img src="../svg/公告.svg" alt="公告图标" class="am-ann-ann-icon">
        <h1 class="am-ann-page-title">公告中心</h1>
    </div>

    <!-- 公告内容容器 -->
    <div class="am-ann-container">
        <!-- 公告统计（特殊ID） -->
        <div class="am-ann-stats-card" id="am-ann-stats-card">
            公告总数：<span class="am-ann-stats-count" id="am-ann-total-count">0</span> 条
        </div>

        <!-- 公告列表（特殊ID） -->
        <div class="am-ann-list-wrap" id="am-ann-list-wrap"></div>
    </div>

    <!-- 公告详情弹窗（特殊ID） -->
    <div class="am-ann-modal-overlay" id="am-ann-modal-overlay">
        <div class="am-ann-modal-content">
            <span class="am-ann-close-modal" id="am-ann-close-modal">&times;</span>
            <h2 class="am-ann-modal-title" id="am-ann-modal-title"></h2>
            <div class="am-ann-modal-meta" id="am-ann-modal-meta"></div>
            <div class="am-ann-modal-body" id="am-ann-modal-body"></div>
        </div>
    </div>

    <!-- ========== 引入Lottie脚本（性能优化：底部加载） ========== -->
    <script src="../main/js/lottie.min.js"></script>
    <script>
        // ========== 所有JS变量添加特殊前缀amAnn_，避免全局变量冲突 ==========
        // 1. 初始化Lottie加载动画
        const amAnn_loadingAnimation = lottie.loadAnimation({
            container: document.getElementById('am-ann-lottie-loading'),
            renderer: 'svg', // 性能更优，矢量渲染
            loop: true,
            autoplay: true,
            path: '../lottie/工作.json',
            rendererSettings: {
                preserveAspectRatio: 'xMidYMid meet',
                clearCanvas: false, // 性能优化：减少画布清空
                progressiveLoad: true // 渐进式加载
            }
        });

        // 2. 读取公告JSON并渲染（核心功能）
        async function amAnn_loadAnnouncements() {
            try {
                // 读取公告JSON（PHP路径已处理跨域）
                const amAnn_response = await fetch('../announcement/公告.json', {
                    cache: 'force-cache', // 性能优化：使用缓存
                    mode: 'cors' // 允许跨域
                });

                if (!amAnn_response.ok) throw new Error('公告数据加载失败');
                
                const amAnn_data = await amAnn_response.json();
                
                // 验证数据结构
                if (!Array.isArray(amAnn_data) && !amAnn_data.announcements) {
                    throw new Error('公告数据格式错误');
                }

                // 整理公告数据（兼容两种格式：数组 / 包含announcements的对象）
                const amAnn_announcements = Array.isArray(amAnn_data) ? amAnn_data : amAnn_data.announcements;
                const amAnn_totalCount = amAnn_announcements.length;

                // 3. 渲染公告统计
                document.getElementById('am-ann-total-count').textContent = amAnn_totalCount;

                // 4. 排序：置顶公告优先
                const amAnn_sortedAnnouncements = amAnn_announcements.sort((a, b) => {
                    // 置顶（true）排前面，非置顶（false）排后面
                    return (b.isSticky ? 1 : 0) - (a.isSticky ? 1 : 0);
                });

                // 5. 渲染公告列表（性能优化：减少DOM操作）
                const amAnn_announcementList = document.getElementById('am-ann-list-wrap');
                const amAnn_fragment = document.createDocumentFragment(); // 文档片段：一次渲染，减少重绘

                amAnn_sortedAnnouncements.forEach((item, index) => {
                    // 验证必要字段
                    const amAnn_title = item.title || '无标题';
                    const amAnn_content = item.content || '无内容';
                    const amAnn_date = item.date || '未知日期';
                    const amAnn_wordCount = item.wordCount || amAnn_content.length;
                    const amAnn_isSticky = item.isSticky || false;

                    // 创建公告卡片
                    const amAnn_card = document.createElement('div');
                    amAnn_card.className = `am-ann-card ${amAnn_isSticky ? 'am-ann-sticky' : ''}`;
                    amAnn_card.setAttribute('data-am-ann-index', index); // 特殊data属性避免冲突

                    // 卡片内容
                    amAnn_card.innerHTML = `
                        <div class="am-ann-card-inner">
                            <div class="am-ann-card-title">
                                ${amAnn_title}
                                ${amAnn_isSticky ? '<span class="am-ann-sticky-tag">置顶</span>' : ''}
                            </div>
                            <div class="am-ann-card-meta">
                                <span>发布日期：${amAnn_date}</span>
                                <span>字数：${amAnn_wordCount}</span>
                            </div>
                            <div class="am-ann-card-preview">${amAnn_content}</div>
                        </div>
                    `;

                    // 点击卡片打开详情弹窗
                    amAnn_card.addEventListener('click', () => amAnn_openModal(item));
                    amAnn_fragment.appendChild(amAnn_card);
                });

                // 一次性添加到DOM（性能优化）
                amAnn_announcementList.appendChild(amAnn_fragment);

            } catch (amAnn_error) {
                console.error('加载公告失败：', amAnn_error);
                document.getElementById('am-ann-stats-card').textContent = `加载失败：${amAnn_error.message}`;
            } finally {
                // 延长加载动画显示时间（从300ms改为2000ms，总显示约2.6秒）
                setTimeout(() => {
                    document.getElementById('am-ann-loading-wrap').style.opacity = 0;
                    setTimeout(() => {
                        document.getElementById('am-ann-loading-wrap').style.display = 'none';
                    }, 600);
                }, 2000);
            }
        }

        // 6. 公告详情弹窗逻辑
        function amAnn_openModal(announcement) {
            const amAnn_modalOverlay = document.getElementById('am-ann-modal-overlay');
            const amAnn_modalTitle = document.getElementById('am-ann-modal-title');
            const amAnn_modalMeta = document.getElementById('am-ann-modal-meta');
            const amAnn_modalBody = document.getElementById('am-ann-modal-body');

            // 填充弹窗内容
            amAnn_modalTitle.textContent = announcement.title || '无标题';
            amAnn_modalMeta.innerHTML = `
                <span>发布日期：${announcement.date || '未知日期'}</span>
                <span>字数：${announcement.wordCount || announcement.content?.length || 0}</span>
                ${announcement.isSticky ? '<span class="am-ann-sticky-tag">置顶</span>' : ''}
            `;
            amAnn_modalBody.textContent = announcement.content || '无内容';

            // 显示弹窗
            amAnn_modalOverlay.style.display = 'flex';
        }

        // 关闭弹窗
        document.getElementById('am-ann-close-modal').addEventListener('click', () => {
            document.getElementById('am-ann-modal-overlay').style.display = 'none';
        });

        // 点击弹窗外部关闭
        document.getElementById('am-ann-modal-overlay').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) {
                e.target.style.display = 'none';
            }
        });

        // 7. 页面加载完成后执行（性能优化）
        // 监听DOM加载完成（不等待图片/动画）
        document.addEventListener('DOMContentLoaded', () => {
            // 延迟加载公告（让页面先渲染基础结构）
            setTimeout(amAnn_loadAnnouncements, 300);
        });

        // 8. 性能优化：窗口失焦时暂停动画
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                amAnn_loadingAnimation.pause(); // 暂停lottie动画
            } else {
                amAnn_loadingAnimation.play(); // 恢复动画
            }
        });

        // 9. 防止全局变量泄漏（封装核心逻辑）
        window.amAnn_publicApi = {
            reloadAnnouncements: amAnn_loadAnnouncements // 暴露必要API，命名仍带特殊前缀
        };
    </script>
</body>
</html>
