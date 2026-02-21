<?php
/**
 * 全局安全响应头配置
 * 禁止跨域、禁止嵌入、防XSS/CSRF、强制内容类型
 */
// 禁止跨域嵌入（核心：防止页面被其他网站嵌入）
header('X-Frame-Options: SAMEORIGIN');
// 内容安全策略（新增cdnjs域名，允许加载Font Awesome样式）
header("Content-Security-Policy: " .
    "default-src 'self'; " .
    "script-src 'self' 'unsafe-inline'; " .
    "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; " . // 关键修改：添加cdnjs域名
    "img-src 'self' data:; " .
    "frame-src 'self'; " . // 仅允许同域iframe
    "font-src 'self' https://cdnjs.cloudflare.com; " . // 补充：允许Font Awesome字体文件
    "connect-src 'self';");
// 防XSS
header('X-XSS-Protection: 1; mode=block');
// 强制MIME类型解析
header('X-Content-Type-Options: nosniff');
// 强制HTTPS（生产环境启用）
// header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
// 引用策略
header('Referrer-Policy: same-origin');
// 禁止缓存敏感页面
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// 验证请求来源（加强CSRF防护）
function validateRequestOrigin(): void {
    $allowedOrigins = [
        $_SERVER['HTTP_HOST'],
        'localhost',
        '127.0.0.1'
    ];
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        $origin = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST);
        if (!in_array($origin, $allowedOrigins)) {
            http_response_code(403);
            die('跨域请求被禁止');
        }
    }
}
