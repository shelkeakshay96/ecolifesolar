-- EcoLife_Lead :: lead
--
-- The reason the site exists. One table covers all three form variants;
-- variant-specific columns are nullable and are enforced by the validator,
-- not by the schema.
--
-- NOTE ON THE TABLE NAME. `LEAD` is a reserved word in MySQL 8 (it became one
-- with the window functions in 8.0). The identifier must therefore be
-- backtick-quoted in every statement that names it -- DDL here, and every
-- SELECT/INSERT/UPDATE/DELETE the resource model generates. The resource
-- layer quotes all identifiers unconditionally, so this costs nothing at the
-- call site, but hand-written SQL against this table will fail without the
-- backticks.

CREATE TABLE `lead` (
  lead_id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_type           ENUM('residential','society','commercial') NOT NULL,

  -- Contact (all variants)
  name                VARCHAR(150) NOT NULL,
  email               VARCHAR(190) NULL,
  phone               VARCHAR(15)  NOT NULL COMMENT 'validated ^[6-9]\\d{9}$',
  city                VARCHAR(100) NOT NULL,
  pincode             VARCHAR(6)   NOT NULL,
  state               VARCHAR(100) NOT NULL DEFAULT 'Maharashtra',

  -- Requirement
  monthly_bill_range  VARCHAR(50)  NULL COMMENT 'whitelisted server-side',
  monthly_bill_amount DECIMAL(10,2) NULL,
  roof_type           ENUM('rcc','metal_sheet','tiled','other') NULL,
  roof_area_sqft      INT UNSIGNED NULL,

  -- Housing society only
  society_name        VARCHAR(190) NULL,
  society_designation ENUM('committee','resident','builder','facility_manager') NULL,
  agm_status          ENUM('approved','in_discussion','not_started') NULL,
  total_flats         SMALLINT UNSIGNED NULL,

  -- Commercial only
  company_name        VARCHAR(190) NULL,
  gstin               VARCHAR(15)  NULL,

  message             TEXT NULL,

  -- Pipeline
  status              ENUM('new','contacted','survey_scheduled','survey_done',
                           'quotation_sent','negotiating','converted',
                           'not_interested','junk') NOT NULL DEFAULT 'new',
  priority            ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  assigned_to         INT UNSIGNED NULL,
  admin_notes         TEXT NULL,
  contacted_at        DATETIME NULL,
  next_followup_at    DATETIME NULL,

  -- Phase 2 flags (denormalised for cheap grid badges)
  has_quotation       TINYINT(1) NOT NULL DEFAULT 0,
  has_invoice         TINYINT(1) NOT NULL DEFAULT 0,

  -- Attribution and forensics
  source_page         VARCHAR(50)  NULL COMMENT 'home | contact',
  utm_source          VARCHAR(100) NULL,
  utm_medium          VARCHAR(100) NULL,
  utm_campaign        VARCHAR(100) NULL,
  ip_address          VARCHAR(45)  NULL,
  user_agent          VARCHAR(255) NULL,

  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (lead_id),
  KEY idx_lead_status (status),
  KEY idx_lead_created (created_at),
  KEY idx_lead_type (lead_type),
  KEY idx_lead_phone (phone),
  KEY idx_lead_assigned (assigned_to),
  KEY idx_lead_status_created (status, created_at),
  KEY idx_lead_followup (next_followup_at),
  CONSTRAINT fk_lead_assigned_to FOREIGN KEY (assigned_to)
      REFERENCES admin_user (user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
