<?php
// 定义JSON文件存储目录（根据你的实际路径调整）
$jsonDir = __DIR__ . '/api_json/';
// 确保目录存在
if (!is_dir($jsonDir)) {
    mkdir($jsonDir, 0755, true);
}

// 获取所有JSON文件
$jsonFiles = glob($jsonDir . '*.json');
$apiList = [];
foreach ($jsonFiles as $file) {
    $id = basename($file, '.json');
    $content = file_get_contents($file);
    $data = json_decode($content, true);
    if ($data) {
        $apiList[] = [
            'id' => $id,
            'name' => $data['name'] ?? '未命名接口',
            'brief' => $data['brief'] ?? '无简介'
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>开放平台</title>
    <style>
        /* 苹果风格基础样式 - 增强版 */
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
            border-radius: 16px; /* 更大更圆润的圆角 */
            box-shadow: 0 8px 30px rgba(0,0,0,0.07); /* 更柔和的阴影 */
            padding: 30px;
            /* 苹果毛玻璃效果（降级兼容） */
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.8);
        }
        h1 {
            color: #1d1d1f;
            font-size: 32px;
            margin-bottom: 25px;
            border-bottom: 1px solid #e6e6e8;
            padding-bottom: 20px;
            font-weight: 600;
        }
        /* API项美化 */
        .api-item {
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #e6e6e8;
            margin-bottom: 15px;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); /* 更丝滑的过渡 */
            position: relative;
            overflow: hidden;
        }
        .api-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background-color: #0071e3; /* 苹果蓝 */
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .api-item:hover {
            background-color: #f9f9fb;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transform: translateY(-2px); /* 轻微上浮 */
        }
        .api-item:hover::before {
            opacity: 1;
        }
        .api-item a {
            text-decoration: none;
            color: #0071e3;
            font-size: 18px;
            font-weight: 500;
            display: block;
            margin-bottom: 8px;
        }
        .api-item .brief {
            color: #6e6e73;
            font-size: 15px;
            line-height: 1.6;
        }
        /* 空数据提示美化 */
        .empty-tip {
            text-align: center;
            padding: 60px 20px;
            color: #86868b;
        }
        .empty-tip i {
            font-size: 60px;
            margin-bottom: 20px;
            display: block;
            color: #e6e6e8;
        }
        .empty-tip p {
            font-size: 16px;
        }
        /* 返回顶部按钮美化 */
        .back-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 48px;
            height: 48px;
            background-color: rgba(255,255,255,0.8); /* 半透明白 */
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
            transform: scale(1.05); /* 轻微放大 */
        }
        .back-top img {
            width: 22px;
            height: 22px;
        }
        /* 响应式适配 */
        @media (max-width: 768px) {
            .container {
                padding: 20px;
                margin: 10px;
            }
            h1 {
                font-size: 24px;
            }
            .api-item {
                padding: 15px;
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
    <div class="container">
        <h1>云端图片储存 —— 开放平台</h1>
        <?php if (empty($apiList)): ?>
            <div class="empty-tip">
                <i>📄</i>
                <p>暂无接口</p>
            </div>
        <?php else: ?>
            <?php foreach ($apiList as $api): ?>
                                <a href="api_detail.php?id=<?php echo htmlspecialchars($api['id']); ?>">
                <div class="api-item">

                        <?php echo htmlspecialchars($api['name']); ?>

                    <div class="brief"><?php echo htmlspecialchars($api['brief']); ?></div>
                                        </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- 返回顶部按钮 -->
    <div class="back-top" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">
        <img src="../svg/返回顶部.svg" alt="返回顶部">
    </div>

    <script>
        // 监听滚动，控制返回顶部按钮显示
        window.addEventListener('scroll', function() {
            const backTop = document.querySelector('.back-top');
            if (window.scrollY > 300) {
                backTop.style.opacity = '1';
                backTop.style.transform = 'translateY(0)';
            } else {
                backTop.style.opacity = '0.6';
                backTop.style.transform = 'translateY(10px)';
            }
        });
    </script>
</body>
</html>