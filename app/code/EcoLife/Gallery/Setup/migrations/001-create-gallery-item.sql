-- EcoLife_Gallery :: gallery_item
--
-- Project photos, managed from the admin panel. This table exists for the
-- same reason core_config does: the family cannot edit files, so "can we add
-- our own photos" has to be answerable with yes.
--
-- image_path and thumbnail_path are stored relative to pub/media/, never as
-- absolute paths or URLs, so the media root can move without a data fix.

CREATE TABLE gallery_item (
  item_id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title           VARCHAR(190) NOT NULL,
  description     TEXT NULL,
  image_path      VARCHAR(255) NOT NULL COMMENT 'relative to pub/media/',
  thumbnail_path  VARCHAR(255) NULL COMMENT 'relative to pub/media/',
  location        VARCHAR(100) NULL COMMENT 'e.g. Satara, Maharashtra',
  system_size_kw  DECIMAL(6,2) NULL,
  install_date    DATE NULL,
  category        ENUM('residential','society','commercial','other')
                      NOT NULL DEFAULT 'residential',
  sort_order      INT NOT NULL DEFAULT 0,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (item_id),
  KEY idx_gallery_active_sort (is_active, sort_order),
  KEY idx_gallery_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
