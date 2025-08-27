-- Create additional databases for testing and development
CREATE DATABASE IF NOT EXISTS `talent2income_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `talent2income_staging` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Grant permissions
GRANT ALL PRIVILEGES ON `talent2income`.* TO 'talent2income_user'@'%';
GRANT ALL PRIVILEGES ON `talent2income_test`.* TO 'talent2income_user'@'%';
GRANT ALL PRIVILEGES ON `talent2income_staging`.* TO 'talent2income_user'@'%';

-- Create read-only user for analytics/reporting
CREATE USER IF NOT EXISTS 'talent2income_readonly'@'%' IDENTIFIED BY 'readonly_password';
GRANT SELECT ON `talent2income`.* TO 'talent2income_readonly'@'%';
GRANT SELECT ON `talent2income_test`.* TO 'talent2income_readonly'@'%';
GRANT SELECT ON `talent2income_staging`.* TO 'talent2income_readonly'@'%';

FLUSH PRIVILEGES;