-- Distinguish the same regulation label in different programs (B.Tech R20 / MCA R20).
-- Resolve duplicate (reg_id, setting_key) rows before applying. No existing values change.
-- Legacy rows without reg_id remain unique by regulation_code.
-- The ALTER is atomic on MySQL 8; the index check makes interrupted-ledger replays safe.
SET @cea_regulation_scope_sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='academic_settings'
     AND INDEX_NAME='uk_regulation_scope_setting') > 0,
  'SELECT 1',
  'ALTER TABLE academic_settings
     ADD COLUMN regulation_scope VARCHAR(64)
       GENERATED ALWAYS AS (
         CASE WHEN reg_id IS NULL THEN CONCAT(''legacy:'', regulation_code)
              ELSE CONCAT(''reg:'', reg_id) END
       ) STORED,
     DROP INDEX uk_reg_setting,
     ADD UNIQUE KEY uk_regulation_scope_setting (regulation_scope, setting_key)'
);
PREPARE cea_regulation_scope_statement FROM @cea_regulation_scope_sql;
EXECUTE cea_regulation_scope_statement;
DEALLOCATE PREPARE cea_regulation_scope_statement;
