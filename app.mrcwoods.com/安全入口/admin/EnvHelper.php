<?php
/**
 * 安全的.env文件读取工具（解决密码读取错误问题）
 */
class EnvHelper {
    private static array $envData = [];

    // 初始化：读取.env文件并解析
    private static function init(): void {
        if (empty(self::$envData)) {
            $envPath = '../../in/.env';
            if (!file_exists($envPath)) {
                die(".env文件不存在：{$envPath}");
            }
            
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                // 跳过注释行
                if (str_starts_with(trim($line), '#')) {
                    continue;
                }
                // 分割key=value（兼容value含=的场景）
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $key = trim($parts[0]);
                    $value = trim($parts[1]);
                    // 移除value的引号（如果有）
                    $value = preg_replace('/^(["\'])(.*)\1$/', '$2', $value);
                    self::$envData[$key] = $value;
                }
            }
        }
    }

    // 获取.env配置项（核心：解决密码读取错误）
    public static function get(string $key): string {
        self::init();
        if (!isset(self::$envData[$key])) {
            die("环境变量{$key}未找到");
        }
        return self::$envData[$key];
    }

    // 验证账号密码（直接返回，避免重复解析）
    public static function getAuth(): array {
        return [
            'username' => self::get('NAME'),
            'password' => self::get('PASS')
        ];
    }

    // JWT相关配置
    public static function getJwtConfig(): array {
        return [
            'secret' => self::get('JWT_SECRET'),
            'expire' => (int)self::get('JWT_EXPIRE')
        ];
    }
}
