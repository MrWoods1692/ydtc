CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL COMMENT '用户邮箱',
  `verify_code` varchar(64) DEFAULT NULL COMMENT '验证码',
  `verify_code_time` int UNSIGNED DEFAULT NULL COMMENT '验证码发送时间戳',
  `verify_fail_count` tinyint UNSIGNED DEFAULT 0 COMMENT '验证码失败次数',
  `token` varchar(64) DEFAULT NULL COMMENT '64位唯一登录令牌',
  `created_at` int UNSIGNED NOT NULL COMMENT '创建时间戳',
  `updated_at` int UNSIGNED NOT NULL COMMENT '更新时间戳',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),  -- 邮箱唯一
  UNIQUE KEY `token` (`token`)   -- token唯一
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';
