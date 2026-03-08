<?php

namespace OAuth\OAuth;

use OAuth\Core\Config;
use OAuth\Core\Database;
use OAuth\Core\OAuthException;

class AuthorizationServer
{
    private int $authCodeExpire;
    private int $accessTokenExpire;

    public function __construct()
    {
        $this->authCodeExpire = (int) Config::get('OAUTH_AUTH_CODE_EXPIRE', 600);
        $this->accessTokenExpire = (int) Config::get('OAUTH_ACCESS_TOKEN_EXPIRE', 86400);
    }

    /**
     * 验证客户端
     */
    public function validateClient(string $clientId, ?string $redirectUri = null): array
    {
        $client = Database::queryOne(
            "SELECT * FROM oauth_clients WHERE client_id = ?",
            [$clientId]
        );

        if (!$client) {
            throw new OAuthException(
                OAuthException::INVALID_CLIENT,
                '客户端不存在',
                401
            );
        }

        if ($redirectUri && $client['redirect_uri'] !== $redirectUri) {
            throw new OAuthException(
                OAuthException::INVALID_REQUEST,
                '回调地址不匹配',
                400
            );
        }

        return $client;
    }

    /**
     * 验证客户端密钥
     */
    public function validateClientSecret(string $clientId, string $clientSecret): array
    {
        $client = Database::queryOne(
            "SELECT * FROM oauth_clients WHERE client_id = ? AND client_secret = ?",
            [$clientId, $clientSecret]
        );

        if (!$client) {
            throw new OAuthException(
                OAuthException::INVALID_CLIENT,
                '客户端认证失败',
                401
            );
        }

        return $client;
    }

    /**
     * 生成授权码
     */
    public function generateAuthCode(string $clientId, int $userId, string $redirectUri): string
    {
        $code = $this->generateToken(64);
        $expiresAt = time() + $this->authCodeExpire;

        Database::insert(
            "INSERT INTO oauth_auth_codes (code, client_id, user_id, redirect_uri, expires_at, created_at) VALUES (?, ?, ?, ?, ?, ?)",
            [$code, $clientId, $userId, $redirectUri, $expiresAt, time()]
        );

        return $code;
    }

    /**
     * 验证授权码
     */
    public function validateAuthCode(string $code, string $clientId): array
    {
        $authCode = Database::queryOne(
            "SELECT * FROM oauth_auth_codes WHERE code = ? AND client_id = ?",
            [$code, $clientId]
        );

        if (!$authCode) {
            throw new OAuthException(
                OAuthException::INVALID_GRANT,
                '授权码无效',
                400
            );
        }

        if ($authCode['expires_at'] < time()) {
            // 删除过期的授权码
            Database::execute("DELETE FROM oauth_auth_codes WHERE code = ?", [$code]);
            throw new OAuthException(
                OAuthException::INVALID_GRANT,
                '授权码已过期',
                400
            );
        }

        // 删除已使用的授权码（一次性使用）
        Database::execute("DELETE FROM oauth_auth_codes WHERE code = ?", [$code]);

        return $authCode;
    }

    /**
     * 生成访问令牌
     */
    public function generateAccessToken(string $clientId, int $userId): array
    {
        $accessToken = $this->generateToken(64);
        $expiresAt = time() + $this->accessTokenExpire;

        Database::insert(
            "INSERT INTO oauth_access_tokens (access_token, client_id, user_id, expires_at, created_at) VALUES (?, ?, ?, ?, ?)",
            [$accessToken, $clientId, $userId, $expiresAt, time()]
        );

        return [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->accessTokenExpire
        ];
    }

    /**
     * 验证访问令牌
     */
    public function validateAccessToken(string $accessToken): array
    {
        $token = Database::queryOne(
            "SELECT * FROM oauth_access_tokens WHERE access_token = ?",
            [$accessToken]
        );

        if (!$token) {
            throw new OAuthException(
                OAuthException::INVALID_GRANT,
                '访问令牌无效',
                401
            );
        }

        if ($token['expires_at'] < time()) {
            Database::execute("DELETE FROM oauth_access_tokens WHERE access_token = ?", [$accessToken]);
            throw new OAuthException(
                OAuthException::INVALID_GRANT,
                '访问令牌已过期',
                401
            );
        }

        return $token;
    }

    /**
     * 生成随机令牌
     */
    private function generateToken(int $length): string
    {
        return bin2hex(random_bytes($length / 2));
    }
}