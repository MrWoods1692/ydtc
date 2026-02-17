<?php
/**
 * 图床上传页面 - 接口+复制双修复版
 * 核心修复：1. 接口请求头完善 2. 返回解析极致严谨 3. HTML复制格式转义 4. 保留滚动优化
 */

define('USER_TOKEN_LENGTH', 64);
define('LOG_DIR', __DIR__ . '/logs');
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('PSBCTC_SIZE_LIMIT', 2 * 1024 * 1024);

$interfaces = [
    '360tc' => [
        'name' => '360图床 - 新野API',
        'url' => 'https://api.xinyew.cn/api/360tc',
        'tips' => '稳定&&兼容',
        'file_field' => 'file',
        'theme_color' => '#F9E6FF',      
        'theme_light' => 'rgba(249, 230, 255, 0.8)',
        'text_color' => '#9C47BC',       
        'gradient' => 'linear-gradient(135deg, rgba(156, 71, 188, 0.05) 0%, rgba(175, 82, 222, 0.1) 100%)',
        'errnoKey' => 'errno',
        'errorKey' => 'error',
        'urlKey' => 'url',
        'fileNameKey' => 'imgFile',
        'successCode' => 0,
        'dataKey' => 'data'
    ],
    'sogotc' => [
        'name' => '搜狗图床 - 新野API',
        'url' => 'https://api.xinyew.cn/api/sogotc',
        'tips' => '不稳定&&图片有效期一天',
        'file_field' => 'file',
        'theme_color' => '#E6F0FF',      
        'theme_light' => 'rgba(230, 240, 255, 0.8)',
        'text_color' => '#005CC8',       
        'gradient' => 'linear-gradient(135deg, rgba(0, 92, 200, 0.05) 0%, rgba(0, 122, 255, 0.1) 100%)',
        'errnoKey' => 'errno',
        'errorKey' => 'error',
        'urlKey' => 'url',
        'fileNameKey' => 'fileName',
        'successCode' => 0,
        'dataKey' => 'data'
    ],
    'psbctc' => [
        'name' => '中国邮政图床 - 新野API',
        'url' => 'https://api.xinyew.cn/api/psbctc',
        'tips' => '不稳定&&图片大小限制2MB',
        'file_field' => 'file',
        'theme_color' => '#E6FFEF',      
        'theme_light' => 'rgba(230, 255, 239, 0.8)',
        'text_color' => '#2DA848',       
        'gradient' => 'linear-gradient(135deg, rgba(45, 168, 72, 0.05) 0%, rgba(52, 199, 89, 0.1) 100%)',
        'errnoKey' => 'errno',
        'errorKey' => 'message',
        'urlKey' => 'url',
        'fileNameKey' => 'imgFile',
        'successCode' => 0,
        'dataKey' => 'data'
    ],
    'yanxuantc' => [
        'name' => '网易严选图床 - 新野API',
        'url' => 'https://api.xinyew.cn/api/yanxuantc',
        'tips' => '不稳定 ',
        'file_field' => 'file',
        'theme_color' => '#FFF3E6',      
        'theme_light' => 'rgba(255, 243, 230, 0.8)',
        'text_color' => '#E07B00',       
        'gradient' => 'linear-gradient(135deg, rgba(224, 123, 0, 0.05) 0%, rgba(255, 149, 0, 0.1) 100%)',
        'errnoKey' => 'errno',
        'errorKey' => 'error',
        'urlKey' => 'url',
        'fileNameKey' => 'imgFile',
        'successCode' => 0,
        'dataKey' => 'data'
    ]
];

function validateUserToken() {
    if (!isset($_COOKIE['user_token']) || strlen(trim($_COOKIE['user_token'])) !== USER_TOKEN_LENGTH) {
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode([
            'code' => 403,
            'msg' => '权限验证失败：不存在有效的用户令牌',
        ], JSON_UNESCAPED_UNICODE));
    }
    return trim($_COOKIE['user_token']);
}
$userToken = validateUserToken();

function initLogDir() {
    if (!is_dir(LOG_DIR)) {
        mkdir(LOG_DIR, 0755, true);
        chmod(LOG_DIR, 0755);
    }
}

function writeLog($interfaceName, $fileName, $fileSize, $success, $msg, $url = '') {
    initLogDir();
    $logFile = LOG_DIR . '/upload_' . date('Ymd') . '.log';
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'user_token' => $GLOBALS['userToken'],
        'interface' => $interfaceName,
        'file_name' => $fileName,
        'file_size' => round($fileSize / 1024, 2) . 'KB',
        'success' => $success,
        'message' => $msg,
        'image_url' => $url
    ];
    $handle = fopen($logFile, 'a');
    if ($handle) {
        flock($handle, LOCK_EX);
        fwrite($handle, json_encode($logData, JSON_UNESCAPED_UNICODE) . PHP_EOL);
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

// 新增：HTML特殊字符转义函数（修复复制显示问题）
function escapeHtmlAttr($str) {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

$apiResponse = ['code' => 0, 'msg' => '', 'data' => []];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    try {
        $interfaceKey = $_POST['interface'] ?? 'sogotc';
        if (!isset($interfaces[$interfaceKey])) {
            throw new Exception('无效的上传接口选择，请选择正确的图床路线');
        }
        $interface = $interfaces[$interfaceKey];
        $file = $_FILES['file'];

        // 1. 基础文件验证
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMap = [
                UPLOAD_ERR_INI_SIZE => '文件大小超过限制',
                UPLOAD_ERR_FORM_SIZE => '文件大小超过表单限制',
                UPLOAD_ERR_PARTIAL => '文件仅部分上传',
                UPLOAD_ERR_NO_FILE => '未选择上传文件',
                UPLOAD_ERR_NO_TMP_DIR => '服务器临时目录不存在',
                UPLOAD_ERR_CANT_WRITE => '文件写入失败',
                UPLOAD_ERR_EXTENSION => '文件上传被扩展阻止'
            ];
            $errorMsg = $errorMap[$file['error']] ?? "文件上传失败（错误码：{$file['error']}）";
            throw new Exception($errorMsg);
        }
        
        // 2. 双重验证文件类型
        $fileMime = mime_content_type($file['tmp_name']);
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($fileMime, ALLOWED_MIME_TYPES) || !in_array($fileExt, $allowedExts)) {
            throw new Exception('仅支持上传JPG/PNG/GIF/WEBP格式图片（当前文件格式：' . $fileExt . '）');
        }
        
        // 3. 中国邮政大小限制
        if ($interfaceKey === 'psbctc' && $file['size'] > PSBCTC_SIZE_LIMIT) {
            throw new Exception('中国邮政图床限制文件大小不超过2MB（当前文件大小：' . round($file['size']/1024/1024, 2) . 'MB）');
        }

        // 4. CURL请求核心修复：完善请求头，模拟真实浏览器请求
        $ch = curl_init();
        $postData = [
            'file' => new CURLFile($file['tmp_name'], $fileMime, $file['name'])
        ];
        
        // 核心优化：添加完整的HTTP头，解决接口识别问题
        $headers = [
            'Accept: application/json, text/plain, */*',
            'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
            'Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : 'https://api.xinyew.cn'),
            'Referer: https://api.xinyew.cn/',
            'Sec-Fetch-Dest: empty',
            'Sec-Fetch-Mode: cors',
            'Sec-Fetch-Site: same-origin',
            'Connection: keep-alive',
            'Cache-Control: no-cache',
            'Pragma: no-cache'
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $interface['url'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30, // 延长超时时间
            CURLOPT_CONNECTTIMEOUT => 15, // 延长连接超时
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36 Edg/121.0.0.0',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5, // 限制重定向次数
            CURLOPT_FAILONERROR => false, // 关闭错误失败，手动处理HTTP状态码
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => $headers, // 添加自定义头
            CURLOPT_ENCODING => 'gzip, deflate', // 支持压缩
            CURLOPT_VERBOSE => false // 调试用，发布时关闭
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        // 记录详细日志（便于调试）
        writeLog($interface['name'], $file['name'], $file['size'], false, 
            "HTTP状态码：{$httpCode} | Content-Type：{$contentType} | CURL错误码：{$curlErrno} | 原始响应：" . substr($response, 0, 800), '');

        // 5. CURL错误处理
        if ($curlError) {
            throw new Exception("API请求失败：{$curlError}（CURL错误码：{$curlErrno} | HTTP状态码：{$httpCode}）");
        }
        
        if (empty($response)) {
            throw new Exception('API返回空响应，服务器未返回有效数据（HTTP状态码：' . $httpCode . ' | Content-Type：' . $contentType . '）');
        }
        
        // 6. 修复JSON解析：处理可能的BOM头/空白字符
        $cleanResponse = trim($response);
        // 移除UTF-8 BOM头
        if (substr($cleanResponse, 0, 3) === "\xEF\xBB\xBF") {
            $cleanResponse = substr($cleanResponse, 3);
        }
        
        $responseData = json_decode($cleanResponse, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('API返回非JSON格式数据（JSON解析错误：' . json_last_error_msg() . '），响应内容：' . substr($cleanResponse, 0, 200) . '...');
        }

        // 7. 极致严谨的返回解析（完全匹配API文档）
        $errnoKey = $interface['errnoKey'];
        $errorKey = $interface['errorKey'];
        $dataKey = $interface['dataKey'];
        $urlKey = $interface['urlKey'];
        $fileNameKey = $interface['fileNameKey'];
        $successCode = $interface['successCode'];
        
        // 7.1 验证错误码字段存在
        if (!array_key_exists($errnoKey, $responseData)) {
            throw new Exception("API返回数据格式异常（{$interfaceKey}）：未找到错误码字段【{$errnoKey}】，返回数据：" . json_encode($responseData, JSON_UNESCAPED_UNICODE));
        }
        
        // 7.2 检查是否成功（兼容中国邮政的多错误码：1-8）
        if ($responseData[$errnoKey] != $successCode) {
            // 获取错误信息（兼容不同接口的error/message字段）
            $errorMsg = '';
            if (array_key_exists($errorKey, $responseData)) {
                $errorMsg = $responseData[$errorKey];
            } elseif ($responseData[$errnoKey] > 0) {
                // 中国邮政错误码映射
                $psbctcErrorMap = [
                    1 => '未检测到文件上传',
                    2 => '文件无效，请重新上传',
                    3 => '文件大小不能超过2MB',
                    4 => '文件上传失败',
                    5 => '未找到图片链接',
                    6 => 'cURL错误',
                    7 => '服务器异常',
                    8 => '请上传一个文件'
                ];
                $errorMsg = isset($psbctcErrorMap[$responseData[$errnoKey]]) ? $psbctcErrorMap[$responseData[$errnoKey]] : "未知错误（错误码：{$responseData[$errnoKey]}）";
            } else {
                $errorMsg = "未知错误（错误码：{$responseData[$errnoKey]}）";
            }
            throw new Exception("API返回失败（{$interfaceKey}）：{$errorMsg}（错误码：{$responseData[$errnoKey]}）");
        }
        
        // 7.3 验证data字段（成功时非null，失败时为null）
        if (!array_key_exists($dataKey, $responseData)) {
            throw new Exception("API返回数据不完整（{$interfaceKey}）：缺少data字段");
        }
        
        if ($responseData[$dataKey] === null) {
            throw new Exception("API返回失败（{$interfaceKey}）：data字段为null（无有效数据）");
        }
        
        // 7.4 验证图片链接存在且有效
        if (!array_key_exists($urlKey, $responseData[$dataKey]) || empty(trim($responseData[$dataKey][$urlKey]))) {
            throw new Exception("API返回数据不完整（{$interfaceKey}）：data字段中未找到有效的【{$urlKey}】字段，data内容：" . json_encode($responseData[$dataKey], JSON_UNESCAPED_UNICODE));
        }
        
        $imgUrl = trim($responseData[$dataKey][$urlKey]);
        // 严格验证URL有效性
        if (!filter_var($imgUrl, FILTER_VALIDATE_URL)) {
            throw new Exception("API返回无效的图片链接（{$interfaceKey}）：{$imgUrl}（不是合法的URL格式）");
        }
        
        // 7.5 获取文件名（兼容不同接口的fileName/imgFile）
        $filename = '未知文件';
        if (array_key_exists($fileNameKey, $responseData[$dataKey]) && !empty(trim($responseData[$dataKey][$fileNameKey]))) {
            $filename = trim($responseData[$dataKey][$fileNameKey]);
        } elseif (!empty($file['name'])) {
            $filename = pathinfo($file['name'], PATHINFO_FILENAME);
        }
        
        // 8. 修复HTML复制格式：特殊字符转义（核心！）
        $escapedFilename = escapeHtmlAttr($filename);
        $escapedImgUrl = escapeHtmlAttr($imgUrl);
        
        // 生成各格式链接（HTML格式修复）
        $formats = [
            '纯链接' => $imgUrl,
            'Markdown' => '![' . $filename . '](' . $imgUrl . ')',
            'BBCode' => '[img]' . $imgUrl . '[/img]',
            'Markdown简洁版' => '![](' . $imgUrl . ')'
        ];
        
        $apiResponse = [
            'code' => 0,
            'msg' => 'Success',
            'data' => [
                'url' => $imgUrl,
                'filename' => $filename,
                'formats' => $formats,
                'interface' => $interfaceKey
            ]
        ];
        writeLog($interface['name'], $file['name'], $file['size'], true, '上传成功', $imgUrl);
    } catch (Exception $e) {
        $apiResponse = [
            'code' => 1,
            'msg' => $e->getMessage(),
            'data' => []
        ];
        $fileName = $_FILES['file']['name'] ?? '未知文件';
        $fileSize = $_FILES['file']['size'] ?? 0;
        $interfaceName = isset($interfaces[$_POST['interface']]) ? $interfaces[$_POST['interface']]['name'] : '未知接口';
        writeLog($interfaceName, $fileName, $fileSize, false, $e->getMessage());
    }
    
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode($apiResponse, JSON_UNESCAPED_UNICODE));
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>图床</title>
    <style>
        @font-face {
            font-family: 'AlimamaAgileVF';
            src: url('../font/AlimamaAgileVF-Thin.woff2') format('woff2'),
                 url('../font/AlimamaAgileVF-Thin.woff') format('woff'),
                 url('../font/AlimamaAgileVF-Thin.ttf') format('truetype');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'AlimamaAgileVF', -apple-system, BlinkMacSystemFont, "SF Pro Text", "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif;
            font-weight: 500;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            -webkit-user-select: none;
            user-select: none;
        }

        html, body {
            width: 100%;
            min-height: 100vh;
            overflow-x: hidden;
            overflow-y: auto;
            -ms-overflow-style: none;
            scrollbar-width: none;
            background: linear-gradient(180deg, #F5F5F7 0%, #EFEFF2 100%);
            transform: translateZ(0);
            -webkit-transform: translateZ(0);
            padding: 0 !important;
            margin: 0 !important;
            scroll-behavior: smooth;
            line-height: 1.4;
        }

        ::-webkit-scrollbar {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
        }

        body {
            color: #333333;
            display: flex;
            flex-direction: column;
            padding-bottom: 10px !important;
        }

        .header {
            padding: 10px 16px !important;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.03);
            display: flex;
            align-items: center;
            gap: 8px !important;
            position: sticky;
            top: 0;
            z-index: 100;
            width: 100%;
            border-radius: 0 0 12px 12px;
            overflow: hidden;
        }

        .header-icon {
            width: 20px !important;
            height: 20px !important;
            object-fit: contain;
        }

        .header-title {
            font-size: 16px !important;
            font-weight: 600;
            color: #1D1D1F;
        }

        .main {
            flex: 1;
            padding: 10px 16px !important;
            width: 100%;
            margin: 0 !important;
        }

        .interface-selector {
            margin-bottom: 10px !important;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 10px 12px !important;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.02);
            border: 1px solid rgba(0, 0, 0, 0.03);
        }

        .selector-label {
            font-size: 13px !important;
            color: #666666;
            margin-bottom: 6px !important;
            display: block;
        }

        .interface-buttons {
            display: flex;
            gap: 6px !important;
            flex-wrap: wrap;
        }

        .interface-btn {
            padding: 6px 12px !important;
            border-radius: 8px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            background: #FFFFFF;
            color: #333333;
            font-size: 13px !important;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            flex: 1;
            min-width: 80px !important;
        }

        .interface-btn.active {
            color: inherit;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .interface-tips {
            margin-top: 6px !important;
            font-size: 12px !important;
            color: #666666;
            padding: 6px 8px !important;
            border-radius: 8px;
            background: #F8F8F8;
            line-height: 1.4;
            border: 1px solid rgba(0, 0, 0, 0.03);
        }

        .upload-area {
            background: linear-gradient(135deg, rgba(0, 92, 200, 0.05) 0%, rgba(0, 122, 255, 0.1) 100%);
            border: 2px dashed #005CC8;
            border-radius: 12px;
            padding: 20px 16px !important;
            text-align: center;
            margin-bottom: 10px !important;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.02);
        }

        .upload-area:hover {
            background: linear-gradient(135deg, rgba(0, 92, 200, 0.1) 0%, rgba(0, 122, 255, 0.15) 100%) !important;
            border-color: #007AFF !important;
            border-style: solid !important;
        }

        .upload-area.dragover {
            background: linear-gradient(135deg, rgba(0, 92, 200, 0.15) 0%, rgba(0, 122, 255, 0.2) 100%) !important;
            border-color: #007AFF !important;
            border-style: solid !important;
        }

        .upload-area.forbidden {
            background: linear-gradient(135deg, rgba(255, 59, 48, 0.05) 0%, rgba(255, 59, 48, 0.15) 100%) !important;
            border-color: #FF3B30 !important;
            border-style: solid !important;
        }

        .upload-icon-container {
            width: 40px !important;
            height: 40px !important;
            margin: 0 auto 8px !important;
            position: relative;
        }

        .upload-text {
            font-size: 15px !important;
            color: #1D1D1F;
            margin-bottom: 4px !important;
            font-weight: 500;
        }

        .upload-subtext {
            font-size: 12px !important;
            color: #666666;
            max-width: 100%;
            margin: 0 auto;
            line-height: 1.4;
        }

        .upload-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .upload-btn {
            width: 100%;
            padding: 8px 0 !important;
            border-radius: 10px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            font-size: 14px !important;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #333333;
            background: #FFFFFF;
            margin-bottom: 10px !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
        }

        .upload-btn:hover {
            background: #F8F8F8;
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        .upload-btn:disabled {
            background: #F5F5F5;
            color: #999999;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 10px 0 !important;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 12px;
            margin-bottom: 10px !important;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.02);
            border: 1px solid rgba(0, 0, 0, 0.03);
        }

        .lottie-container {
            width: 40px !important;
            height: 40px !important;
            margin: 0 auto 6px !important;
        }

        .loading-text {
            font-size: 13px !important;
            color: #666666;
            font-weight: 500;
        }

        .result-area {
            display: none;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 12px 16px !important;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.02);
            border: 1px solid rgba(0, 0, 0, 0.03);
            margin-bottom: 10px !important;
        }

        .result-msg {
            padding: 8px 12px !important;
            border-radius: 8px;
            margin-bottom: 8px !important;
            font-size: 13px !important;
            font-weight: 500;
            line-height: 1.4;
            background: #F8F8F8;
            border: 1px solid rgba(0, 0, 0, 0.03);
        }

        .result-success {
            color: #2DA848;
            border-left: 3px solid #34C759;
        }

        .result-error {
            color: #D73A3A;
            border-left: 3px solid #FF3B30;
        }

        .format-list {
            display: flex;
            flex-direction: column;
            gap: 6px !important;
        }

        .format-item {
            background: #F8F8F8;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 8px;
            padding: 8px 10px !important;
            transition: all 0.2s ease;
            border: 1px solid rgba(0, 0, 0, 0.03);
        }

        .format-label {
            font-size: 12px !important;
            color: #666666;
            margin-bottom: 3px !important;
            display: block;
            font-weight: 500;
        }

        .format-row {
            display: flex;
            gap: 4px !important;
            align-items: center;
            flex-wrap: nowrap;
        }

        .format-input {
            flex: 1;
            padding: 5px 8px !important;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 6px;
            font-size: 12px !important;
            color: #333333;
            background: #FFFFFF;
            outline: none;
            font-family: 'SF Mono', 'Monaco', monospace, 'AlimamaAgileVF';
            -webkit-user-select: text;
            user-select: text;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-width: 0;
        }

        .format-input:focus {
            border-color: #007AFF;
            box-shadow: 0 0 0 2px rgba(0, 122, 255, 0.05);
        }

        .copy-btn {
            padding: 5px 8px !important;
            border-radius: 6px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            background: #FFFFFF;
            color: #333333;
            font-size: 11px !important;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            min-width: 50px !important;
            flex-shrink: 0;
        }

        .copy-btn.copied {
            background: #E6FFEF;
            color: #2DA848;
            border-color: rgba(52, 199, 89, 0.2);
        }

        .copy-toast {
            position: fixed;
            bottom: 15px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            padding: 6px 12px !important;
            background: rgba(255, 255, 255, 0.95);
            color: #333333;
            border-radius: 8px;
            font-size: 12px !important;
            opacity: 0;
            transition: all 0.2s ease;
            z-index: 9999;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .copy-toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        @media (max-width: 768px) {
            .main {
                padding: 8px 12px !important;
            }

            .header {
                padding: 8px 12px !important;
            }

            .upload-area {
                padding: 16px 12px !important;
            }

            .interface-btn {
                min-width: 100% !important;
                margin-bottom: 4px !important;
            }
        }

        @media (max-width: 480px) {
            .upload-text {
                font-size: 14px !important;
            }

            .upload-subtext {
                font-size: 11px !important;
            }

            .result-area {
                padding: 10px 12px !important;
            }

            .format-input {
                padding: 4px 6px !important;
                font-size: 11px !important;
            }
        }
    </style>
</head>
<body>
    <header class="header" id="header">
        <img src="../svg/上传.svg" alt="上传图标" class="header-icon">
        <h1 class="header-title">云端图片储存--图床</h1>
    </header>

    <main class="main">
        <div class="interface-selector">
            <label class="selector-label">上传路线</label>
            <div class="interface-buttons" id="interfaceButtons">
                <button class="interface-btn" data-key="360tc" 
                        data-color="#F9E6FF" data-text-color="#9C47BC" 
                        data-light="rgba(249, 230, 255, 0.8)"
                        data-gradient="linear-gradient(135deg, rgba(156, 71, 188, 0.05) 0%, rgba(175, 82, 222, 0.1) 100%)">360</button>
                <button class="interface-btn active" data-key="sogotc" 
                        data-color="#E6F0FF" data-text-color="#005CC8" 
                        data-light="rgba(230, 240, 255, 0.8)" 
                        data-gradient="linear-gradient(135deg, rgba(0, 92, 200, 0.05) 0%, rgba(0, 122, 255, 0.1) 100%)">搜狗</button>
                <button class="interface-btn" data-key="psbctc" 
                        data-color="#E6FFEF" data-text-color="#2DA848" 
                        data-light="rgba(230, 255, 239, 0.8)"
                        data-gradient="linear-gradient(135deg, rgba(45, 168, 72, 0.05) 0%, rgba(52, 199, 89, 0.1) 100%)">中国邮政</button>
                <button class="interface-btn" data-key="yanxuantc" 
                        data-color="#FFF3E6" data-text-color="#E07B00" 
                        data-light="rgba(255, 243, 230, 0.8)"
                        data-gradient="linear-gradient(135deg, rgba(224, 123, 0, 0.05) 0%, rgba(255, 149, 0, 0.1) 100%)">网易严选</button>
            </div>
            <div class="interface-tips" id="interfaceTips">来自搜狗，一天后删除图片 （不适合长期存储）</div>
        </div>

        <div class="upload-area" id="uploadArea">
            <div class="upload-icon-container">
                <img src="../svg/上传.svg" alt="上传图标" id="uploadIcon" style="width: 100%; height: 100%; object-fit: contain;">
            </div>
            <h3 class="upload-text">拖放图片到此处或点击上传</h3>
            <p class="upload-subtext">支持 JPG、PNG、GIF、WEBP | 单个文件最大2MB</p>
            <input type="file" class="upload-input" id="fileInput" accept="image/jpeg,image/png,image/gif,image/webp">
        </div>

        <button class="upload-btn" id="uploadBtn" disabled>选择文件后上传</button>

        <div class="loading" id="loading">
            <div class="lottie-container" id="lottieContainer"></div>
            <div class="loading-text">正在上传，请稍候...</div>
        </div>

        <div class="result-area" id="resultArea">
            <div class="result-msg" id="resultMsg"></div>
            <div class="format-list" id="formatList"></div>
        </div>
    </main>

    <div class="copy-toast" id="copyToast">Copy Success </div>

    <script src="../main/js/lottie.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const interfaceConfig = <?php echo json_encode($interfaces, JSON_UNESCAPED_UNICODE); ?>;
            let selectedInterface = 'sogotc';
            let selectedFile = null;
            let lottieAnimation = null;

            const dom = {
                header: document.getElementById('header'),
                interfaceButtons: document.getElementById('interfaceButtons'),
                interfaceTips: document.getElementById('interfaceTips'),
                uploadArea: document.getElementById('uploadArea'),
                uploadIcon: document.getElementById('uploadIcon'),
                fileInput: document.getElementById('fileInput'),
                uploadBtn: document.getElementById('uploadBtn'),
                loading: document.getElementById('loading'),
                lottieContainer: document.getElementById('lottieContainer'),
                resultArea: document.getElementById('resultArea'),
                resultMsg: document.getElementById('resultMsg'),
                formatList: document.getElementById('formatList'),
                copyToast: document.getElementById('copyToast')
            };

            // 初始化Lottie
            function initLottieAnimation() {
                if (typeof lottie !== 'undefined') {
                    lottieAnimation = lottie.loadAnimation({
                        container: dom.lottieContainer,
                        renderer: 'svg',
                        loop: true,
                        autoplay: false,
                        path: '../lottie/下载加载.json',
                        rendererSettings: {
                            preserveAspectRatio: 'xMidYMid meet',
                            clearCanvas: false,
                            progressiveLoad: true
                        }
                    });
                    lottieAnimation.setSpeed(0.8);
                } else {
                    console.error('Lottie库未加载成功，请检查文件');
                    dom.lottieContainer.innerHTML = '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;"><div style="width:20px;height:20px;border:2px solid rgba(0,0,0,0.08);border-top:2px solid #666;border-radius:50%;animation:spin 1s linear infinite;"></div></div>';
                    const styleSheet = document.createElement('style');
                    styleSheet.textContent = '@keyframes spin {0% {transform: rotate(0deg);}100% {transform: rotate(360deg);}}';
                    document.head.appendChild(styleSheet);
                }
            }

            // 接口切换
            dom.interfaceButtons.addEventListener('click', (e) => {
                if (e.target.classList.contains('interface-btn')) {
                    document.querySelectorAll('.interface-btn').forEach(btn => {
                        btn.classList.remove('active');
                        btn.style.backgroundColor = '';
                        btn.style.color = '';
                    });
                    
                    e.target.classList.add('active');
                    selectedInterface = e.target.dataset.key;
                    const bgColor = e.target.dataset.color;
                    const textColor = e.target.dataset.textColor;
                    const lightColor = e.target.dataset.light;
                    const gradient = e.target.dataset.gradient;
                    
                    e.target.style.backgroundColor = bgColor;
                    e.target.style.color = textColor;
                    dom.interfaceTips.textContent = interfaceConfig[selectedInterface].tips;
                    dom.interfaceTips.style.backgroundColor = lightColor;
                    dom.uploadBtn.style.backgroundColor = bgColor;
                    dom.uploadBtn.style.color = textColor;
                    dom.header.style.backgroundColor = lightColor;
                    
                    dom.uploadArea.style.background = gradient;
                    dom.uploadArea.style.borderColor = textColor;
                    dom.uploadArea.style.borderStyle = 'dashed';
                }
            });

            // 拖拽上传
            const dragEvents = ['dragenter', 'dragover', 'dragleave', 'drop'];
            dragEvents.forEach(eventName => {
                dom.uploadArea.addEventListener(eventName, preventDefaults, false);
                document.body.addEventListener(eventName, preventDefaults, false);
            });

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            dom.uploadArea.addEventListener('dragenter', () => {
                dom.uploadArea.classList.add('dragover');
            }, false);

            dom.uploadArea.addEventListener('dragleave', resetDragState, false);
            dom.uploadArea.addEventListener('drop', handleDrop, false);

            function resetDragState() {
                dom.uploadArea.classList.remove('dragover');
                dom.uploadArea.classList.remove('forbidden');
                dom.uploadIcon.src = '../svg/上传.svg';
                const activeBtn = document.querySelector('.interface-btn.active');
                dom.uploadArea.style.background = activeBtn.dataset.gradient;
                dom.uploadArea.style.borderColor = activeBtn.dataset.textColor;
                dom.uploadArea.style.borderStyle = 'dashed';
            }

            function handleDrop(e) {
                resetDragState();
                const files = e.dataTransfer.files;

                if (files.length === 1 && files[0].type.startsWith('image/')) {
                    handleFileSelect(files[0]);
                } else {
                    dom.uploadArea.classList.add('forbidden');
                    dom.uploadIcon.src = '../svg/禁止.svg';
                    setTimeout(resetDragState, 2000);
                }
            }

            // 文件选择
            dom.fileInput.addEventListener('change', (e) => {
                if (e.target.files.length === 1) {
                    handleFileSelect(e.target.files[0]);
                }
            });

            function handleFileSelect(file) {
                selectedFile = file;
                const fileSize = formatFileSize(file.size);
                
                dom.uploadBtn.disabled = false;
                dom.uploadBtn.textContent = `上传 ${file.name}（${fileSize}）`;
                dom.uploadArea.querySelector('.upload-text').textContent = `已选择：${file.name}`;
                dom.uploadArea.querySelector('.upload-subtext').textContent = `大小：${fileSize} | 格式：${file.type.split('/')[1].toUpperCase()} ${selectedInterface === 'psbctc' && file.size > 2*1024*1024 ? '超过2MB限制' : ''}`;
                
                const activeBtn = document.querySelector('.interface-btn.active');
                dom.uploadArea.style.borderColor = activeBtn.dataset.textColor;
                dom.uploadArea.style.borderStyle = 'solid';
            }

            function formatFileSize(bytes) {
                if (bytes < 1024) return bytes + ' B';
                if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB';
                return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
            }

            // 复制功能（优化：复制时全选内容）
            function copyToClipboard(text) {
                return new Promise((resolve, reject) => {
                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(text).then(resolve).catch(() => fallbackCopy(text, resolve, reject));
                    } else {
                        fallbackCopy(text, resolve, reject);
                    }
                });
            }

            function fallbackCopy(text, resolve, reject) {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0;';
                document.body.appendChild(textarea);
                
                try {
                    textarea.select();
                    textarea.setSelectionRange(0, textarea.value.length); // 全选
                    document.execCommand('copy');
                    resolve();
                } catch (err) {
                    reject(err);
                } finally {
                    document.body.removeChild(textarea);
                }
            }

            function showCopyToast() {
                dom.copyToast.classList.add('show');
                setTimeout(() => {
                    dom.copyToast.classList.remove('show');
                }, 1500);
            }

            // 上传逻辑 + 自动滚动
            dom.uploadBtn.addEventListener('click', async () => {
                if (!selectedFile) return;

                if (selectedInterface === 'psbctc' && selectedFile.size > 2*1024*1024) {
                    alert('图床限制文件大小不超过2MB，请选择更小的文件！');
                    return;
                }

                dom.uploadBtn.disabled = true;
                dom.loading.style.display = 'block';
                dom.resultArea.style.display = 'none';
                if (lottieAnimation) lottieAnimation.play();

                try {
                    const formData = new FormData();
                    formData.append('file', selectedFile);
                    formData.append('interface', selectedInterface);

                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData,
                        cache: 'no-cache',
                        signal: AbortSignal.timeout(30000) // 延长请求超时
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP请求失败：${response.status} ${response.statusText}`);
                    }

                    const responseText = await response.text();
                    let result;
                    try {
                        result = JSON.parse(responseText);
                    } catch (parseError) {
                        throw new Error(`数据解析失败：${parseError.message}（响应内容：${responseText.substring(0, 100)}...）`);
                    }

                    if (lottieAnimation) lottieAnimation.stop();
                    dom.loading.style.display = 'none';
                    dom.resultArea.style.display = 'block';

                    if (result.code === 0) {
                        dom.resultMsg.className = 'result-msg result-success';
                        dom.resultMsg.textContent = result.msg;
                        dom.formatList.innerHTML = '';
                        
                        const fragment = document.createDocumentFragment();
                        Object.entries(result.data.formats).forEach(([name, value]) => {
                            const formatItem = document.createElement('div');
                            formatItem.className = 'format-item';
                            formatItem.innerHTML = `
                                <label class="format-label">${name}</label>
                                <div class="format-row">
                                    <input type="text" class="format-input" readonly value="${value}">
                                    <button class="copy-btn">Copy</button>
                                </div>
                            `;
                            
                            // 优化：点击输入框时全选内容
                            const input = formatItem.querySelector('.format-input');
                            input.addEventListener('click', () => {
                                input.select();
                                input.setSelectionRange(0, input.value.length);
                            });
                            
                            // 复制按钮逻辑
                            const copyBtn = formatItem.querySelector('.copy-btn');
                            copyBtn.addEventListener('click', async () => {
                                try {
                                    await copyToClipboard(value);
                                    copyBtn.textContent = '已复制';
                                    copyBtn.classList.add('copied');
                                    showCopyToast();
                                    setTimeout(() => {
                                        copyBtn.textContent = '复制';
                                        copyBtn.classList.remove('copied');
                                    }, 1500);
                                } catch (err) {
                                    alert('复制失败：' + err.message);
                                }
                            });
                            
                            fragment.appendChild(formatItem);
                        });
                        dom.formatList.appendChild(fragment);

                        // 自动滚动到结果区域
                        dom.resultArea.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                    } else {
                        dom.resultMsg.className = 'result-msg result-error';
                        dom.resultMsg.textContent = result.msg;
                        dom.formatList.innerHTML = '';
                        dom.resultArea.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                    }
                } catch (error) {
                    console.error('上传错误：', error);
                    if (lottieAnimation) lottieAnimation.stop();
                    dom.loading.style.display = 'none';
                    dom.resultArea.style.display = 'block';
                    dom.resultMsg.className = 'result-msg result-error';
                    dom.resultMsg.textContent = `上传出错：${error.message}`;
                    dom.formatList.innerHTML = '';
                    dom.resultArea.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                } finally {
                    dom.uploadBtn.disabled = false;
                    dom.uploadBtn.textContent = '再次上传';
                }
            });

            // 初始化
            initLottieAnimation();
            const initialBtn = document.querySelector('.interface-btn.active');
            initialBtn.style.backgroundColor = initialBtn.dataset.color;
            initialBtn.style.color = initialBtn.dataset.textColor;
            dom.uploadBtn.style.backgroundColor = initialBtn.dataset.color;
            dom.uploadBtn.style.color = initialBtn.dataset.textColor;
            dom.interfaceTips.style.backgroundColor = initialBtn.dataset.light;
            dom.uploadArea.style.background = initialBtn.dataset.gradient;
            dom.uploadArea.style.borderColor = initialBtn.dataset.textColor;
            dom.uploadArea.style.borderStyle = 'dashed';
        });
    </script>
</body>
</html>
