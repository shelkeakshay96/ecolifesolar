-- EcoLife_Backend :: admin_password_reset
--
-- One row per reset request. A separate table rather than three more columns
-- on admin_user, for three reasons: it leaves an audit trail of who asked and
-- from where, it lets every outstanding token for a user be invalidated in one
-- statement when one of them is used, and it keeps the row that every
-- authenticated request loads from growing columns that matter twice a year.
--
-- token_hash is sha256 of the token, never the token itself. The raw value
-- exists only in the email that was sent. Anyone who can read this table --
-- through a backup, a dump, or a SQL injection somewhere else entirely --
-- still cannot construct a working reset link, which is the whole point of
-- hashing it.
--
-- sha256 rather than password_hash(): the token is 32 bytes from
-- random_bytes(), so it has no guessable structure for a slow hash to protect.
-- A fast hash also keeps lookup a single indexed read rather than a table scan
-- comparing every row.

CREATE TABLE admin_password_reset (
  reset_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED NOT NULL,
  token_hash    CHAR(64) NOT NULL COMMENT 'sha256 of the raw token, hex',
  expires_at    DATETIME NOT NULL,
  used_at       DATETIME NULL,
  requested_ip  VARCHAR(45) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (reset_id),
  UNIQUE KEY uniq_admin_reset_token (token_hash),
  KEY idx_admin_reset_user (user_id, used_at),
  CONSTRAINT fk_admin_reset_user FOREIGN KEY (user_id)
      REFERENCES admin_user (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
