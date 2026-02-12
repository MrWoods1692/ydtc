<?php
/**
 * 基于firebase/php-jwt的JWT工具类（^7.0）
 */
require_once __DIR__ . '/vendor/autoload.php'; // 引入vendor自动加载

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtHelper {
    // 生成JWT Token
    public static function generateToken(array $payload): string {
        $jwtConfig = EnvHelper::getJwtConfig();
        // 添加标准Claims
        $payload['exp'] = time() + $jwtConfig['expire']; // 过期时间
        $payload['iat'] = time(); // 签发时间
        $payload['jti'] = bin2hex(random_bytes(16)); // 唯一ID防重放
        $payload['iss'] = $_SERVER['HTTP_HOST']; // 签发者

        return JWT::encode($payload, $jwtConfig['secret'], 'HS256');
    }

    // 验证JWT Token
    public static function verifyToken(string $token): array|false {
        try {
            $jwtConfig = EnvHelper::getJwtConfig();
            // 验证Token并返回载荷
            $decoded = JWT::decode(
                $token,
                new Key($jwtConfig['secret'], 'HS256')
            );
            // 转换为数组
            return (array)$decoded;
        } catch (Exception $e) {
            // 验证失败（过期、签名错误等）
            return false;
        }
    }
}
