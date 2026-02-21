<?php
/**
 * Google OAuth2 回调处理文件
 * PHP 8.1+
 */

// 生产环境建议关闭错误显示
error_reporting(E_ALL);
ini_set('display_errors', 0);

// 加载环境变量
$env = parse_ini_file('../../../.env');
if (!$env) {
    http_response_code(500);
    die('环境配置文件 .env 加载失败');
}

// 初始化session验证state
session_start();

// 1. 基础参数验证
if (empty($_GET['code']) || empty($_GET['state'])) {
    http_response_code(400);
    die('授权失败：缺少必要的授权参数');
}

// 验证state防止CSRF攻击
if ($_GET['state'] !== $_SESSION['google_oauth_state']) {
    http_response_code(403);
    die('授权失败：非法的请求状态');
}
unset($_SESSION['google_oauth_state']); // 用完即删

// 2. 加载Google配置
$googleConfigFile = __DIR__ . '/.json';
if (file_exists($googleConfigFile)) {
    $googleConfig = json_decode(file_get_contents($googleConfigFile), true)['web'];
} else {
    $googleConfig = [
        'client_id'     => '',
        'client_secret' => '',
        'token_uri'     => 'https://oauth2.googleapis.com/token',
        'redirect_uris' => ['https://app.mrcwoods.com/in/auth/google/callback'],
    ];
}

$config = [
    'client_id'     => $googleConfig['client_id'],
    'client_secret' => $googleConfig['client_secret'],
    'redirect_uri'  => $googleConfig['redirect_uris'][0],
    'token_endpoint'=> $googleConfig['token_uri'] ?? 'https://oauth2.googleapis.com/token',
    'userinfo_endpoint' => 'https://www.googleapis.com/oauth2/v3/userinfo', // Google用户信息接口
];

try {
    // 3. 获取Access Token
    $tokenResponse = getGoogleAccessToken($config, $_GET['code']);
    if (empty($tokenResponse['access_token'])) {
        throw new Exception('获取Access Token失败：' . ($tokenResponse['error'] ?? '未知错误'));
    }

    // 4. 获取用户信息（优先使用id_token解析，备用userinfo接口）
    $userInfo = [];
    if (!empty($tokenResponse['id_token'])) {
        // 解析JWT格式的id_token获取用户信息（无需额外请求）
        $userInfo = parseGoogleIdToken($tokenResponse['id_token']);
    }
    
    // 备用：调用userinfo接口获取
    if (empty($userInfo['email'])) {
        $userInfo = getGoogleUserInfo($config['userinfo_endpoint'], $tokenResponse['access_token']);
    }

    // 验证邮箱有效性
    if (empty($userInfo['email']) || !filter_var($userInfo['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('无法获取有效的用户邮箱信息');
    }
    
    // 验证邮箱是否已验证（Google要求）
    if (empty($userInfo['email_verified']) || $userInfo['email_verified'] !== true) {
        throw new Exception('该Google账号邮箱未验证，无法登录');
    }

    $userEmail = strtolower(trim($userInfo['email']));

    // 5. 数据库操作（复用已有逻辑）
    $pdo = connectDatabase($env);
    $userId = saveOrUpdateUser($pdo, $userEmail, $env);

    // 6. 生成并设置Token Cookie
    $userToken = getOrGenerateUserToken($pdo, $userId, $env);
    setUserTokenCookie($userToken, $env);

    // 7. 跳转到主页面
    header('Location: ../../../../main/');
    exit;

} catch (Exception $e) {
    http_response_code(500);
    die('Google登录失败：' . $e->getMessage());
}

/**
 * 获取Google Access Token
 * @param array $config 配置信息
 * @param string $code 授权码
 * @return array Token响应数据
 */
function getGoogleAccessToken(array $config, string $code): array
{
    $postData = http_build_query([
        'grant_type'    => 'authorization_code',
        'client_id'     => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'code'          => $code,
        'redirect_uri'  => $config['redirect_uri'],
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $config['token_endpoint'],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception("Token请求出错：{$error}");
    }
    
    if ($httpCode !== 200 || empty($response)) {
        throw new Exception("Token接口请求失败 (HTTP {$httpCode})");
    }

    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Token响应解析失败：' . json_last_error_msg());
    }

    return $result;
}

/**
 * 解析Google ID Token（JWT格式）
 * @param string $idToken JWT令牌
 * @return array 用户信息
 */
function parseGoogleIdToken(string $idToken): array
{
    $parts = explode('.', $idToken);
    if (count($parts) !== 3) {
        return [];
    }

    // JWT第二部分是用户信息（Base64URL编码）
    $payload = base64_decode(strtr($parts[1], '-_', '+/'));
    if (!$payload) {
        return [];
    }

    $userInfo = json_decode($payload, true);
    return json_last_error() === JSON_ERROR_NONE ? $userInfo : [];
}

/**
 * 调用Google UserInfo接口获取用户信息
 * @param string $userinfoEndpoint 用户信息接口地址
 * @param string $accessToken Access Token
 * @return array 用户信息
 */
function getGoogleUserInfo(string $userinfoEndpoint, string $accessToken): array
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $userinfoEndpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer {$accessToken}",
            'Accept: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || empty($response)) {
        throw new Exception("用户信息接口请求失败 (HTTP {$httpCode})");
    }

    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('用户信息响应解析失败');
    }

    return $result;
}

/**
 * 以下函数完全复用Linux DO登录的逻辑，无需修改
 */

// 连接数据库
function connectDatabase(array $env): PDO
{
    $dsn = "mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_NAME']};charset=utf8mb4";
    try {
        $pdo = new PDO(
            $dsn,
            $env['DB_USER'],
            $env['DB_PASS'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception('数据库连接失败：' . $e->getMessage());
    }
}

// 保存或更新用户信息
function saveOrUpdateUser(PDO $pdo, string $email, array $env): int
{
    $timestamp = time();
    
    // 检查用户是否已存在
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->bindValue(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch();

    if ($user) {
        // 更新用户最后登录时间
        $stmt = $pdo->prepare("UPDATE users SET updated_at = :updated_at WHERE id = :id");
        $stmt->bindValue(':updated_at', $timestamp);
        $stmt->bindValue(':id', $user['id'], PDO::PARAM_INT);
        $stmt->execute();
        return $user['id'];
    } else {
        // 创建新用户
        $stmt = $pdo->prepare("INSERT INTO users (email, created_at, updated_at) VALUES (:email, :created_at, :updated_at)");
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':created_at', $timestamp);
        $stmt->bindValue(':updated_at', $timestamp);
        $stmt->execute();
        return (int)$pdo->lastInsertId();
    }
}

// 获取或生成用户Token
function getOrGenerateUserToken(PDO $pdo, int $userId, array $env): string
{
    // 检查用户是否已有有效Token
    $stmt = $pdo->prepare("SELECT token FROM users WHERE id = :id LIMIT 1");
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch();

    if (!empty($user['token']) && strlen($user['token']) === (int)$env['TOKEN_LENGTH']) {
        return $user['token'];
    }

    // 生成64位唯一Token
    $tokenLength = (int)$env['TOKEN_LENGTH'];
    do {
        $token = bin2hex(random_bytes($tokenLength / 2));
        // 检查Token是否唯一
        $stmt = $pdo->prepare("SELECT id FROM users WHERE token = :token LIMIT 1");
        $stmt->bindValue(':token', $token);
        $stmt->execute();
    } while ($stmt->fetch());

    // 更新用户Token
    $stmt = $pdo->prepare("UPDATE users SET token = :token, updated_at = :updated_at WHERE id = :id");
    $stmt->bindValue(':token', $token);
    $stmt->bindValue(':updated_at', time());
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    return $token;
}

// 设置用户Token Cookie
function setUserTokenCookie(string $token, array $env): void
{
    $cookieDomains = explode(',', $env['COOKIE_DOMAIN']);
    $cookieDomain = $cookieDomains[0];
    
    $cookieParams = [
        'name'     => 'user_token',
        'value'    => $token,
        'expire'   => time() + 365 * 24 * 3600, // 有效期1年
        'path'     => '/',
        'domain'   => ltrim($cookieDomain, '.'),
        'secure'   => filter_var($env['COOKIE_SECURE'], FILTER_VALIDATE_BOOLEAN),
        'httponly' => true,
        'samesite' => 'Lax',
    ];

    setcookie(
        $cookieParams['name'],
        $cookieParams['value'],
        [
            'expires'  => $cookieParams['expire'],
            'path'     => $cookieParams['path'],
            'domain'   => $cookieParams['domain'],
            'secure'   => $cookieParams['secure'],
            'httponly' => $cookieParams['httponly'],
            'samesite' => $cookieParams['samesite'],
        ]
    );
}
?>