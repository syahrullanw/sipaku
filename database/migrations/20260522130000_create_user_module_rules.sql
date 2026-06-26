CREATE TABLE IF NOT EXISTS user_module_rules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_code VARCHAR(50) NOT NULL,
    module_key VARCHAR(100) NOT NULL,
    is_allowed TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_user_module_rule (role_code, module_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
