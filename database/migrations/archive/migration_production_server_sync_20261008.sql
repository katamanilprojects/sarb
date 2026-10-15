-- ============================================================================
-- SARB CEA: Comprehensive Production Database Migration & Schema Parity Sync
-- Target Database : u182589698_jntuaceasarb (Hostinger Production)
-- Compatibility   : MariaDB 10.4.x - 11.8.x / MySQL 8.0+
-- Generated Date  : October 8, 2026
-- Safety Mode     : Safe, Idempotent, Additive, Non-destructive
-- Description     : Synchronizes production server schema with local standardized
--                   schema covering accreditation, CQI, lab rubrics, trusted core,
--                   attainment integrations, document lifecycles, and queue guards.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';
SET NAMES utf8mb4;

-- ============================================================================
-- PART 1: SCHEMA ALTERATIONS ON EXISTING TABLES (8 TABLES)
-- ============================================================================

-- 1.1 Table: assessment_questions
-- Widen marks precision from decimal(5,2) to decimal(12,5) for precise fractional scoring
ALTER TABLE `assessment_questions` 
  MODIFY COLUMN `marks` decimal(12,5) NOT NULL;

-- 1.2 Table: course_completion_audits
-- Add HOD reviewer attribution column
ALTER TABLE `course_completion_audits` 
  ADD COLUMN IF NOT EXISTS `hod_reviewed_by` int(11) DEFAULT NULL;

-- 1.3 Table: exam_notifications
-- Add exam governance workflow status, audit attribution, and exam type
ALTER TABLE `exam_notifications` 
  ADD COLUMN IF NOT EXISTS `workflow_status` enum('DRAFT','REVIEWED','PUBLISHED') NOT NULL DEFAULT 'DRAFT',
  ADD COLUMN IF NOT EXISTS `reviewed_by` int(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `reviewed_at` datetime DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `exam_type` enum('REGULAR','SUPPLEMENTARY','REVALUATION') NOT NULL DEFAULT 'REGULAR';

-- 1.4 Table: exam_results
-- Allow fractional grade points (e.g., 2.500) rather than truncating to integer
ALTER TABLE `exam_results` 
  MODIFY COLUMN `grade_points` decimal(8,3) DEFAULT NULL;

-- 1.5 Table: external_assessment_marks
-- Support external examination audit batches, raw normalization, and high-precision marks
ALTER TABLE `external_assessment_marks` 
  ADD COLUMN IF NOT EXISTS `source_notification_id` int(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `raw_marks` decimal(12,5) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `raw_max_marks` decimal(12,5) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `normalisation_json` longtext DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `source_batch_id` bigint(20) UNSIGNED DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `evidence_basis` varchar(40) NOT NULL DEFAULT 'LEGACY',
  ADD COLUMN IF NOT EXISTS `is_current` tinyint(1) NOT NULL DEFAULT 1,
  MODIFY COLUMN `external_marks` decimal(12,5) NOT NULL,
  MODIFY COLUMN `max_marks` decimal(12,5) NOT NULL DEFAULT 70.00000;

-- 1.6 Table: student_certificate_requests
-- Add cryptographically secure verification token and unique index for QR verification
ALTER TABLE `student_certificate_requests` 
  ADD COLUMN IF NOT EXISTS `verification_token` char(64) DEFAULT NULL;

ALTER TABLE `student_certificate_requests` 
  ADD UNIQUE INDEX IF NOT EXISTS `uq_certificate_verification_token` (`verification_token`);

-- 1.7 Table: student_documents
-- Add soft deletion lifecycle tracking
ALTER TABLE `student_documents` 
  ADD COLUMN IF NOT EXISTS `deleted_at` datetime DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `deleted_by` int(11) DEFAULT NULL;

-- 1.8 Table: student_marks
-- Widen marks precision from decimal(5,2) to decimal(12,5) for fractional scoring
ALTER TABLE `student_marks` 
  MODIFY COLUMN `marks_obtained` decimal(12,5) NOT NULL;

-- ============================================================================
-- PART 2: CREATE MISSING TABLES IF NOT EXISTS (18 TABLES)
-- ============================================================================

-- 2.1 Table: academic_policy_versions
-- Tracks frozen configuration hashes for regulatory policies per regulation
CREATE TABLE IF NOT EXISTS `academic_policy_versions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `regulation_id` int(11) NOT NULL,
  `policy_hash` char(64) NOT NULL,
  `settings_json` longtext NOT NULL,
  `recorded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `regulation_id` (`regulation_id`,`policy_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2.2 Table: academic_record_snapshots
-- Immutable before-and-after change audit logging for critical institutional academic data
CREATE TABLE IF NOT EXISTS `academic_record_snapshots` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` varchar(64) NOT NULL,
  `department_id` int(11) NOT NULL,
  `actor_user_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `before_json` longtext NOT NULL,
  `after_json` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `entity_type` (`entity_type`,`entity_id`),
  KEY `department_id` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2.3 Table: ai_job_consents
-- Per-user opt-in consent ledger for background processing tasks
CREATE TABLE IF NOT EXISTS `ai_job_consents` (
  `user_id` int(11) NOT NULL,
  `task_key` varchar(50) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`,`task_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2.4 Table: ai_jobs
-- Background asynchronous task queue and execution ledger
CREATE TABLE IF NOT EXISTS `ai_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `snapshot_id` bigint(20) UNSIGNED NOT NULL,
  `owner_user_id` int(11) NOT NULL,
  `task_key` varchar(50) NOT NULL,
  `input_hash` char(64) NOT NULL,
  `prompt_version` varchar(30) NOT NULL,
  `status` enum('QUEUED','RUNNING','DRAFT','FAILED','CANCELLED','APPROVED') NOT NULL DEFAULT 'QUEUED',
  `attempts` int(11) NOT NULL DEFAULT 0,
  `locked_at` datetime DEFAULT NULL,
  `model_id` varchar(150) DEFAULT NULL,
  `model_digest` varchar(100) DEFAULT NULL,
  `output_json` longtext DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `provider_id` int(10) UNSIGNED DEFAULT NULL,
  `payload_json` longtext DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `claim_token` char(32) DEFAULT NULL,
  `review_remarks` text DEFAULT NULL,
  `provider_config_hash` char(64) DEFAULT NULL,
  `next_attempt_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ai_job` (`owner_user_id`,`task_key`,`input_hash`,`prompt_version`),
  KEY `status` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Ensure columns exist if ai_jobs was created in an earlier partial state
ALTER TABLE `ai_jobs` ADD COLUMN IF NOT EXISTS `provider_id` int(10) UNSIGNED DEFAULT NULL;
ALTER TABLE `ai_jobs` ADD COLUMN IF NOT EXISTS `payload_json` longtext DEFAULT NULL;
ALTER TABLE `ai_jobs` ADD COLUMN IF NOT EXISTS `completed_at` datetime DEFAULT NULL;
ALTER TABLE `ai_jobs` ADD COLUMN IF NOT EXISTS `claim_token` char(32) DEFAULT NULL;
ALTER TABLE `ai_jobs` ADD COLUMN IF NOT EXISTS `review_remarks` text DEFAULT NULL;
ALTER TABLE `ai_jobs` ADD COLUMN IF NOT EXISTS `provider_config_hash` char(64) DEFAULT NULL;
ALTER TABLE `ai_jobs` ADD COLUMN IF NOT EXISTS `next_attempt_at` datetime DEFAULT NULL;

-- 2.5 Table: ai_providers
-- Registered execution provider configurations (disabled globally by default)
CREATE TABLE IF NOT EXISTS `ai_providers` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `kind` enum('OLLAMA','OPENAI_COMPATIBLE') NOT NULL,
  `endpoint` varchar(500) NOT NULL,
  `model` varchar(150) NOT NULL,
  `credential_env` varchar(100) DEFAULT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `timeout_seconds` int(11) NOT NULL DEFAULT 45,
  `updated_by` int(11) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `version` int(10) UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `ai_providers` ADD COLUMN IF NOT EXISTS `version` int(10) UNSIGNED NOT NULL DEFAULT 1;

-- 2.6 Table: attainment_import_batches
-- Batch ledger for imported external attainment calculation payloads
CREATE TABLE IF NOT EXISTS `attainment_import_batches` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `subject_id` int(11) NOT NULL,
  `owner_user_id` int(11) NOT NULL,
  `source_hash` char(64) NOT NULL,
  `policy_hash` char(64) NOT NULL,
  `payload_json` longtext NOT NULL,
  `state` enum('DRAFT','COMMITTED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  `committed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `subject_id` (`subject_id`,`state`),
  KEY `owner_user_id` (`owner_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2.7 Table: attainment_policies
-- Regulation-level attainment threshold configuration matrix
CREATE TABLE IF NOT EXISTS `attainment_policies` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `regulation_id` int(11) NOT NULL,
  `subject_type` varchar(40) NOT NULL,
  `config_json` longtext NOT NULL,
  `version` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `updated_by` int(11) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attainment_policy` (`regulation_id`,`subject_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2.8 Table: certificate_sequences
-- Counter ledger ensuring monotonic increment of serial numbers for issued certificates
CREATE TABLE IF NOT EXISTS `certificate_sequences` (
  `prefix` varchar(80) NOT NULL,
  `last_number` int(10) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2.9 Table: course_cqi_actions
-- Structured Continuous Quality Improvement (CQI) Action Tracking (NBA Criterion 3)
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
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `hod_review_status` enum('PENDING','APPROVED','REVISION_REQUESTED') NOT NULL DEFAULT 'PENDING',
  `hod_remarks` text DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `execution_evidence` text DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `followup_measure` text DEFAULT NULL,
  `closed_by` int(11) DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_cca_sub` (`sub_id`),
  KEY `fk_cca_co` (`co_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `course_cqi_actions` ADD COLUMN IF NOT EXISTS `reviewed_by` int(11) DEFAULT NULL;
ALTER TABLE `course_cqi_actions` ADD COLUMN IF NOT EXISTS `due_date` date DEFAULT NULL;
ALTER TABLE `course_cqi_actions` ADD COLUMN IF NOT EXISTS `execution_evidence` text DEFAULT NULL;
ALTER TABLE `course_cqi_actions` ADD COLUMN IF NOT EXISTS `completed_at` datetime DEFAULT NULL;
ALTER TABLE `course_cqi_actions` ADD COLUMN IF NOT EXISTS `followup_measure` text DEFAULT NULL;
ALTER TABLE `course_cqi_actions` ADD COLUMN IF NOT EXISTS `closed_by` int(11) DEFAULT NULL;
ALTER TABLE `course_cqi_actions` ADD COLUMN IF NOT EXISTS `closed_at` datetime DEFAULT NULL;

-- 2.10 Table: lab_rubric_templates
-- Master rubric evaluation templates for autonomous laboratories
CREATE TABLE IF NOT EXISTS `lab_rubric_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_name` varchar(100) NOT NULL,
  `department_id` int(11) DEFAULT NULL COMMENT 'NULL = Institutional global default',
  `total_max_marks` decimal(4,2) NOT NULL DEFAULT 15.00,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2.11 Table: lab_rubric_criteria
-- Individual criteria benchmarks composing a lab rubric template
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

-- 2.12 Table: lab_subject_rubrics
-- Association mapping lab subjects to rubric templates with criteria customizations
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

-- 2.13 Table: lab_rubric_evaluations
-- Student-level criterion-wise evaluation scores linked to uglab_internal_assessment_marks
CREATE TABLE IF NOT EXISTS `lab_rubric_evaluations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uglab_mark_id` int(11) NOT NULL COMMENT 'Links to uglab_internal_assessment_marks.id',
  `criterion_id` int(11) DEFAULT NULL,
  `criterion_name` varchar(100) NOT NULL,
  `marks_obtained` decimal(4,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lab_evaluation` (`uglab_mark_id`,`criterion_name`),
  KEY `fk_lre_uglab` (`uglab_mark_id`),
  CONSTRAINT `fk_lre_uglab` FOREIGN KEY (`uglab_mark_id`) REFERENCES `uglab_internal_assessment_marks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `lab_rubric_evaluations` ADD UNIQUE INDEX IF NOT EXISTS `uq_lab_evaluation` (`uglab_mark_id`,`criterion_name`);

-- 2.14 Table: report_snapshots
-- Immutable snapshots of verified accreditation reports, dossiers, and gazettes
CREATE TABLE IF NOT EXISTS `report_snapshots` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_key` varchar(50) NOT NULL,
  `owner_user_id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `cutoff_at` datetime NOT NULL,
  `policy_hash` char(64) NOT NULL,
  `metrics_hash` char(64) NOT NULL,
  `scope_json` longtext NOT NULL,
  `metrics_json` longtext NOT NULL,
  `state` enum('DRAFT','REVIEWED','PUBLISHED') NOT NULL DEFAULT 'DRAFT',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `owner_user_id` (`owner_user_id`),
  KEY `department_id` (`department_id`,`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2.15 Table: role_module_permissions
-- Fine-grained role-based permission overrides per module and action
CREATE TABLE IF NOT EXISTS `role_module_permissions` (
  `role` varchar(30) NOT NULL,
  `module_key` varchar(50) NOT NULL,
  `action` varchar(20) NOT NULL,
  `is_allowed` tinyint(1) NOT NULL DEFAULT 0,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`role`,`module_key`,`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2.16 Table: schema_migrations
-- Production migration ledger tracking applied migration versions and sha256 checksums
CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `version` varchar(100) NOT NULL,
  `checksum` char(64) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.17 Table: subject_attainment_overrides
-- Per-subject customized OBE attainment calculation overrides
CREATE TABLE IF NOT EXISTS `subject_attainment_overrides` (
  `subject_id` int(11) NOT NULL,
  `config_json` longtext NOT NULL,
  `version` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `updated_by` int(11) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2.18 Table: workflow_review_history
-- Formal institutional audit log tracking review and approval state transitions
CREATE TABLE IF NOT EXISTS `workflow_review_history` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(40) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `actor_user_id` int(11) NOT NULL,
  `from_status` varchar(30) NOT NULL,
  `to_status` varchar(30) NOT NULL,
  `remarks` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `entity_type` (`entity_type`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================================
-- PART 3: MASTER & REFERENCE DATA SEEDING (IDEMPOTENT)
-- ============================================================================

-- 3.1 Buildings Master Data (Safe seeding if table is unpopulated)
INSERT IGNORE INTO `buildings` (`id`, `building_name`, `status`, `created_at`, `updated_at`) VALUES
(1, 'CCC', 1, '2026-02-02 07:15:30', '2026-02-02 07:15:30'),
(2, 'Department of CIVIL Engg.', 1, '2026-02-02 07:15:30', '2026-02-02 07:15:30'),
(3, 'Department of CSE', 1, '2026-02-02 07:15:30', '2026-02-02 07:15:30'),
(4, 'Department of Chemical Engg.', 1, '2026-02-02 07:15:30', '2026-02-02 07:15:30'),
(5, 'Department of ECE', 1, '2026-02-02 07:15:30', '2026-02-02 07:15:30'),
(6, 'Department of EEE', 1, '2026-02-02 07:15:30', '2026-02-02 07:15:30'),
(7, 'Department of Mechanical Engg.', 1, '2026-02-02 07:15:30', '2026-02-02 07:15:30'),
(8, 'Lecture Hall Complex', 1, '2026-02-02 07:15:30', '2026-02-02 07:15:30'),
(9, 'Library', 1, '2026-02-02 07:15:30', '2026-02-02 07:15:30'),
(10, 'Main Building', 1, '2026-02-02 07:15:30', '2026-02-02 07:15:30'),
(11, 'Science Block', 1, '2026-02-02 07:15:30', '2026-02-02 07:15:30'),
(13, 'College Ground', 1, '2026-02-03 05:50:36', '2026-02-03 05:50:36');

-- 3.2 System Feature Modules Registration (Keys 16 to 28)
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
('MOD_CREDIT_PROMOTION', 'Credit Promotion Pipeline', 'EXAM', 'Optional advisory simulation tool for credit-based promotion rules (R23 Sec 18)', 0, 'HIDDEN', 'HIDDEN', 'HIDDEN', 41),
('MOD_AI_ASSISTANCE', 'Optional AI assistance', 'GENERAL', 'Opt-in drafts using an approved local or API provider; academic decisions remain deterministic.', 0, 'VISIBLE', 'HIDDEN', 'VISIBLE', 90)
ON DUPLICATE KEY UPDATE 
  `module_name` = VALUES(`module_name`),
  `category` = VALUES(`category`),
  `description` = VALUES(`description`),
  `is_enabled_globally` = VALUES(`is_enabled_globally`),
  `faculty_visibility` = VALUES(`faculty_visibility`),
  `student_visibility` = VALUES(`student_visibility`),
  `hod_visibility` = VALUES(`hod_visibility`),
  `sort_order` = VALUES(`sort_order`);

-- 3.3 Centralized Regulatory Settings in `academic_settings`
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

-- 3.4 Seed Default 15-Mark Laboratory Rubric Template & Criteria
INSERT INTO `lab_rubric_templates` (`id`, `template_name`, `department_id`, `total_max_marks`, `is_default`, `created_at`) 
VALUES (1, 'Standard Autonomous Practical Rubric', NULL, 15.00, 1, '2026-10-07 11:30:49')
ON DUPLICATE KEY UPDATE `is_default` = 1, `total_max_marks` = VALUES(`total_max_marks`);

INSERT INTO `lab_rubric_criteria` (`id`, `template_id`, `criterion_name`, `criterion_description`, `max_marks`, `sort_order`) 
VALUES 
(1, 1, 'Conduction & Execution', 'Apparatus setup, procedure execution, circuit wiring, or program logic and execution', 5.00, 1),
(2, 1, 'Observation & Record', 'Data observation accuracy, calculations, graphing, and continuous record maintenance', 5.00, 2),
(3, 1, 'Viva-Voce', 'Conceptual understanding, error analysis, theoretical correlations, and oral defense', 5.00, 3)
ON DUPLICATE KEY UPDATE 
  `criterion_name` = VALUES(`criterion_name`),
  `criterion_description` = VALUES(`criterion_description`),
  `max_marks` = VALUES(`max_marks`),
  `sort_order` = VALUES(`sort_order`);

-- 3.5 Schema Migrations Ledger Entries (Synchronizing with migration runner)
INSERT INTO `schema_migrations` (`version`, `checksum`, `applied_at`) VALUES
('baseline_20261007_accreditation.sql', '75acfb11768d34311c2a45ef66c1f218bf7d867e6feb81d0e63da0ef9dd47626', CURRENT_TIMESTAMP),
('20261008_001_trusted_core.sql', '9e2889357f665ace4d8cc4769afa1657aef9bce8e0f21a573da30e396ba8209f', CURRENT_TIMESTAMP),
('20261008_002_document_lifecycle.sql', '21a0b1339efefd4892396d037a566eca30c4bda314269ccdf339ada3f74ffb1a', CURRENT_TIMESTAMP),
('20261008_003_attainment_integrations.sql', '5d056c406face778f6a5efc823063089828297ddd921cd8b07ee5096551c486a', CURRENT_TIMESTAMP),
('20261008_004_ai_queue_guards.sql', 'fe59a517ab6e5bfaacbe4dce07a1b77f3afd5d7186fa63fa12586e452e696614', CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE `checksum` = VALUES(`checksum`);

SET FOREIGN_KEY_CHECKS = 1;
