<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Shanghai');

function loadEnv(): array {
    $envPath = '../in/.env';
    if (!file_exists($envPath)) {
        die("error");
    }
    $env = [];
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with($line, '#')) continue;
        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim($value);
    }
    return $env;
}

function connectDB(array $env): PDO {
    try {
        $dsn = "mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_NAME']};charset=utf8mb4";
        $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die("error");
    }
}

function validateUser(PDO $pdo): ?string {
    if (!isset($_COOKIE['user_token'])) {
        die("error");
    }
    $token = $_COOKIE['user_token'];
    $stmt = $pdo->prepare("SELECT email FROM users WHERE token = :token LIMIT 1");
    $stmt->bindValue(':token', $token);
    $stmt->execute();
    $user = $stmt->fetch();
    return $user ? $user['email'] : null;
}

function downloadImage(string $imgUrl): ?string {
    $tempFile = tempnam(sys_get_temp_dir(), 'ai_draw_');
    $ch = curl_init($imgUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $imgContent = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || empty($imgContent)) {
        unlink($tempFile);
        return null;
    }
    
    file_put_contents($tempFile, $imgContent);
    return $tempFile;
}

function uploadToImgBed(string $tempFile): array {
    $apiUrl = 'https://img.scdn.io/api/v1.php';
    $mime = mime_content_type($tempFile);
    $filename = basename($tempFile) . '.' . explode('/', $mime)[1];
    
    $postData = [
        'image' => new CURLFile($tempFile, $mime, $filename),
        'outputFormat' => 'webp'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    unlink($tempFile);
    
    if ($httpCode !== 200) {
        return [false, "图床上传失败，状态码：{$httpCode}"];
    }
    
    $result = json_decode($response, true);
    if (!$result || !$result['success']) {
        $msg = $result['message'] ?? '上传失败';
        return [false, "图床返回错误：{$msg}"];
    }
    
    return [true, $result];
}

function readLsJson(string $drawDir): array {
    $lsPath = "{$drawDir}/ls.json";
    if (!file_exists($lsPath)) {
        $default = [];
        file_put_contents($lsPath, json_encode($default, JSON_PRETTY_PRINT));
        return $default;
    }
    $content = file_get_contents($lsPath);
    return json_decode($content, true) ?: [];
}

function readPJson(string $drawDir): array {
    $pPath = "{$drawDir}/p.json";
    if (!file_exists($pPath)) {
        $default = [
            "album_name" => "draw",
            "create_time" => time(),
            "last_upload_time" => 0,
            "image_count" => 0,
            "used_space_kb" => 0,
            "remark" => "AI绘画"
        ];
        file_put_contents($pPath, json_encode($default, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $default;
    }
    $content = file_get_contents($pPath);
    return json_decode($content, true) ?: [];
}

function updateImageFiles(string $drawDir, array $imgBedData, string $size, string $prompt): bool {
    $lsList = readLsJson($drawDir);
    $pInfo = readPJson($drawDir);
    
    $sizeKb = isset($imgBedData['data']['compressed_size']) 
        ? round($imgBedData['data']['compressed_size'] / 1024, 2) 
        : 0;
    
    $imgName = basename(parse_url($imgBedData['url'], PHP_URL_PATH));
    $newImg = [
        "url" => $imgBedData['url'],
        "size_kb" => $sizeKb,
        "upload_time" => time(),
        "tags" => [],
        "size" => $size,
        "name" => $imgName,
        "remark" => $prompt
    ];
    
    array_unshift($lsList, $newImg);
    file_put_contents("{$drawDir}/ls.json", json_encode($lsList, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    
    $pInfo['last_upload_time'] = time();
    $pInfo['image_count'] = count($lsList);
    $pInfo['used_space_kb'] = round($pInfo['used_space_kb'] + $sizeKb, 2);
    file_put_contents("{$drawDir}/p.json", json_encode($pInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    return true;
}

// ===================== 主逻辑 =====================
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['img_url'])) {
    die("error");
}

// 1. 验证用户
$env = loadEnv();
$pdo = connectDB($env);
$userEmail = validateUser($pdo);
if (!$userEmail) {
    die("error");
}
$emailEncoded = urlencode($userEmail);
$drawDir = "../users/{$emailEncoded}/draw";

// 2. 下载图片到临时文件
$imgUrl = $_POST['img_url'];
$tempFile = downloadImage($imgUrl);
if (!$tempFile) {
    die("error");
}

// 3. 上传到图床
[$uploadSuccess, $uploadData] = uploadToImgBed($tempFile);
if (!$uploadSuccess) {
    die("error");
}

// 4. 更新用户文件
$size = $_POST['size'] ?? '1024*1024';
$prompt = $_POST['prompt'] ?? '';
$updateSuccess = updateImageFiles($drawDir, $uploadData, $size, $prompt);

if ($updateSuccess) {
    echo "success";
} else {
    echo "error";
}