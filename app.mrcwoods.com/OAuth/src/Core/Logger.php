<?php

namespace OAuth\Core;

class Logger
{
    /**
     * 记录操作日志
     * @param string $email 用户邮箱
     * @param string $action 操作类型
     * @param string $detail 操作详情
     */
    public static function writeOperationLog(string $email, string $action, string $detail): void
    {
        $logDir = dirname(__DIR__, 2) . '/../open-platform_log';
        $logFile = $logDir . '/' . urlencode($email) . '.log';
        
        // 确保日志目录存在
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // 格式化日志条目
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] 操作：{$action} | 详情：{$detail}\n";
        
        // 追加写入日志文件
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * 记录授权操作日志（带IP和User-Agent）
     */
    public static function logAuthorization(string $email, string $action, string $detail): void
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $enhancedDetail = "{$detail} | IP: {$ip} | UA: {$userAgent}";
        self::writeOperationLog($email, $action, $enhancedDetail);
    }
}