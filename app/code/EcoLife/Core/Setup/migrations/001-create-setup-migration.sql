-- EcoLife_Core :: setup_migration
--
-- The migration ledger itself. Every other migration in every module is
-- recorded here, and that record is the only thing that makes setup:upgrade
-- idempotent.
--
-- This is the single file permitted to use IF NOT EXISTS. The ledger cannot
-- record its own creation before it exists, so the migrator bootstraps it:
-- if the table is absent, the applied-list is treated as empty, this file
-- runs, and the row for it is written immediately afterwards. No other
-- migration may use IF NOT EXISTS -- a half-applied migration must fail
-- loudly rather than be silently skipped.

CREATE TABLE IF NOT EXISTS setup_migration (
  module     VARCHAR(64)  NOT NULL,
  migration  VARCHAR(128) NOT NULL,
  applied_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (module, migration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
