DROP TABLE IF EXISTS `file_release_map`;
DROP TABLE IF EXISTS `files`;

DROP TABLE IF EXISTS `release_error_log`;
DROP TABLE IF EXISTS `releases`;

DROP TABLE IF EXISTS `server_error_log`;
DROP TABLE IF EXISTS `servers`;

CREATE TABLE `servers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `label` VARCHAR(255) NULL COMMENT 'Human readable server name',
    `beta` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Is this server beta',
    `dev` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Is this a development server',
    `ip_address` VARCHAR(255) NOT NULL,
    `ssh_login` VARCHAR(255) NOT NULL,
    `project_directory` VARCHAR(255) NOT NULL COMMENT 'Path to project directory',
    `backup_file` VARCHAR(255) NULL COMMENT 'Path to backup file, at least one production server',
    `replication` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Replication (copying) should be started or in progress',
    `blocked` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Replication in progress, or an error occurs',
    `error` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET utf8;

CREATE TABLE `server_error_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `server_id` INT UNSIGNED NOT NULL,
    `message` VARCHAR(255) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET utf8;

CREATE TABLE `releases` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `label` VARCHAR(255) NULL COMMENT 'Human friendly release title',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `beta_replication` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `beta_error` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `beta_started_at` DATETIME NULL,
    `beta_completed_at` DATETIME NULL,
    `production_replication` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `production_backup` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `production_update` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `production_error` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `production_started_at` DATETIME NULL,
    `backup_completed_at` DATETIME NULL,
    `production_completed_at` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET utf8;

CREATE TABLE `release_error_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `release_id` INT UNSIGNED NOT NULL,
    `message` VARCHAR(255) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`release_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET utf8;

CREATE TABLE `files` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `hash` VARCHAR(32) NOT NULL UNIQUE KEY,
    `path` VARCHAR(255) NOT NULL,
    `type` ENUM ('directory', 'file') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET utf8;

CREATE TABLE `file_release_map` (
    `release_id` INT UNSIGNED NOT NULL,
    `file_id` INT UNSIGNED NOT NULL,
    FOREIGN KEY (`release_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET utf8;
