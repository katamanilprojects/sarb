-- ============================================================================
-- Migration: 20261109_001_user_manuals_and_role_visibility_matrix.sql
-- Platform: JNTUA College of Engineering Ananthapuramu (SARB)
-- Description:
--   1. Safely add `academic_section_visibility` to `system_feature_modules` (if not exists).
--   2. Safely add `admin_visibility` to `system_feature_modules` (if not exists).
--   3. Register or update `MOD_USER_MANUALS` ("Role User Manuals (Web & PDF)").
--   4. Ensure all existing feature rows have default visibility for new columns.
-- Idempotency: Safe to execute repeatedly on any MySQL 5.7+ / 8.0+ / MariaDB server.
-- ============================================================================

-- Step 1: Add academic_section_visibility column idempotently
SET @sql_add_academic = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS 
   WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'system_feature_modules' 
     AND COLUMN_NAME = 'academic_section_visibility') > 0,
  'SELECT "Column academic_section_visibility already exists" AS status',
  'ALTER TABLE `system_feature_modules` ADD COLUMN `academic_section_visibility` ENUM(\'VISIBLE\', \'HIDDEN\', \'READONLY\') NOT NULL DEFAULT \'VISIBLE\' AFTER `hod_visibility`'
);
PREPARE stmt_academic FROM @sql_add_academic;
EXECUTE stmt_academic;
DEALLOCATE PREPARE stmt_academic;

-- Step 2: Add admin_visibility column idempotently
SET @sql_add_admin = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS 
   WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'system_feature_modules' 
     AND COLUMN_NAME = 'admin_visibility') > 0,
  'SELECT "Column admin_visibility already exists" AS status',
  'ALTER TABLE `system_feature_modules` ADD COLUMN `admin_visibility` ENUM(\'VISIBLE\', \'HIDDEN\', \'READONLY\') NOT NULL DEFAULT \'VISIBLE\' AFTER `academic_section_visibility`'
);
PREPARE stmt_admin FROM @sql_add_admin;
EXECUTE stmt_admin;
DEALLOCATE PREPARE stmt_admin;

-- Step 3: Ensure existing modules have valid defaults for the new columns
UPDATE `system_feature_modules`
SET `academic_section_visibility` = 'VISIBLE'
WHERE `academic_section_visibility` IS NULL OR `academic_section_visibility` = '';

UPDATE `system_feature_modules`
SET `admin_visibility` = 'VISIBLE'
WHERE `admin_visibility` IS NULL OR `admin_visibility` = '';

-- Step 4: Register / Update MOD_USER_MANUALS
INSERT INTO `system_feature_modules` (
  `module_key`,
  `module_name`,
  `category`,
  `description`,
  `is_enabled_globally`,
  `faculty_visibility`,
  `student_visibility`,
  `hod_visibility`,
  `academic_section_visibility`,
  `admin_visibility`,
  `sort_order`
) VALUES (
  'MOD_USER_MANUALS',
  'Role User Manuals (Web & PDF)',
  'GENERAL',
  'Interactive web user manuals and downloadable PDF step-by-step guides for institutional roles.',
  1,
  'VISIBLE',
  'HIDDEN',
  'VISIBLE',
  'VISIBLE',
  'VISIBLE',
  45
)
ON DUPLICATE KEY UPDATE 
  `module_name`                 = VALUES(`module_name`),
  `category`                    = VALUES(`category`),
  `description`                 = VALUES(`description`),
  `faculty_visibility`          = VALUES(`faculty_visibility`),
  `student_visibility`          = VALUES(`student_visibility`),
  `hod_visibility`              = VALUES(`hod_visibility`),
  `academic_section_visibility` = VALUES(`academic_section_visibility`),
  `admin_visibility`            = VALUES(`admin_visibility`),
  `sort_order`                  = VALUES(`sort_order`);
