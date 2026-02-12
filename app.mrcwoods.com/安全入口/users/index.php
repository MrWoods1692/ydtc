<?php
session_start();

// ==================== 常量定义 ====================
define('ENV_PATH', '../../in/.env');
define('USER_JSON_ROOT', '../../users/');
define('PER_PAGE', 20);
define('CSRF_TOKEN_NAME', 'csrf_token');
define('LOGIN_TIMEOUT', 3600); // 登录超时时间（秒）- 1小时

// ==================== 工具函数 ====================
function safeOutput($data): string
{
    return htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
}

function generateCsrfToken(): string
{
    // 检查并生成CSRF令牌（避免重复生成）
    if (!isset($_SESSION[CSRF_TOKEN_NAME]) || empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function validateCsrfToken(): bool
{
    // 关键修改1：先生成令牌，再验证（避免未定义键）
    generateCsrfToken();
    
    // 关键修改2：添加空值检查
    $sessionToken = $_SESSION[CSRF_TOKEN_NAME] ?? '';
    $postToken = $_POST[CSRF_TOKEN_NAME] ?? '';
    
    if (($_SERVER['REQUEST_METHOD'] === 'POST') && empty($postToken)) {
        $_SESSION['error'] = "请求验证失败，请刷新页面重试";
        return false;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $postToken !== $sessionToken) {
        $_SESSION['error'] = "请求验证失败，请刷新页面重试";
        return false;
    }
    
    return true;
}

function formatTime(?int $timestamp, string $format = 'Y-m-d H:i:s'): string
{
    return $timestamp ? date($format, $timestamp) : '未设置';
}

function formatSpace(?float $kb): string
{
    if (!$kb || $kb <= 0) return '0 KB';
    if ($kb >= 1024 * 1024) return number_format($kb / 1024 / 1024, 2) . ' GB';
    if ($kb >= 1024) return number_format($kb / 1024, 2) . ' MB';
    return number_format($kb, 2) . ' KB';
}

/**
 * 检查登录超时
 */
function checkLoginTimeout(): void
{
    // 关键修改3：添加session键检查
    $loggedIn = $_SESSION['logged_in'] ?? false;
    if ($loggedIn === true) {
        $loginTime = $_SESSION['login_time'] ?? 0;
        // 超过1小时则退出登录
        if (time() - $loginTime > LOGIN_TIMEOUT) {
            session_unset();
            session_destroy();
            $_SESSION['error'] = "登录超时，请重新登录";
        }
    }
}

// ==================== 配置与数据库 ====================
function loadEnv(): array
{
    $env = [];
    if (!file_exists(ENV_PATH) || !is_readable(ENV_PATH)) {
        $_SESSION['error'] = "配置文件不存在或无读取权限：" . ENV_PATH;
        return $env;
    }

    $lines = file(ENV_PATH, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (str_starts_with($line, '#') || empty($line)) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $env[trim($parts[0])] = trim($parts[1]);
        }
    }

    $requiredKeys = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS', 'NAME', 'PASS'];
    foreach ($requiredKeys as $key) {
        if (!isset($env[$key]) || empty($env[$key])) {
            $_SESSION['error'] = "配置文件缺少必要项：{$key}";
            return [];
        }
    }
    return $env;
}

function createDbConnection(array $env): ?PDO
{
    try {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $env['DB_HOST'], $env['DB_PORT'], $env['DB_NAME']);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => true
        ];
        return new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], $options);
    } catch (PDOException $e) {
        $_SESSION['error'] = "数据库连接失败：" . $e->getMessage();
        return null;
    }
}

/**
 * 获取分页用户列表（支持搜索）- 修复HY093参数数量错误
 * @param PDO $pdo 数据库连接
 * @param int $page 当前页码
 * @param string $search 搜索关键词
 * @return array [用户列表, 总条数]
 */
function getPaginatedUsers(PDO $pdo, int $page = 1, string $search = ''): array
{
    try {
        $offset = ($page - 1) * PER_PAGE;
        
        // ========== 关键修复：拆分参数数组 ==========
        // 1. 统计总条数的参数（仅包含search）
        $countParams = [];
        $countSql = "SELECT COUNT(*) AS total FROM users";
        
        // 2. 查询列表的参数（包含search + limit + offset）
        $selectParams = [
            ':limit' => PER_PAGE,
            ':offset' => $offset
        ];
        $selectSql = "SELECT id, email, token, created_at, updated_at FROM users";
        
        // 添加搜索条件
        if (!empty($search)) {
            $countSql .= " WHERE email LIKE :search";
            $selectSql .= " WHERE email LIKE :search";
            $countParams[':search'] = "%{$search}%"; // 统计仅用search
            $selectParams[':search'] = "%{$search}%"; // 列表用search+limit+offset
        }
        
        $selectSql .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";
        
        // 获取总条数（使用countParams，仅包含必要参数）
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($countParams); // 修复：不再传入多余的limit/offset
        $total = $countStmt->fetch()['total'] ?? 0;
        
        // 获取当前页数据（使用selectParams，包含所有需要的参数）
        $stmt = $pdo->prepare($selectSql);
        $stmt->execute($selectParams);
        
        return [$stmt->fetchAll(), $total];
    } catch (PDOException $e) {
        $_SESSION['error'] = "查询用户列表失败：" . $e->getMessage();
        return [[], 0];
    }
}

// ==================== 业务逻辑 ====================
function handleLogin(array $env): bool
{
    // 检查登录超时
    checkLoginTimeout();
    
    // 关键修改4：登录状态检查添加空值判断
    $loggedIn = $_SESSION['logged_in'] ?? false;
    if ($loggedIn === true) return true;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrfToken()) {
        $inputName = trim($_POST['name'] ?? '');
        $inputPass = trim($_POST['pass'] ?? '');
        if ($inputName === $env['NAME'] && $inputPass === $env['PASS']) {
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time(); // 记录登录时间
            return true;
        } else {
            $_SESSION['error'] = "用户名或密码错误";
        }
    }

    renderLoginForm();
    exit;
}

function getUserJsonDetails(string $email): ?array
{
    $safeEmail = preg_replace('/[^\w\@\.\-]/', '', $email);
    if ($safeEmail !== $email) {
        $_SESSION['error'] = "邮箱包含非法字符，无法读取详情";
        return null;
    }

    $encodedEmail = urlencode($safeEmail);
    $jsonPath = realpath(USER_JSON_ROOT . $encodedEmail . '/user.json');
    $rootPath = realpath(USER_JSON_ROOT);
    if (!$jsonPath || !str_starts_with($jsonPath, $rootPath)) {
        $_SESSION['error'] = "非法的文件访问路径：{$encodedEmail}/user.json";
        return null;
    }

    if (!file_exists($jsonPath) || !is_readable($jsonPath)) {
        $_SESSION['error'] = "用户详情文件不存在或无读取权限：{$jsonPath}";
        return null;
    }

    $jsonContent = file_get_contents($jsonPath);
    $userData = json_decode($jsonContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $_SESSION['error'] = "JSON文件格式错误：" . json_last_error_msg();
        return null;
    }
    return $userData;
}

function saveUserJsonDetails(string $email, array $newData): bool
{
    $safeEmail = preg_replace('/[^\w\@\.\-]/', '', $email);
    if ($safeEmail !== $email) {
        $_SESSION['error'] = "邮箱包含非法字符，无法保存修改";
        return false;
    }

    $encodedEmail = urlencode($safeEmail);
    $jsonPath = realpath(USER_JSON_ROOT . $encodedEmail . '/user.json');
    $rootPath = realpath(USER_JSON_ROOT);
    if (!$jsonPath || !str_starts_with($jsonPath, $rootPath)) {
        $_SESSION['error'] = "非法的文件访问路径：{$encodedEmail}/user.json";
        return false;
    }

    if (!file_exists($jsonPath) || !is_writable($jsonPath)) {
        $_SESSION['error'] = "用户详情文件不可写，请检查文件权限：{$jsonPath}";
        return false;
    }

    $userData = getUserJsonDetails($email);
    if (!$userData) return false;

    $errors = [];
    if (isset($newData['username']) && trim($newData['username']) === '') {
        $errors[] = "用户名不能为空";
    } else {
        $userData['username'] = trim($newData['username']);
    }

    if (isset($newData['level'])) {
        $level = (int)$newData['level'];
        if ($level < 0) $errors[] = "等级不能为负数";
        else $userData['level'] = $level;
    }

    if (isset($newData['points'])) {
        $points = (int)$newData['points'];
        if ($points < 0) $errors[] = "积分不能为负数";
        else $userData['points'] = $points;
    }

    if (!empty($errors)) {
        $_SESSION['error'] = implode('，', $errors);
        return false;
    }

    $userData['updated_at'] = time();
    $jsonContent = json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (file_put_contents($jsonPath, $jsonContent) === false) {
        $_SESSION['error'] = "保存用户信息失败，请检查文件权限";
        return false;
    }

    $_SESSION['success'] = "修改成功！";
    return true;
}

// ==================== 视图渲染 ====================
function renderLoginForm(): void
{
    $error = $_SESSION['error'] ?? '';
    unset($_SESSION['error']);
    $csrfToken = generateCsrfToken(); // 确保登录页生成令牌
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --success: #16a34a;
            --success-bg: #f0fdf4;
            --error: #dc2626;
            --error-bg: #fef2f2;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --white: #ffffff;
            --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --radius: 8px;
            --radius-sm: 4px;
            --transition: all 0.2s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        html, body {
            height: 100%;
            background-color: var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            padding: 2.5rem;
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
        }

        .alert {
            padding: 1rem;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
        }

        .alert-error {
            background: var(--error-bg);
            color: var(--error);
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--gray-700);
            font-size: 0.875rem;
            font-weight: 500;
        }

        input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            transition: var(--transition);
        }

        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2);
        }

        .btn {
            width: 100%;
            padding: 0.875rem;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-primary {
            background: var(--primary);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--primary-hover);
        }
    </style>
</head>
<body>
    <div class="login-card">
        <?php if ($error): ?>
            <div class="alert alert-error"><?= safeOutput($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= safeOutput($csrfToken) ?>">
            <div class="form-group">
                <label for="name">账号</label>
                <input type="text" id="name" name="name" required autofocus>
            </div>
            <div class="form-group">
                <label for="pass">密码</label>
                <input type="password" id="pass" name="pass" required>
            </div>
            <button type="submit" class="btn btn-primary">登录</button>
        </form>
    </div>
</body>
</html>
<?php
}

function renderPagination(int $total, int $currentPage, string $search = ''): void
{
    $totalPages = max(1, (int)ceil($total / PER_PAGE));
    if ($totalPages <= 1) return;
    
    // 构建分页链接（保留搜索参数）
    $searchParam = !empty($search) ? '&search=' . urlencode($search) : '';
?>
<div class="pagination">
    <nav>
        <ul>
            <?php if ($currentPage > 1): ?>
                <li><a href="?page=1<?= $searchParam ?>">首页</a></li>
                <li><a href="?page=<?= $currentPage - 1 ?><?= $searchParam ?>">上一页</a></li>
            <?php endif; ?>
            
            <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                <li><a href="?page=<?= $i ?><?= $searchParam ?>" class="<?= $i === $currentPage ? 'active' : '' ?>"><?= $i ?></a></li>
            <?php endfor; ?>
            
            <?php if ($currentPage < $totalPages): ?>
                <li><a href="?page=<?= $currentPage + 1 ?><?= $searchParam ?>">下一页</a></li>
                <li><a href="?page=<?= $totalPages ?><?= $searchParam ?>">尾页</a></li>
            <?php endif; ?>
        </ul>
    </nav>
    <p>共 <?= $total ?> 条记录，第 <?= $currentPage ?> / <?= $totalPages ?> 页</p>
</div>
<?php
}

function renderMainPage(array $userList, int $totalUsers, int $currentPage, ?string $selectedEmail, ?array $userDetails, string $search): void
{
    $error = $_SESSION['error'] ?? '';
    $success = $_SESSION['success'] ?? '';
    unset($_SESSION['error'], $_SESSION['success']);
    $csrfToken = generateCsrfToken(); // 确保主页面生成令牌
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --success: #16a34a;
            --success-bg: #f0fdf4;
            --error: #dc2626;
            --error-bg: #fef2f2;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --white: #ffffff;
            --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --radius: 8px;
            --radius-sm: 4px;
            --transition: all 0.2s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        html, body {
            height: 100%;
            background-color: var(--gray-50);
        }

        .app-container {
            height: 100vh;
            width: 100%;
            display: flex;
            flex-direction: column;
        }

        /* 头部样式 */
        .header {
            padding: 1rem 2rem;
            background: var(--white);
            box-shadow: var(--shadow-sm);
            display: flex;
            justify-content: flex-start;
            align-items: center;
        }

        /* 搜索框样式 */
        .search-container {
            width: 100%;
            max-width: 500px;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid var(--gray-200);
            border-radius: 999px;
            font-size: 0.875rem;
            transition: var(--transition);
            background: var(--gray-50);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2);
            background: var(--white);
        }

        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-600);
            font-size: 1rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-primary {
            background: var(--primary);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--primary-hover);
        }

        .btn-link {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .btn-link:hover {
            color: var(--primary-hover);
            text-decoration: underline;
        }

        /* 内容区域 */
        .content {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
        }

        /* 提示框 */
        .alert {
            padding: 1rem;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            max-width: 100%;
        }

        .alert-error {
            background: var(--error-bg);
            color: var(--error);
        }

        .alert-success {
            background: var(--success-bg);
            color: var(--success);
        }

        /* 表格样式 */
        .table-wrapper {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 1rem 1.25rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-100);
        }

        th {
            background: var(--gray-50);
            color: var(--gray-700);
            font-weight: 500;
            font-size: 0.875rem;
        }

        tr:hover {
            background: var(--gray-50);
        }

        /* Token复制样式 */
        .token-copy {
            cursor: pointer;
            color: var(--primary);
            position: relative;
            user-select: none;
        }

        .token-copy:hover {
            text-decoration: underline;
        }

        .token-copy::after {
            content: '复制';
            position: absolute;
            right: -30px;
            top: 0;
            font-size: 0.75rem;
            color: var(--gray-600);
            opacity: 0;
            transition: var(--transition);
        }

        .token-copy:hover::after {
            opacity: 1;
        }

        .token-copy.tooltip::after {
            content: '已复制!';
            color: var(--success);
            opacity: 1;
        }

        /* 详情卡片 */
        .detail-card {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            padding: 2rem;
            max-width: 100%;
        }

        .detail-card h3 {
            color: var(--gray-700);
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-100);
            font-size: 1.125rem;
            font-weight: 600;
        }

        .detail-item {
            display: flex;
            margin-bottom: 1rem;
        }

        .detail-label {
            width: 120px;
            color: var(--gray-600);
            font-weight: 500;
            font-size: 0.875rem;
        }

        .detail-value {
            flex: 1;
            color: var(--gray-700);
            font-size: 0.875rem;
        }

        /* 编辑表单 */
        .edit-form {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray-100);
        }

        .edit-form h4 {
            color: var(--gray-700);
            margin-bottom: 1.5rem;
            font-size: 1rem;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--gray-700);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            max-width: 500px;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2);
        }

        /* 分页样式 */
        .pagination {
            margin-top: 1.5rem;
            text-align: center;
        }

        .pagination ul {
            list-style: none;
            display: inline-flex;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }

        .pagination a {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-sm);
            color: var(--gray-700);
            text-decoration: none;
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .pagination a:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .pagination a.active {
            background: var(--primary);
            color: var(--white);
            border-color: var(--primary);
        }

        .pagination p {
            color: var(--gray-600);
            font-size: 0.875rem;
        }

        /* 空数据样式 */
        .empty-state {
            padding: 4rem 2rem;
            text-align: center;
            color: var(--gray-600);
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
        }

        .empty-state p {
            margin-top: 0.5rem;
            font-size: 0.875rem;
        }

        /* 响应式适配 */
        @media (max-width: 768px) {
            .header {
                padding: 1rem;
            }

            .content {
                padding: 1rem;
            }

            .detail-item {
                flex-direction: column;
                gap: 0.25rem;
            }

            .detail-label {
                width: 100%;
            }

            .form-group input {
                max-width: 100%;
            }

            th, td {
                padding: 0.75rem;
                font-size: 0.875rem;
            }

            .token-copy::after {
                display: none;
            }

            .search-container {
                max-width: 100%;
            }
        }
    </style>
    <script>
        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            const text = element.textContent.trim();
            navigator.clipboard.writeText(text).then(() => {
                element.classList.add('tooltip');
                setTimeout(() => element.classList.remove('tooltip'), 1500);
            }).catch(err => {
                alert('复制失败：' + err);
            });
        }

        // 搜索表单提交
        function submitSearch() {
            const searchInput = document.getElementById('search-input');
            const searchValue = searchInput.value.trim();
            window.location.href = '?search=' + encodeURIComponent(searchValue) + '&page=1';
        }

        // 回车触发搜索
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search-input');
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        submitSearch();
                    }
                });
            }
        });
    </script>
</head>
<body>
    <div class="app-container">
        <!-- 头部（含搜索框） -->
        <div class="header">
            <div class="search-container">
                <span class="search-icon">Q</span>
                <input type="text" id="search-input" class="search-input" 
                       placeholder="搜索用户邮箱..." value="<?= safeOutput($search) ?>">
            </div>
            <button class="btn btn-primary" style="margin-left: 1rem;" onclick="submitSearch()">搜索</button>
        </div>

        <!-- 内容区域 -->
        <div class="content">
            <!-- 提示信息 -->
            <?php if ($error): ?>
                <div class="alert alert-error"><?= safeOutput($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= safeOutput($success) ?></div>
            <?php endif; ?>

            <!-- 用户详情 -->
            <?php if ($userDetails && $selectedEmail): ?>
                <div class="detail-card">
                    <h3><?= safeOutput($selectedEmail) ?></h3>
                    <div class="detail-item">
                        <div class="detail-label">用户名：</div>
                        <div class="detail-value"><?= safeOutput($userDetails['username'] ?? '未设置') ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">等级：</div>
                        <div class="detail-value"><?= safeOutput($userDetails['level'] ?? 0) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">积分：</div>
                        <div class="detail-value"><?= number_format($userDetails['points'] ?? 0) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">已使用空间：</div>
                        <div class="detail-value"><?= formatSpace($userDetails['used_space_kb'] ?? 0) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">最后更新：</div>
                        <div class="detail-value"><?= formatTime($userDetails['updated_at'] ?? null) ?></div>
                    </div>

                    <!-- 修改表单 -->
                    <div class="edit-form">
                        <h4>编辑信息</h4>
                        <form method="POST" action="?email=<?= urlencode($selectedEmail) ?>&search=<?= urlencode($search) ?>">
                            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= safeOutput($csrfToken) ?>">
                            <input type="hidden" name="edit_user" value="1">
                            
                            <div class="form-group">
                                <label for="username">用户名</label>
                                <input type="text" id="username" name="username" 
                                       value="<?= safeOutput($userDetails['username'] ?? '') ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="level">等级</label>
                                <input type="number" id="level" name="level" min="0" step="1"
                                       value="<?= safeOutput($userDetails['level'] ?? 0) ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="points">积分</label>
                                <input type="number" id="points" name="points" min="0" step="1"
                                       value="<?= safeOutput($userDetails['points'] ?? 0) ?>" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">保存</button>
                            <a href="index.php?search=<?= urlencode($search) ?>" class="btn-link" style="margin-left: 10px;">返回</a>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- 用户列表 -->
                <?php if (empty($userList)): ?>
                    <div class="empty-state">
                        <h3>暂无数据</h3>
                        <p><?= !empty($search) ? '未找到匹配 "' . safeOutput($search) . '" 的用户' : '暂无用户数据' ?></p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>邮箱</th>
                                    <th>Token</th>
                                    <th>创建时间</th>
                                    <th>更新时间</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($userList as $user): ?>
                                    <tr>
                                        <td><?= safeOutput($user['id']) ?></td>
                                        <td>
                                            <a href="?email=<?= urlencode($user['email']) ?>&search=<?= urlencode($search) ?>" class="btn-link">
                                                <?= safeOutput($user['email']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if ($user['token']): ?>
                                                <span id="token_<?= $user['id'] ?>" class="token-copy" 
                                                      onclick="copyToClipboard('token_<?= $user['id'] ?>')">
                                                    <?= safeOutput($user['token']) ?>
                                                </span>
                                            <?php else: ?>
                                                未设置
                                            <?php endif; ?>
                                        </td>
                                        <td><?= formatTime($user['created_at']) ?></td>
                                        <td><?= formatTime($user['updated_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- 分页 -->
                    <?= renderPagination($totalUsers, $currentPage, $search) ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php
}

// ==================== 主程序入口 ====================
$env = loadEnv();
if (empty($env)) {
    die("<div style='display:flex;align-items:center;justify-content:center;height:100vh;color:var(--error);'>配置加载失败：" . ($_SESSION['error'] ?? '未知错误') . "</div>");
}

// 处理用户修改
$selectedEmail = $_GET['email'] ?? null;
$search = trim($_GET['search'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user']) && $selectedEmail && validateCsrfToken()) {
    $selectedEmail = urldecode($selectedEmail);
    $newData = [
        'username' => $_POST['username'] ?? '',
        'level' => $_POST['level'] ?? 0,
        'points' => $_POST['points'] ?? 0
    ];
    saveUserJsonDetails($selectedEmail, $newData);
    $userDetails = getUserJsonDetails($selectedEmail);
}

// 登录验证（含超时检查）
handleLogin($env);

// 数据库连接
$pdo = createDbConnection($env);
if (!$pdo) {
    die("<div style='display:flex;align-items:center;justify-content:center;height:100vh;color:var(--error);'>" . ($_SESSION['error'] ?? '数据库连接失败') . "</div>");
}

// 请求参数处理
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$userDetails = null;

if ($selectedEmail) {
    $selectedEmail = urldecode($selectedEmail);
    $userDetails = getUserJsonDetails($selectedEmail);
}

// 获取用户列表（支持搜索）
[$userList, $totalUsers] = getPaginatedUsers($pdo, $currentPage, $search);

// 渲染页面
renderMainPage($userList, $totalUsers, $currentPage, $selectedEmail, $userDetails, $search);
?>
