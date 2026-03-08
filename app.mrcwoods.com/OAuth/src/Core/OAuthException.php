<?php

namespace OAuth\Core;

use Exception;

class OAuthException extends Exception
{
    protected string $error;
    protected string $errorDescription;

    // OAuth2.0 标准错误类型
    public const INVALID_REQUEST = 'invalid_request';
    public const INVALID_CLIENT = 'invalid_client';
    public const INVALID_GRANT = 'invalid_grant';
    public const UNAUTHORIZED_CLIENT = 'unauthorized_client';
    public const UNSUPPORTED_GRANT_TYPE = 'unsupported_grant_type';
    public const INVALID_SCOPE = 'invalid_scope';
    public const ACCESS_DENIED = 'access_denied';
    public const UNSUPPORTED_RESPONSE_TYPE = 'unsupported_response_type';
    public const SERVER_ERROR = 'server_error';
    public const TEMPORARILY_UNAVAILABLE = 'temporarily_unavailable';

    public function __construct(string $error, string $errorDescription, int $httpCode = 400)
    {
        $this->error = $error;
        $this->errorDescription = $errorDescription;
        parent::__construct($errorDescription, $httpCode);
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function getErrorDescription(): string
    {
        return $this->errorDescription;
    }

    public function toArray(): array
    {
        return [
            'error' => $this->error,
            'error_description' => $this->errorDescription
        ];
    }

    public function sendResponse(): void
    {
        http_response_code($this->getCode());
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
        exit;
    }
}