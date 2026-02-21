<?php
// 启用错误提示（开发环境）
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 允许跨域和嵌入
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("X-Frame-Options: ALLOWALL");

// 字符编码
header('Content-Type: text/html; charset=utf-8');

// -------------------------- 核心配置与参数处理 --------------------------
// 用户目录根路径
define('USER_ROOT', realpath('../users') . DIRECTORY_SEPARATOR);
// API上传地址
define('UPLOAD_API', 'https://img.scdn.io/api/v1.php');
// 用户等级对应空间（单位：KB）
$levelSpaceMap = [
    1 => 2 * 1024 * 1024,       // 2GB
    2 => 4 * 1024 * 1024,       // 4GB
    3 => 8 * 1024 * 1024,       // 8GB
    4 => 16 * 1024 * 1024,      // 16GB
    5 => 32 * 1024 * 1024,      // 32GB
    6 => 64 * 1024 * 1024,      // 64GB
    7 => 128 * 1024 * 1024,     // 128GB
    8 => 256 * 1024 * 1024,     // 256GB
    9 => 512 * 1024 * 1024,     // 512GB
    10 => 1024 * 1024 * 1024,   // 1TB
    11 => PHP_INT_MAX           // 10+级无限
];

// 获取URL参数
$urlUserEmail = isset($_GET['user']) ? trim($_GET['user']) : '';
$urlAlbumName = isset($_GET['album']) ? trim($_GET['album']) : '';
// 获取Cookie中的用户邮箱
$cookieUserEmail = isset($_COOKIE['user_email']) ? trim($_COOKIE['user_email']) : '';

// 判断是否是回收站相册 - 直接显示不存在
if ($urlAlbumName === 'chat') {
    die('
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>403</title>
        <style>
            @font-face {
                font-family: "Alimama Agile";
                src: url("../font/AlimamaAgileVF-Thin.woff2") format("woff2"),
                     url("../font/AlimamaAgileVF-Thin.woff") format("woff"),
                     url("../font/AlimamaAgileVF-Thin.ttf") format("truetype");
                font-weight: normal;
                font-style: normal;
            }
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                font-family: "Alimama Agile", sans-serif;
                -webkit-font-smoothing: antialiased;
            }
            body {
                background-color: #f5f5f7;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                color: #1d1d1f;
            }
            .error-container {
                text-align: center;
                padding: 40px 24px;
                max-width: 500px;
            }
            .error-icon {
                width: 80px;
                height: 80px;
                margin-bottom: 24px;
                opacity: 0.7;
            }
            .error-title {
                font-size: 28px;
                font-weight: 600;
                margin-bottom: 16px;
                color: #1d1d1f;
            }
            .error-desc {
                font-size: 17px;
                color: #6e6e73;
                line-height: 1.6;
                margin-bottom: 32px;
            }
            .back-btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                background-color: #0071e3;
                color: white;
                padding: 12px 24px;
                border-radius: 20px;
                text-decoration: none;
                font-size: 17px;
                font-weight: 500;
                transition: all 0.2s;
            }
            .back-btn:hover {
                background-color: #0077ed;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0, 113, 227, 0.2);
            }
            .back-btn img {
                width: 20px;
                height: 20px;
                filter: invert(1);
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <svg class="error-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2ZM12 20C7.58172 20 4 16.4183 4 12C4 7.58172 7.58172 4 12 4C16.4183 4 20 7.58172 20 12C20 16.4183 16.4183 20 12 20ZM13 7H11V13H13V7ZM13 15H11V17H13V15Z" fill="#6e6e73"/>
            </svg>
            <h1 class="error-title">这不是相册</h1>
            <a href="../album/" class="back-btn">
                <img src="../svg/返回.svg" alt="返回">
                返回相册列表
            </a>
        </div>
    </body>
    </html>
    ');
}
// 判断是否是回收站相册 - 跳转到回收站专用页面
if ($urlAlbumName === '回收站') {
    // 构造跳转URL，对用户邮箱进行URL编码避免特殊字符问题
    $encodedUserEmail = urlencode($urlUserEmail);
    $redirectUrl = "../trash/?user={$encodedUserEmail}";
    
    // 发送重定向响应头
    header("Location: {$redirectUrl}");
    // 终止后续代码执行
    exit();
}


// -------------------------- 权限验证 --------------------------
if (empty($urlUserEmail) || empty($urlAlbumName) || $urlUserEmail !== $cookieUserEmail) {
    die('<div style="text-align:center; margin-top:100px; font-family:Alimama Agile; font-size:18px; color:#ff3b30;">无权查看该相册，请确认访问权限</div>');
}

// -------------------------- 路径处理（防路径遍历） --------------------------
$encodedUserEmail = urlencode($urlUserEmail);
// 过滤危险字符
$safeAlbumName = preg_replace('/[\/:*?"<>|]/', '_', $urlAlbumName);
// 构建用户目录和相册目录
$userDir = USER_ROOT . $encodedUserEmail . DIRECTORY_SEPARATOR;
$albumDir = $userDir . $safeAlbumName . DIRECTORY_SEPARATOR;
$trashDir = $userDir . '回收站' . DIRECTORY_SEPARATOR; // 回收站目录

// 确保目录存在
foreach ([$userDir, $albumDir, $trashDir] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// -------------------------- 读取JSON文件 --------------------------
// 读取用户信息
$userJsonPath = $userDir . 'user.json';
$userData = file_exists($userJsonPath) ? json_decode(file_get_contents($userJsonPath), true) : ['level' => 1, 'used_space_kb' => 0];
$userLevel = isset($userData['level']) ? (int)$userData['level'] : 1;
$userUsedSpace = isset($userData['used_space_kb']) ? (float)$userData['used_space_kb'] : 0;
$userMaxSpace = $levelSpaceMap[min($userLevel, 11)]; // 最大空间
$userRemainSpace = $userMaxSpace - $userUsedSpace;

// 读取相册信息
$albumInfoPath = $albumDir . 'p.json';
$albumInfo = file_exists($albumInfoPath) ? json_decode(file_get_contents($albumInfoPath), true) : [
    'album_name' => $safeAlbumName,
    'create_time' => time(),
    'last_upload_time' => time(),
    'image_count' => 0,
    'used_space_kb' => 0,
    'remark' => ''
];

// 读取图片列表
$photoListPath = $albumDir . 'ls.json';
$photoList = file_exists($photoListPath) ? json_decode(file_get_contents($photoListPath), true) : [];

// -------------------------- 工具函数 --------------------------
/**
 * 记录用户操作日志
 * @param string $action 操作描述
 */
function logUserAction($userDir, $action) {
    $logPath = $userDir . 'log.txt';
    $time = date('Y-m-d H:i:s');
    $logLine = "[$time] $action" . PHP_EOL;
    file_put_contents($logPath, $logLine, FILE_APPEND | LOCK_EX);
}

/**
 * 保存JSON文件（加锁）
 * @param string $path 文件路径
 * @param array $data 数据
 * @return bool
 */
function saveJsonFile($path, $data) {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return file_put_contents($path, $json, LOCK_EX) !== false;
}

/**
 * 获取文件类型对应的SVG图标
 * @param string $fileName 文件名
 * @return string
 */
function getFileIcon($fileName) {
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $icons = [
        'gif' => '../svg/gif.svg',
        'png' => '../svg/png.svg',
        'bmp' => '../svg/bmp.svg',
        'jpg' => '../svg/jpeg.svg',
        'jpeg' => '../svg/jpeg.svg',
        'tiff' => '../svg/tiff.svg',
        'webp' => '../svg/webp.svg'
    ];
    return $icons[$ext] ?? '../svg/png.svg';
}

/**
 * 格式化文件大小（KB转易读格式）
 * @param float $sizeKb 大小（KB）
 * @return string
 */
function formatSize($sizeKb) {
    $units = ['KB', 'MB', 'GB', 'TB'];
    $size = $sizeKb;
    $unitIndex = 0;
    while ($size >= 1024 && $unitIndex < count($units)-1) {
        $size /= 1024;
        $unitIndex++;
    }
    return number_format($size, 2) . ' ' . $units[$unitIndex];
}

/**
 * 获取用户所有相册列表（排除回收站和chat）
 * @param string $userDir 用户目录
 * @return array 相册名称列表
 */
function getUserAlbums($userDir) {
    $albums = [];
    if (is_dir($userDir)) {
        $dirIterator = new DirectoryIterator($userDir);
        foreach ($dirIterator as $fileInfo) {
            if ($fileInfo->isDir() && !$fileInfo->isDot()) {
                $albumName = $fileInfo->getFilename();
                // 排除回收站和chat文件夹
                if (!in_array($albumName, ['回收站', 'chat'])) {
                    $albums[] = $albumName;
                }
            }
        }
    }
    sort($albums);
    return $albums;
}

// -------------------------- 处理AJAX请求 --------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => '未知操作'];
    
    // 批量删除/移动/复制链接
    if ($_POST['action'] === 'batch_operate') {
        $selectedPhotos = json_decode($_POST['photos'], true);
        $operateType = $_POST['type'];
        $targetAlbum = isset($_POST['target_album']) ? preg_replace('/[\/:*?"<>|]/', '_', $_POST['target_album']) : '';
        
        if (empty($selectedPhotos)) {
            $response['message'] = '请选择要操作的图片';
            echo json_encode($response);
            exit;
        }

        // 筛选要操作的图片
        $newPhotoList = [];
        $operatedPhotos = [];
        foreach ($photoList as $photo) {
            if (in_array($photo['url'], $selectedPhotos)) {
                $operatedPhotos[] = $photo;
            } else {
                $newPhotoList[] = $photo;
            }
        }

        // 复制链接操作
        if ($operateType === 'copy_links') {
            $links = [];
            foreach ($operatedPhotos as $photo) {
                $links[] = $photo['url'];
            }
            $response = [
                'success' => true, 
                'message' => '链接已复制',
                'data' => [
                    'links' => $links,
                    'links_text' => implode("\n", $links)
                ]
            ];
            echo json_encode($response);
            exit;
        }

        // 更新相册图片列表（删除/移动操作）
        saveJsonFile($photoListPath, $newPhotoList);

        // 更新相册信息
        $albumInfo['image_count'] = count($newPhotoList);
        $albumInfo['used_space_kb'] = array_sum(array_column($newPhotoList, 'size_kb'));
        saveJsonFile($albumInfoPath, $albumInfo);

        // 更新用户总空间
        $operatedSpace = array_sum(array_column($operatedPhotos, 'size_kb'));
        if ($operateType !== 'move_to_album') {
            $userData['used_space_kb'] = max(0, $userData['used_space_kb'] - $operatedSpace);
            saveJsonFile($userJsonPath, $userData);
        }

        // 不同操作的处理
        switch ($operateType) {
            case 'delete':
                logUserAction($userDir, "批量删除图片：共".count($operatedPhotos)."张，相册：{$safeAlbumName}");
                $response = ['success' => true, 'message' => '删除成功'];
                break;
                
                case 'move_to_trash':
    // 回收站相册信息
    $trashAlbumInfoPath = $trashDir . 'p.json';
    $trashAlbumInfo = file_exists($trashAlbumInfoPath) ? json_decode(file_get_contents($trashAlbumInfoPath), true) : [
        'album_name' => '回收站',
        'create_time' => time(),
        'last_upload_time' => time(),
        'image_count' => 0,
        'used_space_kb' => 0,
        'remark' => '回收站'
    ];
    // 回收站图片列表
    $trashPhotoListPath = $trashDir . 'ls.json';
    $trashPhotoList = file_exists($trashPhotoListPath) ? json_decode(file_get_contents($trashPhotoListPath), true) : [];
    
    // ========== 新增：记录删除时间和来源相册 ==========
    $trashTime = time(); // 当前时间戳作为移入时间
    $originalAlbum = $safeAlbumName; // 原始相册名称（已过滤危险字符）
    $photosWithMeta = [];
    
    foreach ($operatedPhotos as $photo) {
        // 仅在首次移入时添加字段，避免重复操作
        if (!isset($photo['trash_time'])) {
            $photo['trash_time'] = $trashTime;
        }
        if (!isset($photo['original_album'])) {
            $photo['original_album'] = $originalAlbum;
        }
        $photosWithMeta[] = $photo;
    }
    // ========== 结束新增 ==========
    
    // 添加到回收站（使用带元数据的图片数据）
    $trashPhotoList = array_merge($trashPhotoList, $photosWithMeta);
    saveJsonFile($trashPhotoListPath, $trashPhotoList);
    
    // 更新回收站信息
    $trashAlbumInfo['image_count'] = count($trashPhotoList);
    $trashAlbumInfo['used_space_kb'] = array_sum(array_column($trashPhotoList, 'size_kb'));
    $trashAlbumInfo['last_upload_time'] = time();
    saveJsonFile($trashAlbumInfoPath, $trashAlbumInfo);
    
    // 优化日志，记录完整操作信息
    logUserAction($userDir, 
        "批量移动图片到回收站：共".count($operatedPhotos)."张，" .
        "原相册：{$originalAlbum}，移入时间：".date('Y-m-d H:i:s', $trashTime)
    );
    $response = ['success' => true, 'message' => '移动到回收站成功'];
    break;


            
            case 'move_to_album':
                if (empty($targetAlbum)) {
                    $response['message'] = '请选择目标相册';
                    break;
                }
                $targetAlbumDir = $userDir . $targetAlbum . DIRECTORY_SEPARATOR;
                if (!is_dir($targetAlbumDir)) {
                    mkdir($targetAlbumDir, 0755, true);
                    // 创建目标相册信息
                    saveJsonFile($targetAlbumDir . 'p.json', [
                        'album_name' => $targetAlbum,
                        'create_time' => time(),
                        'last_upload_time' => time(),
                        'image_count' => 0,
                        'used_space_kb' => 0,
                        'remark' => ''
                    ]);
                    saveJsonFile($targetAlbumDir . 'ls.json', []);
                }
                
                // 目标相册图片列表
                $targetPhotoListPath = $targetAlbumDir . 'ls.json';
                $targetPhotoList = file_exists($targetPhotoListPath) ? json_decode(file_get_contents($targetPhotoListPath), true) : [];
                $targetPhotoList = array_merge($targetPhotoList, $operatedPhotos);
                saveJsonFile($targetPhotoListPath, $targetPhotoList);
                
                // 更新目标相册信息
                $targetAlbumInfoPath = $targetAlbumDir . 'p.json';
                $targetAlbumInfo = file_exists($targetAlbumInfoPath) ? json_decode(file_get_contents($targetAlbumInfoPath), true) : [];
                $targetAlbumInfo['image_count'] = count($targetPhotoList);
                $targetAlbumInfo['used_space_kb'] = array_sum(array_column($targetPhotoList, 'size_kb'));
                $targetAlbumInfo['last_upload_time'] = time();
                saveJsonFile($targetAlbumInfoPath, $targetAlbumInfo);
                
                logUserAction($userDir, "批量移动图片：共".count($operatedPhotos)."张，从{$safeAlbumName}到{$targetAlbum}");
                $response = ['success' => true, 'message' => '移动到相册成功'];
                break;
        }
        echo json_encode($response);
        exit;
    }

    // 上传图片（优化版）
    if ($_POST['action'] === 'upload_photo') {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $response['message'] = '文件上传失败：' . ($_FILES['image']['error'] ?? '未知错误');
            echo json_encode($response);
            exit;
        }

        $file = $_FILES['image'];
        // 验证文件格式
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'bmp', 'tiff', 'gif'];
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExt, $allowedExts)) {
            $response['message'] = '不支持的文件格式，仅支持：' . implode(', ', $allowedExts);
            echo json_encode($response);
            exit;
        }

        $photoName = !empty($_POST['photo_name']) ? trim($_POST['photo_name']) : $file['name'];
        $photoRemark = !empty($_POST['photo_remark']) ? trim($_POST['photo_remark']) : '';

        // 自动添加后缀
        if (!in_array(pathinfo($photoName, PATHINFO_EXTENSION), $allowedExts)) {
            $photoName .= '.' . $fileExt;
        }

        // 调用上传API
        $cfile = new CURLFile($file['tmp_name'], mime_content_type($file['tmp_name']), $file['name']);
        $postData = ['image' => $cfile, 'cdn_domain' => 'img.scdn.io'];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, UPLOAD_API);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 生产环境建议开启
        $apiResponse = curl_exec($ch);
        $curlError = curl_errno($ch);
        curl_close($ch);

        if ($curlError) {
            $response['message'] = '上传接口调用失败：' . curl_error($ch);
            echo json_encode($response);
            exit;
        }

        $apiData = json_decode($apiResponse, true);
        if (!$apiData || !$apiData['success']) {
            $response['message'] = '图片上传失败：' . ($apiData['message'] ?? '未知错误');
            echo json_encode($response);
            exit;
        }

        // 获取图片信息
        $imageSize = getimagesize($file['tmp_name']);
        $imageDimension = $imageSize ? $imageSize[0] . '*' . $imageSize[1] : '0*0';
        $fileSizeKb = round($file['size'] / 1024, 2);
        $uploadTime = time();

        // 构建图片信息
        $photoData = [
            'url' => $apiData['url'],
            'size_kb' => $fileSizeKb,
            'upload_time' => $uploadTime,
            'tags' => [],
            'size' => $imageDimension,
            'name' => $photoName,
            'remark' => $photoRemark
        ];

        // 更新图片列表
        $photoList[] = $photoData;
        saveJsonFile($photoListPath, $photoList);

        // 更新相册信息
        $albumInfo['image_count'] = count($photoList);
        $albumInfo['used_space_kb'] += $fileSizeKb;
        $albumInfo['last_upload_time'] = $uploadTime;
        saveJsonFile($albumInfoPath, $albumInfo);

        // 更新用户空间
        $userData['used_space_kb'] += $fileSizeKb;
        saveJsonFile($userJsonPath, $userData);

        // 记录日志
        logUserAction($userDir, "上传图片：{$photoName}，相册：{$safeAlbumName}，大小：{$fileSizeKb}KB");

        $response = ['success' => true, 'message' => '上传成功', 'data' => $photoData];
        echo json_encode($response);
        exit;
    }

    // 重命名图片
    if ($_POST['action'] === 'rename_photo') {
        $photoUrl = $_POST['photo_url'];
        $newName = trim($_POST['new_name']);
        
        if (empty($photoUrl) || empty($newName)) {
            $response['message'] = '参数不能为空';
            echo json_encode($response);
            exit;
        }

        // 自动补充后缀
        $found = false;
        $newPhotoList = [];
        foreach ($photoList as $photo) {
            if ($photo['url'] === $photoUrl) {
                $found = true;
                $oldName = $photo['name'];
                $ext = pathinfo($oldName, PATHINFO_EXTENSION);
                if (!empty($ext) && pathinfo($newName, PATHINFO_EXTENSION) !== $ext) {
                    $newName .= '.' . $ext;
                }
                $photo['name'] = $newName;
                logUserAction($userDir, "重命名图片：{$oldName} → {$newName}，相册：{$safeAlbumName}");
            }
            $newPhotoList[] = $photo;
        }

        if (!$found) {
            $response['message'] = '图片不存在';
        } else {
            saveJsonFile($photoListPath, $newPhotoList);
            $response = ['success' => true, 'message' => '重命名成功'];
        }
        echo json_encode($response);
        exit;
    }

    // 修改图片备注
    if ($_POST['action'] === 'update_photo_remark') {
        $photoUrl = $_POST['photo_url'];
        $newRemark = trim($_POST['new_remark']);
        
        if (empty($photoUrl)) {
            $response['message'] = '参数不能为空';
            echo json_encode($response);
            exit;
        }

        $found = false;
        $newPhotoList = [];
        foreach ($photoList as $photo) {
            if ($photo['url'] === $photoUrl) {
                $found = true;
                $photo['remark'] = $newRemark;
                logUserAction($userDir, "修改图片备注：{$photo['name']}，相册：{$safeAlbumName}");
            }
            $newPhotoList[] = $photo;
        }

        if (!$found) {
            $response['message'] = '图片不存在';
        } else {
            saveJsonFile($photoListPath, $newPhotoList);
            $response = ['success' => true, 'message' => '备注修改成功'];
        }
        echo json_encode($response);
        exit;
    }

    // 获取单张图片信息
    if ($_POST['action'] === 'get_photo_info') {
        $photoUrl = $_POST['photo_url'];
        
        if (empty($photoUrl)) {
            $response['message'] = '参数不能为空';
            echo json_encode($response);
            exit;
        }

        $photoInfo = null;
        foreach ($photoList as $photo) {
            if ($photo['url'] === $photoUrl) {
                $photoInfo = $photo;
                // 格式化时间
                $photoInfo['upload_time_str'] = date('Y-m-d H:i:s', $photoInfo['upload_time']);
                $photoInfo['size_str'] = formatSize($photoInfo['size_kb']);
                break;
            }
        }

        if (!$photoInfo) {
            $response['message'] = '图片不存在';
        } else {
            $response = ['success' => true, 'data' => $photoInfo];
        }
        echo json_encode($response);
        exit;
    }

    // 复制单张图片链接
    if ($_POST['action'] === 'copy_photo_link') {
        $photoUrl = $_POST['photo_url'];
        
        if (empty($photoUrl)) {
            $response['message'] = '参数不能为空';
            echo json_encode($response);
            exit;
        }

        $response = [
            'success' => true, 
            'message' => '链接已复制',
            'data' => ['link' => $photoUrl]
        ];
        echo json_encode($response);
        exit;
    }

    echo json_encode($response);
    exit;
}

// 获取用户相册列表（用于移动相册选择）
$userAlbums = getUserAlbums($userDir);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($albumInfo['album_name']) ?> - 相册</title>
    <style>
        /* 引入阿里妈妈灵动体 */
        @font-face {
            font-family: 'Alimama Agile';
            src: url('../font/AlimamaAgileVF-Thin.woff2') format('woff2'),
                 url('../font/AlimamaAgileVF-Thin.woff') format('woff'),
                 url('../font/AlimamaAgileVF-Thin.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        /* 全局样式（苹果风格优化版） */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Alimama Agile', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body {
            background-color: #f5f5f7;
            color: #1d1d1f;
            line-height: 1.5;
            padding-bottom: 80px;
        }

        /* 顶部导航优化 */
        .header {
            background-color: #ffffff;
            padding: 16px 24px;
            border-bottom: 1px solid #e6e6e8;
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-radius: 0 0 16px 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        }

        .back-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #0071e3;
            text-decoration: none;
            font-size: 17px;
            font-weight: 500;
            padding: 8px 12px;
            border-radius: 12px;
            transition: background-color 0.2s;
        }

        .back-btn:hover {
            background-color: #f0f8ff;
        }

        .back-btn img {
            width: 22px;
            height: 22px;
        }

        .album-title {
            font-size: 22px;
            font-weight: 600;
            color: #1d1d1f;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .view-toggle {
            display: flex;
            gap: 4px;
            background-color: #f5f5f7;
            border-radius: 12px;
            padding: 4px;
        }

        .view-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px 10px;
            border-radius: 8px;
            transition: background-color 0.2s;
        }

        .view-btn.active {
            background-color: #ffffff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .view-btn:hover:not(.active) {
            background-color: #ebebeb;
        }

        .view-btn img {
            width: 22px;
            height: 22px;
        }

        .upload-btn {
            background-color: #0071e3;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .upload-btn:hover {
            background-color: #0077ed;
            transform: translateY(-1px);
        }

        .upload-btn img {
            width: 18px;
            height: 18px;
        }

        /* 空间信息优化 */
        .space-info {
            padding: 20px 24px;
            font-size: 15px;
            color: #6e6e73;
            background-color: #ffffff;
            margin: 16px 24px;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }

        .space-bar {
            height: 8px;
            background-color: #e6e6e8;
            border-radius: 4px;
            margin-top: 12px;
            overflow: hidden;
        }

        .space-progress {
            height: 100%;
            background: linear-gradient(90deg, #0071e3 0%, #0087ff 100%);
            border-radius: 4px;
            transition: width 0.3s ease;
            width: <?= min(100, ($userUsedSpace / $userMaxSpace) * 100) ?>%;
        }

        /* 筛选搜索区域优化 - 搜索框圆角+伸长效果 */
        .filter-search {
            padding: 20px 24px;
            background-color: white;
            margin: 0 24px 16px;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }

        .search-box {
            position: relative;
            margin-bottom: 16px;
            width: 100%;
        }

        .search-box input {
            width: 100%;
            max-width: 300px;
            padding: 14px 16px 14px 44px;
            border: 1px solid #e6e6e8;
            border-radius: 20px; /* 圆角优化 */
            font-size: 15px;
            outline: none;
            transition: all 0.3s ease;
            background-color: #f9f9fb;
        }

        .search-box input:focus {
            max-width: 100%; /* 点击伸长 */
            border-color: #0071e3;
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(0, 113, 227, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            opacity: 0.7;
            pointer-events: none;
        }

        .filter-options {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }

        .filter-select {
            padding: 10px 14px;
            border: 1px solid #e6e6e8;
            border-radius: 10px;
            font-size: 15px;
            outline: none;
            background-color: #f9f9fb;
            transition: all 0.2s;
        }

        .filter-select:focus {
            border-color: #0071e3;
            box-shadow: 0 0 0 4px rgba(0, 113, 227, 0.1);
        }

        /* 图片容器优化 */
        .photos-container {
            padding: 0 24px;
            margin-top: 8px;
        }

        /* 网格视图优化 */
        .grid-view {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
        }

        .photo-card {
            background-color: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .photo-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        }

        .photo-card .photo-actions {
            position: absolute;
            top: 12px;
            right: 12px;
            display: none;
            gap: 8px;
        }

        .photo-card:hover .photo-actions {
            display: flex;
        }

        .photo-action-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: rgba(0,0,0,0.6);
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .photo-action-btn:hover {
            background-color: rgba(0,0,0,0.8);
        }

        .photo-action-btn img {
            width: 18px;
            height: 18px;
            filter: invert(1);
        }

        .photo-checkbox {
            position: absolute;
            top: 12px;
            left: 12px;
            width: 22px;
            height: 22px;
            cursor: pointer;
            z-index: 10;
            accent-color: #0071e3;
        }

        .photo-img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-bottom: 1px solid #f0f0f2;
        }

        .photo-info {
            padding: 16px;
            font-size: 15px;
        }

        .photo-name {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 6px;
            font-weight: 500;
        }

        .photo-meta {
            color: #6e6e73;
            font-size: 13px;
            display: flex;
            justify-content: space-between;
        }

        /* 列表视图优化 */
        .list-view {
            display: none;
            flex-direction: column;
            gap: 12px;
        }

        .photo-item {
            background-color: white;
            border-radius: 16px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .photo-item:hover {
            background-color: #f9f9fb;
            transform: translateX(2px);
        }

        .photo-item .photo-actions {
            margin-left: auto;
            display: none;
            gap: 8px;
        }

        .photo-item:hover .photo-actions {
            display: flex;
        }

        .photo-item-checkbox {
            width: 22px;
            height: 22px;
            cursor: pointer;
            accent-color: #0071e3;
        }

        .photo-item-icon {
            width: 56px;
            height: 56px;
            object-fit: contain;
            border-radius: 8px;
            background-color: #f5f5f7;
            padding: 8px;
        }

        .photo-item-details {
            flex: 1;
        }

        .photo-item-name {
            font-size: 16px;
            margin-bottom: 6px;
            font-weight: 500;
        }

        .photo-item-meta {
            display: flex;
            gap: 20px;
            font-size: 13px;
            color: #6e6e73;
        }

        /* 批量操作栏优化 - 替换下载为复制链接 */
        .batch-actions {
            background-color: white;
            padding: 16px 24px;
            border-top: 1px solid #e6e6e8;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            display: none;
            justify-content: space-between;
            align-items: center;
            z-index: 90;
            border-radius: 16px 16px 0 0;
            box-shadow: 0 -4px 12px rgba(0,0,0,0.04);
        }

        .batch-count {
            font-size: 16px;
            font-weight: 500;
        }

        .batch-btns {
            display: flex;
            gap: 12px;
        }

        .batch-btn {
            padding: 10px 20px;
            border-radius: 10px;
            border: none;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
        }

        .batch-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .delete-btn {
            background-color: #ff3b30;
            color: white;
        }

        .delete-btn:hover {
            background-color: #ff453a;
        }

        .move-trash-btn {
            background-color: #ff9500;
            color: white;
        }

        .move-trash-btn:hover {
            background-color: #ff9f1a;
        }

        .move-album-btn {
            background-color: #0071e3;
            color: white;
        }

        .move-album-btn:hover {
            background-color: #0077ed;
        }

        .copy-link-btn {
            background-color: #34c759;
            color: white;
        }

        .copy-link-btn:hover {
            background-color: #30d158;
        }

        /* 弹窗基础样式优化 */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0,0,0,0.5);
            z-index: 200;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(8px);
        }

        .modal-content {
            background-color: white;
            border-radius: 24px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 28px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f0f0f2;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #6e6e73;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.2s;
        }

        .close-modal:hover {
            background-color: #f0f0f2;
        }

        /* 上传弹窗优化 - 增强显示效果 */
        .upload-modal-content {
            width: 90%;
            max-width: 600px;
        }

        .upload-area {
            border: 2px dashed #e6e6e8;
            border-radius: 16px;
            padding: 48px 24px;
            text-align: center;
            margin-bottom: 24px;
            cursor: pointer;
            transition: all 0.2s;
            background-color: #f9f9fb;
            position: relative;
        }

        .upload-area:hover {
            border-color: #0071e3;
            background-color: #f0f8ff;
        }

        .upload-area .file-list {
            position: absolute;
            bottom: 16px;
            left: 0;
            width: 100%;
            padding: 0 16px;
            font-size: 13px;
            color: #6e6e73;
            text-align: left;
            max-height: 80px;
            overflow-y: auto;
        }

        .upload-icon {
            width: 56px;
            height: 56px;
            margin-bottom: 20px;
            opacity: 0.7;
        }

        .upload-text {
            font-size: 17px;
            margin-bottom: 10px;
            font-weight: 500;
        }

        .upload-hint {
            font-size: 14px;
            color: #6e6e73;
            max-width: 80%;
            margin: 0 auto;
        }

        .photo-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-bottom: 24px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .form-label {
            font-size: 15px;
            font-weight: 600;
            color: #1d1d1f;
        }

        .form-input {
            padding: 14px 16px;
            border: 1px solid #e6e6e8;
            border-radius: 12px;
            font-size: 15px;
            outline: none;
            transition: all 0.2s;
            background-color: #f9f9fb;
        }

        .form-input:focus {
            border-color: #0071e3;
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(0, 113, 227, 0.1);
        }

        .upload-submit {
            background-color: #0071e3;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-size: 17px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
        }

        .upload-submit:hover {
            background-color: #0077ed;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 113, 227, 0.2);
        }

        .upload-progress {
            height: 8px;
            background-color: #e6e6e8;
            border-radius: 4px;
            margin-top: 20px;
            display: none;
            overflow: hidden;
        }

        .upload-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #0071e3 0%, #0087ff 100%);
            border-radius: 4px;
            width: 0%;
            transition: width 0.2s ease;
        }

        /* 上传文件列表显示 */
        .upload-file-list {
            margin-top: 16px;
            padding: 12px;
            background-color: #f9f9fb;
            border-radius: 12px;
            max-height: 120px;
            overflow-y: auto;
        }

        .upload-file-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 0;
            font-size: 14px;
            border-bottom: 1px solid #f0f0f2;
        }

        .upload-file-item:last-child {
            border-bottom: none;
        }

        .upload-file-icon {
            width: 18px;
            height: 18px;
            opacity: 0.7;
        }

        /* 移动相册弹窗优化 */
        .move-album-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .album-select {
            padding: 14px 16px;
            border: 1px solid #e6e6e8;
            border-radius: 12px;
            font-size: 15px;
            outline: none;
            background-color: #f9f9fb;
            transition: all 0.2s;
        }

        .album-select:focus {
            border-color: #0071e3;
            box-shadow: 0 0 0 4px rgba(0, 113, 227, 0.1);
        }

        .move-submit {
            background-color: #0071e3;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-size: 17px;
            cursor: pointer;
            margin-top: 20px;
            transition: all 0.2s;
            font-weight: 500;
        }

        .move-submit:hover {
            background-color: #0077ed;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 113, 227, 0.2);
        }

        /* 图片信息弹窗 */
        .photo-info-content {
            max-width: 550px;
        }

        .photo-info-header {
            display: flex;
            gap: 20px;
            margin-bottom: 24px;
        }

        .photo-info-preview {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 12px;
        }

        .photo-info-basic {
            flex: 1;
        }

        .photo-info-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .photo-info-meta {
            font-size: 14px;
            color: #6e6e73;
            line-height: 1.6;
        }

        .photo-info-details {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 16px;
            font-size: 15px;
        }

        .info-label {
            color: #6e6e73;
            font-weight: 500;
        }

        .info-value {
            word-break: break-all;
        }

        .photo-remark-display {
            margin-top: 20px;
            padding: 16px;
            background-color: #f9f9fb;
            border-radius: 12px;
            font-size: 15px;
            min-height: 80px;
        }

        /* 重命名/修改备注弹窗 */
        .rename-form, .remark-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* 复制链接弹窗 */
        .copy-link-modal-content {
            max-width: 450px;
        }

        .link-content {
            padding: 16px;
            background-color: #f9f9fb;
            border-radius: 12px;
            font-size: 14px;
            word-break: break-all;
            margin-bottom: 20px;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #e6e6e8;
        }

        /* 返回顶部按钮优化 */
        .back-to-top {
            position: fixed;
            bottom: 80px;
            right: 24px;
            width: 52px;
            height: 52px;
            background-color: white;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            cursor: pointer;
            z-index: 80;
            transition: all 0.2s;
        }

        .back-to-top:hover {
            transform: scale(1.08);
            box-shadow: 0 6px 16px rgba(0,0,0,0.15);
        }

        .back-to-top img {
            width: 26px;
            height: 26px;
            filter: brightness(0.8);
        }

        /* 提示框优化 */
        .toast {
            position: fixed;
            top: 24px;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(0,0,0,0.85);
            color: white;
            padding: 14px 28px;
            border-radius: 12px;
            font-size: 15px;
            z-index: 300;
            display: none;
            transition: all 0.3s ease;
            backdrop-filter: blur(4px);
        }

        /* 响应式适配优化 */
        @media (max-width: 768px) {
            .grid-view {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 16px;
            }

            .filter-options {
                flex-direction: column;
                align-items: stretch;
            }

            .batch-actions {
                flex-direction: column;
                gap: 16px;
                padding: 20px;
            }

            .batch-btns {
                width: 100%;
                justify-content: space-between;
            }

            .batch-btn {
                padding: 8px 16px;
                font-size: 14px;
            }

            .photo-info-header {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .photo-info-details {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .info-label {
                color: #1d1d1f;
                font-weight: 600;
            }

            .search-box input {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- 顶部导航 -->
    <div class="header">
        <a href="../album/" class="back-btn">
            <img src="../svg/返回.svg" alt="返回">
            <span>返回</span>
        </a>
        <div class="album-title"><?= htmlspecialchars($albumInfo['album_name']) ?></div>
        <div class="header-actions">
            <div class="view-toggle">
                <button class="view-btn active" id="gridViewBtn">
                    <img src="../svg/网格视图.svg" alt="网格视图">
                </button>
                <button class="view-btn" id="listViewBtn">
                    <img src="../svg/列表视图.svg" alt="列表视图">
                </button>
            </div>
            <button class="upload-btn" id="uploadBtn">
                <img src="../svg/上传.svg" alt="上传">
                <span>上传图片</span>
            </button>
        </div>
    </div>

    <!-- 空间信息 -->
    <div class="space-info">
        <div>已用空间：<?= formatSize($userUsedSpace) ?> / <?= $userLevel >= 11 ? '无限' : formatSize($userMaxSpace) ?>（剩余：<?= $userLevel >= 11 ? '无限' : formatSize($userRemainSpace) ?>）</div>
        <div class="space-bar">
            <div class="space-progress"></div>
        </div>
    </div>

    <!-- 筛选搜索区域 -->
    <div class="filter-search">
        <div class="search-box">
            <img src="../svg/搜索.svg" alt="搜索" class="search-icon">
            <input type="text" id="searchInput" placeholder="搜索图片名称...">
        </div>
        <div class="filter-options">
            <select class="filter-select" id="typeFilter">
                <option value="all">所有格式</option>
                <option value="jpg">JPG/JPEG</option>
                <option value="png">PNG</option>
                <option value="webp">WEBP</option>
                <option value="gif">GIF</option>
                <option value="bmp">BMP</option>
                <option value="tiff">TIFF</option>
            </select>
            <select class="filter-select" id="sortBy">
                <option value="upload_time">按上传时间</option>
                <option value="name">按名称</option>
                <option value="size_kb">按大小</option>
            </select>
            <select class="filter-select" id="sortOrder">
                <option value="desc">倒序</option>
                <option value="asc">正序</option>
            </select>
        </div>
    </div>

    <!-- 图片容器 -->
    <div class="photos-container">
        <!-- 网格视图 -->
        <div class="grid-view" id="gridView">
            <?php foreach ($photoList as $photo): ?>
                <div class="photo-card" data-url="<?= htmlspecialchars($photo['url']) ?>">
                    <input type="checkbox" class="photo-checkbox" data-url="<?= htmlspecialchars($photo['url']) ?>">
                    <div class="photo-actions">
                        <button class="photo-action-btn" onclick="viewPhotoInfo('<?= htmlspecialchars($photo['url']) ?>')" title="查看信息">
                            <img src="../svg/信息.svg" alt="信息">
                        </button>
                        <button class="photo-action-btn" onclick="renamePhoto('<?= htmlspecialchars($photo['url']) ?>', '<?= htmlspecialchars($photo['name']) ?>')" title="重命名">
                            <img src="../svg/重命名.svg" alt="重命名">
                        </button>
                        <button class="photo-action-btn" onclick="editPhotoRemark('<?= htmlspecialchars($photo['url']) ?>', '<?= htmlspecialchars($photo['remark']) ?>')" title="修改备注">
                            <img src="../svg/备注.svg" alt="备注">
                        </button>
                        <button class="photo-action-btn" onclick="copySingleLink('<?= htmlspecialchars($photo['url']) ?>')" title="复制链接">
                            <img src="../svg/复制.svg" alt="复制链接">
                        </button>
                    </div>
                    <img src="<?= htmlspecialchars($photo['url']) ?>" alt="<?= htmlspecialchars($photo['name']) ?>" class="photo-img">
                    <div class="photo-info">
                        <div class="photo-name"><?= htmlspecialchars($photo['name']) ?></div>
                        <div class="photo-meta">
                            <span><?= formatSize($photo['size_kb']) ?></span>
                            <span><?= date('Y-m-d', $photo['upload_time']) ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- 列表视图 -->
        <div class="list-view" id="listView">
            <?php foreach ($photoList as $photo): ?>
                <div class="photo-item" data-url="<?= htmlspecialchars($photo['url']) ?>">
                    <input type="checkbox" class="photo-item-checkbox" data-url="<?= htmlspecialchars($photo['url']) ?>">
                    <img src="<?= getFileIcon($photo['name']) ?>" alt="文件图标" class="photo-item-icon">
                    <div class="photo-item-details">
                        <div class="photo-item-name"><?= htmlspecialchars($photo['name']) ?></div>
                        <div class="photo-item-meta">
                            <span><?= formatSize($photo['size_kb']) ?></span>
                            <span><?= $photo['size'] ?></span>
                            <span><?= date('Y-m-d H:i', $photo['upload_time']) ?></span>
                        </div>
                    </div>
                    <div class="photo-actions">
                        <button class="photo-action-btn" onclick="viewPhotoInfo('<?= htmlspecialchars($photo['url']) ?>')" title="查看信息">
                            <img src="../svg/信息.svg" alt="信息">
                        </button>
                        <button class="photo-action-btn" onclick="renamePhoto('<?= htmlspecialchars($photo['url']) ?>', '<?= htmlspecialchars($photo['name']) ?>')" title="重命名">
                            <img src="../svg/重命名.svg" alt="重命名">
                        </button>
                        <button class="photo-action-btn" onclick="editPhotoRemark('<?= htmlspecialchars($photo['url']) ?>', '<?= htmlspecialchars($photo['remark']) ?>')" title="修改备注">
                            <img src="../svg/备注.svg" alt="备注">
                        </button>
                        <button class="photo-action-btn" onclick="copySingleLink('<?= htmlspecialchars($photo['url']) ?>')" title="复制链接">
                            <img src="../svg/复制.svg" alt="复制链接">
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 批量操作栏 - 替换下载为复制链接 -->
    <div class="batch-actions" id="batchActions">
        <div class="batch-count" id="batchCount">已选择 <span id="selectedCount">0</span> 张图片</div>
        <div class="batch-btns">
            <button class="batch-btn delete-btn" id="batchDeleteBtn">删除</button>
            <button class="batch-btn move-trash-btn" id="batchMoveTrashBtn">移至回收站</button>
            <button class="batch-btn move-album-btn" id="batchMoveAlbumBtn">移动到相册</button>
            <button class="batch-btn copy-link-btn" id="batchCopyLinkBtn">复制链接</button>
        </div>
    </div>

    <!-- 上传弹窗 - 优化显示 -->
    <div class="modal" id="uploadModal">
        <div class="modal-content upload-modal-content">
            <div class="modal-header">
                <div class="modal-title">上传图片</div>
                <button class="close-modal" id="closeUploadModal">&times;</button>
            </div>
            <form id="uploadForm" enctype="multipart/form-data">
                <div class="upload-area" id="uploadArea">
                    <img src="../svg/上传.svg" alt="上传" class="upload-icon">
                    <div class="upload-text">点击或拖拽文件到此处上传</div>
                    <div class="upload-hint">支持格式：JPG、PNG、WEBP、BMP、TIFF、GIF</div>
                    <input type="file" id="fileInput" name="image" accept="image/*" multiple style="display: none;">
                    <div class="upload-file-list" id="uploadFileList"></div>
                </div>
                <div class="photo-form">
                    <div class="form-group">
                        <label class="form-label">图片名称</label>
                        <input type="text" class="form-input" id="photoName" placeholder="自动使用文件名（无后缀自动补充）">
                    </div>
                    <div class="form-group">
                        <label class="form-label">图片备注</label>
                        <input type="text" class="form-input" id="photoRemark" placeholder="可选">
                    </div>
                </div>
                <button type="button" class="upload-submit" id="submitUpload">开始上传</button>
                <div class="upload-progress" id="uploadProgress">
                    <div class="upload-progress-bar" id="uploadProgressBar"></div>
                </div>
            </form>
        </div>
    </div>

    <!-- 移动相册弹窗 -->
    <div class="modal" id="moveAlbumModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">移动到相册</div>
                <button class="close-modal" id="closeMoveAlbumModal">&times;</button>
                            </div>
            <div class="move-album-form">
                <div class="form-group">
                    <label class="form-label">目标相册</label>
                    <select class="album-select" id="targetAlbumSelect">
                        <?php foreach ($userAlbums as $album): ?>
                            <option value="<?= htmlspecialchars($album) ?>"><?= htmlspecialchars($album) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">新建相册（可选）</label>
                    <input type="text" class="form-input" id="newAlbumName" placeholder="输入新相册名称">
                </div>
                <button class="move-submit" id="submitMoveAlbum">确认移动</button>
            </div>
        </div>
    </div>

    <!-- 复制链接弹窗 -->
    <div class="modal" id="copyLinkModal">
        <div class="modal-content copy-link-modal-content">
            <div class="modal-header">
                <div class="modal-title">复制链接</div>
                <button class="close-modal" id="closeCopyLinkModal">&times;</button>
            </div>
            <div class="link-content" id="linkContent"></div>
            <button class="move-submit" id="copyToClipboardBtn">复制到剪贴板</button>
        </div>
    </div>

    <!-- 图片信息弹窗 -->
    <div class="modal" id="photoInfoModal">
        <div class="modal-content photo-info-content">
            <div class="modal-header">
                <div class="modal-title">图片信息</div>
                <button class="close-modal" id="closePhotoInfoModal">&times;</button>
            </div>
            <div class="photo-info-header">
                <img src="" alt="图片预览" class="photo-info-preview" id="photoInfoPreview">
                <div class="photo-info-basic">
                    <div class="photo-info-name" id="photoInfoName"></div>
                    <div class="photo-info-meta" id="photoInfoMeta"></div>
                </div>
            </div>
            <div class="photo-info-details">
                <div class="info-label">图片URL</div>
                <div class="info-value" id="photoInfoUrl"></div>
                
                <div class="info-label">文件大小</div>
                <div class="info-value" id="photoInfoSize"></div>
                
                <div class="info-label">图片尺寸</div>
                <div class="info-value" id="photoInfoDimension"></div>
                
                <div class="info-label">上传时间</div>
                <div class="info-value" id="photoInfoUploadTime"></div>
            </div>
            <div class="form-group" style="margin-top: 20px;">
                <label class="form-label">备注信息</label>
                <div class="photo-remark-display" id="photoInfoRemark"></div>
            </div>
        </div>
    </div>

    <!-- 重命名弹窗 -->
    <div class="modal" id="renameModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">重命名图片</div>
                <button class="close-modal" id="closeRenameModal">&times;</button>
            </div>
            <div class="rename-form">
                <div class="form-group">
                    <label class="form-label">当前名称</label>
                    <div class="info-value" id="currentPhotoName"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">新名称</label>
                    <input type="text" class="form-input" id="newPhotoName" placeholder="输入新名称（无需后缀）">
                </div>
                <button class="move-submit" id="submitRename">确认重命名</button>
            </div>
        </div>
    </div>

    <!-- 修改备注弹窗 -->
    <div class="modal" id="remarkModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">修改图片备注</div>
                <button class="close-modal" id="closeRemarkModal">&times;</button>
            </div>
            <div class="remark-form">
                <div class="form-group">
                    <label class="form-label">图片名称</label>
                    <div class="info-value" id="remarkPhotoName"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">备注内容</label>
                    <textarea class="form-input" id="newPhotoRemark" placeholder="输入备注信息（可选）" rows="4"></textarea>
                </div>
                <button class="move-submit" id="submitRemark">保存备注</button>
            </div>
        </div>
    </div>

    <!-- 返回顶部按钮 -->
    <div class="back-to-top" id="backToTop">
        <img src="../svg/返回.svg" alt="返回顶部" style="transform: rotate(180deg);">
    </div>

    <!-- 提示框 -->
    <div class="toast" id="toast"></div>
<script>
// ========== 统一的智能打开函数 ==========

// 生成唯一消息 ID
function generateMsgId() {
    return `${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
}

// 智能打开链接：检测 Tauri 环境，支持多层 iframe
function openLinkSmart(url) {
    console.log('openLinkSmart called:', url);
    
    // 检测是否在 Tauri 环境中（任何一层有 __TAURI__ 就算）
    const isTauri = (function() {
        let win = window;
        while (win) {
            if (win.__TAURI__?.core) return true;
            if (win === win.parent) break; // 到顶层了
            win = win.parent;
        }
        return false;
    })();
    
    if (isTauri) {
        console.log('Tauri detected in hierarchy, sending postMessage to top');
        // Tauri 环境：发消息到顶层，让 Tauri 创建新窗口
        const message = {
            type: 'OPEN_EXTERNAL_LINK',
            url: url,
            msgId: generateMsgId(),
            timestamp: Date.now()
        };
        window.top.postMessage(message, '*');
    } else {
        // 普通浏览器：直接用 window.open
        console.log('Not in Tauri, using window.open');
        window.open(url, '_blank');
    }
}
    
    
        // 全局变量
        let selectedPhotos = [];
        let allPhotos = <?= json_encode($photoList) ?>;
        let currentUploadFiles = [];
        let currentUploadIndex = 0;
        let currentPhotoUrl = ''; // 当前操作的图片URL
        let currentCopyLinks = ''; // 待复制的链接

        // DOM加载完成
        document.addEventListener('DOMContentLoaded', function() {
            // 视图切换
            const gridViewBtn = document.getElementById('gridViewBtn');
            const listViewBtn = document.getElementById('listViewBtn');
            const gridView = document.getElementById('gridView');
            const listView = document.getElementById('listView');

            gridViewBtn.addEventListener('click', function() {
                gridView.style.display = 'grid';
                listView.style.display = 'none';
                gridViewBtn.classList.add('active');
                listViewBtn.classList.remove('active');
            });

            listViewBtn.addEventListener('click', function() {
                gridView.style.display = 'none';
                listView.style.display = 'flex';
                listViewBtn.classList.add('active');
                gridViewBtn.classList.remove('active');
            });

            // 搜索和筛选 - 搜索框圆角+伸长效果已通过CSS实现
            const searchInput = document.getElementById('searchInput');
            const typeFilter = document.getElementById('typeFilter');
            const sortBy = document.getElementById('sortBy');
            const sortOrder = document.getElementById('sortOrder');

            // 实时搜索和筛选
            function filterAndSortPhotos() {
                const searchText = searchInput.value.toLowerCase().trim();
                const selectedType = typeFilter.value;
                const sortField = sortBy.value;
                const sortDirection = sortOrder.value === 'asc' ? 1 : -1;

                // 筛选
                let filtered = allPhotos.filter(photo => {
                    // 名称筛选
                    const nameMatch = photo.name.toLowerCase().includes(searchText);
                    // 类型筛选
                    let typeMatch = true;
                    if (selectedType !== 'all') {
                        const ext = photo.name.split('.').pop().toLowerCase();
                        typeMatch = (selectedType === 'jpg' && (ext === 'jpg' || ext === 'jpeg')) || ext === selectedType;
                    }
                    return nameMatch && typeMatch;
                });

                // 排序
                filtered.sort((a, b) => {
                    let aVal = a[sortField];
                    let bVal = b[sortField];
                    
                    // 名称排序按字符串比较
                    if (sortField === 'name') {
                        return aVal.localeCompare(bVal) * sortDirection;
                    }
                    // 数值排序
                    return (aVal - bVal) * sortDirection;
                });

                // 更新视图
                renderPhotos(filtered);
            }

            // 渲染图片列表
            function renderPhotos(photos) {
                const gridView = document.getElementById('gridView');
                const listView = document.getElementById('listView');
                
                // 清空视图
                gridView.innerHTML = '';
                listView.innerHTML = '';

                // 渲染网格视图
                photos.forEach(photo => {
                    const photoCard = document.createElement('div');
                    photoCard.className = 'photo-card';
                    photoCard.dataset.url = photo.url;
                    photoCard.innerHTML = `
                        <input type="checkbox" class="photo-checkbox" data-url="${photo.url}">
                        <div class="photo-actions">
                            <button class="photo-action-btn" onclick="viewPhotoInfo('${photo.url}')" title="查看信息">
                                <img src="../svg/信息.svg" alt="信息">
                            </button>
                            <button class="photo-action-btn" onclick="renamePhoto('${photo.url}', '${photo.name}')" title="重命名">
                                <img src="../svg/重命名.svg" alt="重命名">
                            </button>
                            <button class="photo-action-btn" onclick="editPhotoRemark('${photo.url}', '${photo.remark}')" title="修改备注">
                                <img src="../svg/备注.svg" alt="备注">
                            </button>
                            <button class="photo-action-btn" onclick="copySingleLink('${photo.url}')" title="复制链接">
                                <img src="../svg/复制.svg" alt="复制链接">
                            </button>
                        </div>
                        <img src="${photo.url}" alt="${photo.name}" class="photo-img">
                        <div class="photo-info">
                            <div class="photo-name">${photo.name}</div>
                            <div class="photo-meta">
                                <span>${formatSize(photo.size_kb)}</span>
                                <span>${new Date(photo.upload_time * 1000).toLocaleDateString()}</span>
                            </div>
                        </div>
                    `;
                    gridView.appendChild(photoCard);

                    // 渲染列表视图
                    const photoItem = document.createElement('div');
                    photoItem.className = 'photo-item';
                    photoItem.dataset.url = photo.url;
                    const ext = photo.name.split('.').pop().toLowerCase();
                    const iconMap = {
                        'gif': '../svg/gif.svg',
                        'png': '../svg/png.svg',
                        'bmp': '../svg/bmp.svg',
                        'jpg': '../svg/jpeg.svg',
                        'jpeg': '../svg/jpeg.svg',
                        'tiff': '../svg/tiff.svg',
                        'webp': '../svg/webp.svg'
                    };
                    const icon = iconMap[ext] || '../svg/png.svg';
                    
                    photoItem.innerHTML = `
                        <input type="checkbox" class="photo-item-checkbox" data-url="${photo.url}">
                        <img src="${icon}" alt="文件图标" class="photo-item-icon">
                        <div class="photo-item-details">
                            <div class="photo-item-name">${photo.name}</div>
                            <div class="photo-item-meta">
                                <span>${formatSize(photo.size_kb)}</span>
                                <span>${photo.size}</span>
                                <span>${new Date(photo.upload_time * 1000).toLocaleString()}</span>
                            </div>
                        </div>
                        <div class="photo-actions">
                            <button class="photo-action-btn" onclick="viewPhotoInfo('${photo.url}')" title="查看信息">
                                <img src="../svg/信息.svg" alt="信息">
                            </button>
                            <button class="photo-action-btn" onclick="renamePhoto('${photo.url}', '${photo.name}')" title="重命名">
                                <img src="../svg/重命名.svg" alt="重命名">
                            </button>
                            <button class="photo-action-btn" onclick="editPhotoRemark('${photo.url}', '${photo.remark}')" title="修改备注">
                                <img src="../svg/备注.svg" alt="备注">
                            </button>
                            <button class="photo-action-btn" onclick="copySingleLink('${photo.url}')" title="复制链接">
                                <img src="../svg/复制.svg" alt="复制链接">
                            </button>
                        </div>
                    `;
                    listView.appendChild(photoItem);
                });

                // 重新绑定事件
                bindPhotoEvents();
                bindCheckboxEvents();
            }

            // 格式化文件大小
            function formatSize(sizeKb) {
                const units = ['KB', 'MB', 'GB', 'TB'];
                let size = sizeKb;
                let unitIndex = 0;
                while (size >= 1024 && unitIndex < units.length - 1) {
                    size /= 1024;
                    unitIndex++;
                }
                return size.toFixed(2) + ' ' + units[unitIndex];
            }

            // 绑定图片点击事件（查看图片）
            function bindPhotoEvents() {
                document.querySelectorAll('.photo-card, .photo-item').forEach(item => {
                    item.addEventListener('click', function(e) {
                        // 避免点击复选框/操作按钮时触发查看
                        if (e.target.type !== 'checkbox' && !e.target.closest('.photo-actions')) {
                            const url = this.dataset.url;
                            const albumName = encodeURIComponent('<?= $safeAlbumName ?>');
                            const userEmail = encodeURIComponent('<?= $urlUserEmail ?>');
                            const photoUrl = encodeURIComponent(url);
openLinkSmart(`https://app.mrcwoods.com/photo-look/?user=${userEmail}&album=${albumName}&photo=${photoUrl}`);
                        }
                    });
                });
            }

            // 绑定复选框事件
            function bindCheckboxEvents() {
                document.querySelectorAll('.photo-checkbox, .photo-item-checkbox').forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const url = this.dataset.url;
                        if (this.checked) {
                            if (!selectedPhotos.includes(url)) {
                                selectedPhotos.push(url);
                            }
                        } else {
                            selectedPhotos = selectedPhotos.filter(item => item !== url);
                        }
                        updateBatchActions();
                    });
                });
            }

            // 更新批量操作栏
            function updateBatchActions() {
                const batchActions = document.getElementById('batchActions');
                const selectedCount = document.getElementById('selectedCount');
                
                selectedCount.textContent = selectedPhotos.length;
                
                if (selectedPhotos.length > 0) {
                    batchActions.style.display = 'flex';
                } else {
                    batchActions.style.display = 'none';
                }
            }

            // 上传弹窗 - 优化显示效果（显示选择的文件列表）
            const uploadBtn = document.getElementById('uploadBtn');
            const uploadModal = document.getElementById('uploadModal');
            const closeUploadModal = document.getElementById('closeUploadModal');
            const uploadArea = document.getElementById('uploadArea');
            const fileInput = document.getElementById('fileInput');
            const submitUpload = document.getElementById('submitUpload');
            const uploadProgress = document.getElementById('uploadProgress');
            const uploadProgressBar = document.getElementById('uploadProgressBar');
            const uploadFileList = document.getElementById('uploadFileList');

            uploadBtn?.addEventListener('click', function() {
                uploadModal.style.display = 'flex';
            });

            closeUploadModal.addEventListener('click', function() {
                uploadModal.style.display = 'none';
                resetUploadForm();
            });

            // 点击上传区域选择文件
            uploadArea.addEventListener('click', function() {
                fileInput.click();
            });

            // 拖拽上传
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.style.borderColor = '#0071e3';
            });

            uploadArea.addEventListener('dragleave', function() {
                this.style.borderColor = '#e6e6e8';
            });

            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                this.style.borderColor = '#e6e6e8';
                if (e.dataTransfer.files.length > 0) {
                    fileInput.files = e.dataTransfer.files;
                    currentUploadFiles = Array.from(e.dataTransfer.files);
                    updateUploadFileList(); // 更新文件列表显示
                    showToast(`已选择 ${currentUploadFiles.length} 个文件`);
                }
            });

            // 文件选择变化 - 优化显示选择的文件列表
            fileInput.addEventListener('change', function() {
                currentUploadFiles = Array.from(this.files);
                updateUploadFileList(); // 更新文件列表显示
                if (currentUploadFiles.length > 0) {
                    showToast(`已选择 ${currentUploadFiles.length} 个文件`);
                }
            });

            // 更新上传文件列表显示
            function updateUploadFileList() {
                uploadFileList.innerHTML = '';
                if (currentUploadFiles.length === 0) return;
                
                currentUploadFiles.forEach((file, index) => {
                    const fileItem = document.createElement('div');
                    fileItem.className = 'upload-file-item';
                    // 获取文件图标
                    const ext = file.name.split('.').pop().toLowerCase();
                    const iconMap = {
                        'gif': '../svg/gif.svg',
                        'png': '../svg/png.svg',
                        'bmp': '../svg/bmp.svg',
                        'jpg': '../svg/jpeg.svg',
                        'jpeg': '../svg/jpeg.svg',
                        'tiff': '../svg/tiff.svg',
                        'webp': '../svg/webp.svg'
                    };
                    const icon = iconMap[ext] || '../svg/png.svg';
                    
                    fileItem.innerHTML = `
                        <img src="${icon}" class="upload-file-icon" alt="${ext}">
                        <span>${file.name} (${formatSize(file.size / 1024)})</span>
                    `;
                    uploadFileList.appendChild(fileItem);
                });
            }

            // 提交上传
            submitUpload.addEventListener('click', function() {
                if (currentUploadFiles.length === 0) {
                    showToast('请先选择要上传的图片');
                    return;
                }

                currentUploadIndex = 0;
                uploadProgress.style.display = 'block';
                uploadProgressBar.style.width = '0%';
                uploadNextFile();
            });

            // 逐个上传文件
            function uploadNextFile() {
                if (currentUploadIndex >= currentUploadFiles.length) {
                    showToast('所有图片上传完成！');
                    uploadModal.style.display = 'none';
                    resetUploadForm();
                    // 刷新页面
                    window.location.reload();
                    return;
                }

                const file = currentUploadFiles[currentUploadIndex];
                const photoName = document.getElementById('photoName').value.trim() || file.name;
                const photoRemark = document.getElementById('photoRemark').value.trim();

                const formData = new FormData();
                formData.append('action', 'upload_photo');
                formData.append('image', file);
                formData.append('photo_name', photoName);
                formData.append('photo_remark', photoRemark);

                const xhr = new XMLHttpRequest();
                xhr.open('POST', window.location.href, true);

                // 上传进度
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percent = (e.loaded / e.total) * 100;
                        const overallPercent = (currentUploadIndex / currentUploadFiles.length) * 100 + (percent / currentUploadFiles.length);
                        uploadProgressBar.style.width = overallPercent + '%';
                    }
                });

                // 上传完成
                xhr.addEventListener('load', function() {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            showToast(`上传成功：${file.name}`);
                        } else {
                            showToast(`上传失败：${response.message}`);
                        }
                    } catch (e) {
                        showToast(`上传失败：网络错误`);
                    }

                    currentUploadIndex++;
                    uploadNextFile();
                });

                // 上传错误
                xhr.addEventListener('error', function() {
                    showToast(`上传失败：${file.name}（网络错误）`);
                    currentUploadIndex++;
                    uploadNextFile();
                });

                xhr.send(formData);
            }

            // 重置上传表单
            function resetUploadForm() {
                fileInput.value = '';
                document.getElementById('photoName').value = '';
                document.getElementById('photoRemark').value = '';
                currentUploadFiles = [];
                uploadProgress.style.display = 'none';
                uploadProgressBar.style.width = '0%';
                uploadFileList.innerHTML = ''; // 清空文件列表
            }

            // 批量操作 - 替换下载为复制链接
            const batchDeleteBtn = document.getElementById('batchDeleteBtn');
            const batchMoveTrashBtn = document.getElementById('batchMoveTrashBtn');
            const batchMoveAlbumBtn = document.getElementById('batchMoveAlbumBtn');
            const batchCopyLinkBtn = document.getElementById('batchCopyLinkBtn'); // 复制链接按钮
            const moveAlbumModal = document.getElementById('moveAlbumModal');
            const closeMoveAlbumModal = document.getElementById('closeMoveAlbumModal');
            const submitMoveAlbum = document.getElementById('submitMoveAlbum');
            const targetAlbumSelect = document.getElementById('targetAlbumSelect');
            const newAlbumName = document.getElementById('newAlbumName');
            const copyLinkModal = document.getElementById('copyLinkModal');
            const closeCopyLinkModal = document.getElementById('closeCopyLinkModal');
            const linkContent = document.getElementById('linkContent');
            const copyToClipboardBtn = document.getElementById('copyToClipboardBtn');

            // 批量删除
            batchDeleteBtn.addEventListener('click', function() {
                if (!confirm('确定要删除选中的图片吗？此操作不可恢复！')) {
                    return;
                }
                batchOperate('delete');
            });

            // 移动到回收站
            batchMoveTrashBtn.addEventListener('click', function() {
                if (!confirm('确定要将选中的图片移至回收站吗？')) {
                    return;
                }
                batchOperate('move_to_trash');
            });

            // 移动到相册
            batchMoveAlbumBtn.addEventListener('click', function() {
                moveAlbumModal.style.display = 'flex';
            });

            closeMoveAlbumModal.addEventListener('click', function() {
                moveAlbumModal.style.display = 'none';
            });

            // 确认移动到相册
            submitMoveAlbum.addEventListener('click', function() {
                let targetAlbum = newAlbumName.value.trim() || targetAlbumSelect.value;
                if (!targetAlbum) {
                    showToast('请选择或输入目标相册名称');
                    return;
                }
                batchOperate('move_to_album', targetAlbum);
                moveAlbumModal.style.display = 'none';
            });

            // 批量复制链接
            batchCopyLinkBtn.addEventListener('click', function() {
                if (selectedPhotos.length === 0) {
                    showToast('请选择要复制链接的图片');
                    return;
                }
                
                // 获取选中图片的链接
                const links = selectedPhotos.map(url => url);
                currentCopyLinks = links.join('\n');
                
                // 显示复制链接弹窗
                linkContent.textContent = currentCopyLinks;
                copyLinkModal.style.display = 'flex';
            });

            // 关闭复制链接弹窗
            closeCopyLinkModal.addEventListener('click', function() {
                copyLinkModal.style.display = 'none';
                currentCopyLinks = '';
            });

            // 复制到剪贴板
            copyToClipboardBtn.addEventListener('click', function() {
                if (!currentCopyLinks) {
                    showToast('没有可复制的链接');
                    return;
                }
                
                // 使用剪贴板API复制
                navigator.clipboard.writeText(currentCopyLinks)
                    .then(() => {
                        showToast('链接已成功复制到剪贴板');
                        copyLinkModal.style.display = 'none';
                        currentCopyLinks = '';
                        // 取消选中状态
                        selectedPhotos = [];
                        updateBatchActions();
                        document.querySelectorAll('.photo-checkbox, .photo-item-checkbox').forEach(checkbox => {
                            checkbox.checked = false;
                        });
                    })
                    .catch(err => {
                        showToast('复制失败，请手动复制');
                        console.error('复制失败:', err);
                    });
            });

            // 执行批量操作
            function batchOperate(type, targetAlbum = '') {
                const formData = new FormData();
                formData.append('action', 'batch_operate');
                formData.append('photos', JSON.stringify(selectedPhotos));
                formData.append('type', type);
                if (targetAlbum) {
                    formData.append('target_album', targetAlbum);
                }

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message);
                        // 刷新页面
                        window.location.reload();
                    } else {
                        showToast(data.message);
                    }
                })
                .catch(error => {
                    showToast('操作失败：网络错误');
                });
            }

            // 返回顶部
            const backToTop = document.getElementById('backToTop');
            window.addEventListener('scroll', function() {
                if (window.scrollY > 300) {
                    backToTop.style.display = 'flex';
                } else {
                    backToTop.style.display = 'none';
                }
            });

            backToTop.addEventListener('click', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });

            // 提示框
            function showToast(message) {
                const toast = document.getElementById('toast');
                toast.textContent = message;
                toast.style.display = 'block';
                toast.style.opacity = '1';
                setTimeout(() => {
                    toast.style.opacity = '0';
                    setTimeout(() => {
                        toast.style.display = 'none';
                    }, 300);
                }, 3000);
            }

            // 绑定搜索筛选事件
            searchInput.addEventListener('input', filterAndSortPhotos);
            typeFilter.addEventListener('change', filterAndSortPhotos);
            sortBy.addEventListener('change', filterAndSortPhotos);
            sortOrder.addEventListener('change', filterAndSortPhotos);

            // 初始化
            bindPhotoEvents();
            bindCheckboxEvents();
            filterAndSortPhotos();
        });

        // 查看图片信息
        function viewPhotoInfo(photoUrl) {
            const modal = document.getElementById('photoInfoModal');
            const preview = document.getElementById('photoInfoPreview');
            const name = document.getElementById('photoInfoName');
            const meta = document.getElementById('photoInfoMeta');
            const url = document.getElementById('photoInfoUrl');
            const size = document.getElementById('photoInfoSize');
            const dimension = document.getElementById('photoInfoDimension');
            const uploadTime = document.getElementById('photoInfoUploadTime');
            const remark = document.getElementById('photoInfoRemark');

            // 请求图片信息
            const formData = new FormData();
            formData.append('action', 'get_photo_info');
            formData.append('photo_url', photoUrl);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const info = data.data;
                    preview.src = info.url;
                    name.textContent = info.name;
                    meta.textContent = `${info.size_str} · ${info.size}`;
                    url.textContent = info.url;
                    size.textContent = info.size_str;
                    dimension.textContent = info.size;
                    uploadTime.textContent = info.upload_time_str;
                    remark.textContent = info.remark || '无备注信息';
                    
                    modal.style.display = 'flex';
                } else {
                    showToast(data.message);
                }
            })
            .catch(error => {
                showToast('获取图片信息失败');
            });

            // 关闭按钮事件
            document.getElementById('closePhotoInfoModal').onclick = function() {
                modal.style.display = 'none';
            };
        }

        // 重命名图片
        function renamePhoto(photoUrl, currentName) {
            currentPhotoUrl = photoUrl;
            const modal = document.getElementById('renameModal');
            const currentNameEl = document.getElementById('currentPhotoName');
            const newNameEl = document.getElementById('newPhotoName');
            
            currentNameEl.textContent = currentName;
            // 自动去除后缀填充输入框
            const nameWithoutExt = currentName.substring(0, currentName.lastIndexOf('.')) || currentName;
            newNameEl.value = nameWithoutExt;
            
            modal.style.display = 'flex';

            // 关闭按钮
            document.getElementById('closeRenameModal').onclick = function() {
                modal.style.display = 'none';
                currentPhotoUrl = '';
            };

            // 提交重命名
            document.getElementById('submitRename').onclick = function() {
                const newName = newNameEl.value.trim();
                if (!newName) {
                    showToast('新名称不能为空');
                    return;
                }

                const formData = new FormData();
                formData.append('action', 'rename_photo');
                formData.append('photo_url', currentPhotoUrl);
                formData.append('new_name', newName);

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message);
                        modal.style.display = 'none';
                        window.location.reload();
                    } else {
                        showToast(data.message);
                    }
                })
                .catch(error => {
                    showToast('重命名失败：网络错误');
                });
            };
        }

        // 修改图片备注
        function editPhotoRemark(photoUrl, currentRemark) {
            currentPhotoUrl = photoUrl;
            const modal = document.getElementById('remarkModal');
            const photoNameEl = document.getElementById('remarkPhotoName');
            const newRemarkEl = document.getElementById('newPhotoRemark');
            
            // 获取图片名称
            const photo = allPhotos.find(p => p.url === photoUrl);
            photoNameEl.textContent = photo ? photo.name : '未知图片';
            newRemarkEl.value = currentRemark;
            
            modal.style.display = 'flex';

            // 关闭按钮
            document.getElementById('closeRemarkModal').onclick = function() {
                modal.style.display = 'none';
                currentPhotoUrl = '';
            };

            // 提交备注修改
            document.getElementById('submitRemark').onclick = function() {
                const newRemark = newRemarkEl.value.trim();

                const formData = new FormData();
                formData.append('action', 'update_photo_remark');
                formData.append('photo_url', currentPhotoUrl);
                formData.append('new_remark', newRemark);

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message);
                        modal.style.display = 'none';
                        window.location.reload();
                    } else {
                        showToast(data.message);
                    }
                })
                .catch(error => {
                    showToast('修改备注失败：网络错误');
                });
            };
        }

        // 复制单张图片链接
        function copySingleLink(photoUrl) {
            // 使用剪贴板API复制
            navigator.clipboard.writeText(photoUrl)
                .then(() => {
                    showToast('链接已成功复制到剪贴板');
                })
                .catch(err => {
                    // 降级方案：显示链接让用户手动复制
                    currentCopyLinks = photoUrl;
                    const linkContent = document.getElementById('linkContent');
                    linkContent.textContent = photoUrl;
                    const copyLinkModal = document.getElementById('copyLinkModal');
                    copyLinkModal.style.display = 'flex';
                    showToast('自动复制失败，请手动复制');
                    console.error('复制失败:', err);
                });
        }

        // 全局提示函数
        function showToast(message) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.style.display = 'block';
            toast.style.opacity = '1';
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => {
                    toast.style.display = 'none';
                }, 300);
            }, 3000);
        }
    </script>
</body>
</html>
