<?php
declare(strict_types=1);

// 错误处理
error_reporting(E_ALL);
ini_set('display_errors', '1');

// 加载环境变量
$env = parse_ini_file(__DIR__ . '/.env');
if (!$env) {
    die("配置文件.env加载失败");
}

// 1. 解析URL参数
$userEmail = isset($_GET['user']) ? urldecode($_GET['user']) : '';
$albumName = isset($_GET['album']) ? urldecode($_GET['album']) : '';
$photoUrl = isset($_GET['photo']) ? urldecode($_GET['photo']) : '';

// 参数校验
if (empty($userEmail) || empty($albumName) || empty($photoUrl)) {
    die("参数不完整：缺少用户邮箱、相册名称或图片地址");
}

// 2. 数据库连接
try {
    $pdo = new PDO(
        "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset=utf8mb4",
        $env['DB_USER'],
        $env['DB_PASS'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("数据库连接失败：" . $e->getMessage());
}

// 3. 验证用户Token
$userToken = isset($_COOKIE['user_token']) ? $_COOKIE['user_token'] : '';
if (empty($userToken)) {
    die("未检测到登录令牌，请先登录");
}

// 查询Token对应的用户
$stmt = $pdo->prepare("SELECT email FROM users WHERE token = :token LIMIT 1");
$stmt->bindValue(':token', $userToken);
$stmt->execute();
$dbUser = $stmt->fetch();

if (!$dbUser || $dbUser['email'] !== $userEmail) {
    die("权限验证失败：Token不匹配或用户不存在");
}

// 4. 读取相册JSON文件
$userDir = __DIR__ . '/../users/' . urlencode($userEmail);
$albumDir = $userDir . '/' . $albumName;
$jsonFile = $albumDir . '/ls.json';

if (!file_exists($jsonFile)) {
    die("相册不存在：{$albumName}");
}

$jsonContent = file_get_contents($jsonFile);
$photoList = json_decode($jsonContent, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("JSON文件解析失败：{$jsonFile}");
}

// 查找当前图片信息
$currentPhoto = null;
foreach ($photoList as $photo) {
    if ($photo['url'] === $photoUrl) {
        $currentPhoto = $photo;
        break;
    }
}

if (!$currentPhoto) {
    die("图片不存在于该相册：{$photoUrl}");
}

// 5. 处理重命名/备注修改（POST请求）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['status' => 'error', 'msg' => '操作失败'];

    try {
        switch ($_POST['action']) {
            case 'rename':
                $newName = trim($_POST['newName']);
                if (empty($newName)) {
                    throw new Exception("新名称不能为空");
                }
                // 更新图片名称
                foreach ($photoList as &$photo) {
                    if ($photo['url'] === $photoUrl) {
                        $photo['name'] = $newName;
                        break;
                    }
                }
                unset($photo);
                break;

            case 'update_remark':
                $newRemark = trim($_POST['newRemark']);
                // 更新备注
                foreach ($photoList as &$photo) {
                    if ($photo['url'] === $photoUrl) {
                        $photo['remark'] = $newRemark;
                        break;
                    }
                }
                unset($photo);
                break;

            default:
                throw new Exception("不支持的操作");
        }

        // 写入更新后的JSON
        if (file_put_contents($jsonFile, json_encode($photoList, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
            $response = ['status' => 'success', 'msg' => '操作成功'];
        } else {
            throw new Exception("文件写入失败");
        }
    } catch (Exception $e) {
        $response['msg'] = $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

// 格式化图片信息
$currentPhoto['upload_time_format'] = date('Y-m-d H:i:s', $currentPhoto['upload_time']);
$currentPhoto['size_kb_format'] = number_format($currentPhoto['size_kb'], 2) . ' KB';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($currentPhoto['name']) ?> - 图片查看器</title>
    <link rel="stylesheet" href="css/style.css">
    <!-- 引入Lottie JS -->
    <script src="../main/js/lottie.min.js"></script>
        <link rel="preload" href="/../font/AlimamaAgileVF-Thin.woff2" as="font" type="font/woff2" crossorigin>
</head>
<body>
    <!-- 侧边栏 -->
    <div class="sidebar">
        <!-- 图片名称显示 -->
        <div class="sidebar-photo-name">
            <?= htmlspecialchars($currentPhoto['name']) ?>
        </div>
        <div class="sidebar-title">功能菜单</div>
        <button class="sidebar-btn" id="btn-info" title="查看信息">
            <img src="../svg/信息.svg" alt="信息">
            <span>查看信息</span>
        </button>
        <button class="sidebar-btn" id="btn-rename" title="重命名">
            <img src="../svg/重命名.svg" alt="重命名">
            <span>重命名</span>
        </button>
        <button class="sidebar-btn" id="btn-remark" title="修改备注">
            <img src="../svg/编辑.svg" alt="修改备注">
            <span>修改备注</span>
        </button>
        <button class="sidebar-btn" id="btn-copy" title="复制链接">
            <img src="../svg/复制.svg" alt="复制链接">
            <span>分享图片</span>
        </button>
        <button class="sidebar-btn" id="btn-zoom-in" title="放大 (+)">
            <img src="../svg/放大.svg" alt="放大">
            <span>放大</span>
        </button>
        <button class="sidebar-btn" id="btn-zoom-out" title="缩小 (-)">
            <img src="../svg/缩小.svg" alt="缩小">
            <span>缩小</span>
        </button>
        <button class="sidebar-btn" id="btn-rotate-left" title="逆时针旋转">
            <img src="../svg/逆时针旋转.svg" alt="逆时针旋转">
            <span>逆时针旋转</span>
        </button>
        <button class="sidebar-btn" id="btn-rotate-right" title="顺时针旋转">
            <img src="../svg/顺时针旋转.svg" alt="顺时针旋转">
            <span>顺时针旋转</span>
        </button>
        <button class="sidebar-btn" id="btn-reset" title="重置 (空格)">
            <img src="../svg/图片.svg" alt="重置">
            <span>重置</span>
        </button>
        <button class="sidebar-btn" id="btn-print" title="打印图片">
            <img src="../svg/打印.svg" alt="打印">
            <span>打印图片</span>
        </button>
        <button class="sidebar-btn" id="btn-download" title="下载图片">
            <img src="../svg/下载.svg" alt="下载">
            <span>下载图片</span>
        </button>
        <button class="sidebar-btn" id="btn-ai-repair" title="AI修图">
            <img src="../svg/AI修图.svg" alt="AI修图">
            <span>AI修图</span>
        </button>
        <button class="sidebar-btn" id="btn-ai-recognize" title="AI识图">
            <img src="../svg/AI识图.svg" alt="AI识图">
            <span>AI识图</span>
        </button>
        <button class="sidebar-btn" id="btn-extract-text" title="提取文字">
            <img src="../svg/文字提取.svg" alt="提取文字">
            <span>提取文字</span>
        </button>
    </div>

    <!-- 主内容区 -->
    <div class="main-content">
        <div class="photo-container" id="photoContainer">
            <img 
                src="<?= htmlspecialchars($currentPhoto['url']) ?>" 
                alt="<?= htmlspecialchars($currentPhoto['name']) ?>"
                id="mainPhoto"
                draggable="false"
                style="transform: scale(1) rotate(0deg); transition: transform 0.2s ease;"
            >
        </div>
    </div>

    <!-- 模态框：查看信息 -->
    <div class="modal" id="modal-info">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <h3>图片信息</h3>
            <table class="info-table">
                <tr>
                    <th>文件名</th>
                    <td><?= htmlspecialchars($currentPhoto['name']) ?></td>
                </tr>
                <tr>
                    <th>备注</th>
                    <td><?= htmlspecialchars($currentPhoto['remark']) ?: '无' ?></td>
                </tr>
                <tr>
                    <th>文件大小</th>
                    <td><?= htmlspecialchars($currentPhoto['size_kb_format']) ?></td>
                </tr>
                <tr>
                    <th>分辨率</th>
                    <td><?= htmlspecialchars($currentPhoto['size']) ?></td>
                </tr>
                <tr>
                    <th>上传时间</th>
                    <td><?= htmlspecialchars($currentPhoto['upload_time_format']) ?></td>
                </tr>

            </table>
        </div>
    </div>

    <!-- 模态框：重命名 -->
    <div class="modal" id="modal-rename">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <h3>重命名</h3>
            <input type="text" id="newNameInput" value="<?= htmlspecialchars($currentPhoto['name']) ?>" class="modal-input">
            <div class="modal-actions">
                <button class="modal-btn cancel-btn">取消</button>
                <button class="modal-btn confirm-btn" id="confirmRename">确认</button>
            </div>
        </div>
    </div>

    <!-- 模态框：修改备注 -->
    <div class="modal" id="modal-remark">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <h3>修改备注</h3>
            <textarea id="newRemarkInput" class="modal-textarea"><?= htmlspecialchars($currentPhoto['remark']) ?></textarea>
            <div class="modal-actions">
                <button class="modal-btn cancel-btn">取消</button>
                <button class="modal-btn confirm-btn" id="confirmRemark">确认</button>
            </div>
        </div>
    </div>

    <!-- 模态框：AI修图 -->
    <div class="modal" id="modal-ai-repair">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <h3>AI修图</h3>
            <input type="text" id="repairPrompt" placeholder="输入修图提示词（如：高清修复、卡通风格）" class="modal-input">
            <div class="progress-container" id="repairProgressContainer" style="display: none;">
                <div class="progress-bar" id="repairProgressBar"></div>
                <div class="progress-text" id="repairProgressText">0%</div>
            </div>
            <div class="loading-animation" id="repairLoading" style="display: none;">
                <!-- Lottie动画容器 -->
                <div class="lottie-container" id="repairLottie"></div>
                <p>图片生成中...</p>
            </div>
            <div id="repairResult" style="display: none;"></div>
            <div class="modal-actions">
                <button class="modal-btn cancel-btn" id="cancelRepair">取消</button>
                <button class="modal-btn confirm-btn" id="confirmRepair">开始修图</button>
            </div>
        </div>
    </div>

    <!-- 模态框：AI识图（修改为手动触发） -->
    <div class="modal" id="modal-ai-recognize">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <h3>AI识图</h3>
            <!-- 新增：手动触发按钮 -->
            <div class="modal-actions" style="margin-bottom: 20px; justify-content: flex-start;">
                <button class="modal-btn confirm-btn" id="startRecognize">开始识别</button>
            </div>
            <!-- 识图进度条 -->
            <div class="progress-container" id="recognizeProgressContainer" style="display: none;">
                <div class="progress-bar" id="recognizeProgressBar"></div>
                <div class="progress-text" id="recognizeProgressText">0%</div>
            </div>
            <div class="loading-animation" id="recognizeLoading" style="display: none;">
                <!-- Lottie动画容器 -->
                <div class="lottie-container" id="recognizeLottie"></div>
                <p>正在识别图片...</p>
            </div>
            <div id="recognizeResult" class="result-content"></div>
            <div class="modal-actions">
                <button class="modal-btn confirm-btn" id="refreshRecognize">重新识别</button>
            </div>
        </div>
    </div>

    <!-- 模态框：提取文字（修改为手动触发 + 新增翻译功能） -->
    <div class="modal" id="modal-extract-text">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <h3>文字提取</h3>
            <!-- 新增：手动触发按钮 -->
            <div class="modal-actions" style="margin-bottom: 20px; justify-content: flex-start;">
                <button class="modal-btn confirm-btn" id="startExtract">开始提取</button>
            </div>
            <!-- 提取文字进度条 -->
            <div class="progress-container" id="extractProgressContainer" style="display: none;">
                <div class="progress-bar" id="extractProgressBar"></div>
                <div class="progress-text" id="extractProgressText">0%</div>
            </div>
            <div class="loading-animation" id="extractLoading" style="display: none;">
                <!-- Lottie动画容器 -->
                <div class="lottie-container" id="extractLottie"></div>
                <p>正在提取文字...</p>
            </div>
            <div id="extractResult" class="result-content"></div>
            
            <!-- 新增：翻译功能区域 -->
            <div id="translateArea" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
                <h4 style="margin-bottom: 15px; color: #2c3e50;">文字翻译</h4>
                <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                    <select id="targetLanguage" class="modal-input" style="flex: 1; margin-bottom: 0;">
                        <option value="en">英语</option>
                        <option value="ja">日语</option>
                        <option value="ko">韩语</option>
                        <option value="fr">法语</option>
                        <option value="de">德语</option>
                        <option value="es">西班牙语</option>
                        <option value="ru">俄语</option>
                        <option value="zh">中文</option>
                    </select>
                    <button class="modal-btn confirm-btn" id="translateText">翻译文字</button>
                </div>
                <div id="translateResult" class="result-content" style="min-height: 80px; margin: 0;"></div>
            </div>
            
            <div class="modal-actions">
                <button class="modal-btn confirm-btn" id="copyExtractText">复制提取的文字</button>
            </div>
        </div>
    </div>

    <!-- 提示框 -->
    <div class="toast" id="toast"></div>

    <script>
        // 全局变量
        const PHOTO_URL = "<?= htmlspecialchars($currentPhoto['url']) ?>";
        const PHOTO_NAME = "<?= htmlspecialchars($currentPhoto['name']) ?>";
        const AI_REPAIR_TIMEOUT = <?= $env['AI_REPAIR_TIMEOUT'] ?> * 1000;
        const AI_REPAIR_PROGRESS_MAX = <?= $env['AI_REPAIR_PROGRESS_MAX'] ?> * 1000;
        
        // 导入JS
        document.addEventListener('DOMContentLoaded', function() {
            // 加载主交互逻辑
            const script = document.createElement('script');
            script.src = 'js/main.js';
            document.body.appendChild(script);
        });
    </script>
</body>
</html>
