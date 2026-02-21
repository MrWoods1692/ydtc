<?php
/**
 * 积分商城系统 V9.1 - 精简美化版
 * 核心：仅保留等级升级商品 + 积分兑换码兑换功能
 * 界面：现代化设计，全屏展示
 */

// ====================== 1. 加载.env配置文件 ======================
$envPath = __DIR__ . '/.env';
if (!file_exists($envPath)) {
    die("错误：未找到.env配置文件，请先创建.env文件");
}

$envConfig = parse_ini_file($envPath, true);
if ($envConfig === false) {
    die("错误：.env配置文件解析失败，请检查格式");
}

$requiredKeys = [
    'mysql' => ['host', 'user', 'pass', 'dbname'],
    'paths' => ['user_root', 'user_data_filename'],
    'security' => ['token_length', 'inline_style_hash']
];
foreach ($requiredKeys as $section => $keys) {
    if (!isset($envConfig[$section])) {
        die("错误：.env文件中缺少[{$section}]配置节");
    }
    foreach ($keys as $key) {
        if (!isset($envConfig[$section][$key]) || empty($envConfig[$section][$key])) {
            die("错误：.env文件中[{$section}]节缺少{$key}配置");
        }
    }
}

define('CONFIG', [
    'mysql' => [
        'host'        => $envConfig['mysql']['host'],
        'user'        => $envConfig['mysql']['user'],
        'pass'        => $envConfig['mysql']['pass'],
        'dbname'      => $envConfig['mysql']['dbname'],
        'charset'     => $envConfig['mysql']['charset'] ?? 'utf8mb4',
        'persistent'  => filter_var($envConfig['mysql']['persistent'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
        'timeout'     => (int)($envConfig['mysql']['timeout'] ?? 30)
    ],
    'paths' => [
        'user_root'          => $envConfig['paths']['user_root'],
        'user_data_filename' => $envConfig['paths']['user_data_filename'],
        'font_dir'           => $envConfig['paths']['font_dir'] ?? '../font/',
        'svg_dir'            => $envConfig['paths']['svg_dir'] ?? '../svg/',
        'sqlite_dir'         => $envConfig['paths']['sqlite_dir'] ?? './data/',
        'log_dir'            => $envConfig['paths']['log_dir'] ?? './logs/',
        'redeem_code_db'     => $envConfig['paths']['redeem_code_db'] ?? './data/redeem_codes.db'
    ],
    'business' => [
        'default_album' => $envConfig['business']['default_album'] ?? 'default',
        'max_level'     => (int)($envConfig['business']['max_level'] ?? 11),
        'level_up'      => [
            1 => (int)($envConfig['business']['level_up_1'] ?? 99),
            2 => (int)($envConfig['business']['level_up_2'] ?? 250),
            3 => (int)($envConfig['business']['level_up_3'] ?? 520),
            4 => (int)($envConfig['business']['level_up_4'] ?? 666),
            5 => (int)($envConfig['business']['level_up_5'] ?? 1024),
            6 => (int)($envConfig['business']['level_up_6'] ?? 1314),
            7 => (int)($envConfig['business']['level_up_7'] ?? 6666),
            8 => (int)($envConfig['business']['level_up_8'] ?? 9999),
            9 => (int)($envConfig['business']['level_up_9'] ?? 65536),
            10 => (int)($envConfig['business']['level_up_10'] ?? 114514)
        ],
        'redeem_points' => array_map('intval', explode(',', $envConfig['business']['redeem_points'] ?? '10,20,50,100')),
        'buy_points'    => array_map('intval', explode(',', $envConfig['business']['buy_points'] ?? '10,20,50,100,500,1000'))
    ],
    'security' => [
        'allow_origin'    => $envConfig['security']['allow_origin'] ?? '*',
        'csp_nonce_len'   => (int)($envConfig['security']['csp_nonce_len'] ?? 16),
        'file_lock_timeout' => (int)($envConfig['security']['file_lock_timeout'] ?? 5),
        'sqlite_cache_size' => (int)($envConfig['security']['sqlite_cache_size'] ?? 10000),
        'csp_script_hash' => $envConfig['security']['csp_script_hash'] ?? 'sha256-OXqjjmv8hsJ7lR+n3ceuDu6KBNh8WlE7+5+vLocG1f4=',
        'inline_style_hash' => $envConfig['security']['inline_style_hash'],
        'cookie_path'     => $envConfig['security']['cookie_path'] ?? '/',
        'cookie_domain'   => $envConfig['security']['cookie_domain'] ?? '',
        'token_length'    => (int)($envConfig['security']['token_length'] ?? 64)
    ]
]);

// ====================== 2. 自定义异常类 ======================
class DatabaseException extends Exception {}
class UserDataException extends Exception {}
class BusinessException extends Exception {}
class SecurityException extends Exception {}

// ====================== 3. 数据库连接管理器 ======================
class DBManager {
    private static array $connections = [];

    // MySQL连接（用户Token）
    public static function getMysql(): PDO {
        $key = 'mysql';
        if (!isset(self::$connections[$key]) || !(self::$connections[$key] instanceof PDO)) {
            try {
                $dsn = sprintf(
                    "mysql:host=%s;dbname=%s;charset=%s;connect_timeout=%d",
                    CONFIG['mysql']['host'],
                    CONFIG['mysql']['dbname'],
                    CONFIG['mysql']['charset'],
                    CONFIG['mysql']['timeout']
                );
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_PERSISTENT         => CONFIG['mysql']['persistent'],
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . CONFIG['mysql']['charset']
                ];
                self::$connections[$key] = new PDO($dsn, CONFIG['mysql']['user'], CONFIG['mysql']['pass'], $options);
            } catch (PDOException $e) {
                throw new DatabaseException('MySQL连接失败：' . $e->getMessage());
            }
        }
        return self::$connections[$key];
    }

    // 商城积分兑换码SQLite连接
    public static function getRedeemSqlite(): PDO {
        $key = 'sqlite_redeem';
        if (!isset(self::$connections[$key]) || !(self::$connections[$key] instanceof PDO)) {
            $dbPath = CONFIG['paths']['redeem_code_db'];
            $dbDir = dirname($dbPath);

            if (!is_dir($dbDir) && !mkdir($dbDir, 0755, true)) {
                throw new DatabaseException('无法创建兑换码SQLite目录：' . $dbDir);
            }

            try {
                self::$connections[$key] = new PDO("sqlite:{$dbPath}");
                $pdo = self::$connections[$key];
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

                // 积分兑换码表结构
                $pdo->exec("CREATE TABLE IF NOT EXISTS redeem_codes (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    code TEXT NOT NULL UNIQUE,
                    points INTEGER NOT NULL,
                    created_at INTEGER NOT NULL
                )");

            } catch (PDOException $e) {
                throw new DatabaseException('兑换码SQLite连接失败：' . $e->getMessage());
            }
        }
        return self::$connections[$key];
    }

    public static function closeAll(): void {
        foreach (self::$connections as $conn) {
            if ($conn instanceof PDO) {
                $conn = null;
            }
        }
        self::$connections = [];
    }
}

// ====================== 4. 核心工具函数 ======================
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken(): bool {
    return !empty($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
}

function generateCspNonce(): string {
    $nonce = bin2hex(random_bytes(CONFIG['security']['csp_nonce_len']));
    $_SESSION['csp_nonce'] = $nonce;
    return $nonce;
}

function getUserEmail(string $token): ?string {
    $cleanToken = trim($token);
    $logDir = rtrim(CONFIG['paths']['log_dir'], '/');
    if (!is_dir($logDir) && !mkdir($logDir, 0755, true)) {
        error_log('登录检测：无法创建日志目录');
        return null;
    }
    
    error_log("[登录检测] 传入Token：{$token} | 清理后：{$cleanToken} | 长度：" . strlen($cleanToken));
    
    if (strlen($cleanToken) !== CONFIG['security']['token_length']) {
        error_log("[登录检测] Token长度错误：实际" . strlen($cleanToken) . "位，要求" . CONFIG['security']['token_length'] . "位");
        return null;
    }

    try {
        $pdo = DBManager::getMysql();
        $stmt = $pdo->prepare("SELECT email FROM users WHERE token = ? LIMIT 1");
        $stmt->execute([$cleanToken]);
        $user = $stmt->fetch();
        
        if ($user) {
            error_log("[登录检测] Token匹配成功，用户邮箱：{$user['email']}");
            return filter_var($user['email'], FILTER_VALIDATE_EMAIL) ? $user['email'] : null;
        } else {
            error_log("[登录检测] Token未匹配到用户：{$cleanToken}");
            return null;
        }
    } catch (DatabaseException $e) {
        error_log("[登录检测] MySQL查询失败：" . $e->getMessage());
        return null;
    }
}

function getUserDataPath(string $email): string {
    $safeEmail = urlencode($email);
    $filePath = rtrim(CONFIG['paths']['user_root'], '/') . "/{$safeEmail}/" . CONFIG['paths']['user_data_filename'];
    error_log("[用户数据路径] 生成路径：{$filePath}");
    return $filePath;
}

function readUserData(string $filePath): array {
    error_log("[读取用户数据] 尝试读取文件：{$filePath}");
    
    if (!file_exists($filePath)) {
        error_log("[读取用户数据] 文件不存在：{$filePath}，将使用初始数据并尝试创建文件");
        $initData = [
            'username'      => '未知用户',
            'level'         => 1,
            'points'        => 0,
            'used_space_kb' => 0
        ];
        $dir = dirname($filePath);
        if (!is_dir($dir) && mkdir($dir, 0755, true)) {
            file_put_contents($filePath, json_encode($initData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            error_log("[读取用户数据] 已创建初始文件：{$filePath}");
        }
        return $initData;
    }

    $handle = fopen($filePath, 'r');
    if (!$handle) {
        error_log("[读取用户数据] 无法打开文件：{$filePath}");
        throw new UserDataException('无法打开用户数据文件');
    }

    if (!flock($handle, LOCK_SH | LOCK_NB)) {
        fclose($handle);
        error_log("[读取用户数据] 文件读取锁获取失败：{$filePath}");
        throw new UserDataException('文件读取锁获取失败');
    }

    $content = file_get_contents($filePath);
    flock($handle, LOCK_UN);
    fclose($handle);

    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("[读取用户数据] JSON解析失败：{$filePath}，错误：" . json_last_error_msg());
        throw new UserDataException('用户数据JSON解析失败：' . json_last_error_msg());
    }

    $userData = [
        'username'      => $data['username'] ?? '未知用户',
        'level'         => (int)($data['level'] ?? 1),
        'points'        => (int)($data['points'] ?? 0),
        'used_space_kb' => (int)($data['used_space_kb'] ?? 0)
    ];
    error_log("[读取用户数据] 成功读取：{$filePath}，数据：" . json_encode($userData));
    return $userData;
}

function updateUserData(string $filePath, array $newData): bool {
    $dir = dirname($filePath);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        error_log("[更新用户数据] 无法创建目录：{$dir}");
        return false;
    }

    $oldData = file_exists($filePath) ? readUserData($filePath) : [];
    $mergedData = array_merge($oldData, $newData);
    error_log("[更新用户数据] 合并后数据：" . json_encode($mergedData));

    $handle = fopen($filePath, 'w');
    if (!$handle) {
        error_log("[更新用户数据] 无法打开文件：{$filePath}");
        return false;
    }

    $timeout = time() + CONFIG['security']['file_lock_timeout'];
    while (!flock($handle, LOCK_EX) && time() < $timeout) {
        usleep(100000);
    }

    if (time() >= $timeout) {
        fclose($handle);
        error_log("[更新用户数据] 文件写入锁超时：{$filePath}");
        return false;
    }

    fwrite($handle, json_encode($mergedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($handle, LOCK_UN);
    fclose($handle);
    error_log("[更新用户数据] 成功写入：{$filePath}");

    return true;
}

function generateRandomCode(): string {
    return bin2hex(random_bytes(32));
}

function createRedeemCode(int $points): string {
    try {
        $pdo = DBManager::getRedeemSqlite();
        $code = generateRandomCode();
        $now = time();
        
        $stmt = $pdo->prepare("INSERT INTO redeem_codes (code, points, created_at) VALUES (?, ?, ?)");
        $stmt->execute([$code, $points, $now]);
        
        error_log("[生成兑换码] 成功：{$code} ({$points}积分)");
        return $code;
    } catch (DatabaseException $e) {
        error_log("[生成兑换码] 失败：" . $e->getMessage());
        throw new BusinessException('兑换码生成失败，请稍后重试');
    }
}

function validateAndUseRedeemCode(string $code): int {
    try {
        $pdo = DBManager::getRedeemSqlite();
        if (strlen($code) !== 64) {
            throw new BusinessException('兑换码格式错误（需64位）');
        }
        
        $stmt = $pdo->prepare("SELECT id, points FROM redeem_codes WHERE code = ? LIMIT 1");
        $stmt->execute([$code]);
        $codeData = $stmt->fetch();
        
        if (!$codeData) {
            throw new BusinessException('兑换码不存在或已失效');
        }
        
        $stmt = $pdo->prepare("DELETE FROM redeem_codes WHERE id = ?");
        $stmt->execute([$codeData['id']]);
        
        error_log("[使用兑换码] 成功：{$code} ({$codeData['points']}积分)，已删除");
        return (int)$codeData['points'];
    } catch (DatabaseException $e) {
        error_log("[验证兑换码] 失败：" . $e->getMessage());
        throw new BusinessException('兑换码验证失败，请稍后重试');
    }
}

function renderModal(string $title, string $content, string $type = 'info'): string {
    switch ($type) {
        case 'success':
            $typeClass = 'modal-success';
            $icon = '';
            break;
        case 'error':
            $typeClass = 'modal-error';
            $icon = '';
            break;
        case 'warning':
            $typeClass = 'modal-warning';
            $icon = '⚠';
            break;
        default:
            $typeClass = 'modal-info';
            $icon = 'ℹ';
    }
    
    $title = htmlspecialchars($title);
    $content = htmlspecialchars($content);
    
    return <<<HTML
    <div class="modal-overlay" id="customModal">
        <div class="modal-container {$typeClass}">
            <div class="modal-icon">{$icon}</div>
            <div class="modal-header">
                <h3>{$title}</h3>
                <button class="modal-close js-modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <p>{$content}</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary js-modal-close">确认</button>
            </div>
        </div>
    </div>
    HTML;
}

function getProducts(): array {
    return [
        [
            'id' => 5,
            'name' => '升级',
            'description' => '花费对应积分，升级等级一级',
            'price' => 0,
            'type' => 'level_up',
            'limit' => 0,
            'icon' => '升级.svg'
        ]
    ];
}

function renderProductCard(array $product, int $userPoints, string $userEmail): string {
    $id = htmlspecialchars((string)$product['id']);
    $name = htmlspecialchars($product['name']);
    $description = htmlspecialchars($product['description']);
    $icon = htmlspecialchars(CONFIG['paths']['svg_dir'] . $product['icon']);
    
    $price = $product['price'];
    if ($product['type'] === 'level_up' && $userPoints >= 0) {
        $userData = readUserData(getUserDataPath($userEmail));
        $currentLevel = (int)$userData['level'];
        $price = (int)(CONFIG['business']['level_up'][$currentLevel] ?? 0);
    }
    
    $limit = (int)$product['limit'];
    $remaining = -1;
    $disabled = false;
    $disabledClass = '';
    $limitText = '';
    
    $levelTip = '';
    if ($product['type'] === 'level_up') {
        $userData = readUserData(getUserDataPath($userEmail));
        $currentLevel = (int)$userData['level'];
        $maxLevel = CONFIG['business']['max_level'];
        
        if ($currentLevel >= $maxLevel) {
            $levelTip = "<div class='product-limit limit-exhausted'>已达最高等级</div>";
            $disabled = true;
            $disabledClass = 'btn-disabled';
        } else {
            $requiredPoints = (int)(CONFIG['business']['level_up'][$currentLevel] ?? 0);
            $levelTip = "<div class='product-limit'>升级需{$requiredPoints}积分</div>";
            $price = $requiredPoints;
            
            if ($userPoints < $price) {
                $disabled = true;
                $disabledClass = 'btn-disabled';
            }
        }
    }
    
    $btnDisabledAttr = $disabled ? 'disabled' : '';
    if ($disabled) {
        if ($userPoints < $price) {
            $btnText = '积分不足';
        } elseif ($product['type'] === 'level_up') {
            $btnText = '已达最高等级';
        } else {
            $btnText = '已达限购';
        }
    } else {
        $btnText = '立即升级';
    }
    
    return <<<HTML
    <div class="product-card">
        <div class="product-icon">
            <img src="{$icon}" alt="{$name}图标" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48Y2lyY2xlIGN4PSIzMCIgY3k9IjMwIiByPSIzMCIgZmlsbD0iIzQyOTllMSIvPjxwYXRoIGQ9Ik0yMCAyNUMxNS42IDI1IDEyIDI4LjYgMTIgMzNDMTIgMzcuNCAxNS42IDQxIDIwIDQxSDI1VjI1SDIwVjI1WiIgZmlsbD0iI0ZGRkZGRiIvPjxwYXRoIGQ9Ik0zNSAyNUMzNSAyMCA0MCAxNSA0NSAxNUg1MFYzMEg0NVYzMEg0MFY0MUg0NUM0OS40IDQxIDUzIDM3LjQgNTMgMzNDNTMgMjguNiA0OS40IDI1IDQ1IDI1SDM1VjI1WiIgZmlsbD0iI0ZGRkZGRiIvPjwvc3ZnPg==';">
        </div>
        <div class="product-header">
            <h3 class="product-name">{$name}</h3>
            <div class="product-price">{$price}积分</div>
        </div>
        <div class="product-desc">{$description}</div>
        {$limitText}
        {$levelTip}
        <form method="POST" class="product-form">
            <input type="hidden" name="csrf_token" value="{$_SESSION['csrf_token']}">
            <input type="hidden" name="product_id" value="{$product['id']}">
            <button type="submit" class="btn btn-product {$disabledClass}" {$btnDisabledAttr}>
                {$btnText}
            </button>
        </form>
    </div>
    HTML;
}

// ====================== 5. 初始化配置 ======================
if (session_status() === PHP_SESSION_NONE) {
    $sessionConfig = [
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'gc_maxlifetime' => 3600,
        'cookie_path' => CONFIG['security']['cookie_path'],
    ];
    if (!empty(CONFIG['security']['cookie_domain'])) {
        $sessionConfig['cookie_domain'] = CONFIG['security']['cookie_domain'];
    }
    session_start($sessionConfig);
}

$cspNonce = generateCspNonce();

$headers = [
    'Access-Control-Allow-Origin'      => CONFIG['security']['allow_origin'],
    'Access-Control-Allow-Methods'     => 'GET, POST, OPTIONS',
    'Access-Control-Allow-Headers'     => 'Content-Type, X-Requested-With',
    'Access-Control-Allow-Credentials' => 'true',
    'X-Frame-Options'                  => 'SAMEORIGIN',
    'Content-Security-Policy'          => sprintf(
        "default-src 'self'; " .
        "font-src 'self' %s data:; " .
        "img-src 'self' %s data:; " .
        "style-src 'self' 'nonce-%s' 'unsafe-hashes' %s %s; " .
        "script-src 'self' 'nonce-%s' 'strict-dynamic'; " .
        "style-src-attr 'unsafe-hashes' %s %s; " .
        "object-src 'none'; base-uri 'self';",
        CONFIG['paths']['font_dir'],
        CONFIG['paths']['svg_dir'],
        $cspNonce,
        CONFIG['security']['csp_script_hash'],
        CONFIG['security']['inline_style_hash'],
        $cspNonce,
        CONFIG['security']['csp_script_hash'],
        CONFIG['security']['inline_style_hash']
    ),
    'X-Content-Type-Options' => 'nosniff',
    'X-XSS-Protection'       => '1; mode=block',
    'Charset'                => 'UTF-8'
];
foreach ($headers as $key => $value) {
    header("{$key}: {$value}");
}

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
$logDir = rtrim(CONFIG['paths']['log_dir'], '/');
if (!is_dir($logDir) && !mkdir($logDir, 0755, true)) {
    die('无法创建日志目录');
}
ini_set('error_log', $logDir . '/error.log');

// ====================== 6. 核心业务逻辑 ======================
$userToken = $_COOKIE['user_token'] ?? '';
error_log("[主流程] Cookie中读取的user_token：{$userToken} | 是否为空：" . (empty($userToken) ? '是' : '否'));

$userEmail = $userToken ? getUserEmail($userToken) : null;
error_log("[主流程] Token验证结果：" . ($userEmail ? "成功（{$userEmail}）" : "失败"));

$userData = [];
$message = '';
$messageType = '';
$products = getProducts();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    DBManager::closeAll();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userEmail) {
    try {
        if (!validateCsrfToken()) {
            throw new SecurityException('CSRF验证失败，请刷新页面重试');
        }

        if (isset($_POST['redeem_code']) && !empty(trim($_POST['redeem_code']))) {
            $code = trim($_POST['redeem_code']);
            
            $points = validateAndUseRedeemCode($code);
            
            $userFilePath = getUserDataPath($userEmail);
            $userData = readUserData($userFilePath);
            $newPoints = (int)$userData['points'] + $points;
            $updateSuccess = updateUserData($userFilePath, ['points' => $newPoints]);
            
            if ($updateSuccess) {
                $message = "兑换码使用成功！获得{$points}积分，当前剩余{$newPoints}积分";
                $messageType = 'success';
                $userData = readUserData($userFilePath);
            } else {
                throw new BusinessException('积分更新失败，请稍后重试');
            }
        } 
        elseif (isset($_POST['product_id']) && is_numeric($_POST['product_id'])) {
            $productId = (int)$_POST['product_id'];
            $product = null;
            foreach ($products as $p) {
                if ($p['id'] === $productId) {
                    $product = $p;
                    break;
                }
            }

            if (!$product) {
                throw new BusinessException('商品不存在');
            }

            $userFilePath = getUserDataPath($userEmail);
            $userData = readUserData($userFilePath);
            $userPoints = (int)$userData['points'];
            $currentLevel = (int)$userData['level'];

            $price = (int)(CONFIG['business']['level_up'][$currentLevel] ?? 0);
            if ($price <= 0) {
                throw new BusinessException('当前等级无升级配置');
            }
            if ($currentLevel >= CONFIG['business']['max_level']) {
                throw new BusinessException('已达最高等级，无法升级');
            }

            if ($userPoints < $price) {
                throw new BusinessException("积分不足！需要{$price}积分，当前仅有{$userPoints}积分");
            }

            $newPoints = $userPoints - $price;
            $newLevel = $currentLevel + 1;
            $updateSuccess = updateUserData($userFilePath, [
                'level' => $newLevel,
                'points' => $newPoints
            ]);

            if ($updateSuccess) {
                $message = "升级成功！已从{$currentLevel}级升至{$newLevel}级，扣除{$price}积分，剩余{$newPoints}积分";
                $messageType = 'success';
                $userData = readUserData($userFilePath);
            } else {
                throw new BusinessException('操作失败，请稍后重试');
            }
        } else {
            throw new BusinessException('无效的请求参数');
        }
    } catch (BusinessException $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    } catch (UserDataException $e) {
        $message = $e->getMessage();
        $messageType = 'error';
        error_log('用户数据操作失败：' . $e->getMessage());
    } catch (DatabaseException $e) {
        $message = '系统异常，请稍后重试';
        $messageType = 'error';
        error_log('数据库操作失败：' . $e->getMessage());
    } catch (SecurityException $e) {
        $message = $e->getMessage();
        $messageType = 'error';
        error_log('安全验证失败：' . $e->getMessage());
    } catch (Exception $e) {
        $message = '系统错误，请联系管理员';
        $messageType = 'error';
        error_log('未知错误：' . $e->getMessage());
    }
}

if ($userEmail && empty($userData)) {
    try {
        $userFilePath = getUserDataPath($userEmail);
        $userData = readUserData($userFilePath);
    } catch (UserDataException $e) {
        $message = '读取用户数据失败：' . $e->getMessage();
        $messageType = 'error';
    }
}

DBManager::closeAll();
$csrfToken = generateCsrfToken();

// ====================== 7. 页面渲染 ======================
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>积分商城 | 等级升级</title>
    <style nonce="<?= $cspNonce ?>">
/* 基础重置与全局样式 */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif;
}

html, body {
    height: 100%;
    width: 100%;
    overflow: hidden;
    background: #f8fafc; /* 浅色主背景 */
    color: #1e293b; /* 主文字色 */
}

/* 全屏容器 */
.app-container {
    height: 100vh;
    width: 100vw;
    display: flex;
    flex-direction: column;
    padding: 20px;
    overflow-y: auto;
}

/* 头部样式 */
.app-header {
    text-align: center;
    margin-bottom: 30px;
    padding: 20px 0;
}

.app-header h1 {
    font-size: 2.5rem;
    font-weight: 600;
    margin-bottom: 10px;
    color: #0f172a; /* 深色标题 */
    text-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.app-header p {
    font-size: 1.1rem;
    color: #64748b; /* 次要文字色 */
}

/* 卡片通用样式 */
.card {
    background: #ffffff; /* 卡片白色背景 */
    border-radius: 20px;
    border: 1px solid #e2e8f0; /* 浅色边框 */
    box-shadow: 0 4px 12px rgba(0,0,0,0.05); /* 轻微阴影 */
    padding: 20px;
    margin-bottom: 20px;
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.08); /* 悬浮增强阴影 */
}

/* 用户信息卡片 */
.user-card {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.user-card h2 {
    font-size: 1.8rem;
    font-weight: 500;
    color: #0f172a;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 15px;
}

.user-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.user-stat-item {
    background: #f1f5f9; /* 浅灰背景 */
    border-radius: 15px;
    padding: 20px;
    text-align: center;
}

.user-stat-label {
    font-size: 1rem;
    color: #64748b; /* 次要文字 */
    margin-bottom: 10px;
}

.user-stat-value {
    font-size: 2.2rem;
    font-weight: 600;
    color: #0f172a; /* 主要数值色 */
}

/* 兑换码区域 */
.redeem-section {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.section-title {
    font-size: 1.5rem;
    font-weight: 500;
    color: #0f172a;
    padding-bottom: 10px;
    border-bottom: 1px solid #e2e8f0;
}

.redeem-form {
    width: 100%;
}

.redeem-input-group {
    display: flex;
    gap: 15px;
    width: 100%;
}

.redeem-input {
    flex: 1;
    padding: 18px 25px;
    border: 1px solid #e2e8f0;
    border-radius: 15px;
    background: #f8fafc;
    color: #1e293b;
    font-size: 1.1rem;
    outline: none;
    transition: all 0.3s ease;
}

.redeem-input::placeholder {
    color: #94a3b8; /* 占位符浅灰色 */
}

.redeem-input:focus {
    border-color: #38bdf8; /* 聚焦蓝色边框 */
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.1); /* 轻微聚焦阴影 */
}

/* 按钮样式 */
.btn {
    padding: 15px 30px;
    border: none;
    border-radius: 15px;
    font-size: 1.1rem;
    font-weight: 500;
    cursor: pointer;
    outline: none;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-primary {
    background: linear-gradient(135deg, #38bdf8 0%, #0ea5e9 100%); /* 浅蓝色渐变 */
    color: #ffffff;
    box-shadow: 0 4px 15px rgba(14, 165, 233, 0.2);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(14, 165, 233, 0.3);
}

.btn-primary:disabled {
    opacity: 0.7;
    transform: none;
    box-shadow: none;
    cursor: not-allowed;
}

.btn-product {
    width: 100%;
    padding: 18px;
    font-size: 1.1rem;
}

.btn-disabled {
    background: #e2e8f0 !important; /* 禁用按钮浅灰 */
    color: #94a3b8 !important; /* 禁用文字色 */
    cursor: not-allowed;
    transform: none !important;
    box-shadow: none !important;
}

/* 商品卡片 */
.product-grid {
    display: flex;
    justify-content: center;  /* 水平居中 */
    align-items: center;      /* 垂直居中（如果高度允许） */
    gap: 30px;
    width: 100%;
}

.product-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    padding: 40px;
    min-width: 100px; 
    display: flex;
    flex-direction: column;
    height: 100%;
    transition: all 0.3s ease;
}

.product-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
}

.product-icon {
    text-align: center;
    margin-bottom: 20px;
}

.product-icon img {
    width: 80px;
    height: 80px;
    object-fit: contain;
    filter: drop-shadow(0 4px 8px rgba(0,0,0,0.05));
}

.product-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f1f5f9;
}

.product-name {
    font-size: 1.4rem;
    font-weight: 500;
    color: #0f172a;
}

.product-price {
    background: #f1f5f9;
    color: #0f172a;
    padding: 8px 20px;
    border-radius: 50px;
    font-size: 1.1rem;
    font-weight: 500;
}

.product-desc {
    color: #64748b;
    font-size: 1.05rem;
    line-height: 1.6;
    margin-bottom: 25px;
    flex: 1;
}

.product-limit {
    font-size: 1rem;
    margin-bottom: 20px;
    padding: 12px 15px;
    border-radius: 10px;
    background: #f1f5f9;
    color: #475569;
}

.limit-exhausted {
    background: #fef2f2; /* 浅红背景 */
    color: #ef4444; /* 红色文字 */
}

/* 未登录提示 */
.login-prompt {
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 40px;
}

.login-prompt h3 {
    font-size: 2rem;
    margin-bottom: 20px;
    font-weight: 500;
    color: #0f172a;
}

.login-prompt p {
    font-size: 1.2rem;
    color: #64748b;
    max-width: 600px;
}

/* 弹窗样式 */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.1); /* 浅色遮罩 */
    backdrop-filter: blur(5px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.modal-container {
    background: #ffffff;
    border-radius: 20px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    overflow: hidden;
    color: #1e293b;
    animation: modalFadeIn 0.4s ease;
    position: relative;
    border: 1px solid #e2e8f0;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: translateY(-30px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-icon {
    position: absolute;
    top: 20px;
    right: 20px;
    font-size: 24px;
}

.modal-header {
    padding: 30px 30px 20px;
    border-bottom: 1px solid #f1f5f9;
}

.modal-header h3 {
    font-size: 1.6rem;
    font-weight: 600;
    margin-right: 40px;
    color: #0f172a;
}

.modal-close {
    position: absolute;
    top: 20px;
    right: 20px;
    background: transparent;
    border: none;
    font-size: 1.8rem;
    cursor: pointer;
    color: #94a3b8;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s ease;
}

.modal-close:hover {
    color: #0f172a;
    background: #f1f5f9;
}

.modal-body {
    padding: 25px 30px;
    font-size: 1.1rem;
    line-height: 1.8;
    color: #475569;
}

.modal-footer {
    padding: 20px 30px;
    border-top: 1px solid #f1f5f9;
    text-align: right;
}

/* 弹窗状态色 */
.modal-success .modal-header {
    border-bottom-color: #ecfdf5;
}
.modal-success .modal-header h3 {
    color: #10b981; /* 成功绿色 */
}

.modal-error .modal-header {
    border-bottom-color: #fee2e2;
}
.modal-error .modal-header h3 {
    color: #ef4444; /* 错误红色 */
}

.modal-warning .modal-header {
    border-bottom-color: #fffbeb;
}
.modal-warning .modal-header h3 {
    color: #f59e0b; /* 警告黄色 */
}

/* 响应式适配 */
@media (max-width: 768px) {
    .app-container {
        padding: 15px;
    }

    .app-header h1 {
        font-size: 2rem;
    }

    .user-stats {
        grid-template-columns: 1fr;
    }

    .redeem-input-group {
        flex-direction: column;
        gap: 15px;
    }

    .product-grid {
        grid-template-columns: 1fr;
    }

    .card {
        padding: 20px;
    }

    .product-card {
        padding: 20px;
    }

    .modal-container {
        width: 95%;
    }
}

@media (max-width: 480px) {
    .app-header h1 {
        font-size: 1.8rem;
    }

    .btn {
        padding: 12px 20px;
        font-size: 1rem;
    }

    .redeem-input {
        padding: 15px 20px;
        font-size: 1rem;
    }
}
    </style>
</head>
<body>
    <div class="app-container">

        <!-- 弹窗 -->
        <?php if ($message): ?>
            <?= renderModal(
                $messageType === 'success' ? '操作成功' : ($messageType === 'error' ? '操作失败' : '提示'),
                $message,
                $messageType
            ) ?>
        <?php endif; ?>

        <!-- 用户信息区域 -->
        <?php if ($userEmail): ?>
            <div class="card user-card">
                <h2>Welcome 【<?= htmlspecialchars($userData['username']) ?>】</h2>
                <div class="user-stats">
                    <div class="user-stat-item">
                        <div class="user-stat-label">当前等级</div>
                        <div class="user-stat-value"><?= (int)$userData['level'] ?>级</div>
                    </div>
                    <div class="user-stat-item">
                        <div class="user-stat-label">剩余积分</div>
                        <div class="user-stat-value"><?= (int)$userData['points'] ?></div>
                    </div>
                </div>
            </div>

            <!-- 积分兑换码兑换区域 -->
            <div class="card redeem-section">
                <h2 class="section-title">积分兑换码兑换</h2>
                <form method="POST" class="redeem-form">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <div class="redeem-input-group">
                        <input type="text" name="redeem_code" class="redeem-input" placeholder="请输入积分兑换码" maxlength="64" required>
                        <button type="submit" class="btn btn-primary redeem-btn">立即兑换</button>
                    </div>
                </form>
            </div>

            <!-- 等级升级区域 -->
            <div class="card">
                <h2 class="section-title">等级升级</h2>
                <div class="product-grid">
                    <?php foreach ($products as $product): ?>
                        <?= renderProductCard($product, (int)$userData['points'], $userEmail) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- 未登录提示 -->
            <div class="login-prompt">
                <h3>请先登录</h3>
                <p>未检测到有效登录状态，请登录后再使用积分商城功能</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- 弹窗控制脚本 -->
    <script nonce="<?= $cspNonce ?>">
        function closeModal() {
            const modal = document.getElementById('customModal');
            if (modal) {
                modal.remove();
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // 关闭弹窗按钮
            const closeButtons = document.querySelectorAll('.js-modal-close');
            closeButtons.forEach(button => {
                button.addEventListener('click', closeModal);
            });

            // 点击弹窗外部关闭
            const modalOverlay = document.getElementById('customModal');
            if (modalOverlay) {
                modalOverlay.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeModal();
                    }
                });
            }

            // 自动关闭弹窗
            const modal = document.getElementById('customModal');
            if (modal) {
                setTimeout(closeModal, 5000);
            }

            // 表单提交禁用按钮
            const productForms = document.querySelectorAll('.product-form');
            productForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const btn = this.querySelector('.btn-product');
                    if (btn && !btn.disabled) {
                        btn.disabled = true;
                        btn.textContent = '处理中...';
                    }
                });
            });

            // 兑换码表单提交禁用按钮
            const redeemForm = document.querySelector('.redeem-form');
            if (redeemForm) {
                redeemForm.addEventListener('submit', function(e) {
                    const btn = this.querySelector('.redeem-btn');
                    if (btn) {
                        btn.disabled = true;
                        btn.textContent = '兑换中...';
                    }
                });
            }
        });
    </script>
</body>
</html>