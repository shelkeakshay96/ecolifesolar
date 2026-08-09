-- EcoLife_Lead :: lead_status_history
--
-- Every status change is recorded, so "who called this customer and when" is
-- answerable without guesswork. This matters the moment more than one person
-- is working the pipeline.
--
-- from_status / to_status are VARCHAR rather than ENUM on purpose: history is
-- a permanent record, and it must stay readable after the `lead`.`status`
-- ENUM gains or loses a value.

CREATE TABLE lead_status_history (
  history_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id       INT UNSIGNED NOT NULL,
  admin_user_id INT UNSIGNED NULL,
  from_status   VARCHAR(30) NULL,
  to_status     VARCHAR(30) NOT NULL,
  comment       TEXT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (history_id),
  KEY idx_lsh_lead (lead_id),
  KEY idx_lsh_created (created_at),
  CONSTRAINT fk_lsh_lead FOREIGN KEY (lead_id)
      REFERENCES `lead` (lead_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_lsh_admin FOREIGN KEY (admin_user_id)
      REFERENCES admin_user (user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
