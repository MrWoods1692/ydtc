-- OAuth2.0 授权服务数据库表
-- 请在已有的 users 表基础上执行以下SQL

-- OAuth客户端应用表
CREATE TABLE IF NOT EXISTS `oauth_clients` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) NOT NULL COMMENT '客户端ID',
  `client_secret` varchar(128) NOT NULL COMMENT '客户端密钥',
  `name` varchar(128) NOT NULL COMMENT '应用名称',
  `homepage` varchar(512) DEFAULT NULL COMMENT '应用主页',
  `description` text COMMENT '应用描述',
  `icon` varchar(512) DEFAULT NULL COMMENT '应用图标URL',
  `redirect_uri` varchar(512) NOT NULL COMMENT '回调地址',
  `user_id` int UNSIGNED NOT NULL COMMENT '所属用户ID',
  `created_at` int UNSIGNED NOT NULL COMMENT '创建时间戳',
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_id` (`client_id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='OAuth客户端表';

-- 授权日志表（用于统计）
CREATE TABLE IF NOT EXISTS `oauth_authorization_logs` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) NOT NULL COMMENT '客户端ID',
  `user_id` int UNSIGNED NOT NULL COMMENT '用户ID',
  `action` varchar(32) NOT NULL COMMENT '操作类型：authorize/deny',
  `ip` varchar(45) DEFAULT NULL COMMENT 'IP地址',
  `user_agent` varchar(512) DEFAULT NULL COMMENT '用户代理',
  `created_at` int UNSIGNED NOT NULL COMMENT '创建时间戳',
  PRIMARY KEY (`id`),
  KEY `client_user` (`client_id`, `user_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='授权日志表';

-- 授权码表
CREATE TABLE IF NOT EXISTS `oauth_auth_codes` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(128) NOT NULL COMMENT '授权码',
  `client_id` varchar(64) NOT NULL COMMENT '客户端ID',
  `user_id` int UNSIGNED NOT NULL COMMENT '用户ID',
  `redirect_uri` varchar(512) NOT NULL COMMENT '回调地址',
  `expires_at` int UNSIGNED NOT NULL COMMENT '过期时间戳',
  `created_at` int UNSIGNED NOT NULL COMMENT '创建时间戳',
  PRIMARY KEY (`id`),
  KEY `code` (`code`),
  KEY `client_user` (`client_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='授权码表';

-- 访问令牌表
CREATE TABLE IF NOT EXISTS `oauth_access_tokens` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `access_token` varchar(128) NOT NULL COMMENT '访问令牌',
  `client_id` varchar(64) NOT NULL COMMENT '客户端ID',
  `user_id` int UNSIGNED NOT NULL COMMENT '用户ID',
  `expires_at` int UNSIGNED NOT NULL COMMENT '过期时间戳',
  `created_at` int UNSIGNED NOT NULL COMMENT '创建时间戳',
  PRIMARY KEY (`id`),
  UNIQUE KEY `access_token` (`access_token`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='访问令牌表';

-- 插入一个测试客户端（可选，用于测试）
-- INSERT INTO `oauth_clients` (`client_id`, `client_secret`, `name`, `redirect_uri`, `user_id`, `created_at`) VALUES
-- ('test_client_id', 'test_client_secret', '测试应用', 'http://localhost:8080/callback', 1, UNIX_TIMESTAMP());
