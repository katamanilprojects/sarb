-- ============================================================================
-- SARB CEA: Complete Accreditation & Modularity Database Migration
-- Target Engine: MariaDB / MySQL (InnoDB, utf8mb4_general_ci)
-- Script: migration_accreditation_071026.sql
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. Register Feature Modules in `system_feature_modules`
-- ----------------------------------------------------------------------------
INSERT INTO `system_feature_modules` 
(`module_key`, `module_name`, `category`, `description`, `is_enabled_globally`, `faculty_visibility`, `student_visibility`, `hod_visibility`, `sort_order`)
VALUES
('MOD_SAR_FORM_C3', 'NBA SAR Form C-3 Dossier', 'OBE', 'Standalone CO attainment and gap analysis report generator for NBA Criterion 3', 1, 'VISIBLE', 'HIDDEN', 'VISIBLE', 30),
('MOD_COURSE_MASTER_FILE', 'Autonomous Course Master File', 'ACCREDITATION', 'Standalone extended course file with lesson plans, diary reconciliation, and DAAC audit certificates', 1, 'VISIBLE', 'HIDDEN', 'VISIBLE', 31),
('MOD_ATTENDANCE_CONDONATION_GAZETTE', 'Attendance Condonation Gazette', 'ATTENDANCE', 'Automated regulatory condonation and detention gazette generator', 1, 'READONLY', 'HIDDEN', 'VISIBLE', 32),
('MOD_BLOOM_ANALYSIS', 'Bloom HOTS/LOTS Advisory', 'CIA', 'Real-time advisory badge displaying cognitive level balance during question design', 1, 'VISIBLE', 'HIDDEN', 'VISIBLE', 33),
('MOD_PO_PSO_ATTAINMENT', 'PO/PSO Articulation & Surveys', 'OBE', 'Blended direct and indirect program outcome attainment matrices (NBA Table 3.2 & 3.3.2)', 1, 'READONLY', 'HIDDEN', 'VISIBLE', 34),
('MOD_LAB_RUBRICS', 'Laboratory Rubric Templates', 'CIA', 'Dual-mode lab evaluation: direct marks entry with optional editable rubrics templates', 1, 'VISIBLE', 'HIDDEN', 'VISIBLE', 35),
('MOD_ATTENDANCE_DELETE_REQUESTS', 'Attendance Deletion Workflow', 'ATTENDANCE', 'HOD single-tier attendance deletion approval backed by institutional audit_logs', 1, 'VISIBLE', 'HIDDEN', 'VISIBLE', 36),
('MOD_STUDENT_ATTENDANCE_RISK', 'Student Attendance Risk Gauge', 'STUDENT_PORTAL', 'Dynamic safe-margin and recovery periods indicator on student dashboard', 1, 'HIDDEN', 'VISIBLE', 'HIDDEN', 37),
('MOD_CQI_ACTION_TRACKING', 'CQI Remedial Action Tracker', 'OBE', 'Structured action-taken narrative logging for course outcomes with attainment gaps', 1, 'VISIBLE', 'HIDDEN', 'VISIBLE', 38),
('MOD_CERTIFICATE_QR', 'Cryptographic QR Credentialing', 'GENERAL', 'Public verification URLs and QR tokens on issued certificates and custodial records', 1, 'HIDDEN', 'VISIBLE', 'VISIBLE', 39),
('MOD_BATCH_OBE', 'Multi-Batch Longitudinal OBE', 'OBE', 'Multi-cohort longitudinal PEO and PO attainment trend comparisons', 1, 'HIDDEN', 'HIDDEN', 'READONLY', 40),
('MOD_CREDIT_PROMOTION', 'Credit Promotion Pipeline', 'EXAM', 'Optional advisory simulation tool for credit-based promotion rules (R23 Sec 18)', 0, 'HIDDEN', 'HIDDEN', 'HIDDEN', 41)
ON DUPLICATE KEY UPDATE 
  `module_name` = VALUES(`module_name`),
  `category` = VALUES(`category`),
  `description` = VALUES(`description`);

-- ----------------------------------------------------------------------------
-- 2. Insert Centralized Regulatory Parameters in `academic_settings`
-- ----------------------------------------------------------------------------
INSERT INTO `academic_settings` (`reg_id`, `regulation_code`, `category`, `setting_key`, `setting_value`, `data_type`, `description`, `is_editable`)
SELECT MIN(r.id), r.regulation, 'CIA', 'bloom_hots_lots_enforce', '0', 'BOOL', 'Enforce strict 40% minimum HOTS (L4-L6) marks during CIA question entry (0=Advisory, 1=Strict)', 1
FROM regulations r WHERE r.is_active = 1
GROUP BY r.regulation
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

INSERT INTO `academic_settings` (`reg_id`, `regulation_code`, `category`, `setting_key`, `setting_value`, `data_type`, `description`, `is_editable`)
SELECT MIN(r.id), r.regulation, 'GENERAL', 'daac_audit_variance_threshold_pct', '15.00', 'FLOAT', 'Syllabus lag percentage triggering DAAC mid-semester audit alert', 1
FROM regulations r WHERE r.is_active = 1
GROUP BY r.regulation
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

INSERT INTO `academic_settings` (`reg_id`, `regulation_code`, `category`, `setting_key`, `setting_value`, `data_type`, `description`, `is_editable`)
SELECT MIN(r.id), r.regulation, 'GENERAL', 'diary_lag_alert_threshold', '3', 'INT', 'Number of lectures diary entries lag planned topics before advisory alert', 1
FROM regulations r WHERE r.is_active = 1
GROUP BY r.regulation
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- ----------------------------------------------------------------------------
-- 3. Create Flexible Laboratory Evaluation Rubrics Tables
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lab_rubric_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_name` varchar(100) NOT NULL,
  `department_id` int(11) DEFAULT NULL COMMENT 'NULL = Institutional global default',
  `total_max_marks` decimal(4,2) NOT NULL DEFAULT 15.00,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `lab_rubric_criteria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `criterion_name` varchar(100) NOT NULL,
  `criterion_description` text DEFAULT NULL,
  `max_marks` decimal(4,2) NOT NULL DEFAULT 5.00,
  `sort_order` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_lrc_template` (`template_id`),
  CONSTRAINT `fk_lrc_template` FOREIGN KEY (`template_id`) REFERENCES `lab_rubric_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `lab_subject_rubrics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject_id` int(11) NOT NULL,
  `template_id` int(11) DEFAULT NULL,
  `criterion_name` varchar(100) NOT NULL,
  `max_marks` decimal(4,2) NOT NULL DEFAULT 5.00,
  `sort_order` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_lsr_subject` (`subject_id`),
  KEY `fk_lsr_template` (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `lab_rubric_evaluations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uglab_mark_id` int(11) NOT NULL COMMENT 'Links to uglab_internal_assessment_marks.id',
  `criterion_id` int(11) DEFAULT NULL,
  `criterion_name` varchar(100) NOT NULL,
  `marks_obtained` decimal(4,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_lre_uglab` (`uglab_mark_id`),
  CONSTRAINT `fk_lre_uglab` FOREIGN KEY (`uglab_mark_id`) REFERENCES `uglab_internal_assessment_marks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed Default 15-Mark Tripartite Lab Rubric Template
INSERT INTO `lab_rubric_templates` (`id`, `template_name`, `department_id`, `total_max_marks`, `is_default`) 
VALUES (1, 'Standard Autonomous Practical Rubric', NULL, 15.00, 1)
ON DUPLICATE KEY UPDATE `is_default` = 1;

INSERT INTO `lab_rubric_criteria` (`id`, `template_id`, `criterion_name`, `criterion_description`, `max_marks`, `sort_order`) 
VALUES 
(1, 1, 'Conduction & Execution', 'Apparatus setup, procedure execution, circuit wiring, or program logic and execution', 5.00, 1),
(2, 1, 'Observation & Record', 'Data observation accuracy, calculations, graphing, and continuous record maintenance', 5.00, 2),
(3, 1, 'Viva-Voce', 'Conceptual understanding, error analysis, theoretical correlations, and oral defense', 5.00, 3)
ON DUPLICATE KEY UPDATE `max_marks` = VALUES(`max_marks`);

-- ----------------------------------------------------------------------------
-- 4. Create Structured Continuous Quality Improvement (CQI) Action Tracking Table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `course_cqi_actions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sub_id` int(11) NOT NULL,
  `co_id` int(11) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `target_benchmark` decimal(5,2) NOT NULL DEFAULT 60.00,
  `attained_percentage` decimal(5,2) NOT NULL,
  `attainment_gap` decimal(5,2) NOT NULL,
  `action_proposed` text NOT NULL,
  `action_type` enum('REMEDIAL_CLASS','PEDAGOGICAL_CHANGE','TUTORIAL_HOURS','ASSIGNMENT_REVISION','CONTENT_BEYOND_SYLLABUS') NOT NULL DEFAULT 'REMEDIAL_CLASS',
  `faculty_id` int(11) NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `hod_review_status` enum('PENDING','APPROVED','REVISION_REQUESTED') NOT NULL DEFAULT 'PENDING',
  `hod_remarks` text DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_cca_sub` (`sub_id`),
  KEY `fk_cca_co` (`co_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
