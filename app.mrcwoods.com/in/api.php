<?php
/**
 * PHP8.1 用户注册/登录接口 (多域名最终版)
*/

// 1. 强制开启输出缓冲，杜绝任何意外输出
ob_start();

// 2. 日志功能
function writeLoginLog($logData) {
    $logDir = __DIR__ . '/log/';
    if (!is_dir($logDir)) mkdir($logDir, 0755, true);
    $logFile = $logDir . 'login_' . date('Ymd') . '.log';
    
    $logContent = json_encode([
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'email' => $logData['email'] ?? 'unknown',
        'action' => $logData['action'] ?? 'unknown',
        'status' => $logData['status'] ?? 'unknown',
        'message' => $logData['message'] ?? '',
        'token' => $logData['token'] ?? '',
        'cookie_config' => $logData['cookie_config'] ?? '',
        'debug_data' => $logData['debug_data'] ?? ''  // 调试用
    ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    
    file_put_contents($logFile, $logContent, FILE_APPEND | LOCK_EX);
}

// 3. 加载.env配置
function loadEnv($path = '.env') {
    if (!file_exists($path)) {
        writeLoginLog(['status' => 'error', 'message' => '配置文件不存在']);
        exit(json_encode(['code' => 500, 'msg' => '配置文件不存在']));
    }
    $env = parse_ini_file($path);
    foreach ($env as $key => $value) {
        $_ENV[$key] = $value;
    }
}
loadEnv();

// 4. 跨域头配置【适配多域名】
// 4.1 解析多域名配置
$allowedOrigins = explode(',', $_ENV['ALLOWED_ORIGIN']); // 解析多个跨域源
$allowedCookieDomains = explode(',', $_ENV['COOKIE_DOMAIN']); // 解析多个Cookie域名
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigin = trim($allowedOrigins[0]); // 默认跨域源

// 4.2 动态匹配请求Origin（确保跨域头与请求源一致）
if (!empty($requestOrigin)) {
    foreach ($allowedOrigins as $origin) {
        $origin = trim($origin);
        if ($requestOrigin === $origin) {
            $allowedOrigin = $origin;
            break;
        }
    }
}

// 4.3 发送跨域头
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Origin: {$allowedOrigin}");

// 5. 处理OPTIONS预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    writeLoginLog([
        'action' => 'options', 
        'status' => 'success', 
        'message' => '预检请求通过',
        'debug_data' => [
            'request_origin' => $requestOrigin, 
            'allowed_origin' => $allowedOrigin,
            'allowed_origins' => $allowedOrigins
        ]
    ]);
    exit(json_encode(['code' => 200, 'msg' => 'ok']));
}

// 6. 通用过滤函数（不过滤验证码）
function filterInput($data, $isCode = false) {
    if (!is_string($data)) return $data;
    $data = trim($data);
    if ($isCode) return $data;
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// 7. 数据库连接
try {
    $dsn = "mysql:host={$_ENV['DB_HOST']};port={$_ENV['DB_PORT']};dbname={$_ENV['DB_NAME']};charset=utf8mb4";
    $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    writeLoginLog(['status' => 'error', 'message' => '数据库连接失败', 'error' => $e->getMessage()]);
    exit(json_encode(['code' => 500, 'msg' => '数据库连接失败']));
}

// 8. 生成唯一Token
function generateUniqueToken($pdo) {
    do {
        $token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("SELECT id FROM users WHERE token = ? LIMIT 1");
        $stmt->execute([$token]);
    } while ($stmt->rowCount() > 0);
    return $token;
}

// 9. 生成验证码
function generateVerifyCode() {
    $chars = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz123456789';
    $length = 8;
    $code = '';
    $charsLen = strlen($chars);
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, $charsLen - 1)];
    }
    return $code;
}

// 10. 发送邮箱验证码
function sendVerifyEmail($email, $code) {
    $apiUrl = $_ENV['EMAIL_API_URL'];
    $params = [
        'token' => $_ENV['EMAIL_API_TOKEN'] ?? '',
        'send' => $_ENV['EMAIL_SEND'],
        'pass' => $_ENV['EMAIL_PASS'],
        'receive' => $email,
        'mtie' => '[云图]',
        'title' => '验证码',
        'content' => "<!DOCTYPE html>
        <html>
        <head><meta charset='utf-8'></head>
        <body style='font-family: -apple-system, BlinkMacSystemFont, sans-serif;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                <h2 style='color: #1d1d1f;'>云图验证码</h2>
                <p style='color: #86868b; font-size: 14px;'>有效期5分钟，请勿泄露给他人：</p>
                <div style='font-size: 32px; font-weight: bold; color: #0071e3; margin: 20px 0; letter-spacing: 4px; background: #f5f5f7; padding: 15px; border-radius: 8px; text-align: center;'>{$code}</div>
                <p style='color: #86868b; font-size: 12px;'>如非本人操作，请忽略此邮件。</p>
            </div>
        </body>
        </html>"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $curlError = curl_errno($ch) ? curl_error($ch) : '';
    curl_close($ch);

    if ($curlError) {
        writeLoginLog(['email' => $email, 'action' => 'send_code', 'status' => 'error', 'message' => '邮箱发送失败', 'error' => $curlError]);
    }
    return $response !== false;
}

// 11. 处理前端请求
$request = json_decode(file_get_contents('php://input'), true) ?? [];
$action = filterInput($request['action'] ?? '');
$email = filterInput($request['email'] ?? '');
$inputCode = filterInput($request['verify_code'] ?? '', true);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    writeLoginLog(['email' => $email, 'action' => $action, 'status' => 'error', 'message' => '邮箱格式错误']);
    exit(json_encode(['code' => 400, 'msg' => '邮箱格式错误']));
}

try {
    // 查找/注册用户
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        $now = time();
        $insertStmt = $pdo->prepare("INSERT INTO users (email, created_at, updated_at) VALUES (?, ?, ?)");
        $insertStmt->execute([$email, $now, $now]);
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        writeLoginLog(['email' => $email, 'action' => 'register', 'status' => 'success', 'message' => '用户自动注册成功']);
    }

    // 发送验证码
    if ($action === 'send_code') {
        try {
            $lastSendTime = $user['verify_code_time'] ?? 0;
            if (time() - $lastSendTime < ($_ENV['VERIFY_CODE_INTERVAL'] ?? 60)) {
                writeLoginLog(['email' => $email, 'action' => 'send_code', 'status' => 'error', 'message' => '验证码发送过于频繁']);
                exit(json_encode(['code' => 400, 'msg' => '验证码发送过于频繁，请稍后再试']));
            }

            $verifyCode = generateVerifyCode();
            sendVerifyEmail($email, $verifyCode);

            $stmt = $pdo->prepare("UPDATE users SET verify_code = ?, verify_code_time = ?, verify_fail_count = 0, updated_at = ? WHERE id = ?");
            $stmt->execute([$verifyCode, time(), time(), $user['id']]);

            writeLoginLog([
                'email' => $email,
                'action' => 'send_code',
                'status' => 'success',
                'message' => '验证码发送成功',
                'debug_data' => ['sent_code' => $verifyCode]
            ]);
        } catch (Exception $e) {
            writeLoginLog([
                'email' => $email,
                'action' => 'send_code',
                'status' => 'error',
                'message' => '异常但已假定发送成功',
                'error' => $e->getMessage()
            ]);
        }
        exit(json_encode(['code' => 200, 'msg' => '验证码已发送至你的邮箱']));
    }

    // 登录验证
    elseif ($action === 'login') {
        $dbCode = $user['verify_code'] ?? '';
        $failCount = $user['verify_fail_count'] ?? 0;

        writeLoginLog([
            'email' => $email, 
            'action' => 'login_verify', 
            'status' => 'debug',
            'debug_data' => [
                'input_code' => $inputCode,
                'db_code' => $dbCode,
                'input_length' => strlen($inputCode),
                'db_length' => strlen($dbCode),
                'user_id' => $user['id'] ?? 'none'
            ]
        ]);

        if ($failCount >= ($_ENV['VERIFY_FAIL_LIMIT'] ?? 5)) {
            writeLoginLog(['email' => $email, 'action' => 'login', 'status' => 'error', 'message' => '验证码失败次数过多']);
            exit(json_encode(['code' => 400, 'msg' => '验证码失败次数过多，请重新发送']));
        }

        if (empty($dbCode)) {
            $stmt = $pdo->prepare("UPDATE users SET verify_fail_count = verify_fail_count + 1, updated_at = ? WHERE id = ?");
            $stmt->execute([time(), $user['id']]);
            $remaining = ($_ENV['VERIFY_FAIL_LIMIT'] ?? 5) - $failCount - 1;
            writeLoginLog([
                'email' => $email,
                'action' => 'login',
                'status' => 'error',
                'message' => "验证码错误，剩余次数：{$remaining}",
                'debug_data' => ['input' => $inputCode, 'db' => $dbCode]
            ]);
            exit(json_encode(['code' => 400, 'msg' => "验证码错误，剩余尝试次数：{$remaining}"]));
        }

        $inputCode = trim($inputCode);
        $dbCode = trim($dbCode);
        
        if ($inputCode !== $dbCode) {
            $stmt = $pdo->prepare("UPDATE users SET verify_fail_count = verify_fail_count + 1, updated_at = ? WHERE id = ?");
            $stmt->execute([time(), $user['id']]);
            $remaining = ($_ENV['VERIFY_FAIL_LIMIT'] ?? 5) - $failCount - 1;
            writeLoginLog([
                'email' => $email, 
                'action' => 'login', 
                'status' => 'error', 
                'message' => "验证码错误，剩余次数：{$remaining}",
                'debug_data' => ['input' => $inputCode, 'db' => $dbCode]
            ]);
            exit(json_encode(['code' => 400, 'msg' => "验证码错误，剩余尝试次数：{$remaining}"]));
        }

        if (time() - ($user['verify_code_time'] ?? 0) > 300) {
            writeLoginLog(['email' => $email, 'action' => 'login', 'status' => 'error', 'message' => '验证码已过期']);
            exit(json_encode(['code' => 400, 'msg' => '验证码已过期，请重新发送']));
        }

        // 生成Token并设置Cookie
        $newToken = generateUniqueToken($pdo);
        $stmt = $pdo->prepare("UPDATE users SET token = ?, verify_code = NULL, verify_fail_count = 0, updated_at = ? WHERE id = ?");
        $stmt->execute([$newToken, time(), $user['id']]);

        ob_clean();

        $cookieName = 'user_token';
        $cookieValue = $newToken;
        $cookieExpire = time() + 86400;
        $cookiePath = '/';

        // 动态匹配Cookie域名
        $requestHost = $_SERVER['HTTP_HOST'];
        if (strpos($requestHost, ':') !== false) {
            $requestHost = explode(':', $requestHost)[0];
        }
        $cookieDomain = trim($allowedCookieDomains[0]);
        foreach ($allowedCookieDomains as $domain) {
            $domain = trim($domain);
            if (strpos($requestHost, ltrim($domain, '.')) !== false) {
                $cookieDomain = $domain;
                break;
            }
        }

        // 【强制添加Secure】解决Cookie写入失败核心问题
        $cookieHeader = sprintf(
            "Set-Cookie: %s=%s; Expires=%s; Path=%s; Domain=%s; Secure; HttpOnly; SameSite=None",
            $cookieName,
            $cookieValue,
            gmdate('D, d-M-Y H:i:s T', $cookieExpire),
            $cookiePath,
            $cookieDomain
        );
        header($cookieHeader);

        writeLoginLog([
            'email' => $email,
            'action' => 'login',
            'status' => 'success',
            'message' => '登录成功',
            'token' => $newToken,
            'cookie_config' => [
                'request_host' => $requestHost,
                'domain' => $cookieDomain,
                'secure' => 'Secure;',
                'same_site' => 'None'
            ]
        ]);

        $response = [
            'code' => 200,
            'msg' => '登录成功',
            'data' => [
                'redirect' => '../main/',
                'token' => $newToken
            ]
        ];
        echo json_encode($response);
        ob_end_flush();
        exit;
    } else {
        writeLoginLog(['email' => $email, 'action' => $action, 'status' => 'error', 'message' => '无效的请求动作']);
        exit(json_encode(['code' => 400, 'msg' => '无效的请求动作']));
    }

} catch (Exception $e) {
    writeLoginLog(['email' => $email, 'action' => $action ?? 'unknown', 'status' => 'error', 'message' => '服务器异常', 'error' => $e->getMessage()]);
    exit(json_encode(['code' => 500, 'msg' => '服务器繁忙']));
}