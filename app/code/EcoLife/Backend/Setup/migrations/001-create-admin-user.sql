-- EcoLife_Backend :: admin_user
--
-- Rows are created only via `bin/ecolife admin:user:create`. This table is
-- never seeded and never carries a default password -- a shipped default
-- credential is the single most common way a small site like this is taken
-- over.
--
-- failures_num / first_failure / lock_expires implement login throttling:
-- five failures inside the window locks the account for fifteen minutes.

CREATE TABLE admin_user (
  user_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username       VARCHAR(50)  NOT NULL,
  email          VARCHAR(190) NOT NULL,
  password_hash  VARCHAR(255) NOT NULL COMMENT 'password_hash(), PASSWORD_DEFAULT',
  first_name     VARCHAR(80)  NULL,
  last_name      VARCHAR(80)  NULL,
  role           ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  failures_num   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  first_failure  DATETIME NULL,
  lock_expires   DATETIME NULL,
  last_login_at  DATETIME NULL,
  last_login_ip  VARCHAR(45) NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uniq_admin_user_username (username),
  UNIQUE KEY uniq_admin_user_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
