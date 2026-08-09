-- EcoLife_Backend :: admin_activity_log
--
-- Append-only audit trail across every admin-editable entity. Deliberately
-- decoupled from the entities it describes: entity_type/entity_id is a soft
-- reference, so a deleted lead leaves its history behind instead of taking
-- the evidence with it.

CREATE TABLE admin_activity_log (
  log_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_user_id INT UNSIGNED NULL,
  entity_type   VARCHAR(50) NOT NULL COMMENT 'lead | gallery_item | admin_user',
  entity_id     INT UNSIGNED NULL,
  action        VARCHAR(50) NOT NULL COMMENT 'created | updated | deleted | login',
  details       TEXT NULL COMMENT 'JSON',
  ip_address    VARCHAR(45) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (log_id),
  KEY idx_aal_entity (entity_type, entity_id),
  KEY idx_aal_user (admin_user_id),
  KEY idx_aal_created (created_at),
  CONSTRAINT fk_aal_admin FOREIGN KEY (admin_user_id)
      REFERENCES admin_user (user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
