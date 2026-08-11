-- EcoLife_Testimonial :: testimonial
--
-- What customers actually said. Managed from the admin panel for the same
-- reason gallery_item is: the family cannot edit files, and a quote with a
-- misspelled customer name has to be fixable without a developer.
--
-- The table ships empty on purpose. No quotes had been supplied when this was
-- built, and the home page renders no markup at all while it stays empty --
-- inventing three plausible-sounding customers would have undercut the one
-- thing the competitor comparison said we were already better at.
--
-- language exists because the competitor carries a Marathi testimonial and it
-- reads as more local than its English ones do. It drives lang= on the quote,
-- so a screen reader pronounces Marathi as Marathi.

CREATE TABLE testimonial (
  testimonial_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_name   VARCHAR(120) NOT NULL,
  location        VARCHAR(100) NULL COMMENT 'town or area, e.g. Karad',
  quote           TEXT NOT NULL,
  system_size_kw  DECIMAL(6,2) NULL,
  language        ENUM('en','mr') NOT NULL DEFAULT 'en',
  sort_order      INT NOT NULL DEFAULT 0,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (testimonial_id),
  KEY idx_testimonial_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
