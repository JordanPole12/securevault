CREATE DATABASE IF NOT EXISTS securevault CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE securevault;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role ENUM('admin') NOT NULL DEFAULT 'admin',
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE user_keys (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  public_key TEXT NOT NULL,
  key_fingerprint CHAR(64) NOT NULL,
  key_algorithm VARCHAR(32) NOT NULL DEFAULT 'RSA-4096',
  key_purpose ENUM('authentication','encryption') NOT NULL,
  key_status ENUM('active','revoked') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL,
  UNIQUE KEY uq_fingerprint (key_fingerprint),
  KEY idx_user_purpose (user_id, key_purpose, key_status),
  CONSTRAINT fk_keys_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE secure_contents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  key_id INT UNSIGNED NOT NULL,
  content_type ENUM('secure_note','document','announcement','article','system_info','confidential_record') NOT NULL,
  encrypted_title TEXT NOT NULL,
  encrypted_content MEDIUMTEXT NOT NULL,
  encrypted_aes_key TEXT NOT NULL,
  nonce VARCHAR(32) NOT NULL,
  authentication_tag VARCHAR(32) NOT NULL,
  encryption_algorithm VARCHAR(64) NOT NULL DEFAULT 'AES-256-GCM+RSA-OAEP-SHA256',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_type (content_type),
  CONSTRAINT fk_content_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_content_key FOREIGN KEY (key_id) REFERENCES user_keys(id)
) ENGINE=InnoDB;

CREATE TABLE secure_files (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  encrypted_filename TEXT NOT NULL,
  encrypted_path VARCHAR(255) NOT NULL,
  encrypted_aes_key TEXT NOT NULL,
  nonce VARCHAR(32) NOT NULL,
  authentication_tag VARCHAR(32) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_file_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE authentication_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  key_fingerprint CHAR(64) NULL,
  event_type VARCHAR(40) NOT NULL,
  status ENUM('success','failure') NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ip_time (ip_address, status, created_at)
) ENGINE=InnoDB;

CREATE TABLE security_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(40) NOT NULL,
  resource_type VARCHAR(40) NULL,
  resource_id INT UNSIGNED NULL,
  status VARCHAR(16) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_action_time (action, created_at)
) ENGINE=InnoDB;

CREATE TABLE decoy_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attempted_key_fingerprint CHAR(64) NULL,
  event_type VARCHAR(40) NOT NULL,
  status VARCHAR(16) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE system_settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(64) NOT NULL UNIQUE,
  setting_value VARCHAR(255) NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO system_settings (setting_key, setting_value) VALUES
 ('symmetric_algorithm','AES-256-GCM'),
 ('asymmetric_algorithm','RSA-4096 (OAEP-SHA256 / PSS-SHA256)'),
 ('challenge_ttl_seconds','120');
