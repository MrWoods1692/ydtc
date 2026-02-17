<?php
declare(strict_types=1);

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
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
case 'qrcode_scan':
    try {
        // 使用第三方 API 解码二维码
        $apiUrl = 'https://api.2dcode.biz/v1/read-qr-code?file_url=' . urlencode($photoUrl);
        
        // 尝试使用 file_get_contents
        $response = @file_get_contents($apiUrl);
        
        if ($response === false) {
            // 如果 file_get_contents 失败，尝试使用 cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
            
            $response = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($response === false) {
                throw new Exception("请求 API 失败: " . $error);
            }
        }
        
        // 解析 JSON 响应
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("API 返回数据解析失败");
        }
        
        // 检查 API 返回状态
        if (!isset($result['code']) || $result['code'] !== 0) {
            $msg = $result['message'] ?? '未知错误';
            throw new Exception("二维码识别失败: " . $msg);
        }
        
        // 检查是否有识别结果
        if (empty($result['data']['contents'][0])) {
            throw new Exception("图片中未识别到二维码");
        }
        
        $qrContent = $result['data']['contents'][0];
        
        $response = ['status' => 'success', 'data' => $qrContent];
        
    } catch (\Exception $e) {
        error_log("QRCode Error: " . $e->getMessage());
        $response = ['status' => 'error', 'msg' => $e->getMessage()];
    }
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
    exit;
                
                
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
    <meta name="description" content="安全稳定的云端图片储存服务，支持原图备份、分类、多端同步。免费起步，TB级空间任选，一键分享外链，摄影师与设计师的首选云相册。">
    <meta name="description" content="全球CDN加速的云端图片储存，秒开预览不卡顿。自动压缩省流量，支持多种格式托管，外链直传论坛与电商，新用户送2GB空间。">
    <meta name="description" content="端到端加密的私密云端图库，本地密钥掌控数据主权。防误删回收站、异地容灾备份，家庭照片与企业素材的安全保险箱。">
    <meta name="description" content="摄影师专属云端图片仓库，EXIF信息完整保留，AI智能体找图快。">
    <meta name="description" content="免费好用的云端图片储存，储存空间免费送。API接口丰富，5分钟接入网站图床，支持防盗链。">
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
        <button class="sidebar-btn" id="btn-qrcode" title="二维码识别">
            <img src="../svg/二维码.svg" alt="二维码识别">
            <span>二维码识别</span>
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
                    <option value="zh-cn">简体中文</option>
                    <option value="zh-tw">繁體中文</option>
                    <option value="ja">日语</option>
                    <option value="ko">韩语</option>
                    <option value="fr">法语</option>
                    <option value="de">德语</option>
                    <option value="es">西班牙语</option>
                    <option value="ru">俄语</option>
                    <option value="pt">葡萄牙语</option>
                    <option value="it">意大利语</option>
                    <option value="ar">阿拉伯语</option>
                    <option value="th">泰语</option>
                    <option value="vi">越南语</option>
                    <option value="wyw">文言文</option>
                    <option value="yue">粤语</option>
                    <option value="ms">马来语</option>
                    <option value="id">印尼语</option>
                    <option value="hi">印地语</option>
                    <option value="bn">孟加拉语</option>
                    <option value="fa">波斯语</option>
                    <option value="tr">土耳其语</option>
                    <option value="he">希伯来语</option>
                    <option value="ur">乌尔都语</option>
                    <option value="ta">泰米尔语</option>
                    <option value="te">泰卢固语</option>
                    <option value="mr">马拉地语</option>
                    <option value="gu">古吉拉特语</option>
                    <option value="kn">卡纳达语</option>
                    <option value="ml">马拉雅拉姆语</option>
                    <option value="pa">旁遮普语</option>
                    <option value="my">缅甸语</option>
                    <option value="km">高棉语</option>
                    <option value="lo">老挝语</option>
                    <option value="ne">尼泊尔语</option>
                    <option value="si">僧伽罗语</option>
                    <option value="nl">荷兰语</option>
                    <option value="sv">瑞典语</option>
                    <option value="no">挪威语</option>
                    <option value="da">丹麦语</option>
                    <option value="fi">芬兰语</option>
                    <option value="pl">波兰语</option>
                    <option value="cs">捷克语</option>
                    <option value="sk">斯洛伐克语</option>
                    <option value="hu">匈牙利语</option>
                    <option value="ro">罗马尼亚语</option>
                    <option value="bg">保加利亚语</option>
                    <option value="el">希腊语</option>
                    <option value="uk">乌克兰语</option>
                    <option value="sr">塞尔维亚语</option>
                    <option value="hr">克罗地亚语</option>
                    <option value="sl">斯洛文尼亚语</option>
                    <option value="et">爱沙尼亚语</option>
                    <option value="lv">拉脱维亚语</option>
                    <option value="lt">立陶宛语</option>
                    <option value="is">冰岛语</option>
                    <option value="ga">爱尔兰语</option>
                    <option value="cy">威尔士语</option>
                    <option value="ca">加泰罗尼亚语</option>
                    <option value="eu">巴斯克语</option>
                    <option value="gl">加利西亚语</option>
                    <option value="af">南非荷兰语</option>
                    <option value="sw">斯瓦希里语</option>
                    <option value="ha">豪萨语</option>
                    <option value="ig">伊博语</option>
                    <option value="yo">约鲁巴语</option>
                    <option value="zu">祖鲁语</option>
                    <option value="xh">科萨语</option>
                    <option value="mg">马尔加什语</option>
                    <option value="am">阿姆哈拉语</option>
                    <option value="so">索马里语</option>
                    <option value="mn">蒙古语</option>
                    <option value="hy">亚美尼亚语</option>
                    <option value="ka">格鲁吉亚语</option>
                    <option value="az">阿塞拜疆语</option>
                    <option value="kk">哈萨克语</option>
                    <option value="uz">乌兹别克语</option>
                    <option value="tg">塔吉克语</option>
                    <option value="be">白俄罗斯语</option>
                    <option value="eo">世界语</option>
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

    <!-- 模态框：二维码识别 -->
    <div class="modal" id="modal-qrcode">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <h3>二维码识别</h3>
            <!-- 手动触发按钮 -->
            <div class="modal-actions" style="margin-bottom: 20px; justify-content: flex-start;">
                <button class="modal-btn confirm-btn" id="startQrcode">开始识别</button>
            </div>
            <!-- 进度条 -->
            <div class="progress-container" id="qrcodeProgressContainer" style="display: none;">
                <div class="progress-bar" id="qrcodeProgressBar"></div>
                <div class="progress-text" id="qrcodeProgressText">0%</div>
            </div>
            <div class="loading-animation" id="qrcodeLoading" style="display: none;">
                <div class="lottie-container" id="qrcodeLottie"></div>
                <p>正在识别二维码...</p>
            </div>
            <div id="qrcodeResult" class="result-content"></div>
            <div class="modal-actions">
                <button class="modal-btn confirm-btn" id="refreshQrcode">重新识别</button>
                <button class="modal-btn confirm-btn" id="copyQrcode" style="display: none;">复制内容</button>
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
const cacheUtils = {
    // 获取缓存
    getCache: (photoUrl, type) => {
        // 修复：先 encodeURIComponent 再 btoa
        const key = `ai_${type}_${btoa(encodeURIComponent(photoUrl))}`;
        const cache = localStorage.getItem(key);
        if (!cache) return null;
        
        const cacheObj = JSON.parse(cache);
        if (Date.now() - cacheObj.timestamp > 3600 * 1000) {
            localStorage.removeItem(key);
            return null;
        }
        return cacheObj.data;
    },
    
    // 设置缓存
    setCache: (photoUrl, type, data) => {
        // 修复：先 encodeURIComponent 再 btoa
        const key = `ai_${type}_${btoa(encodeURIComponent(photoUrl))}`;
        localStorage.setItem(key, JSON.stringify({
            timestamp: Date.now(),
            data: data
        }));
    },
    
    // 清除指定图片的所有缓存
    clearCache: (photoUrl) => {
        const encodedKey = btoa(encodeURIComponent(photoUrl));
        Object.keys(localStorage).forEach(key => {
            if (key.startsWith(`ai_`) && key.includes(encodedKey)) {
                localStorage.removeItem(key);
            }
        });
    }
};

// 全局变量
let scale = 1;
let rotation = 0; // 旋转角度
let isDragging = false;
let startX, startY, offsetX = 0, offsetY = 0;
let repairTimer = null;
let recognizeTimer = null;
let extractTimer = null;
let translateTimer = null; 

// Lottie实例存储
let lottieInstances = {
    repair: null,
    recognize: null,
    extract: null,
    qrcode: null  // 添加这行
};

// 全局DOM元素对象（确保DOM加载完成后再获取）
let elements = {};

// ========== 全局函数挂载（解决作用域问题） ==========
window.copyRecognizeText = copyRecognizeText;
window.copyToClipboard = copyToClipboard;
window.fallbackCopyTextToClipboard = fallbackCopyTextToClipboard;

// 初始化Lottie动画
function initLottie() {
    if (typeof lottie === 'undefined') {
        console.error('Lottie库未加载');
        return;
    }
    
    // 初始化AI修图Lottie
    if (elements.repairLottie) {
        lottieInstances.repair = lottie.loadAnimation({
            container: elements.repairLottie,
            renderer: 'svg',
            loop: true,
            autoplay: false,
            path: '../lottie/下载加载.json'
        });
    }
    
    // 初始化AI识图Lottie
    if (elements.recognizeLottie) {
        lottieInstances.recognize = lottie.loadAnimation({
            container: elements.recognizeLottie,
            renderer: 'svg',
            loop: true,
            autoplay: false,
            path: '../lottie/下载加载.json'
        });
    }
    
    // 初始化文字提取Lottie
    if (elements.extractLottie) {
        lottieInstances.extract = lottie.loadAnimation({
            container: elements.extractLottie,
            renderer: 'svg',
            loop: true,
            autoplay: false,
            path: '../lottie/下载加载.json'
        });
    }
    
    // 初始化二维码识别Lottie
    if (elements.qrcodeLottie) {
        lottieInstances.qrcode = lottie.loadAnimation({
            container: elements.qrcodeLottie,
            renderer: 'svg',
            loop: true,
            autoplay: false,
            path: '../lottie/下载加载.json'
        });
    }
}

// 初始化DOM元素
function initElements() {
    elements = {
        // 模态框
        modalInfo: document.getElementById('modal-info'),
        modalRename: document.getElementById('modal-rename'),
        modalRemark: document.getElementById('modal-remark'),
        modalAiRepair: document.getElementById('modal-ai-repair'),
        modalAiRecognize: document.getElementById('modal-ai-recognize'),
        modalExtractText: document.getElementById('modal-extract-text'),
        
        // 按钮
        btnInfo: document.getElementById('btn-info'),
        btnRename: document.getElementById('btn-rename'),
        btnRemark: document.getElementById('btn-remark'),
        btnCopy: document.getElementById('btn-copy'),
        btnZoomIn: document.getElementById('btn-zoom-in'),
        btnZoomOut: document.getElementById('btn-zoom-out'),
        btnRotateLeft: document.getElementById('btn-rotate-left'),
        btnRotateRight: document.getElementById('btn-rotate-right'),
        btnReset: document.getElementById('btn-reset'),
        btnPrint: document.getElementById('btn-print'),
        btnDownload: document.getElementById('btn-download'),
        btnAiRepair: document.getElementById('btn-ai-repair'),
        btnAiRecognize: document.getElementById('btn-ai-recognize'),
        btnExtractText: document.getElementById('btn-extract-text'),
        
        // AI识图/提取文字手动触发按钮
        startRecognize: document.getElementById('startRecognize'),
        startExtract: document.getElementById('startExtract'),
        
        // 翻译功能元素
        targetLanguage: document.getElementById('targetLanguage'),
        translateText: document.getElementById('translateText'),
        translateArea: document.getElementById('translateArea'),
        translateResult: document.getElementById('translateResult'),
        
        // 其他
        mainPhoto: document.getElementById('mainPhoto'),
        photoContainer: document.getElementById('photoContainer'),
        toast: document.getElementById('toast'),
        
        // AI修图相关
        repairPrompt: document.getElementById('repairPrompt'),
        repairProgressContainer: document.getElementById('repairProgressContainer'),
        repairProgressBar: document.getElementById('repairProgressBar'),
        repairProgressText: document.getElementById('repairProgressText'),
        repairLoading: document.getElementById('repairLoading'),
        repairResult: document.getElementById('repairResult'),
        confirmRepair: document.getElementById('confirmRepair'),
        cancelRepair: document.getElementById('cancelRepair'),
        repairLottie: document.getElementById('repairLottie'),
        
        // AI识图相关
        recognizeProgressContainer: document.getElementById('recognizeProgressContainer'),
        recognizeProgressBar: document.getElementById('recognizeProgressBar'),
        recognizeProgressText: document.getElementById('recognizeProgressText'),
        recognizeLoading: document.getElementById('recognizeLoading'),
        recognizeResult: document.getElementById('recognizeResult'),
        refreshRecognize: document.getElementById('refreshRecognize'),
        recognizeLottie: document.getElementById('recognizeLottie'),
        
        // 提取文字相关
        extractProgressContainer: document.getElementById('extractProgressContainer'),
        extractProgressBar: document.getElementById('extractProgressBar'),
        extractProgressText: document.getElementById('extractProgressText'),
        extractLoading: document.getElementById('extractLoading'),
        extractResult: document.getElementById('extractResult'),
        copyExtractText: document.getElementById('copyExtractText'),
        extractLottie: document.getElementById('extractLottie'),
        
        // 重命名/备注
        newNameInput: document.getElementById('newNameInput'),
        newRemarkInput: document.getElementById('newRemarkInput'),
        confirmRename: document.getElementById('confirmRename'),
        confirmRemark: document.getElementById('confirmRemark'),
        cancelBtns: document.querySelectorAll('.cancel-btn'),
        
        // 在 elements 对象中添加
        modalQrcode: document.getElementById('modal-qrcode'),
        btnQrcode: document.getElementById('btn-qrcode'),
        startQrcode: document.getElementById('startQrcode'),
        qrcodeProgressContainer: document.getElementById('qrcodeProgressContainer'),
        qrcodeProgressBar: document.getElementById('qrcodeProgressBar'),
        qrcodeProgressText: document.getElementById('qrcodeProgressText'),
        qrcodeLoading: document.getElementById('qrcodeLoading'),
        qrcodeResult: document.getElementById('qrcodeResult'),
        refreshQrcode: document.getElementById('refreshQrcode'),
        copyQrcode: document.getElementById('copyQrcode'),
        qrcodeLottie: document.getElementById('qrcodeLottie'),
    };
}

// 初始化
function init() {
    // 先初始化DOM元素
    initElements();
    
    // 检查关键元素是否存在
    if (!elements.modalInfo || !elements.btnInfo) {
        console.error('关键DOM元素未找到，模态框功能无法初始化');
        return;
    }
    
    // 初始化Lottie
    initLottie();
    // 绑定事件
    bindEvents();
    // 初始化图片拖拽
    initDrag();
    // 绑定快捷键
    bindShortcuts();
    // 绑定滚轮缩放
    bindWheelZoom();
    
    console.log('初始化完成，模态框功能已就绪');
}

// 事件绑定
function bindEvents() {
    // 模态框关闭按钮
    document.querySelectorAll('.close-btn').forEach(btn => {
        btn.addEventListener('click', closeAllModals);
    });
    
    // 取消按钮
    elements.cancelBtns.forEach(btn => {
        btn.addEventListener('click', closeAllModals);
    });
    
    // 点击模态框外部关闭
    window.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            closeAllModals();
        }
    });
    
    // 功能按钮点击事件（增加存在性检查）
    if (elements.btnInfo) elements.btnInfo.addEventListener('click', () => openModal(elements.modalInfo));
    if (elements.btnRename) elements.btnRename.addEventListener('click', () => openModal(elements.modalRename));
    if (elements.btnRemark) elements.btnRemark.addEventListener('click', () => openModal(elements.modalRemark));
    if (elements.btnCopy) elements.btnCopy.addEventListener('click', copyPhotoUrl);
    if (elements.btnZoomIn) elements.btnZoomIn.addEventListener('click', zoomIn);
    if (elements.btnZoomOut) elements.btnZoomOut.addEventListener('click', zoomOut);
    if (elements.btnRotateLeft) elements.btnRotateLeft.addEventListener('click', rotateLeft);
    if (elements.btnRotateRight) elements.btnRotateRight.addEventListener('click', rotateRight);
    if (elements.btnReset) elements.btnReset.addEventListener('click', resetImage);
    if (elements.btnPrint) elements.btnPrint.addEventListener('click', printPhoto);
    if (elements.btnDownload) elements.btnDownload.addEventListener('click', downloadPhoto);
    if (elements.btnAiRepair) elements.btnAiRepair.addEventListener('click', () => openModal(elements.modalAiRepair));
    if (elements.btnAiRecognize) elements.btnAiRecognize.addEventListener('click', () => openModal(elements.modalAiRecognize));
    if (elements.btnExtractText) elements.btnExtractText.addEventListener('click', () => openModal(elements.modalExtractText));
    
    // AI识图手动触发
    if (elements.startRecognize) elements.startRecognize.addEventListener('click', aiRecognize);
    if (elements.refreshRecognize) elements.refreshRecognize.addEventListener('click', aiRecognize);
    
    // 提取文字手动触发
    if (elements.startExtract) elements.startExtract.addEventListener('click', extractText);
    
    // 翻译按钮事件
    if (elements.translateText) elements.translateText.addEventListener('click', translateExtractText);
    
    // 重命名确认
    if (elements.confirmRename) elements.confirmRename.addEventListener('click', updateName);
    
    // 备注确认
    if (elements.confirmRemark) elements.confirmRemark.addEventListener('click', updateRemark);
    
    // AI修图确认/取消
    if (elements.confirmRepair) elements.confirmRepair.addEventListener('click', startAiRepair);
    if (elements.cancelRepair) elements.cancelRepair.addEventListener('click', cancelAiRepair);
    
    // 复制提取文字
    if (elements.copyExtractText) elements.copyExtractText.addEventListener('click', copyExtractText);
    
    // 翻译结果复制按钮（事件委托）
    if (elements.translateResult) {
        elements.translateResult.addEventListener('click', function(e) {
            if (e.target.classList.contains('copy-translate-btn')) {
                copyTranslatedText();
            }
        });
    }
    
    // 二维码识别
    if (elements.btnQrcode) elements.btnQrcode.addEventListener('click', () => openModal(elements.modalQrcode));
    if (elements.startQrcode) elements.startQrcode.addEventListener('click', scanQrcode);
    if (elements.refreshQrcode) elements.refreshQrcode.addEventListener('click', scanQrcode);
    if (elements.copyQrcode) elements.copyQrcode.addEventListener('click', copyQrcodeResult);
}

// 打开模态框（核心修复：确保模态框能显示）
function openModal(modal) {
    if (!modal) {
        console.error('模态框元素不存在');
        return;
    }
    
    closeAllModals();
    modal.style.display = 'flex'; // 显示模态框
    modal.scrollTop = 0; // 重置滚动位置
    
    // 重置对应模态框状态
    if (modal === elements.modalAiRecognize) {
        resetRecognizeState();
    }
    if (modal === elements.modalExtractText) {
        resetExtractState();
        if (elements.translateArea) elements.translateArea.style.display = 'none';
        if (elements.translateResult) elements.translateResult.innerHTML = '';
    }
    if (modal === elements.modalQrcode) {
        resetQrcodeState();
    }
    console.log(`模态框 ${modal.id} 已打开`);
}

// 关闭所有模态框
function closeAllModals() {
    if (elements.modalInfo) elements.modalInfo.style.display = 'none';
    if (elements.modalRename) elements.modalRename.style.display = 'none';
    if (elements.modalRemark) elements.modalRemark.style.display = 'none';
    if (elements.modalAiRepair) elements.modalAiRepair.style.display = 'none';
    if (elements.modalAiRecognize) elements.modalAiRecognize.style.display = 'none';
    if (elements.modalExtractText) elements.modalExtractText.style.display = 'none';
    if (elements.modalQrcode) elements.modalQrcode.style.display = 'none';
    
    // 停止所有Lottie动画
    Object.values(lottieInstances).forEach(instance => {
        if (instance) instance.stop();
    });
    
    // 重置所有进度状态
    cancelAiRepair();
    resetRecognizeState();
    resetExtractState();
    resetTranslateState();
    resetQrcodeState();
}

// 以下是其他核心函数（保留原有逻辑，仅增加存在性检查）
function bindWheelZoom() {
    if (!elements.photoContainer) return;
    
    elements.photoContainer.addEventListener('wheel', function(e) {
        e.preventDefault();
        
        const mouseX = e.clientX;
        const mouseY = e.clientY;
        
        const rect = elements.mainPhoto.getBoundingClientRect();
        const imgX = rect.left + rect.width / 2;
        const imgY = rect.top + rect.height / 2;
        
        const offsetFromImgX = mouseX - imgX;
        const offsetFromImgY = mouseY - imgY;
        
        const delta = e.deltaY > 0 ? -0.1 : 0.1;
        const newScale = Math.max(0.1, Math.min(scale + delta, 5));
        
        const scaleRatio = newScale / scale;
        
        offsetX = (offsetX + offsetFromImgX) * scaleRatio - offsetFromImgX;
        offsetY = (offsetY + offsetFromImgY) * scaleRatio - offsetFromImgY;
        
        scale = newScale;
        
        updateImageTransform();
        
        showToast(`缩放：${(scale * 100).toFixed(0)}%`, 800);
    }, { passive: false });
}

function bindShortcuts() {
    document.addEventListener('keydown', function(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
        
        switch(e.key) {
            case '+':
            case '=':
                zoomIn();
                e.preventDefault();
                break;
            case '-':
                zoomOut();
                e.preventDefault();
                break;
            case ' ':
                resetImage();
                e.preventDefault();
                break;
            case 'p':
                printPhoto();
                e.preventDefault();
                break;
        }
    });
}

function initDrag() {
    if (!elements.photoContainer || !elements.mainPhoto) return;
    
    // PC端拖拽
    elements.photoContainer.addEventListener('mousedown', startDrag);
    document.addEventListener('mousemove', drag);
    document.addEventListener('mouseup', stopDrag);
    document.addEventListener('mouseleave', stopDrag);
    
    // 移动端拖拽
    elements.photoContainer.addEventListener('touchstart', (e) => {
        const touch = e.touches[0];
        startX = touch.clientX;
        startY = touch.clientY;
        isDragging = true;
        e.preventDefault();
        elements.mainPhoto.style.transition = 'none';
    });
    
    document.addEventListener('touchmove', (e) => {
        if (!isDragging) return;
        const touch = e.touches[0];
        const x = touch.clientX;
        const y = touch.clientY;
        const dx = x - startX;
        const dy = y - startY;
        
        updateImagePosition(dx, dy);
        e.preventDefault();
    });
    
    document.addEventListener('touchend', stopDrag);
    document.addEventListener('touchcancel', stopDrag);
}

function startDrag(e) {
    isDragging = true;
    startX = e.clientX;
    startY = e.clientY;
    
    const transform = window.getComputedStyle(elements.mainPhoto).transform;
    const matrix = new DOMMatrix(transform);
    offsetX = matrix.e;
    offsetY = matrix.f;
    
    elements.photoContainer.style.cursor = 'grabbing';
    elements.mainPhoto.style.transition = 'none';
    elements.mainPhoto.style.zIndex = '10';
}

function drag(e) {
    if (!isDragging) return;
    
    const x = e.clientX;
    const y = e.clientY;
    const dx = x - startX;
    const dy = y - startY;
    
    updateImagePosition(dx, dy);
}

function updateImagePosition(dx, dy) {
    if (!elements.mainPhoto) return;
    elements.mainPhoto.style.transform = `translate(${offsetX + dx}px, ${offsetY + dy}px) scale(${scale}) rotate(${rotation}deg)`;
}

function stopDrag() {
    if (!isDragging || !elements.mainPhoto) return;
    
    isDragging = false;
    elements.photoContainer.style.cursor = 'move';
    elements.mainPhoto.style.transition = 'transform 0.2s ease';
    elements.mainPhoto.style.zIndex = '1';
    
    const transform = window.getComputedStyle(elements.mainPhoto).transform;
    const matrix = new DOMMatrix(transform);
    offsetX = matrix.e;
    offsetY = matrix.f;
}

function resetRecognizeState() {
    if (recognizeTimer) {
        clearInterval(recognizeTimer);
        recognizeTimer = null;
    }
    
    if (elements.recognizeProgressContainer) elements.recognizeProgressContainer.style.display = 'none';
    if (elements.recognizeLoading) elements.recognizeLoading.style.display = 'none';
    if (elements.recognizeProgressBar) elements.recognizeProgressBar.style.width = '0%';
    if (elements.recognizeProgressText) elements.recognizeProgressText.textContent = '0%';
    if (elements.recognizeResult) elements.recognizeResult.innerHTML = '';
    
    if (lottieInstances.recognize) lottieInstances.recognize.stop();
}


function resetExtractState() {
    if (extractTimer) {
        clearInterval(extractTimer);
        extractTimer = null;
    }
    
    if (elements.extractProgressContainer) elements.extractProgressContainer.style.display = 'none';
    if (elements.extractLoading) elements.extractLoading.style.display = 'none';
    if (elements.extractProgressBar) elements.extractProgressBar.style.width = '0%';
    if (elements.extractProgressText) elements.extractProgressText.textContent = '0%';
    if (elements.extractResult) elements.extractResult.innerHTML = '';
    
    if (lottieInstances.extract) lottieInstances.extract.stop();
}

function resetTranslateState() {
    if (translateTimer) {
        clearInterval(translateTimer);
        translateTimer = null;
    }
    
    if (elements.translateResult) elements.translateResult.innerHTML = '';
}

function showToast(msg, duration = 3000) {
    if (!elements.toast) return;
    
    elements.toast.textContent = msg;
    elements.toast.classList.add('show');
    
    setTimeout(() => {
        elements.toast.classList.remove('show');
    }, duration);
}

function parseApiError(responseText) {
    try {
        const errorObj = JSON.parse(responseText);
        if (errorObj.code && errorObj.message && errorObj.timestamp) {
            return true;
        }
        return false;
    } catch (e) {
        return false;
    }
}

function copyPhotoUrl() {
    const input = document.createElement('input');
    input.value = PHOTO_URL;
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    document.body.removeChild(input);
    showToast('链接复制成功', 1000);
}

function zoomIn() {
    scale = Math.min(scale + 0.1, 5);
    updateImageTransform();
    showToast(`缩放：${(scale * 100).toFixed(0)}%`, 1000);
}

function zoomOut() {
    scale = Math.max(scale - 0.1, 0.1);
    updateImageTransform();
    showToast(`缩放：${(scale * 100).toFixed(0)}%`, 1000);
}

function rotateLeft() {
    rotation -= 90;
    updateImageTransform();
    showToast('逆时针旋转90°', 1000);
}

function rotateRight() {
    rotation += 90;
    updateImageTransform();
    showToast('顺时针旋转90°', 1000);
}

function resetImage() {
    scale = 1;
    rotation = 0;
    offsetX = 0;
    offsetY = 0;
    updateImageTransform();
    showToast('图片已重置', 1000);
}

function updateImageTransform() {
    if (!elements.mainPhoto) return;
    elements.mainPhoto.style.transform = `translate(${offsetX}px, ${offsetY}px) scale(${scale}) rotate(${rotation}deg)`;
}

function downloadPhoto() {
    const a = document.createElement('a');
    a.href = PHOTO_URL;
    a.download = PHOTO_URL.split('/').pop() || 'photo.jpg';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    showToast('开始下载图片', 1000);
}

function printPhoto() {
    try {
        const browserInfo = getBrowserInfo();
        console.log('打印操作 - 浏览器信息：', browserInfo);
        
        const currentTransform = elements.mainPhoto.style.transform;
        elements.mainPhoto.style.transform = 'scale(1) rotate(0deg) translate(0, 0)';
        
        setTimeout(() => {
            window.print();
            
            setTimeout(() => {
                elements.mainPhoto.style.transform = currentTransform;
            }, 100);
            
            showToast('打印预览已打开', 1500);
        }, 200);
        
    } catch (e) {
        showToast('打印功能调用失败：' + e.message, 2000);
        console.error('打印错误：', e);
    }
}

function getBrowserInfo() {
    const userAgent = navigator.userAgent.toLowerCase();
    let browser = 'unknown';
    
    if (userAgent.includes('msie') || userAgent.includes('trident')) {
        browser = 'IE';
    } else if (userAgent.includes('edge')) {
        browser = 'Edge';
    } else if (userAgent.includes('chrome')) {
        browser = 'Chrome';
    } else if (userAgent.includes('firefox')) {
        browser = 'Firefox';
    } else if (userAgent.includes('safari')) {
        browser = 'Safari';
    } else if (userAgent.includes('opera') || userAgent.includes('opr')) {
        browser = 'Opera';
    }
    
    return {
        name: browser,
        version: getBrowserVersion(userAgent, browser),
        userAgent: userAgent
    };
}

function getBrowserVersion(userAgent, browser) {
    let version = 'unknown';
    
    switch(browser) {
        case 'Chrome':
            version = userAgent.match(/chrome\/(\d+)/)[1] || 'unknown';
            break;
        case 'Firefox':
            version = userAgent.match(/firefox\/(\d+)/)[1] || 'unknown';
            break;
        case 'Safari':
            version = userAgent.match(/version\/(\d+)/)[1] || 'unknown';
            break;
        case 'Edge':
            version = userAgent.match(/edge\/(\d+)/)[1] || 'unknown';
            break;
        case 'IE':
            version = userAgent.match(/msie (\d+)/)[1] || userAgent.match(/rv:(\d+)/)[1] || 'unknown';
            break;
        case 'Opera':
            version = userAgent.match(/opera\/(\d+)/)[1] || userAgent.match(/opr\/(\d+)/)[1] || 'unknown';
            break;
    }
    
    return version;
}

function updateName() {
    if (!elements.newNameInput) return;
    
    const newName = elements.newNameInput.value.trim();
    if (!newName) {
        showToast('文件名不能为空', 2000);
        return;
    }
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=rename&newName=${encodeURIComponent(newName)}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showToast('重命名成功', 1000);
            closeAllModals();
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast('重命名失败：' + data.msg, 2000);
        }
    })
    .catch(err => {
        showToast('请求失败：' + err.message, 2000);
    });
}

function updateRemark() {
    if (!elements.newRemarkInput) return;
    
    const newRemark = elements.newRemarkInput.value.trim();
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=update_remark&newRemark=${encodeURIComponent(newRemark)}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showToast('备注修改成功', 1000);
            closeAllModals();
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast('备注修改失败：' + data.msg, 2000);
        }
    })
    .catch(err => {
        showToast('请求失败：' + err.message, 2000);
    });
}

function startAiRepair() {
    if (!elements.repairPrompt) return;
    
    const prompt = elements.repairPrompt.value.trim();
    if (!prompt) {
        showToast('请输入修图提示词', 2000);
        return;
    }
    
    const cacheKey = `${PHOTO_URL}_${prompt}`;
    const cacheData = cacheUtils.getCache(cacheKey, 'repair');
    if (cacheData) {
        if (elements.repairResult) {
            elements.repairResult.style.display = 'block';
            elements.repairResult.innerHTML = `
                <p style="color: #2ecc71; font-weight: 500; margin-bottom: 10px;">修图成功！(本地缓存)</p>
                <img src="${cacheData}" alt="修图结果" style="max-width: 100%; margin: 10px 0; border-radius: 6px;">
                <a href="${cacheData}" download="ai_repair_${Date.now()}.jpg" class="modal-btn confirm-btn" style="margin-top: 10px;">下载修图结果</a>
            `;
        }
        showToast('从本地缓存加载修图结果', 1500);
        return;
    }
    
    if (elements.repairProgressContainer) elements.repairProgressContainer.style.display = 'block';
    if (elements.repairLoading) elements.repairLoading.style.display = 'flex';
    if (elements.repairResult) elements.repairResult.style.display = 'none';
    if (elements.repairProgressBar) elements.repairProgressBar.style.width = '0%';
    if (elements.repairProgressText) elements.repairProgressText.textContent = '0%';
    
    if (lottieInstances.repair) lottieInstances.repair.play();
    
    let elapsed = 0;
    repairTimer = setInterval(() => {
        elapsed += 500;
        const progress = Math.min((elapsed / AI_REPAIR_PROGRESS_MAX) * 100, 100);
        if (elements.repairProgressBar) elements.repairProgressBar.style.width = `${progress}%`;
        if (elements.repairProgressText) elements.repairProgressText.textContent = `${Math.round(progress)}%`;
        
        if (progress >= 100) {
            clearInterval(repairTimer);
        }
    }, 500);
    
    fetch('https://yunzhiapi.cn/API/nano-banana/pro.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `token=OiIOttqk9jZS&msg=${encodeURIComponent(prompt)}&url=${encodeURIComponent(PHOTO_URL)}`,
        timeout: AI_REPAIR_TIMEOUT
    })
    .then(res => {
        return res.text().then(text => {
            return {
                ok: res.ok,
                status: res.status,
                text: text
            };
        });
    })
    .then(data => {
        clearInterval(repairTimer);
        if (elements.repairProgressContainer) elements.repairProgressContainer.style.display = 'none';
        if (elements.repairLoading) elements.repairLoading.style.display = 'none';
        if (elements.repairResult) elements.repairResult.style.display = 'block';
        
        if (lottieInstances.repair) lottieInstances.repair.stop();
        
        if (parseApiError(data.text)) {
            if (elements.repairResult) {
                elements.repairResult.innerHTML = `<p style="color: #e74c3c; font-weight: 500;">服务器繁忙，请稍后重试</p>`;
            }
            showToast('服务器繁忙，请稍后重试', 2000);
            return;
        }
        
        let responseObj;
        try {
            responseObj = JSON.parse(data.text);
        } catch (e) {
            if (elements.repairResult) {
                elements.repairResult.innerHTML = `<p style="color: #e74c3c; font-weight: 500;">服务器返回数据格式错误</p>`;
            }
            showToast('修图失败', 2000);
            return;
        }
        
        if (responseObj.status === 'success' && responseObj.image_url) {
            cacheUtils.setCache(cacheKey, 'repair', responseObj.image_url);
            
            if (elements.repairResult) {
                elements.repairResult.innerHTML = `
                    <p style="color: #2ecc71; font-weight: 500; margin-bottom: 10px;">修图成功！</p>
                    <img src="${responseObj.image_url}" alt="修图结果" style="max-width: 100%; margin: 10px 0; border-radius: 6px;">
                    <a href="${responseObj.image_url}" download="ai_repair_${Date.now()}.jpg" class="modal-btn confirm-btn" style="margin-top: 10px;">下载修图结果</a>
                `;
            }
            showToast('修图成功', 1500);
        } else {
            if (elements.repairResult) {
                elements.repairResult.innerHTML = `<p style="color: #e74c3c; font-weight: 500;">修图失败：${responseObj.msg || '未知错误'}</p>`;
            }
            showToast('修图失败', 2000);
        }
    })
    .catch(err => {
        clearInterval(repairTimer);
        if (elements.repairProgressContainer) elements.repairProgressContainer.style.display = 'none';
        if (elements.repairLoading) elements.repairLoading.style.display = 'none';
        if (elements.repairResult) elements.repairResult.style.display = 'block';
        
        if (lottieInstances.repair) lottieInstances.repair.stop();
        
        if (elements.repairResult) {
            elements.repairResult.innerHTML = `<p style="color: #e74c3c; font-weight: 500;">服务器繁忙，请稍后重试</p>`;
        }
        showToast('修图请求失败', 2000);
    });
}

function cancelAiRepair() {
    if (repairTimer) {
        clearInterval(repairTimer);
        repairTimer = null;
    }
    
    if (elements.repairProgressContainer) elements.repairProgressContainer.style.display = 'none';
    if (elements.repairLoading) elements.repairLoading.style.display = 'none';
    if (elements.repairResult) elements.repairResult.style.display = 'none';
    if (elements.repairProgressBar) elements.repairProgressBar.style.width = '0%';
    
    if (lottieInstances.repair) lottieInstances.repair.stop();
}

function aiRecognize() {
    resetRecognizeState();
    
    const cacheData = cacheUtils.getCache(PHOTO_URL, 'recognize');
    if (cacheData) {
        if (elements.recognizeResult) {
            elements.recognizeResult.innerHTML = `
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; white-space: pre-wrap; line-height: 1.6; max-height: 300px; overflow-y: auto;">
                    ${cacheData}
                </div>
                <button class="modal-btn confirm-btn" style="margin-top: 10px; padding: 6px 12px; font-size: 14px;" onclick="copyRecognizeText()">
                    复制识别结果
                </button>
            `;
        }
        showToast('从本地缓存加载识图结果', 1500);
        return;
    }
    
    if (elements.recognizeProgressContainer) elements.recognizeProgressContainer.style.display = 'block';
    if (elements.recognizeLoading) elements.recognizeLoading.style.display = 'flex';
    
    if (lottieInstances.recognize) lottieInstances.recognize.play();
    
    let elapsed = 0;
    recognizeTimer = setInterval(() => {
        elapsed += 300;
        const progress = Math.min((elapsed / 180000) * 100, 100);
        if (elements.recognizeProgressBar) elements.recognizeProgressBar.style.width = `${progress}%`;
        if (elements.recognizeProgressText) elements.recognizeProgressText.textContent = `${Math.round(progress)}%`;
    }, 300);
    
    const url = `https://yunzhiapi.cn/API/glm4.6-ocr.php?token=OiIOttqk9jZS&question=请识别图片内容并对图片进行详细描述&image=${encodeURIComponent(PHOTO_URL)}`;

    
    fetch(url)
    .then(res => {
        return res.text().then(text => {
            return {
                ok: res.ok,
                status: res.status,
                text: text
            };
        });
    })
    .then(data => {
        clearInterval(recognizeTimer);
        if (elements.recognizeProgressContainer) elements.recognizeProgressContainer.style.display = 'none';
        if (elements.recognizeLoading) elements.recognizeLoading.style.display = 'none';
        
        if (lottieInstances.recognize) lottieInstances.recognize.stop();
        
        if (parseApiError(data.text)) {
            if (elements.recognizeResult) {
                elements.recognizeResult.innerHTML = `<p style="color: #e74c3c; font-weight: 500;">服务器繁忙，请稍后重试</p>`;
            }
            showToast('服务器繁忙，请稍后重试', 2000);
            return;
        }
        
        let resultText = data.text.trim();
        resultText = resultText.replace(/<\|beginofbox\|>/g, '').replace(/<\|endofbox\|>/g, '').trim();
        
        if (!resultText) {
            resultText = '未识别到图片内容';
        }
        
        cacheUtils.setCache(PHOTO_URL, 'recognize', resultText);
        
        if (elements.recognizeResult) {
            elements.recognizeResult.innerHTML = `
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; white-space: pre-wrap; line-height: 1.6; max-height: 300px; overflow-y: auto;">
                    ${resultText}
                </div>
                <button class="modal-btn confirm-btn" style="margin-top: 10px; padding: 6px 12px; font-size: 14px;" onclick="copyRecognizeText()">
                    复制识别结果
                </button>
            `;
        }
        showToast('识图完成', 1500);
    })
    .catch(err => {
        clearInterval(recognizeTimer);
        if (elements.recognizeProgressContainer) elements.recognizeProgressContainer.style.display = 'none';
        if (elements.recognizeLoading) elements.recognizeLoading.style.display = 'none';
        
        if (lottieInstances.recognize) lottieInstances.recognize.stop();
        
        if (elements.recognizeResult) {
            elements.recognizeResult.innerHTML = `<p style="color: #e74c3c; font-weight: 500;">服务器繁忙，请稍后重试</p>`;
        }
        showToast('识图失败', 2000);
    });
}

function extractText() {
    resetExtractState();
    
    const cacheData = cacheUtils.getCache(PHOTO_URL, 'extract_text');
    if (cacheData) {
        if (elements.extractResult) {
            elements.extractResult.innerHTML = `
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; white-space: pre-wrap; line-height: 1.6; max-height: 200px; overflow-y: auto;">
                    ${cacheData}
                </div>
            `;
        }
        
        if (cacheData !== '未提取到文字' && elements.translateArea) {
            elements.translateArea.style.display = 'block';
        }
        showToast('从本地缓存加载提取结果', 1500);
        return;
    }
    
    if (elements.extractProgressContainer) elements.extractProgressContainer.style.display = 'block';
    if (elements.extractLoading) elements.extractLoading.style.display = 'flex';
    
    if (lottieInstances.extract) lottieInstances.extract.play();
    
    let elapsed = 0;
    extractTimer = setInterval(() => {
        elapsed += 300;
        const progress = Math.min((elapsed / 3000) * 100, 100);
        if (elements.extractProgressBar) elements.extractProgressBar.style.width = `${progress}%`;
        if (elements.extractProgressText) elements.extractProgressText.textContent = `${Math.round(progress)}%`;
    }, 300);
    
    const url = `https://yunzhiapi.cn/API/ocrwzsb.php?token=OiIOttqk9jZS&url=${encodeURIComponent(PHOTO_URL)}&target=chs&type=json`;

    
    fetch(url)
    .then(res => {
        return res.text().then(text => {
            return {
                ok: res.ok,
                status: res.status,
                text: text
            };
        });
    })
    .then(data => {
        clearInterval(extractTimer);
        if (elements.extractProgressContainer) elements.extractProgressContainer.style.display = 'none';
        if (elements.extractLoading) elements.extractLoading.style.display = 'none';
        
        if (lottieInstances.extract) lottieInstances.extract.stop();
        
        if (parseApiError(data.text)) {
            if (elements.extractResult) {
                elements.extractResult.innerHTML = `<p style="color: #e74c3c; font-weight: 500;">服务器繁忙，请稍后重试</p>`;
            }
            showToast('服务器繁忙，请稍后重试', 2000);
            return;
        }
        
        let resultText = '';
        try {
            const responseObj = JSON.parse(data.text);
            if (responseObj.status === 'success' && responseObj.data?.ParsedResults?.[0]?.ParsedText) {
                resultText = responseObj.data.ParsedResults[0].ParsedText.trim() || '未提取到文字';
            } else {
                resultText = '未提取到文字';
            }
        } catch (e) {
            resultText = '未提取到文字';
        }
        
        cacheUtils.setCache(PHOTO_URL, 'extract_text', resultText);
        
        if (elements.extractResult) {
            elements.extractResult.innerHTML = `
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; white-space: pre-wrap; line-height: 1.6; max-height: 200px; overflow-y: auto;">
                    ${resultText}
                </div>
            `;
        }
        
        if (resultText !== '未提取到文字' && !resultText.includes('服务器繁忙') && elements.translateArea) {
            elements.translateArea.style.display = 'block';
        }
        showToast('文字提取完成', 1500);
    })
    .catch(err => {
        clearInterval(extractTimer);
        if (elements.extractProgressContainer) elements.extractProgressContainer.style.display = 'none';
        if (elements.extractLoading) elements.extractLoading.style.display = 'none';
        
        if (lottieInstances.extract) lottieInstances.extract.stop();
        
        if (elements.extractResult) {
            elements.extractResult.innerHTML = `<p style="color: #e74c3c; font-weight: 500;">服务器繁忙，请稍后重试</p>`;
        }
        showToast('文字提取失败', 2000);
    });
}

function translateExtractText() {
    if (!elements.extractResult || !elements.targetLanguage || !elements.translateResult) return;
    
    const textElement = elements.extractResult.querySelector('div') || elements.extractResult;
    const text = textElement.textContent.trim();
    const target = elements.targetLanguage.value;
    
    if (!text || text === '未提取到文字' || text.includes('服务器繁忙')) {
        showToast('无有效文字可翻译', 2000);
        return;
    }
    
    resetTranslateState();
    elements.translateResult.innerHTML = `<div style="text-align: center; color: #666; padding: 10px;">
        <div style="margin-bottom: 8px;">翻译中...</div>
        <div style="width: 40px; height: 40px; margin: 0 auto;">
            <div style="width: 100%; height: 100%; border: 3px solid #eee; border-top: 3px solid #3498db; border-radius: 50%; animation: spin 1s linear infinite;"></div>
        </div>
    </div>
    <style>@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>`;
    
    const url = `https://yunzhiapi.cn/API/wnyyfy.php?token=OiIOttqk9jZS&msg=${encodeURIComponent(text)}&target=${encodeURIComponent(target)}`;

    
    fetch(url)
    .then(res => res.json())
    .then(data => {
        if (data.status !== 'success') {
            elements.translateResult.innerHTML = `<p style="color: #e74c3c; font-weight: 500; padding: 10px;">翻译失败：${data.message || '未知错误'}</p>`;
            showToast('翻译失败', 2000);
            return;
        }
        
const langNames = {
   'en': '英语',
   'zh-cn': '简体中文',
   'zh-tw': '繁體中文',
   'ja': '日语',
   'ko': '韩语',
   'fr': '法语',
   'de': '德语',
   'es': '西班牙语',
   'ru': '俄语',
   'pt': '葡萄牙语',
   'it': '意大利语',
   'ar': '阿拉伯语',
   'th': '泰语',
   'vi': '越南语',
   'wyw': '文言文',
   'yue': '粤语',
   'ms': '马来语',
   'id': '印尼语',
   'hi': '印地语',
   'bn': '孟加拉语',
   'fa': '波斯语',
   'tr': '土耳其语',
   'he': '希伯来语',
   'ur': '乌尔都语',
   'ta': '泰米尔语',
   'te': '泰卢固语',
   'mr': '马拉地语',
   'gu': '古吉拉特语',
   'kn': '卡纳达语',
   'ml': '马拉雅拉姆语',
   'pa': '旁遮普语',
   'my': '缅甸语',
   'km': '高棉语',
   'lo': '老挝语',
   'ne': '尼泊尔语',
   'si': '僧伽罗语',
   'nl': '荷兰语',
   'sv': '瑞典语',
   'no': '挪威语',
   'da': '丹麦语',
   'fi': '芬兰语',
   'pl': '波兰语',
   'cs': '捷克语',
   'sk': '斯洛伐克语',
   'hu': '匈牙利语',
   'ro': '罗马尼亚语',
   'bg': '保加利亚语',
   'el': '希腊语',
   'uk': '乌克兰语',
   'sr': '塞尔维亚语',
   'hr': '克罗地亚语',
   'sl': '斯洛文尼亚语',
   'et': '爱沙尼亚语',
   'lv': '拉脱维亚语',
   'lt': '立陶宛语',
   'is': '冰岛语',
   'ga': '爱尔兰语',
   'cy': '威尔士语',
   'ca': '加泰罗尼亚语',
   'eu': '巴斯克语',
   'gl': '加利西亚语',
   'af': '南非荷兰语',
   'sw': '斯瓦希里语',
   'ha': '豪萨语',
   'ig': '伊博语',
   'yo': '约鲁巴语',
   'zu': '祖鲁语',
   'xh': '科萨语',
   'mg': '马尔加什语',
   'am': '阿姆哈拉语',
   'so': '索马里语',
   'mn': '蒙古语',
   'hy': '亚美尼亚语',
   'ka': '格鲁吉亚语',
   'az': '阿塞拜疆语',
   'kk': '哈萨克语',
   'uz': '乌兹别克语',
   'tg': '塔吉克语',
   'be': '白俄罗斯语',
   'eo': '世界语'
};
        
        elements.translateResult.innerHTML = `
            <div style="background: #f0f8fb; padding: 15px; border-radius: 8px; margin-bottom: 10px;">
                <div style="color: #666; font-size: 14px; margin-bottom: 8px;">
                    <i class="fas fa-file-alt"></i> 原文
                </div>
                <div style="white-space: pre-wrap; line-height: 1.6; color: #333;">
                    ${data.data.original_text}
                </div>
            </div>
            <div style="background: #f5fafe; padding: 15px; border-radius: 8px; border-left: 4px solid #2ecc71;">
                <div style="color: #666; font-size: 14px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;">
                    <span><i class="fas fa-language"></i> ${langNames[data.data.target_language] || data.data.target_language}</span>
                </div>
                <div style="white-space: pre-wrap; line-height: 1.6; color: #2c3e50; font-weight: 500;">
                    ${data.data.translated_text}
                </div>
            </div>
        `;
        showToast(`翻译为${langNames[data.data.target_language] || data.data.target_language}成功`, 1500);
    })
    .catch(err => {
        elements.translateResult.innerHTML = `<p style="color: #e74c3c; font-weight: 500; padding: 10px;">翻译请求失败：${err.message || '网络错误'}</p>`;
        showToast('翻译失败', 2000);
    });
}

function copyRecognizeText() {
    if (!elements.recognizeResult) return;
    
    const text = elements.recognizeResult.querySelector('div')?.textContent.trim() || elements.recognizeResult.textContent.trim();
    if (!text || text.includes('服务器繁忙')) {
        showToast('无文字可复制', 2000);
        return;
    }
    
    copyToClipboard(text, '识别结果复制成功');
}

function copyTranslatedText() {
    if (!elements.translateResult) return;
    
    const translateDiv = elements.translateResult.querySelector('div:nth-child(2)');
    if (!translateDiv) {
        showToast('无翻译结果可复制', 2000);
        return;
    }
    
    const text = translateDiv.querySelector('div:last-child').textContent.trim();
    copyToClipboard(text, '翻译结果复制成功');
}

function copyExtractText() {
    if (!elements.extractResult) return;
    
    const textElement = elements.extractResult.querySelector('div') || elements.extractResult;
    const text = textElement.textContent.trim();
    
    if (!text || text === '未提取到文字' || text.includes('服务器繁忙')) {
        showToast('无文字可复制', 2000);
        return;
    }
    
    copyToClipboard(text, '提取文字复制成功');
}

function copyToClipboard(text, successMsg = '复制成功') {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text)
            .then(() => {
                showToast(successMsg, 1000);
            })
            .catch(err => {
                fallbackCopyTextToClipboard(text, successMsg);
            });
    } else {
        fallbackCopyTextToClipboard(text, successMsg);
    }
}

// 重置二维码识别状态
function resetQrcodeState() {
    if (qrcodeTimer) {
        clearInterval(qrcodeTimer);
        qrcodeTimer = null;
    }
    
    if (elements.qrcodeProgressContainer) elements.qrcodeProgressContainer.style.display = 'none';
    if (elements.qrcodeLoading) elements.qrcodeLoading.style.display = 'none';
    if (elements.qrcodeProgressBar) elements.qrcodeProgressBar.style.width = '0%';
    if (elements.qrcodeProgressText) elements.qrcodeProgressText.textContent = '0%';
    if (elements.qrcodeResult) elements.qrcodeResult.innerHTML = '';
    if (elements.copyQrcode) elements.copyQrcode.style.display = 'none';
    
    if (lottieInstances.qrcode) lottieInstances.qrcode.stop();
}

// 二维码识别
let qrcodeTimer = null;

function scanQrcode() {
    resetQrcodeState();
    
    // 检查缓存
    const cacheData = cacheUtils.getCache(PHOTO_URL, 'qrcode');
    if (cacheData) {
        displayQrcodeResult(cacheData, true);
        return;
    }
    
    if (elements.qrcodeProgressContainer) elements.qrcodeProgressContainer.style.display = 'block';
    if (elements.qrcodeLoading) elements.qrcodeLoading.style.display = 'flex';
    
    if (lottieInstances.qrcode) lottieInstances.qrcode.play();
    
    let elapsed = 0;
    qrcodeTimer = setInterval(() => {
        elapsed += 300;
        const progress = Math.min((elapsed / 5000) * 100, 100);
        if (elements.qrcodeProgressBar) elements.qrcodeProgressBar.style.width = `${progress}%`;
        if (elements.qrcodeProgressText) elements.qrcodeProgressText.textContent = `${Math.round(progress)}%`;
    }, 300);
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=qrcode_scan`
    })
    .then(res => res.json())
    .then(data => {
        clearInterval(qrcodeTimer);
        if (elements.qrcodeProgressContainer) elements.qrcodeProgressContainer.style.display = 'none';
        if (elements.qrcodeLoading) elements.qrcodeLoading.style.display = 'none';
        
        if (lottieInstances.qrcode) lottieInstances.qrcode.stop();
        
        if (data.status === 'success' && data.data) {
            cacheUtils.setCache(PHOTO_URL, 'qrcode', data.data);
            displayQrcodeResult(data.data, false);
        } else {
            throw new Error(data.msg || '未识别到二维码');
        }
    })
    .catch(err => {
        clearInterval(qrcodeTimer);
        if (elements.qrcodeProgressContainer) elements.qrcodeProgressContainer.style.display = 'none';
        if (elements.qrcodeLoading) elements.qrcodeLoading.style.display = 'none';
        
        if (lottieInstances.qrcode) lottieInstances.qrcode.stop();
        
        if (elements.qrcodeResult) {
            elements.qrcodeResult.innerHTML = `<p style="color: #e74c3c; font-weight: 500;">${err.message || '识别失败，请确保图片包含清晰的二维码'}</p>`;
        }
        showToast('二维码识别失败', 2000);
    });
}

function displayQrcodeResult(data, fromCache) {
    const cacheLabel = fromCache ? '<span style="color: #3498db; font-size: 12px; margin-left: 10px;">(缓存)</span>' : '';
    
    // 判断是否为URL
    const isUrl = /^https?:\/\//i.test(data);
    
    let html = `
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; word-break: break-all; line-height: 1.6;">
            <div style="font-weight: 500; margin-bottom: 10px; color: #2c3e50;">
                识别结果：${cacheLabel}
            </div>
            <div style="color: #555; font-family: monospace; background: #fff; padding: 10px; border-radius: 4px; border: 1px solid #e0e0e0;">
                ${escapeHtml(data)}
            </div>
        </div>
    `;
    
    // 如果是URL，添加打开链接按钮
    if (isUrl) {
        html += `
            <div style="margin-top: 1px;">
                <a href="${escapeHtml(data)}" target="_blank" class="modal-btn confirm-btn" style="text-decoration: none; display: inline-block; margin-right: 1px;">
                    打开链接
                </a>
            </div>
        `;
    }
    
    if (elements.qrcodeResult) {
        elements.qrcodeResult.innerHTML = html;
    }
    if (elements.copyQrcode) {
        elements.copyQrcode.style.display = 'inline-block';
        elements.copyQrcode.dataset.content = data;
    }
    
    showToast(fromCache ? '从缓存加载识别结果' : '二维码识别成功', 1500);
}

function copyQrcodeResult() {
    if (!elements.copyQrcode || !elements.copyQrcode.dataset.content) return;
    copyToClipboard(elements.copyQrcode.dataset.content, '二维码内容复制成功');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}


function fallbackCopyTextToClipboard(text, successMsg) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    
    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.position = "fixed";
    textArea.style.opacity = "0";
    
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        const successful = document.execCommand('copy');
        if (successful) {
            showToast(successMsg, 1000);
        } else {
            showToast('复制失败，请手动复制', 2000);
        }
    } catch (err) {
        showToast('复制失败：' + err.message, 2000);
    }
    
    document.body.removeChild(textArea);
}

// 页面卸载时清理资源
window.addEventListener('beforeunload', () => {
    Object.values(lottieInstances).forEach(instance => {
        if (instance) {
            instance.destroy();
        }
    });
    
    if (repairTimer) clearInterval(repairTimer);
    if (recognizeTimer) clearInterval(recognizeTimer);
    if (extractTimer) clearInterval(extractTimer);
    if (translateTimer) clearInterval(translateTimer);
    if (qrcodeTimer) clearInterval(qrcodeTimer);
});

// 确保DOM完全加载后再初始化
document.addEventListener('DOMContentLoaded', function() {
    init();
});
    </script>
</body>
</html>
