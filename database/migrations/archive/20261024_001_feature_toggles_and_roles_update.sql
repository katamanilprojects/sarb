-- ============================================================================
-- Migration: 20261024_001_feature_toggles_and_roles_update.sql
-- Description: Register and enforce default toggle states for system modules:
--   - MOD_CQI_ACTION_TRACKING (disabled until dynamic Superadmin activation)
--   - MOD_STUDENT_PROFILE (disabled until dynamic Superadmin activation; HOD READONLY)
--   - MOD_CERTIFICATES (disabled until dynamic Superadmin activation)
--   - MOD_CERTIFICATE_QR (disabled until dynamic Superadmin activation)
--   - MOD_RESULTS_PUBLISH (controlled by Superadmin toggle; HOD READONLY)
-- ============================================================================

INSERT INTO `system_feature_modules` 
(`module_key`, `module_name`, `category`, `description`, `is_enabled_globally`, `faculty_visibility`, `student_visibility`, `hod_visibility`, `sort_order`)
VALUES
('MOD_STUDENT_PROFILE', 'Student Profiles & Digital Dossier', 'STUDENT_PORTAL', 'Institutional student profiles, personal dossier, and digital document vault', 0, 'HIDDEN', 'VISIBLE', 'READONLY', 42),
('MOD_CERTIFICATES', 'Institutional Certificates & Custody', 'GENERAL', 'Original certificate custody ledger, issuance workflow, and verification', 0, 'HIDDEN', 'VISIBLE', 'HIDDEN', 43),
('MOD_RESULTS_PUBLISH', 'Department & Semester Results', 'EXAM', 'Published semester results, pass percentages, student grade cards, and backlog rosters', 0, 'HIDDEN', 'VISIBLE', 'READONLY', 44)
ON DUPLICATE KEY UPDATE 
  `module_name` = VALUES(`module_name`),
  `category` = VALUES(`category`),
  `description` = VALUES(`description`),
  `hod_visibility` = VALUES(`hod_visibility`);

-- Keep modules disabled by default until Superadmin dynamically enables them
UPDATE `system_feature_modules` 
SET `is_enabled_globally` = 0 
WHERE `module_key` IN (
  'MOD_CQI_ACTION_TRACKING', 
  'MOD_STUDENT_PROFILE', 
  'MOD_CERTIFICATES', 
  'MOD_CERTIFICATE_QR'
);
