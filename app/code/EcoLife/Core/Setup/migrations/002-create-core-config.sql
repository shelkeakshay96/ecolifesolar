-- EcoLife_Core :: core_config
--
-- Key-value store for everything the family must be able to change without
-- touching files. Load-bearing: they cannot edit code, so any contact detail,
-- business fact or notification setting that is hardcoded in a template is a
-- detail they can never correct themselves.

CREATE TABLE core_config (
  config_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  path        VARCHAR(190) NOT NULL COMMENT 'e.g. general/contact/phone',
  value       TEXT NULL,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (config_id),
  UNIQUE KEY uniq_core_config_path (path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
