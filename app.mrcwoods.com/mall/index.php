<?php
/**
 * 积分商城系统 V9.1 - 卡密/兑换码分离版（恢复原商品列表）
 * 核心：仅保留外部卡密+等级升级商品，移除商城兑换码商品，双库分离逻辑不变
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
        // 双库分离路径
        'card_code_db'       => $envConfig['paths']['card_code_db'] ?? './data/card_codes.db',   // 外部卡密
        'redeem_code_db'     => $envConfig['paths']['redeem_code_db'] ?? './data/redeem_codes.db' // 商城积分兑换码
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

// ====================== 3. 数据库连接管理器（双库分离） ======================
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

    // 外部卡密SQLite连接（含所属用户）
    public static function getCardSqlite(): PDO {
        $key = 'sqlite_card';
        if (!isset(self::$connections[$key]) || !(self::$connections[$key] instanceof PDO)) {
            $dbPath = CONFIG['paths']['card_code_db'];
            $dbDir = dirname($dbPath);

            if (!is_dir($dbDir) && !mkdir($dbDir, 0755, true)) {
                throw new DatabaseException('无法创建卡密SQLite目录：' . $dbDir);
            }

            try {
                self::$connections[$key] = new PDO("sqlite:{$dbPath}");
                $pdo = self::$connections[$key];
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

                // 卡密表结构（含所属用户，标记已用）
                $pdo->exec("CREATE TABLE IF NOT EXISTS card_codes (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    code TEXT NOT NULL UNIQUE,
                    points INTEGER NOT NULL,
                    user_email TEXT NOT NULL,
                    created_at INTEGER NOT NULL,
                    used INTEGER DEFAULT 0,
                    used_at INTEGER DEFAULT 0
                )");

                // 购买限制表（复用原有）
                $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_limits (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_email TEXT NOT NULL,
                    product_id INTEGER NOT NULL,
                    purchase_date TEXT NOT NULL,
                    purchase_count INTEGER DEFAULT 1,
                    UNIQUE(user_email, product_id, purchase_date)
                )");

            } catch (PDOException $e) {
                throw new DatabaseException('卡密SQLite连接失败：' . $e->getMessage());
            }
        }
        return self::$connections[$key];
    }

    // 商城积分兑换码SQLite连接（无所属用户）
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

                // 积分兑换码表结构（仅兑换码+积分，无用户关联）
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
// 生成CSRF Token
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// 验证CSRF Token
function validateCsrfToken(): bool {
    return !empty($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
}

// 生成CSP Nonce
function generateCspNonce(): string {
    $nonce = bin2hex(random_bytes(CONFIG['security']['csp_nonce_len']));
    $_SESSION['csp_nonce'] = $nonce;
    return $nonce;
}

// 通过Token获取用户邮箱
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

// 获取用户数据文件路径
function getUserDataPath(string $email): string {
    $safeEmail = urlencode($email);
    $filePath = rtrim(CONFIG['paths']['user_root'], '/') . "/{$safeEmail}/" . CONFIG['paths']['user_data_filename'];
    error_log("[用户数据路径] 生成路径：{$filePath}");
    return $filePath;
}

// 读取用户数据
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

// 更新用户数据
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

// 生成64位随机码（通用）
function generateRandomCode(): string {
    return bin2hex(random_bytes(32)); // 32字节=64位16进制字符
}

// 检查用户今日购买次数
function checkPurchaseLimit(string $userEmail, int $productId, int $maxCount): int {
    try {
        $pdo = DBManager::getCardSqlite(); // 购买限制存在卡密库中
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("SELECT purchase_count FROM purchase_limits WHERE user_email = ? AND product_id = ? AND purchase_date = ? LIMIT 1");
        $stmt->execute([$userEmail, $productId, $today]);
        $result = $stmt->fetch();
        
        $currentCount = $result ? (int)$result['purchase_count'] : 0;
        return $currentCount >= $maxCount ? 0 : ($maxCount - $currentCount); // 返回剩余购买次数
    } catch (DatabaseException $e) {
        error_log("[检查限购] 失败：" . $e->getMessage());
        throw new BusinessException('限购检查失败，请稍后重试');
    }
}

// 增加购买次数记录
function incrementPurchaseCount(string $userEmail, int $productId): void {
    try {
        $pdo = DBManager::getCardSqlite();
        $today = date('Y-m-d');
        
        // 先尝试更新
        $stmt = $pdo->prepare("UPDATE purchase_limits SET purchase_count = purchase_count + 1 WHERE user_email = ? AND product_id = ? AND purchase_date = ?");
        $stmt->execute([$userEmail, $productId, $today]);
        
        // 如果没有更新到记录，插入新记录
        if ($stmt->rowCount() === 0) {
            $stmt = $pdo->prepare("INSERT INTO purchase_limits (user_email, product_id, purchase_date) VALUES (?, ?, ?)");
            $stmt->execute([$userEmail, $productId, $today]);
        }
    } catch (DatabaseException $e) {
        error_log("[增加购买次数] 失败：" . $e->getMessage());
        throw new BusinessException('购买记录更新失败，请稍后重试');
    }
}

// 生成外部卡密（含所属用户）
function createCardCode(string $userEmail, int $points): string {
    try {
        $pdo = DBManager::getCardSqlite();
        $code = generateRandomCode();
        $now = time();
        
        $stmt = $pdo->prepare("INSERT INTO card_codes (code, points, user_email, created_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$code, $points, $userEmail, $now]);
        
        error_log("[生成卡密] 成功：{$code} ({$points}积分) 绑定邮箱：{$userEmail}");
        return $code; // 返回生成的卡密
    } catch (DatabaseException $e) {
        error_log("[生成卡密] 失败：" . $e->getMessage());
        throw new BusinessException('卡密生成失败，请稍后重试');
    }
}

// 生成商城积分兑换码（无所属用户）- 保留函数（管理后台用）
function createRedeemCode(int $points): string {
    try {
        $pdo = DBManager::getRedeemSqlite();
        $code = generateRandomCode();
        $now = time();
        
        $stmt = $pdo->prepare("INSERT INTO redeem_codes (code, points, created_at) VALUES (?, ?, ?)");
        $stmt->execute([$code, $points, $now]);
        
        error_log("[生成兑换码] 成功：{$code} ({$points}积分)");
        return $code; // 返回生成的兑换码
    } catch (DatabaseException $e) {
        error_log("[生成兑换码] 失败：" . $e->getMessage());
        throw new BusinessException('兑换码生成失败，请稍后重试');
    }
}

// 验证并使用商城积分兑换码（使用后直接删除）
function validateAndUseRedeemCode(string $code): int {
    try {
        $pdo = DBManager::getRedeemSqlite();
        // 验证兑换码格式
        if (strlen($code) !== 64) {
            throw new BusinessException('兑换码格式错误（需64位）');
        }
        
        // 查询兑换码
        $stmt = $pdo->prepare("SELECT id, points FROM redeem_codes WHERE code = ? LIMIT 1");
        $stmt->execute([$code]);
        $codeData = $stmt->fetch();
        
        if (!$codeData) {
            throw new BusinessException('兑换码不存在或已失效');
        }
        
        // 使用后直接删除（核心逻辑）
        $stmt = $pdo->prepare("DELETE FROM redeem_codes WHERE id = ?");
        $stmt->execute([$codeData['id']]);
        
        error_log("[使用兑换码] 成功：{$code} ({$codeData['points']}积分)，已删除");
        return (int)$codeData['points'];
    } catch (DatabaseException $e) {
        error_log("[验证兑换码] 失败：" . $e->getMessage());
        throw new BusinessException('兑换码验证失败，请稍后重试');
    }
}

// 渲染弹窗（区分卡密/兑换码展示）
function renderModal(string $title, string $content, string $type = 'info', array $codeData = []): string {
    // 兼容 PHP 7.x 的写法
    switch ($type) {
        case 'success':
            $typeClass = 'modal-success';
            break;
        case 'error':
            $typeClass = 'modal-error';
            break;
        case 'warning':
            $typeClass = 'modal-warning';
            break;
        default:
            $typeClass = 'modal-info';
    }
    
    $title = htmlspecialchars($title);
    $content = htmlspecialchars($content);
    
    // 码展示区域（仅卡密，无兑换码商品）
    $codeHtml = '';
    if (!empty($codeData) && isset($codeData['type']) && $codeData['type'] === 'card') {
        // 仅展示卡密（含所属用户）
        $code = htmlspecialchars($codeData['code']);
        $userEmail = htmlspecialchars($codeData['user_email'] ?? '');
        $codeHtml = <<<HTML
        <div class="code-container">
            <div class="code-label">外部卡密（点击复制）：</div>
            <div class="code-value" id="redeemCode">{$code}</div>
            <div class="code-user">所属用户：{$userEmail}</div>
            <button class="btn btn-copy" onclick="copyCode()">复制卡密</button>
        </div>
        HTML;
    }
    
    return <<<HTML
    <div class="modal-overlay" id="customModal">
        <div class="modal-container {$typeClass}">
            <div class="modal-header">
                <h3>{$title}</h3>
                <button class="modal-close js-modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <p>{$content}</p>
                {$codeHtml}
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary js-modal-close">确认</button>
            </div>
        </div>
    </div>
    HTML;
}

// ====================== 核心修改：恢复原商品列表（仅外部卡密+等级升级） ======================
function getProducts(): array {
    return [
        // 仅保留外部卡密商品
        [
            'id' => 1,
            'name' => '1000积分外部卡密',
            'description' => '花费1000积分，获得1000积分外部卡密（含所属用户绑定）',
            'price' => 1000,
            'type' => 'card', // 仅卡密类型
            'code_points' => 1000,
            'limit' => 0,
            'icon' => '卡密.svg'
        ],
        [
            'id' => 2,
            'name' => '5000积分外部卡密',
            'description' => '花费5000积分，获得5000积分外部卡密（含所属用户绑定）',
            'price' => 5000,
            'type' => 'card',
            'code_points' => 5000,
            'limit' => 0,
            'icon' => '卡密.svg'
        ],
        // 保留等级升级商品
        [
            'id' => 5,
            'name' => '升级等级一级',
            'description' => '花费对应积分，升级等级一级（无每日限购）',
            'price' => 0, // 动态计算所需积分
            'type' => 'level_up',
            'limit' => 0,
            'icon' => '固件升级.svg'
        ]
    ];
}

// 渲染商品卡片
function renderProductCard(array $product, int $userPoints, string $userEmail): string {
    $id = htmlspecialchars((string)$product['id']);
    $name = htmlspecialchars($product['name']);
    $description = htmlspecialchars($product['description']);
    $icon = htmlspecialchars(CONFIG['paths']['svg_dir'] . $product['icon']);
    
    // 动态计算升级等级商品的价格
    $price = $product['price'];
    if ($product['type'] === 'level_up' && $userPoints >= 0) {
        $userData = readUserData(getUserDataPath($userEmail));
        $currentLevel = (int)$userData['level'];
        $price = (int)(CONFIG['business']['level_up'][$currentLevel] ?? 0);
    }
    
    $limit = (int)$product['limit'];
    
    // 检查限购剩余次数
    $remaining = $limit > 0 ? checkPurchaseLimit($userEmail, $product['id'], $limit) : -1;
    $disabled = $userPoints < $price || ($limit > 0 && $remaining <= 0);
    $disabledClass = $disabled ? 'btn-disabled' : '';
    $limitText = '';
    
    if ($limit > 0) {
        $limitText = $remaining > 0 ? 
            "<div class='product-limit'>今日剩余：{$remaining}/{$limit}次</div>" : 
            "<div class='product-limit limit-exhausted'>今日已达限购次数</div>";
    }
    
    // 等级升级商品特殊提示
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
        }
    }
    
    // 提前计算按钮状态和文字
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
        $btnText = '立即兑换';
    }
    
    return <<<HTML
    <div class="product-card">
        <div class="product-icon">
            <img src="{$icon}" alt="{$name}图标">
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

// 响应头配置
$headers = [
    'Access-Control-Allow-Origin'      => CONFIG['security']['allow_origin'],
    'Access-Control-Allow-Methods'     => 'GET, POST, OPTIONS',
    'Access-Control-Allow-Headers'     => 'Content-Type, X-Requested-With',
    'Access-Control-Allow-Credentials' => 'true',
    'X-Frame-Options'                  => 'SAMEORIGIN',
    'Content-Security-Policy'          => sprintf(
        "default-src 'self'; " .
        "font-src 'self' %s; " .
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

// 错误处理
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
$codeData = []; // 仅存储卡密数据
$products = getProducts();

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    DBManager::closeAll();
    exit;
}

// 处理POST请求（兑换码兑换/商品兑换）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userEmail) {
    try {
        if (!validateCsrfToken()) {
            throw new SecurityException('CSRF验证失败，请刷新页面重试');
        }

        // 处理商城积分兑换码兑换（保留手动输入兑换码功能）
        if (isset($_POST['redeem_code']) && !empty(trim($_POST['redeem_code']))) {
            $code = trim($_POST['redeem_code']);
            
            // 验证并使用兑换码（使用后删除）
            $points = validateAndUseRedeemCode($code);
            
            // 更新用户积分
            $userFilePath = getUserDataPath($userEmail);
            $userData = readUserData($userFilePath);
            $newPoints = (int)$userData['points'] + $points;
            $updateSuccess = updateUserData($userFilePath, ['points' => $newPoints]);
            
            if ($updateSuccess) {
                $message = "兑换码使用成功！获得{$points}积分，当前剩余{$newPoints}积分";
                $messageType = 'success';
                // 重新读取用户数据
                $userData = readUserData($userFilePath);
            } else {
                throw new BusinessException('积分更新失败，请稍后重试');
            }
        } 
        // 处理商品兑换（仅卡密+等级升级）
        elseif (isset($_POST['product_id']) && is_numeric($_POST['product_id'])) {
            $productId = (int)$_POST['product_id'];
            // 查找商品
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

            // 读取用户数据
            $userFilePath = getUserDataPath($userEmail);
            $userData = readUserData($userFilePath);
            $userPoints = (int)$userData['points'];
            $currentLevel = (int)$userData['level'];

            // 动态计算升级等级商品的价格
            $price = $product['price'];
            if ($product['type'] === 'level_up') {
                $price = (int)(CONFIG['business']['level_up'][$currentLevel] ?? 0);
                if ($price <= 0) {
                    throw new BusinessException('当前等级无升级配置');
                }
                if ($currentLevel >= CONFIG['business']['max_level']) {
                    throw new BusinessException('已达最高等级，无法升级');
                }
            }

            // 检查积分是否足够
            if ($userPoints < $price) {
                throw new BusinessException("积分不足！需要{$price}积分，当前仅有{$userPoints}积分");
            }

            // 检查限购
            if ($product['limit'] > 0) {
                $remaining = checkPurchaseLimit($userEmail, $productId, $product['limit']);
                if ($remaining <= 0) {
                    throw new BusinessException("该商品每日限购{$product['limit']}次，今日已达上限");
                }
            }

            // 扣减积分
            $newPoints = $userPoints - $price;
            $updateSuccess = false;

            // 仅处理卡密和等级升级（无兑换码商品）
            if ($product['type'] === 'card') {
                // 生成外部卡密（含所属用户）
                $code = createCardCode($userEmail, $product['code_points']);
                // 更新用户积分
                $updateSuccess = updateUserData($userFilePath, ['points' => $newPoints]);
                if ($updateSuccess) {
                    $message = "兑换成功！已生成{$product['code_points']}积分外部卡密，扣除{$product['price']}积分，剩余{$newPoints}积分";
                    $messageType = 'success';
                    // 仅存储卡密数据
                    $codeData = [
                        'type' => 'card',
                        'code' => $code,
                        'user_email' => $userEmail
                    ];
                }
            } elseif ($product['type'] === 'level_up') {
                // 升级等级
                $newLevel = $currentLevel + 1;
                // 更新用户等级和积分
                $updateSuccess = updateUserData($userFilePath, [
                    'level' => $newLevel,
                    'points' => $newPoints
                ]);
                if ($updateSuccess) {
                    $message = "升级成功！已从{$currentLevel}级升至{$newLevel}级，扣除{$price}积分，剩余{$newPoints}积分";
                    $messageType = 'success';
                }
            }

            if (!$updateSuccess) {
                throw new BusinessException('操作失败，请稍后重试');
            }

            // 重新读取用户数据
            $userData = readUserData($userFilePath);
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

// 读取用户数据（非POST请求）
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
    <title>积分商城 | 兑换中心</title>
    <style nonce="<?= $cspNonce ?>">
        /* 隐藏右侧滚动条 */
        body {
            overflow-y: hidden;
            overflow-x: hidden;
        }
        
        /* 全局滚动条样式（备用） */
        ::-webkit-scrollbar {
            display: none;
        }
        
        /* 全局样式重置 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-weight: 400 !important; /* 强制设置font-weight:400 */
            transition: all 0.3s ease;
        }

        /* 字体配置 - 阿里妈妈灵动字体 */
        @font-face {
            font-family: 'AlimamaAgileVF';
            src: url("<?= htmlspecialchars(CONFIG['paths']['font_dir']) ?>AlimamaAgileVF-Thin.woff2") format('woff2'),
                 url("<?= htmlspecialchars(CONFIG['paths']['font_dir']) ?>AlimamaAgileVF-Thin.ttf") format('truetype');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }

        /* 全局背景 */
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4eaf5 100%);
            min-height: 100vh;
            padding: 20px;
            color: #333;
            font-family: 'AlimamaAgileVF', 'Microsoft YaHei', sans-serif !important;
        }

        /* 容器样式 */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            padding: 30px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.9);
            height: calc(100vh - 40px);
            overflow-y: auto;
            scrollbar-width: none; /* 隐藏Firefox滚动条 */
        }

        /* 头部样式 */
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f0f4ff;
        }

        .header h1 {
            font-size: 2.2rem;
            color: #2d3748;
            margin-bottom: 10px;
        }

        .header p {
            color: #718096;
            font-size: 1rem;
        }

        /* 用户信息卡片 */
        .user-card {
            background: linear-gradient(120deg, #4299e1 0%, #38b2ac 100%);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            color: white;
            box-shadow: 0 8px 24px rgba(66, 153, 225, 0.2);
        }

        .user-card h2 {
            margin-bottom: 15px;
            font-size: 1.8rem;
        }

        .user-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 15px;
        }

        .user-stat-item {
            background: rgba(255, 255, 255, 0.2);
            padding: 15px 20px;
            border-radius: 12px;
            min-width: 140px;
            text-align: center;
            backdrop-filter: blur(10px);
        }

        .user-stat-label {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 8px;
        }

        .user-stat-value {
            font-size: 1.8rem;
            color: white;
        }

        /* 兑换码区域样式 */
        .redeem-section {
            margin-bottom: 30px;
            padding: 25px;
            background: #f8f9fa;
            border-radius: 16px;
            border: 1px solid #f0f4ff;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.03);
        }

        .redeem-form {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
            width: 100%;
        }

        .redeem-input-group {
            display: flex;
            gap: 10px;
            width: 100%;
        }

        .redeem-input {
            flex: 1;
            padding: 14px 20px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            outline: none;
            transition: all 0.3s ease;
            font-family: 'AlimamaAgileVF', 'Microsoft YaHei', sans-serif;
        }

        .redeem-input:focus {
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }

        .redeem-btn {
            white-space: nowrap;
            padding: 14px 28px;
        }

        /* 商品区域标题 */
        .section-title {
            font-size: 1.5rem;
            color: #2d3748;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f4ff;
        }

        /* 商品网格 */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        /* 商品卡片 */
        .product-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 25px;
            border: 1px solid #f0f4ff;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        /* 商品图标 */
        .product-icon {
            text-align: center;
            margin-bottom: 15px;
        }

        .product-icon img {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }

        .product-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .product-name {
            font-size: 1.2rem;
            color: #2d3748;
            margin: 0;
        }

        .product-price {
            background: #e8f4f8;
            color: #4299e1;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .product-desc {
            color: #718096;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 20px;
            flex: 1;
        }

        .product-limit {
            font-size: 0.85rem;
            margin-bottom: 15px;
            padding: 5px 10px;
            border-radius: 8px;
            background: #f0f8fb;
            color: #4299e1;
        }

        .limit-exhausted {
            background: #fef7fb;
            color: #e53e3e;
        }

        /* 按钮样式 */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            cursor: pointer;
            outline: none;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(120deg, #4299e1 0%, #38b2ac 100%);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(66, 153, 225, 0.3);
        }

        .btn-product {
            width: 100%;
            padding: 14px;
            font-size: 1rem;
        }

        .btn-disabled {
            background: #e2e8f0 !important;
            color: #a0aec0 !important;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        /* 卡密复制按钮 */
        .btn-copy {
            background: #48bb78;
            color: white;
            padding: 8px 16px;
            font-size: 0.9rem;
            margin-top: 10px;
            border-radius: 8px;
        }

        .btn-copy:hover {
            background: #38a169;
        }

        /* 弹窗样式 */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(5px);
        }

        .modal-container {
            background: #ffffff;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #f0f4ff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 1.3rem;
            color: #2d3748;
        }

        .modal-success .modal-header {
            border-bottom-color: #c6f6d5;
        }

        .modal-error .modal-header {
            border-bottom-color: #fed7d7;
        }

        .modal-warning .modal-header {
            border-bottom-color: #fefcbf;
        }

        .modal-close {
            background: transparent;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #718096;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .modal-close:hover {
            color: #2d3748;
            background: #f0f4ff;
        }

        .modal-body {
            padding: 25px;
            font-size: 1.05rem;
            color: #4a5568;
            line-height: 1.6;
        }

        .modal-footer {
            padding: 15px 25px;
            border-top: 1px solid #f0f4ff;
            text-align: right;
        }

        /* 卡密展示区域（仅卡密） */
        .code-container {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }

        .code-label {
            font-size: 0.95rem;
            color: #4a5568;
            margin-bottom: 8px;
        }

        .code-value {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            padding: 10px;
            background: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            word-break: break-all;
            margin-bottom: 10px;
            color: #2d3748;
        }

        .code-user {
            font-size: 0.9rem;
            color: #718096;
            margin-bottom: 10px;
        }

        /* 未登录提示 */
        .login-prompt {
            text-align: center;
            padding: 50px 20px;
            color: #718096;
        }

        .login-prompt h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: #2d3748;
        }

        /* 响应式适配 */
        @media (max-width: 768px) {
            .container {
                padding: 20px;
                border-radius: 15px;
                height: calc(100vh - 20px);
            }

            .user-stats {
                flex-direction: column;
                gap: 10px;
            }

            .user-stat-item {
                width: 100%;
            }

            .redeem-input-group {
                flex-direction: column;
            }
            
            .redeem-btn {
                width: 100%;
            }

            .product-grid {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 1.8rem;
            }

            .user-card h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- 页面头部 -->
        <div class="header">
            <h1>积分商城</h1>
            <p>积分兑换超值权益</p>
        </div>

        <!-- 弹窗 -->
        <?php if ($message): ?>
            <?= renderModal(
                $messageType === 'success' ? '操作成功' : ($messageType === 'error' ? '操作失败' : '提示'),
                $message,
                $messageType,
                $codeData
            ) ?>
        <?php endif; ?>

        <!-- 用户信息区域 -->
        <?php if ($userEmail): ?>
            <div class="user-card">
                <h2>欢迎回来，<?= htmlspecialchars($userData['username']) ?></h2>
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

            <!-- 商城积分兑换码兑换区域（保留手动输入功能） -->
            <div class="redeem-section">
                <h2 class="section-title">商城积分兑换码兑换</h2>
                <form method="POST" class="redeem-form">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <div class="redeem-input-group">
                        <input type="text" name="redeem_code" class="redeem-input" placeholder="请输入64位商城积分兑换码" maxlength="64" required>
                        <button type="submit" class="btn btn-primary redeem-btn">立即兑换</button>
                    </div>
                </form>
            </div>

            <!-- 商品兑换区域（仅原商品） -->
            <div class="section">
                <h2 class="section-title">商品兑换</h2>
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
        // 复制卡密函数
        function copyCode() {
            const codeElement = document.getElementById('redeemCode');
            if (codeElement) {
                const code = codeElement.textContent;
                navigator.clipboard.writeText(code).then(() => {
                    alert('卡密已复制到剪贴板！');
                }).catch(err => {
                    // 降级方案：手动选中复制
                    const textArea = document.createElement('textarea');
                    textArea.value = code;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                    alert('卡密已复制到剪贴板！');
                });
            }
        }

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

            // 自动关闭弹窗
            const modal = document.getElementById('customModal');
            if (modal && !modal.querySelector('.code-container')) {
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
