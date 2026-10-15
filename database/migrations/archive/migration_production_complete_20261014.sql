/*
 * Complete production upgrade for u182589698_jntuaceasarb_server_131026.sql.
 * File: migration_production_complete_20261014.sql
 * Supported syntax: MySQL 8.0+ and MariaDB 10.4+; phpMyAdmin or mysql client.
 * Select the target database before importing. No USE or credentials are embedded.
 * Take a fresh backup; run with application writes paused and stop on SQL errors.
 * DDL commits implicitly: this is resumable, not an all-or-nothing transaction.
 * Covers October 5 CO backfill/index repair and October 7-13 pending migrations.
 * October 6 regulation/schema/seeds already exist in the supplied baseline and
 * are deliberately not reset. This is not an installer for older/empty schemas.
 * Existing feature switches, settings, rubric definitions, and nonzero faculty
 * orders are retained. No academic rows are deleted and FK checks stay enabled.
 * Uses metadata guards instead of MariaDB-only ADD COLUMN/INDEX IF NOT EXISTS.
 * No stored procedures, DELIMITER commands or CREATE ROUTINE privilege required.
 * Reimport this file after a partial failure; do not import older alternatives.
 */

SET @cea_old_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = CONCAT_WS(',', NULLIF(@@SESSION.sql_mode, ''), 'STRICT_ALL_TABLES');
SET NAMES utf8mb4 COLLATE utf8mb4_general_ci;
SELECT DATABASE() AS target_database, VERSION() AS server_version;

SET @cea_sql = IF(DATABASE() IS NOT NULL,
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_select_target_database_first`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'academic_settings'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_required_table_academic_settings_missing`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'regulations'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_required_table_regulations_missing`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'regulation_course_categories'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_required_table_regulation_course_categories_missing`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'regulation_course_types'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_required_table_regulation_course_types_missing`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'curriculum_subjects'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_required_table_curriculum_subjects_missing`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_outcomes'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_required_table_course_outcomes_missing`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'co_po_mapping'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_required_table_co_po_mapping_missing`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subjects'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_required_table_subjects_missing`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assessment_questions'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_required_table_assessment_questions_missing`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_completion_audits'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_required_table_course_completion_audits_missing`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_notifications'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_required_table_exam_notifications_missing`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_results'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_required_table_exam_results_missing`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'external_assessment_marks'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_required_table_external_assessment_marks_missing`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_certificate_requests'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_required_table_student_certificate_requests_missing`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_documents'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_required_table_student_documents_missing`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_marks'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_required_table_student_marks_missing`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'system_feature_modules'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_required_table_system_feature_modules_missing`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'uglab_internal_assessment_marks'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_required_table_uglab_internal_assessment_marks_missing`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'faculties'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_required_table_faculties_missing`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_required_table_users_missing`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'academic_settings' AND COLUMN_NAME = 'reg_id'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_october6_baseline_academic_settings_reg_id_missing`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'regulations' AND COLUMN_NAME = 'normal_duration_years'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_october6_baseline_regulations_normal_duration_years_mi`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'curriculum_subjects' AND COLUMN_NAME = 'cie_max_marks'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_october6_baseline_curriculum_subjects_cie_max_marks_mi`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subjects' AND COLUMN_NAME = 'curr_sub_id'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_october6_baseline_subjects_curr_sub_id_missing`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_outcomes' AND COLUMN_NAME = 'curr_sub_id'),
    'SET @cea_duplicate_masters = (SELECT COUNT(*) FROM (SELECT curr_sub_id, co_number FROM course_outcomes WHERE sub_id IS NULL AND curr_sub_id IS NOT NULL GROUP BY curr_sub_id, co_number HAVING COUNT(*) > 1) duplicate_master_keys)',
    'SET @cea_duplicate_masters = 0');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(@cea_duplicate_masters = 0,
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_duplicate_master_course_outcomes`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

/* Check existing migration checksums before changing application tables. */

CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `version` varchar(100) NOT NULL,
  `checksum` char(64) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM schema_migrations WHERE version = 'baseline_20261007_accreditation.sql' AND checksum <> '75acfb11768d34311c2a45ef66c1f218bf7d867e6feb81d0e63da0ef9dd47626'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_migration_checksum_conflict_check_schema_migrations`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM schema_migrations WHERE version = '20261008_001_trusted_core.sql' AND checksum <> '9e2889357f665ace4d8cc4769afa1657aef9bce8e0f21a573da30e396ba8209f'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_migration_checksum_conflict_check_schema_migrations`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM schema_migrations WHERE version = '20261008_002_document_lifecycle.sql' AND checksum <> '21a0b1339efefd4892396d037a566eca30c4bda314269ccdf339ada3f74ffb1a'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_migration_checksum_conflict_check_schema_migrations`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM schema_migrations WHERE version = '20261008_003_attainment_integrations.sql' AND checksum <> '5d056c406face778f6a5efc823063089828297ddd921cd8b07ee5096551c486a'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_migration_checksum_conflict_check_schema_migrations`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM schema_migrations WHERE version = '20261008_004_ai_queue_guards.sql' AND checksum <> 'fe59a517ab6e5bfaacbe4dce07a1b77f3afd5d7186fa63fa12586e452e696614'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_migration_checksum_conflict_check_schema_migrations`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM schema_migrations WHERE version = '20261013_001_faculty_sort_order.sql' AND checksum <> '5b29ef91f9fe1bde56fad49ebce1854febc54f57ef02ca4f1e9ed72e0f0715d6'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_migration_checksum_conflict_check_schema_migrations`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

/* 1. Create accreditation and integration tables before extending them. */

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

CREATE TABLE IF NOT EXISTS `ai_job_consents` (
  `user_id` int(11) NOT NULL,
  `task_key` varchar(50) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`,`task_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE IF NOT EXISTS `certificate_sequences` (
  `prefix` varchar(80) NOT NULL,
  `last_number` int(10) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE IF NOT EXISTS `lab_rubric_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_name` varchar(100) NOT NULL,
  `department_id` int(11) DEFAULT NULL COMMENT 'NULL = Institutional global default',
  `total_max_marks` decimal(4,2) NOT NULL DEFAULT 15.00,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lab_evaluation` (`uglab_mark_id`,`criterion_name`),
  KEY `fk_lre_uglab` (`uglab_mark_id`),
  CONSTRAINT `fk_lre_uglab` FOREIGN KEY (`uglab_mark_id`) REFERENCES `uglab_internal_assessment_marks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE IF NOT EXISTS `role_module_permissions` (
  `role` varchar(30) NOT NULL,
  `module_key` varchar(50) NOT NULL,
  `action` varchar(20) NOT NULL,
  `is_allowed` tinyint(1) NOT NULL DEFAULT 0,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`role`,`module_key`,`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `version` varchar(100) NOT NULL,
  `checksum` char(64) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `subject_attainment_overrides` (
  `subject_id` int(11) NOT NULL,
  `config_json` longtext NOT NULL,
  `version` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `updated_by` int(11) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

/* 2. Extend existing and partially migrated tables without duplicate columns. */

SET @cea_numeric_type = (SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assessment_questions' AND COLUMN_NAME = 'marks');
SET @cea_sql = IF(@cea_numeric_type IN ('decimal','tinyint','smallint','mediumint','int','bigint'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_numeric_column_type_assessment_questions_marks`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;
SET @cea_numeric_scale = (SELECT NUMERIC_SCALE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assessment_questions' AND COLUMN_NAME = 'marks');
SET @cea_numeric_precision = (SELECT NUMERIC_PRECISION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assessment_questions' AND COLUMN_NAME = 'marks');
SET @cea_new_scale = GREATEST(COALESCE(@cea_numeric_scale, 0), 5);
SET @cea_new_precision = GREATEST(COALESCE(@cea_numeric_precision - @cea_numeric_scale, 0), 7) + @cea_new_scale;
SET @cea_sql = IF(@cea_new_precision <= 65 AND @cea_new_scale <= IF(LOCATE('MariaDB', VERSION()) > 0, 38, 30),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_decimal_capacity_limit_assessment_questions_marks`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;
SET @cea_sql = IF(@cea_numeric_type = 'decimal' AND @cea_numeric_scale >= 5 AND @cea_numeric_precision - @cea_numeric_scale >= 7, 'SET @cea_noop = 1', CONCAT('ALTER TABLE `assessment_questions` MODIFY COLUMN `marks` decimal(', @cea_new_precision, ',', @cea_new_scale, ') NOT NULL'));
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_completion_audits' AND COLUMN_NAME = 'hod_reviewed_by'),
    'ALTER TABLE `course_completion_audits` ADD COLUMN `hod_reviewed_by` int(11) DEFAULT NULL',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_notifications' AND COLUMN_NAME = 'workflow_status'),
    'ALTER TABLE `exam_notifications` ADD COLUMN `workflow_status` enum(''DRAFT'',''REVIEWED'',''PUBLISHED'') NOT NULL DEFAULT ''DRAFT''',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_notifications' AND COLUMN_NAME = 'reviewed_by'),
    'ALTER TABLE `exam_notifications` ADD COLUMN `reviewed_by` int(11) DEFAULT NULL',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_notifications' AND COLUMN_NAME = 'reviewed_at'),
    'ALTER TABLE `exam_notifications` ADD COLUMN `reviewed_at` datetime DEFAULT NULL',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_notifications' AND COLUMN_NAME = 'exam_type'),
    'ALTER TABLE `exam_notifications` ADD COLUMN `exam_type` enum(''REGULAR'',''SUPPLEMENTARY'',''REVALUATION'') NOT NULL DEFAULT ''REGULAR''',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_numeric_type = (SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_results' AND COLUMN_NAME = 'grade_points');
SET @cea_sql = IF(@cea_numeric_type IN ('decimal','tinyint','smallint','mediumint','int','bigint'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_numeric_column_type_exam_results_grade_points`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;
SET @cea_numeric_scale = (SELECT NUMERIC_SCALE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_results' AND COLUMN_NAME = 'grade_points');
SET @cea_numeric_precision = (SELECT NUMERIC_PRECISION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_results' AND COLUMN_NAME = 'grade_points');
SET @cea_new_scale = GREATEST(COALESCE(@cea_numeric_scale, 0), 3);
SET @cea_new_precision = GREATEST(COALESCE(@cea_numeric_precision - @cea_numeric_scale, 0), 5) + @cea_new_scale;
SET @cea_sql = IF(@cea_new_precision <= 65 AND @cea_new_scale <= IF(LOCATE('MariaDB', VERSION()) > 0, 38, 30),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_decimal_capacity_limit_exam_results_grade_points`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;
SET @cea_sql = IF(@cea_numeric_type = 'decimal' AND @cea_numeric_scale >= 3 AND @cea_numeric_precision - @cea_numeric_scale >= 5, 'SET @cea_noop = 1', CONCAT('ALTER TABLE `exam_results` MODIFY COLUMN `grade_points` decimal(', @cea_new_precision, ',', @cea_new_scale, ') DEFAULT NULL'));
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'external_assessment_marks' AND COLUMN_NAME = 'source_notification_id'),
    'ALTER TABLE `external_assessment_marks` ADD COLUMN `source_notification_id` int(11) DEFAULT NULL',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'external_assessment_marks' AND COLUMN_NAME = 'raw_marks'),
    'ALTER TABLE `external_assessment_marks` ADD COLUMN `raw_marks` decimal(12,5) DEFAULT NULL',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'external_assessment_marks' AND COLUMN_NAME = 'raw_max_marks'),
    'ALTER TABLE `external_assessment_marks` ADD COLUMN `raw_max_marks` decimal(12,5) DEFAULT NULL',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'external_assessment_marks' AND COLUMN_NAME = 'normalisation_json'),
    'ALTER TABLE `external_assessment_marks` ADD COLUMN `normalisation_json` longtext DEFAULT NULL',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'external_assessment_marks' AND COLUMN_NAME = 'source_batch_id'),
    'ALTER TABLE `external_assessment_marks` ADD COLUMN `source_batch_id` bigint(20) UNSIGNED DEFAULT NULL',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'external_assessment_marks' AND COLUMN_NAME = 'evidence_basis'),
    'ALTER TABLE `external_assessment_marks` ADD COLUMN `evidence_basis` varchar(60) NOT NULL DEFAULT ''LEGACY''',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'external_assessment_marks' AND COLUMN_NAME = 'is_current'),
    'ALTER TABLE `external_assessment_marks` ADD COLUMN `is_current` tinyint(1) NOT NULL DEFAULT 1',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_numeric_type = (SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'external_assessment_marks' AND COLUMN_NAME = 'external_marks');
SET @cea_sql = IF(@cea_numeric_type IN ('decimal','tinyint','smallint','mediumint','int','bigint'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_numeric_column_type_external_assessment_marks_external`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;
SET @cea_numeric_scale = (SELECT NUMERIC_SCALE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'external_assessment_marks' AND COLUMN_NAME = 'external_marks');
SET @cea_numeric_precision = (SELECT NUMERIC_PRECISION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'external_assessment_marks' AND COLUMN_NAME = 'external_marks');
SET @cea_new_scale = GREATEST(COALESCE(@cea_numeric_scale, 0), 5);
SET @cea_new_precision = GREATEST(COALESCE(@cea_numeric_precision - @cea_numeric_scale, 0), 7) + @cea_new_scale;
SET @cea_sql = IF(@cea_new_precision <= 65 AND @cea_new_scale <= IF(LOCATE('MariaDB', VERSION()) > 0, 38, 30),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_decimal_capacity_limit_external_assessment_marks_exter`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;
SET @cea_sql = IF(@cea_numeric_type = 'decimal' AND @cea_numeric_scale >= 5 AND @cea_numeric_precision - @cea_numeric_scale >= 7, 'SET @cea_noop = 1', CONCAT('ALTER TABLE `external_assessment_marks` MODIFY COLUMN `external_marks` decimal(', @cea_new_precision, ',', @cea_new_scale, ') NOT NULL'));
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_numeric_type = (SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'external_assessment_marks' AND COLUMN_NAME = 'max_marks');
SET @cea_sql = IF(@cea_numeric_type IN ('decimal','tinyint','smallint','mediumint','int','bigint'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_numeric_column_type_external_assessment_marks_max_mark`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;
SET @cea_numeric_scale = (SELECT NUMERIC_SCALE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'external_assessment_marks' AND COLUMN_NAME = 'max_marks');
SET @cea_numeric_precision = (SELECT NUMERIC_PRECISION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'external_assessment_marks' AND COLUMN_NAME = 'max_marks');
SET @cea_new_scale = GREATEST(COALESCE(@cea_numeric_scale, 0), 5);
SET @cea_new_precision = GREATEST(COALESCE(@cea_numeric_precision - @cea_numeric_scale, 0), 7) + @cea_new_scale;
SET @cea_sql = IF(@cea_new_precision <= 65 AND @cea_new_scale <= IF(LOCATE('MariaDB', VERSION()) > 0, 38, 30),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_decimal_capacity_limit_external_assessment_marks_max_m`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;
SET @cea_sql = IF(@cea_numeric_type = 'decimal' AND @cea_numeric_scale >= 5 AND @cea_numeric_precision - @cea_numeric_scale >= 7, 'SET @cea_noop = 1', CONCAT('ALTER TABLE `external_assessment_marks` MODIFY COLUMN `max_marks` decimal(', @cea_new_precision, ',', @cea_new_scale, ') NOT NULL DEFAULT 70.00000'));
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_certificate_requests' AND COLUMN_NAME = 'verification_token'),
    'ALTER TABLE `student_certificate_requests` ADD COLUMN `verification_token` char(64) DEFAULT NULL',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_index_ok = COALESCE((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') = 'verification_token' AND MIN(NON_UNIQUE) <= 0 AND COUNT(SUB_PART) = 0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_certificate_requests' AND INDEX_NAME = 'uq_certificate_verification_token'), 0);
SET @cea_index_exists = EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_certificate_requests' AND INDEX_NAME = 'uq_certificate_verification_token');
SET @cea_sql = IF(@cea_index_ok, 'SET @cea_noop = 1',
    IF(@cea_index_exists, 'ALTER TABLE `student_certificate_requests` DROP INDEX `uq_certificate_verification_token`, ADD UNIQUE INDEX `uq_certificate_verification_token` (`verification_token`)',
        'ALTER TABLE `student_certificate_requests` ADD UNIQUE INDEX `uq_certificate_verification_token` (`verification_token`)'));
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_documents' AND COLUMN_NAME = 'deleted_at'),
    'ALTER TABLE `student_documents` ADD COLUMN `deleted_at` datetime DEFAULT NULL',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_documents' AND COLUMN_NAME = 'deleted_by'),
    'ALTER TABLE `student_documents` ADD COLUMN `deleted_by` int(11) DEFAULT NULL',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_numeric_type = (SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_marks' AND COLUMN_NAME = 'marks_obtained');
SET @cea_sql = IF(@cea_numeric_type IN ('decimal','tinyint','smallint','mediumint','int','bigint'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_numeric_column_type_student_marks_marks_obtained`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;
SET @cea_numeric_scale = (SELECT NUMERIC_SCALE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_marks' AND COLUMN_NAME = 'marks_obtained');
SET @cea_numeric_precision = (SELECT NUMERIC_PRECISION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_marks' AND COLUMN_NAME = 'marks_obtained');
SET @cea_new_scale = GREATEST(COALESCE(@cea_numeric_scale, 0), 5);
SET @cea_new_precision = GREATEST(COALESCE(@cea_numeric_precision - @cea_numeric_scale, 0), 7) + @cea_new_scale;
SET @cea_sql = IF(@cea_new_precision <= 65 AND @cea_new_scale <= IF(LOCATE('MariaDB', VERSION()) > 0, 38, 30),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_decimal_capacity_limit_student_marks_marks_obtained`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;
SET @cea_sql = IF(@cea_numeric_type = 'decimal' AND @cea_numeric_scale >= 5 AND @cea_numeric_precision - @cea_numeric_scale >= 7, 'SET @cea_noop = 1', CONCAT('ALTER TABLE `student_marks` MODIFY COLUMN `marks_obtained` decimal(', @cea_new_precision, ',', @cea_new_scale, ') NOT NULL'));
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ai_jobs' AND COLUMN_NAME = 'provider_id'),
    'ALTER TABLE `ai_jobs` ADD COLUMN `provider_id` int(10) UNSIGNED DEFAULT NULL',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ai_jobs' AND COLUMN_NAME = 'payload_json'),
    'ALTER TABLE `ai_jobs` ADD COLUMN `payload_json` longtext DEFAULT NULL',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ai_jobs' AND COLUMN_NAME = 'completed_at'),
    'ALTER TABLE `ai_jobs` ADD COLUMN `completed_at` datetime DEFAULT NULL',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ai_jobs' AND COLUMN_NAME = 'claim_token'),
    'ALTER TABLE `ai_jobs` ADD COLUMN `claim_token` char(32) DEFAULT NULL',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ai_jobs' AND COLUMN_NAME = 'review_remarks'),
    'ALTER TABLE `ai_jobs` ADD COLUMN `review_remarks` text DEFAULT NULL',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ai_jobs' AND COLUMN_NAME = 'provider_config_hash'),
    'ALTER TABLE `ai_jobs` ADD COLUMN `provider_config_hash` char(64) DEFAULT NULL',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ai_jobs' AND COLUMN_NAME = 'next_attempt_at'),
    'ALTER TABLE `ai_jobs` ADD COLUMN `next_attempt_at` datetime DEFAULT NULL',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ai_providers' AND COLUMN_NAME = 'version'),
    'ALTER TABLE `ai_providers` ADD COLUMN `version` int(10) UNSIGNED NOT NULL DEFAULT 1',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_cqi_actions' AND COLUMN_NAME = 'reviewed_by'),
    'ALTER TABLE `course_cqi_actions` ADD COLUMN `reviewed_by` int(11) DEFAULT NULL',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_cqi_actions' AND COLUMN_NAME = 'due_date'),
    'ALTER TABLE `course_cqi_actions` ADD COLUMN `due_date` date DEFAULT NULL',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_cqi_actions' AND COLUMN_NAME = 'execution_evidence'),
    'ALTER TABLE `course_cqi_actions` ADD COLUMN `execution_evidence` text DEFAULT NULL',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_cqi_actions' AND COLUMN_NAME = 'completed_at'),
    'ALTER TABLE `course_cqi_actions` ADD COLUMN `completed_at` datetime DEFAULT NULL',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_cqi_actions' AND COLUMN_NAME = 'followup_measure'),
    'ALTER TABLE `course_cqi_actions` ADD COLUMN `followup_measure` text DEFAULT NULL',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_cqi_actions' AND COLUMN_NAME = 'closed_by'),
    'ALTER TABLE `course_cqi_actions` ADD COLUMN `closed_by` int(11) DEFAULT NULL',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_cqi_actions' AND COLUMN_NAME = 'closed_at'),
    'ALTER TABLE `course_cqi_actions` ADD COLUMN `closed_at` datetime DEFAULT NULL',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_index_ok = COALESCE((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') = 'uglab_mark_id,criterion_name' AND MIN(NON_UNIQUE) <= 0 AND COUNT(SUB_PART) = 0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lab_rubric_evaluations' AND INDEX_NAME = 'uq_lab_evaluation'), 0);
SET @cea_index_exists = EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lab_rubric_evaluations' AND INDEX_NAME = 'uq_lab_evaluation');
SET @cea_sql = IF(@cea_index_ok, 'SET @cea_noop = 1',
    IF(@cea_index_exists, 'ALTER TABLE `lab_rubric_evaluations` DROP INDEX `uq_lab_evaluation`, ADD UNIQUE INDEX `uq_lab_evaluation` (`uglab_mark_id`, `criterion_name`)',
        'ALTER TABLE `lab_rubric_evaluations` ADD UNIQUE INDEX `uq_lab_evaluation` (`uglab_mark_id`, `criterion_name`)'));
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

/* 3. Result status, evidence length and lookup indexes (October 9). */

SET @cea_result_type = (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_results' AND COLUMN_NAME = 'result_status');

SET @cea_sql = IF(@cea_result_type LIKE 'enum(%',
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_exam_results_result_status_must_be_enum`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(LOCATE('''MALPRACTICE''', @cea_result_type) > 0, 'SET @cea_noop = 1', CONCAT('ALTER TABLE `exam_results` MODIFY COLUMN `result_status` ', LEFT(@cea_result_type, CHAR_LENGTH(@cea_result_type) - 1), ',''MALPRACTICE'') NOT NULL DEFAULT ''PASS'''));
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'external_assessment_marks' AND COLUMN_NAME = 'evidence_basis' AND CHARACTER_MAXIMUM_LENGTH < 60),
    'ALTER TABLE `external_assessment_marks` MODIFY COLUMN `evidence_basis` varchar(60) NOT NULL DEFAULT ''LEGACY''',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_index_ok = COALESCE((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') = 'student_id,subject_id' AND MIN(NON_UNIQUE) <= 1 AND COUNT(SUB_PART) = 0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_results' AND INDEX_NAME = 'idx_er_student_subject'), 0);
SET @cea_sql = IF(@cea_index_ok OR NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_results' AND INDEX_NAME = 'idx_er_student_subject' AND NON_UNIQUE = 0),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_unique_index_conflict_exam_results_idx_er_student_subj`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;
SET @cea_index_exists = EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_results' AND INDEX_NAME = 'idx_er_student_subject');
SET @cea_sql = IF(@cea_index_ok, 'SET @cea_noop = 1',
    IF(@cea_index_exists, 'ALTER TABLE `exam_results` DROP INDEX `idx_er_student_subject`, ADD INDEX `idx_er_student_subject` (`student_id`, `subject_id`)',
        'ALTER TABLE `exam_results` ADD INDEX `idx_er_student_subject` (`student_id`, `subject_id`)'));
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_index_ok = COALESCE((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') = 'htno,subject_code' AND MIN(NON_UNIQUE) <= 1 AND COUNT(SUB_PART) = 0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_results' AND INDEX_NAME = 'idx_er_htno_subcode'), 0);
SET @cea_sql = IF(@cea_index_ok OR NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_results' AND INDEX_NAME = 'idx_er_htno_subcode' AND NON_UNIQUE = 0),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_unique_index_conflict_exam_results_idx_er_htno_subcode`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;
SET @cea_index_exists = EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_results' AND INDEX_NAME = 'idx_er_htno_subcode');
SET @cea_sql = IF(@cea_index_ok, 'SET @cea_noop = 1',
    IF(@cea_index_exists, 'ALTER TABLE `exam_results` DROP INDEX `idx_er_htno_subcode`, ADD INDEX `idx_er_htno_subcode` (`htno`, `subject_code`)',
        'ALTER TABLE `exam_results` ADD INDEX `idx_er_htno_subcode` (`htno`, `subject_code`)'));
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

/* 4. Faculty ordering: preserve every nonzero custom order (October 13). */

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'faculties' AND COLUMN_NAME = 'sort_order'),
    'ALTER TABLE `faculties` ADD COLUMN `sort_order` INT(11) NOT NULL DEFAULT 0 AFTER `dept_id`',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_index_ok = COALESCE((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') = 'dept_id,sort_order' AND MIN(NON_UNIQUE) <= 1 AND COUNT(SUB_PART) = 0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'faculties' AND INDEX_NAME = 'idx_faculties_dept_sort'), 0);
SET @cea_sql = IF(@cea_index_ok OR NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'faculties' AND INDEX_NAME = 'idx_faculties_dept_sort' AND NON_UNIQUE = 0),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_unique_index_conflict_faculties_idx_faculties_dept_sor`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;
SET @cea_index_exists = EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'faculties' AND INDEX_NAME = 'idx_faculties_dept_sort');
SET @cea_sql = IF(@cea_index_ok, 'SET @cea_noop = 1',
    IF(@cea_index_exists, 'ALTER TABLE `faculties` DROP INDEX `idx_faculties_dept_sort`, ADD INDEX `idx_faculties_dept_sort` (`dept_id`, `sort_order`)',
        'ALTER TABLE `faculties` ADD INDEX `idx_faculties_dept_sort` (`dept_id`, `sort_order`)'));
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

DROP TEMPORARY TABLE IF EXISTS cea_migration_faculty_orders;
CREATE TEMPORARY TABLE cea_migration_faculty_orders (
    id int NOT NULL PRIMARY KEY, initial_order int NOT NULL
) ENGINE=InnoDB;
INSERT INTO cea_migration_faculty_orders (id, initial_order)
SELECT f.id,
       COALESCE((SELECT MAX(existing.sort_order) FROM faculties existing WHERE existing.dept_id = f.dept_id), 0)
       + ROW_NUMBER() OVER (
           PARTITION BY f.dept_id
           ORDER BY COALESCE(u.status, 0) DESC,
                    FIELD(f.designation, 'Professor', 'Assoc. Professor', 'Asst. Professor', 'Asst. Professor (Adhoc)', 'Guest Faculty'),
                    f.id
       )
FROM faculties f
LEFT JOIN users u ON u.username = f.username
WHERE f.sort_order = 0;
UPDATE faculties f
JOIN cea_migration_faculty_orders ranked ON ranked.id = f.id
SET f.sort_order = ranked.initial_order
WHERE f.sort_order = 0;
DROP TEMPORARY TABLE cea_migration_faculty_orders;

/* 5. October 5 CO master/sibling backfill and corrected composite index. */

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_outcomes' AND COLUMN_NAME = 'curr_sub_id'),
    'ALTER TABLE `course_outcomes` ADD COLUMN `curr_sub_id` int(11) NULL DEFAULT NULL AFTER `co_description`',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_sql = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_outcomes' AND COLUMN_NAME = 'sub_id' AND IS_NULLABLE = 'NO'),
    'ALTER TABLE `course_outcomes` MODIFY COLUMN `sub_id` int(11) NULL DEFAULT NULL',
    'SET @cea_noop = 1');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_index_ok = COALESCE((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') = 'curr_sub_id' AND MIN(NON_UNIQUE) <= 1 AND COUNT(SUB_PART) = 0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_outcomes' AND INDEX_NAME = 'idx_co_curr_sub'), 0);
SET @cea_sql = IF(@cea_index_ok OR NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_outcomes' AND INDEX_NAME = 'idx_co_curr_sub' AND NON_UNIQUE = 0),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_unique_index_conflict_course_outcomes_idx_co_curr_sub`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;
SET @cea_index_exists = EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_outcomes' AND INDEX_NAME = 'idx_co_curr_sub');
SET @cea_sql = IF(@cea_index_ok, 'SET @cea_noop = 1',
    IF(@cea_index_exists, 'ALTER TABLE `course_outcomes` DROP INDEX `idx_co_curr_sub`, ADD INDEX `idx_co_curr_sub` (`curr_sub_id`)',
        'ALTER TABLE `course_outcomes` ADD INDEX `idx_co_curr_sub` (`curr_sub_id`)'));
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

SET @cea_index_ok = COALESCE((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') = 'curr_sub_id,co_number' AND MIN(NON_UNIQUE) <= 1 AND COUNT(SUB_PART) = 0 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_outcomes' AND INDEX_NAME = 'idx_co_curr_num'), 0);
SET @cea_sql = IF(@cea_index_ok OR NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_outcomes' AND INDEX_NAME = 'idx_co_curr_num' AND NON_UNIQUE = 0),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_unique_index_conflict_course_outcomes_idx_co_curr_num`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;
SET @cea_index_exists = EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_outcomes' AND INDEX_NAME = 'idx_co_curr_num');
SET @cea_sql = IF(@cea_index_ok, 'SET @cea_noop = 1',
    IF(@cea_index_exists, 'ALTER TABLE `course_outcomes` DROP INDEX `idx_co_curr_num`, ADD INDEX `idx_co_curr_num` (`curr_sub_id`, `co_number`)',
        'ALTER TABLE `course_outcomes` ADD INDEX `idx_co_curr_num` (`curr_sub_id`, `co_number`)'));
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

START TRANSACTION;

UPDATE course_outcomes co
JOIN subjects s ON s.id = co.sub_id
SET co.curr_sub_id = s.curr_sub_id
WHERE co.curr_sub_id IS NULL AND s.curr_sub_id IS NOT NULL;

INSERT INTO `course_outcomes` (`curr_sub_id`, `sub_id`, `co_number`, `co_description`, `bloom_level`, `target_threshold_percent`)
SELECT 
    s.curr_sub_id,
    NULL AS sub_id,
    co.co_number,
    co.co_description,
    co.bloom_level,
    co.target_threshold_percent
FROM `course_outcomes` co
JOIN `subjects` s ON co.sub_id = s.id
JOIN (

    SELECT s2.curr_sub_id, co2.co_number, MAX(co2.id) AS max_co_id
    FROM `course_outcomes` co2
    JOIN `subjects` s2 ON co2.sub_id = s2.id
    WHERE s2.curr_sub_id IS NOT NULL
    GROUP BY s2.curr_sub_id, co2.co_number
) best ON co.id = best.max_co_id
LEFT JOIN `course_outcomes` existing_master 
    ON existing_master.curr_sub_id = s.curr_sub_id 
   AND existing_master.co_number = co.co_number 
   AND existing_master.sub_id IS NULL
WHERE existing_master.id IS NULL;

INSERT INTO `co_po_mapping` (`co_id`, `po_id`, `weightage`)
SELECT 
    m_co.id AS co_id,
    cpm.po_id,
    ROUND(AVG(cpm.weightage)) AS weightage
FROM `co_po_mapping` cpm
JOIN `course_outcomes` o_co ON cpm.co_id = o_co.id
JOIN `subjects` s ON o_co.sub_id = s.id
JOIN `course_outcomes` m_co 
    ON m_co.curr_sub_id = s.curr_sub_id 
   AND m_co.co_number = o_co.co_number 
   AND m_co.sub_id IS NULL
LEFT JOIN `co_po_mapping` existing_map 
    ON existing_map.co_id = m_co.id 
   AND existing_map.po_id = cpm.po_id
WHERE existing_map.id IS NULL
GROUP BY m_co.id, cpm.po_id;

INSERT INTO `course_outcomes` (`sub_id`, `curr_sub_id`, `co_number`, `co_description`, `bloom_level`, `target_threshold_percent`)
SELECT 
    s.id AS sub_id,
    m_co.curr_sub_id,
    m_co.co_number,
    m_co.co_description,
    m_co.bloom_level,
    m_co.target_threshold_percent
FROM `subjects` s
JOIN `course_outcomes` m_co 
    ON s.curr_sub_id = m_co.curr_sub_id 
   AND m_co.sub_id IS NULL
LEFT JOIN `course_outcomes` existing_off_co 
    ON existing_off_co.sub_id = s.id 
   AND existing_off_co.co_number = m_co.co_number
WHERE existing_off_co.id IS NULL;

INSERT INTO `co_po_mapping` (`co_id`, `po_id`, `weightage`)
SELECT 
    o_co.id AS co_id,
    m_map.po_id,
    m_map.weightage
FROM `course_outcomes` o_co
JOIN `course_outcomes` m_co 
    ON o_co.curr_sub_id = m_co.curr_sub_id 
   AND o_co.co_number = m_co.co_number 
   AND m_co.sub_id IS NULL
JOIN `co_po_mapping` m_map 
    ON m_map.co_id = m_co.id
LEFT JOIN `co_po_mapping` existing_map 
    ON existing_map.co_id = o_co.id 
   AND existing_map.po_id = m_map.po_id
WHERE o_co.sub_id IS NOT NULL 
  AND existing_map.id IS NULL;

/* 6. Register missing modules and defaults; retain administrator choices. */

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
ON DUPLICATE KEY UPDATE `module_key` = VALUES(`module_key`);

INSERT INTO `academic_settings` (`reg_id`, `regulation_code`, `category`, `setting_key`, `setting_value`, `data_type`, `description`, `is_editable`)
SELECT r.id, r.regulation, 'CIA', 'bloom_hots_lots_enforce', '0', 'BOOL', 'Enforce strict 40% minimum HOTS (L4-L6) marks during CIA question entry (0=Advisory, 1=Strict)', 1
FROM regulations r WHERE r.is_active = 1

ON DUPLICATE KEY UPDATE `setting_key` = VALUES(`setting_key`);

INSERT INTO `academic_settings` (`reg_id`, `regulation_code`, `category`, `setting_key`, `setting_value`, `data_type`, `description`, `is_editable`)
SELECT r.id, r.regulation, 'GENERAL', 'daac_audit_variance_threshold_pct', '15.00', 'FLOAT', 'Syllabus lag percentage triggering DAAC mid-semester audit alert', 1
FROM regulations r WHERE r.is_active = 1

ON DUPLICATE KEY UPDATE `setting_key` = VALUES(`setting_key`);

INSERT INTO `academic_settings` (`reg_id`, `regulation_code`, `category`, `setting_key`, `setting_value`, `data_type`, `description`, `is_editable`)
SELECT r.id, r.regulation, 'GENERAL', 'diary_lag_alert_threshold', '3', 'INT', 'Number of lectures diary entries lag planned topics before advisory alert', 1
FROM regulations r WHERE r.is_active = 1

ON DUPLICATE KEY UPDATE `setting_key` = VALUES(`setting_key`);

INSERT INTO lab_rubric_templates (template_name, department_id, total_max_marks, is_default)
SELECT 'Standard Autonomous Practical Rubric', NULL, 15.00, 1
FROM (SELECT 1 AS seed) seed
LEFT JOIN lab_rubric_templates existing ON existing.template_name = 'Standard Autonomous Practical Rubric' AND existing.department_id IS NULL
WHERE existing.id IS NULL;
SET @cea_rubric_template_id = (SELECT MIN(id) FROM lab_rubric_templates WHERE template_name = 'Standard Autonomous Practical Rubric' AND department_id IS NULL);

INSERT INTO lab_rubric_criteria (template_id, criterion_name, criterion_description, max_marks, sort_order)
SELECT @cea_rubric_template_id, 'Conduction & Execution', 'Apparatus setup, procedure execution, circuit wiring, or program logic and execution', 5.00, 1
FROM (SELECT 1 AS seed) seed
LEFT JOIN lab_rubric_criteria existing ON existing.template_id = @cea_rubric_template_id AND existing.criterion_name = 'Conduction & Execution'
WHERE existing.id IS NULL;

INSERT INTO lab_rubric_criteria (template_id, criterion_name, criterion_description, max_marks, sort_order)
SELECT @cea_rubric_template_id, 'Observation & Record', 'Data observation accuracy, calculations, graphing, and continuous record maintenance', 5.00, 2
FROM (SELECT 1 AS seed) seed
LEFT JOIN lab_rubric_criteria existing ON existing.template_id = @cea_rubric_template_id AND existing.criterion_name = 'Observation & Record'
WHERE existing.id IS NULL;

INSERT INTO lab_rubric_criteria (template_id, criterion_name, criterion_description, max_marks, sort_order)
SELECT @cea_rubric_template_id, 'Viva-Voce', 'Conceptual understanding, error analysis, theoretical correlations, and oral defense', 5.00, 3
FROM (SELECT 1 AS seed) seed
LEFT JOIN lab_rubric_criteria existing ON existing.template_id = @cea_rubric_template_id AND existing.criterion_name = 'Viva-Voce'
WHERE existing.id IS NULL;

UPDATE `system_feature_modules` SET `description` = 'Semester result CSV batch import matching legacy/university formats, SGPA/CGPA calculation, subject-wise and ledger student portal grade view, CIA vs exam internal discrepancy reconciliation audit, and 3-tier SEE CO attainment auto-sync.' WHERE `module_key` = 'MOD_RESULTS_PUBLISH';

/* Verify completeness before recording migration versions. */

DROP TEMPORARY TABLE IF EXISTS cea_migration_expected_columns;
CREATE TEMPORARY TABLE cea_migration_expected_columns (
    table_name varchar(64) NOT NULL, column_name varchar(64) NOT NULL,
    PRIMARY KEY (table_name,column_name)
) ENGINE=InnoDB;
INSERT INTO cea_migration_expected_columns (table_name,column_name) VALUES
('academic_policy_versions','id'),
('academic_policy_versions','regulation_id'),
('academic_policy_versions','policy_hash'),
('academic_policy_versions','settings_json'),
('academic_policy_versions','recorded_by'),
('academic_policy_versions','created_at'),
('academic_record_snapshots','id'),
('academic_record_snapshots','entity_type'),
('academic_record_snapshots','entity_id'),
('academic_record_snapshots','department_id'),
('academic_record_snapshots','actor_user_id'),
('academic_record_snapshots','reason'),
('academic_record_snapshots','before_json'),
('academic_record_snapshots','after_json'),
('academic_record_snapshots','created_at'),
('academic_settings','id'),
('academic_settings','reg_id'),
('academic_settings','regulation_code'),
('academic_settings','category'),
('academic_settings','setting_key'),
('academic_settings','setting_value'),
('academic_settings','data_type'),
('academic_settings','description'),
('academic_settings','is_editable'),
('academic_settings','updated_by'),
('academic_settings','updated_at'),
('academic_settings_audit','id'),
('academic_settings_audit','setting_id'),
('academic_settings_audit','regulation_code'),
('academic_settings_audit','setting_key'),
('academic_settings_audit','old_value'),
('academic_settings_audit','new_value'),
('academic_settings_audit','changed_by'),
('academic_settings_audit','changed_at'),
('academic_settings_audit','ip_address'),
('academic_years','id'),
('academic_years','acad_year'),
('academic_years','status'),
('activity_logs','id'),
('activity_logs','user_id'),
('activity_logs','action'),
('activity_logs','details'),
('activity_logs','timestamp'),
('ai_jobs','id'),
('ai_jobs','snapshot_id'),
('ai_jobs','owner_user_id'),
('ai_jobs','task_key'),
('ai_jobs','input_hash'),
('ai_jobs','prompt_version'),
('ai_jobs','status'),
('ai_jobs','attempts'),
('ai_jobs','locked_at'),
('ai_jobs','model_id'),
('ai_jobs','model_digest'),
('ai_jobs','output_json'),
('ai_jobs','error_message'),
('ai_jobs','reviewed_by'),
('ai_jobs','reviewed_at'),
('ai_jobs','created_at'),
('ai_jobs','provider_id'),
('ai_jobs','payload_json'),
('ai_jobs','completed_at'),
('ai_jobs','claim_token'),
('ai_jobs','review_remarks'),
('ai_jobs','provider_config_hash'),
('ai_jobs','next_attempt_at'),
('ai_job_consents','user_id'),
('ai_job_consents','task_key'),
('ai_job_consents','is_enabled'),
('ai_providers','id'),
('ai_providers','name'),
('ai_providers','kind'),
('ai_providers','endpoint'),
('ai_providers','model'),
('ai_providers','credential_env'),
('ai_providers','is_enabled'),
('ai_providers','timeout_seconds'),
('ai_providers','updated_by'),
('ai_providers','updated_at'),
('ai_providers','version'),
('assessment_components','id'),
('assessment_components','assessment_id'),
('assessment_components','component_type'),
('assessment_components','sequence_number'),
('assessment_questions','id'),
('assessment_questions','component_id'),
('assessment_questions','question_label'),
('assessment_questions','question_type'),
('assessment_questions','marks'),
('assessment_questions','blooms_level_id'),
('attainment_import_batches','id'),
('attainment_import_batches','subject_id'),
('attainment_import_batches','owner_user_id'),
('attainment_import_batches','source_hash'),
('attainment_import_batches','policy_hash'),
('attainment_import_batches','payload_json'),
('attainment_import_batches','state'),
('attainment_import_batches','committed_at'),
('attainment_import_batches','created_at'),
('attainment_policies','id'),
('attainment_policies','regulation_id'),
('attainment_policies','subject_type'),
('attainment_policies','config_json'),
('attainment_policies','version'),
('attainment_policies','updated_by'),
('attainment_policies','updated_at'),
('attendance','id'),
('attendance','stu_id'),
('attendance','sub_id'),
('attendance','date'),
('attendance','hour'),
('attendance','status'),
('attendance','updatedat'),
('attendance_delete_requests','id'),
('attendance_delete_requests','faculty_id'),
('attendance_delete_requests','subject_id'),
('attendance_delete_requests','date'),
('attendance_delete_requests','hour'),
('attendance_delete_requests','reason'),
('attendance_delete_requests','status'),
('attendance_delete_requests','request_date'),
('attendance_delete_requests','approval_date'),
('attendance_rules','id'),
('attendance_rules','reg_id'),
('attendance_rules','criteria'),
('attendance_rules','operator1'),
('attendance_rules','value1'),
('attendance_rules','operator2'),
('attendance_rules','value2'),
('attendance_rules','updatedat'),
('audit_logs','id'),
('audit_logs','user_id'),
('audit_logs','username'),
('audit_logs','role'),
('audit_logs','action'),
('audit_logs','entity_type'),
('audit_logs','entity_id'),
('audit_logs','target_user_id'),
('audit_logs','status'),
('audit_logs','details'),
('audit_logs','ip_address'),
('audit_logs','user_agent'),
('audit_logs','created_at'),
('batch_peo_targets','id'),
('batch_peo_targets','batch_id'),
('batch_peo_targets','peo_id'),
('batch_peo_targets','target_score'),
('batch_peo_targets','updated_at'),
('blooms_levels','id'),
('blooms_levels','blooms_level'),
('blooms_levels','blooms_label'),
('buildings','id'),
('buildings','building_name'),
('buildings','status'),
('buildings','created_at'),
('buildings','updated_at'),
('certificate_sequences','prefix'),
('certificate_sequences','last_number'),
('cia_attachments','id'),
('cia_attachments','subject_id'),
('cia_attachments','assessment_number'),
('cia_attachments','file_title'),
('cia_attachments','file_path'),
('cia_attachments','updated_on'),
('classes','id'),
('classes','acad_year'),
('classes','classname'),
('classes','yearsem'),
('classes','section'),
('classes','start_date'),
('classes','end_date'),
('classes','status'),
('classes','spec_id'),
('classes','timing_id'),
('classes','reg_id'),
('classes','updatedat'),
('classes','batch_id'),
('class_timetables','id'),
('class_timetables','class_id'),
('class_timetables','weekday'),
('class_timetables','Hour'),
('class_timetables','subject_code'),
('class_timetables','subject_id'),
('class_timetables','building_name'),
('class_timetables','class_hall_name'),
('class_timings','id'),
('class_timings','timing_id'),
('class_timings','hour'),
('class_timings','hour_desc'),
('class_timings','start_time'),
('class_timings','end_time'),
('class_timings','updatedat'),
('class_timing_schedule','id'),
('class_timing_schedule','class_id'),
('class_timing_schedule','timing_id'),
('class_timing_schedule','from_date'),
('class_timing_schedule','to_date'),
('class_timing_schedule','created_by'),
('class_timing_schedule','updatedat'),
('course_completion_audits','id'),
('course_completion_audits','sub_id'),
('course_completion_audits','faculty_id'),
('course_completion_audits','total_planned_lectures'),
('course_completion_audits','total_actual_conducted'),
('course_completion_audits','total_compensatory_classes'),
('course_completion_audits','syllabus_completion_pct'),
('course_completion_audits','unit1_completion_date'),
('course_completion_audits','unit2_completion_date'),
('course_completion_audits','unit3_completion_date'),
('course_completion_audits','unit4_completion_date'),
('course_completion_audits','unit5_completion_date'),
('course_completion_audits','deviations_reason'),
('course_completion_audits','compensatory_actions'),
('course_completion_audits','topics_beyond_syllabus'),
('course_completion_audits','faculty_signoff_status'),
('course_completion_audits','faculty_signoff_at'),
('course_completion_audits','hod_approval_status'),
('course_completion_audits','hod_approval_at'),
('course_completion_audits','hod_remarks'),
('course_completion_audits','reconciliation_mapping'),
('course_completion_audits','created_at'),
('course_completion_audits','updated_at'),
('course_completion_audits','hod_reviewed_by'),
('course_cqi_actions','id'),
('course_cqi_actions','sub_id'),
('course_cqi_actions','co_id'),
('course_cqi_actions','academic_year'),
('course_cqi_actions','target_benchmark'),
('course_cqi_actions','attained_percentage'),
('course_cqi_actions','attainment_gap'),
('course_cqi_actions','action_proposed'),
('course_cqi_actions','action_type'),
('course_cqi_actions','faculty_id'),
('course_cqi_actions','submitted_at'),
('course_cqi_actions','hod_review_status'),
('course_cqi_actions','hod_remarks'),
('course_cqi_actions','reviewed_at'),
('course_cqi_actions','reviewed_by'),
('course_cqi_actions','due_date'),
('course_cqi_actions','execution_evidence'),
('course_cqi_actions','completed_at'),
('course_cqi_actions','followup_measure'),
('course_cqi_actions','closed_by'),
('course_cqi_actions','closed_at'),
('course_outcomes','id'),
('course_outcomes','sub_id'),
('course_outcomes','co_number'),
('course_outcomes','co_description'),
('course_outcomes','curr_sub_id'),
('course_outcomes','bloom_level'),
('course_outcomes','target_threshold_percent'),
('co_po_mapping','id'),
('co_po_mapping','co_id'),
('co_po_mapping','po_id'),
('co_po_mapping','weightage'),
('curriculum_course_outcomes','id'),
('curriculum_course_outcomes','curr_sub_id'),
('curriculum_course_outcomes','co_number'),
('curriculum_course_outcomes','co_description'),
('curriculum_course_outcomes','bloom_level'),
('curriculum_course_outcomes','target_threshold_percent'),
('curriculum_course_outcomes','created_at'),
('curriculum_course_outcomes','updated_at'),
('curriculum_co_po_mapping','id'),
('curriculum_co_po_mapping','curr_co_id'),
('curriculum_co_po_mapping','po_id'),
('curriculum_co_po_mapping','weightage'),
('curriculum_subjects','id'),
('curriculum_subjects','prog_id'),
('curriculum_subjects','reg_id'),
('curriculum_subjects','spec_id'),
('curriculum_subjects','yearsem'),
('curriculum_subjects','subject_sno'),
('curriculum_subjects','subcode'),
('curriculum_subjects','sub_fullname'),
('curriculum_subjects','sub_shortname'),
('curriculum_subjects','sub_type'),
('curriculum_subjects','course_category'),
('curriculum_subjects','lecture_hours'),
('curriculum_subjects','tutorial_hours'),
('curriculum_subjects','pr_hours'),
('curriculum_subjects','practical_hours'),
('curriculum_subjects','credits'),
('curriculum_subjects','status'),
('curriculum_subjects','created_at'),
('curriculum_subjects','updated_at'),
('curriculum_subjects','cie_max_marks'),
('curriculum_subjects','see_max_marks'),
('curriculum_subjects','total_marks'),
('curriculum_subjects','has_see'),
('curriculum_subjects','elective_track'),
('curriculum_subjects','delivery_mode'),
('curriculum_subjects','prerequisites'),
('departments','id'),
('departments','username'),
('departments','dept_shortname'),
('departments','dept_fullname'),
('departments','status'),
('departments','updatedat'),
('diary','id'),
('diary','sub_id'),
('diary','faculty_id'),
('diary','date'),
('diary','hour'),
('diary','diary'),
('diary','updatedat'),
('diary','lesson_plan_id'),
('exam_notifications','id'),
('exam_notifications','notification_code'),
('exam_notifications','title'),
('exam_notifications','academic_year'),
('exam_notifications','program_id'),
('exam_notifications','regulation_id'),
('exam_notifications','yearsem'),
('exam_notifications','month_year'),
('exam_notifications','release_date'),
('exam_notifications','is_published'),
('exam_notifications','created_by'),
('exam_notifications','created_at'),
('exam_notifications','updated_at'),
('exam_notifications','workflow_status'),
('exam_notifications','reviewed_by'),
('exam_notifications','reviewed_at'),
('exam_notifications','exam_type'),
('exam_results','id'),
('exam_results','notification_id'),
('exam_results','htno'),
('exam_results','student_id'),
('exam_results','subject_code'),
('exam_results','subject_name'),
('exam_results','subject_id'),
('exam_results','internal_marks'),
('exam_results','external_marks'),
('exam_results','total_marks'),
('exam_results','grade_letter'),
('exam_results','grade_points'),
('exam_results','credits_registered'),
('exam_results','credits_earned'),
('exam_results','result_status'),
('exam_results','imported_at'),
('external_assessment_marks','id'),
('external_assessment_marks','student_id'),
('external_assessment_marks','subject_id'),
('external_assessment_marks','entry_mode'),
('external_assessment_marks','external_marks'),
('external_assessment_marks','max_marks'),
('external_assessment_marks','q1_marks'),
('external_assessment_marks','choice_marks'),
('external_assessment_marks','part_a_marks'),
('external_assessment_marks','part_b_marks'),
('external_assessment_marks','submitted_by'),
('external_assessment_marks','submitted_at'),
('external_assessment_marks','source_notification_id'),
('external_assessment_marks','raw_marks'),
('external_assessment_marks','raw_max_marks'),
('external_assessment_marks','normalisation_json'),
('external_assessment_marks','source_batch_id'),
('external_assessment_marks','evidence_basis'),
('external_assessment_marks','is_current'),
('faculties','id'),
('faculties','username'),
('faculties','designation'),
('faculties','facultyid'),
('faculties','dept_id'),
('faculties','sort_order'),
('faculties','status'),
('faculties','updatedat'),
('faculty_sub','id'),
('faculty_sub','faculty_id'),
('faculty_sub','sub_id'),
('faculty_sub','updatedat'),
('fac_activity_logs','id'),
('fac_activity_logs','user_id'),
('fac_activity_logs','action'),
('fac_activity_logs','details'),
('fac_activity_logs','timestamp'),
('halls','id'),
('halls','building_id'),
('halls','hall_name'),
('halls','status'),
('halls','created_at'),
('halls','updated_at'),
('internal_assessments','id'),
('internal_assessments','sub_id'),
('internal_assessments','assessment_number'),
('internal_assessments','created_at'),
('internal_assessment_marks','id'),
('internal_assessment_marks','student_id'),
('internal_assessment_marks','subject_id'),
('internal_assessment_marks','assessment_number'),
('internal_assessment_marks','subjective_marks'),
('internal_assessment_marks','objective_marks'),
('internal_assessment_marks','assignment_marks'),
('lab_rubric_criteria','id'),
('lab_rubric_criteria','template_id'),
('lab_rubric_criteria','criterion_name'),
('lab_rubric_criteria','criterion_description'),
('lab_rubric_criteria','max_marks'),
('lab_rubric_criteria','sort_order'),
('lab_rubric_evaluations','id'),
('lab_rubric_evaluations','uglab_mark_id'),
('lab_rubric_evaluations','criterion_id'),
('lab_rubric_evaluations','criterion_name'),
('lab_rubric_evaluations','marks_obtained'),
('lab_rubric_evaluations','created_at'),
('lab_rubric_templates','id'),
('lab_rubric_templates','template_name'),
('lab_rubric_templates','department_id'),
('lab_rubric_templates','total_max_marks'),
('lab_rubric_templates','is_default'),
('lab_rubric_templates','created_at'),
('lab_subject_rubrics','id'),
('lab_subject_rubrics','subject_id'),
('lab_subject_rubrics','template_id'),
('lab_subject_rubrics','criterion_name'),
('lab_subject_rubrics','max_marks'),
('lab_subject_rubrics','sort_order'),
('lesson_plans','id'),
('lesson_plans','sub_id'),
('lesson_plans','unit_number'),
('lesson_plans','lecture_number'),
('lesson_plans','planned_topic'),
('lesson_plans','co_id'),
('lesson_plans','bloom_level'),
('lesson_plans','pedagogy'),
('lesson_plans','reference_material'),
('lesson_plans','planned_hours'),
('lesson_plans','created_at'),
('peos','id'),
('peos','dept_id'),
('peos','program_id'),
('peos','peo_code'),
('peos','peo_title'),
('peos','peo_description'),
('peos','target_score'),
('peos','effective_from_year'),
('peos','sort_order'),
('peos','is_active'),
('peos','created_at'),
('peos','updated_at'),
('peo_mission_mapping','id'),
('peo_mission_mapping','peo_id'),
('peo_mission_mapping','mission_key'),
('peo_mission_mapping','correlation_level'),
('peo_mission_mapping','updated_at'),
('pg_internal_assessment_marks','id'),
('pg_internal_assessment_marks','student_id'),
('pg_internal_assessment_marks','subject_id'),
('pg_internal_assessment_marks','assessment_number'),
('pg_internal_assessment_marks','marks'),
('po_peo_mapping','id'),
('po_peo_mapping','po_pso_id'),
('po_peo_mapping','peo_id'),
('po_peo_mapping','correlation_level'),
('po_peo_mapping','updated_at'),
('po_pso','id'),
('po_pso','acad_year'),
('po_pso','regulation'),
('po_pso','specid'),
('po_pso','batch_id'),
('po_pso','po_pso'),
('po_pso','orderid'),
('po_pso','code'),
('po_pso','description'),
('po_pso','updated_at'),
('po_pso','reg_id'),
('po_pso','target_score'),
('po_pso','effective_from_year'),
('programs','id'),
('programs','program_code'),
('programs','prog_shortname'),
('programs','prog_fullname'),
('programs','updatedat'),
('programs','program_level'),
('question_co_mapping','id'),
('question_co_mapping','question_id'),
('question_co_mapping','co_id'),
('regulations','id'),
('regulations','regulation'),
('regulations','prog_id'),
('regulations','updatedat'),
('regulations','start_year'),
('regulations','is_active'),
('regulations','normal_duration_years'),
('regulations','max_duration_years'),
('regulations','gap_year_extension_years'),
('regulations','total_semesters'),
('regulations','total_degree_credits'),
('regulations','lateral_entry_credits'),
('regulations','honors_credits'),
('regulations','minor_credits'),
('regulations','has_lateral_entry'),
('regulations','has_honors'),
('regulations','has_minors'),
('regulations','has_gap_year'),
('regulations','has_internal_improvement'),
('regulations','effective_admitted_batch'),
('regulations','les_effective_batch'),
('regulation_course_categories','id'),
('regulation_course_categories','reg_id'),
('regulation_course_categories','category_code'),
('regulation_course_categories','category_name'),
('regulation_course_categories','min_allocation_pct'),
('regulation_course_categories','max_allocation_pct'),
('regulation_course_categories','target_credits'),
('regulation_course_categories','description'),
('regulation_course_categories','created_at'),
('regulation_course_categories','updated_at'),
('regulation_course_types','id'),
('regulation_course_types','reg_id'),
('regulation_course_types','type_code'),
('regulation_course_types','type_name'),
('regulation_course_types','evaluation_scheme'),
('regulation_course_types','cie_max_marks'),
('regulation_course_types','see_max_marks'),
('regulation_course_types','total_marks'),
('regulation_course_types','has_see'),
('regulation_course_types','is_credit_course'),
('regulation_course_types','description'),
('regulation_course_types','created_at'),
('regulation_course_types','updated_at'),
('report_snapshots','id'),
('report_snapshots','report_key'),
('report_snapshots','owner_user_id'),
('report_snapshots','department_id'),
('report_snapshots','subject_id'),
('report_snapshots','cutoff_at'),
('report_snapshots','policy_hash'),
('report_snapshots','metrics_hash'),
('report_snapshots','scope_json'),
('report_snapshots','metrics_json'),
('report_snapshots','state'),
('report_snapshots','reviewed_by'),
('report_snapshots','reviewed_at'),
('report_snapshots','created_at'),
('role_module_permissions','role'),
('role_module_permissions','module_key'),
('role_module_permissions','action'),
('role_module_permissions','is_allowed'),
('role_module_permissions','updated_by'),
('role_module_permissions','updated_at'),
('schema_migrations','version'),
('schema_migrations','checksum'),
('schema_migrations','applied_at'),
('specialization','id'),
('specialization','spec_code'),
('specialization','spec_shortname'),
('specialization','spec_fullname'),
('specialization','dept_id'),
('specialization','prog_id'),
('specialization','status'),
('specialization','updatedat'),
('students','id'),
('students','username'),
('students','class_id'),
('students','status'),
('students','updatedat'),
('students','date_of_joining'),
('student_batches','id'),
('student_batches','program_id'),
('student_batches','regulation_id'),
('student_batches','batch_name'),
('student_batches','admission_year'),
('student_batches','graduation_year'),
('student_batches','is_active'),
('student_batches','created_at'),
('student_certificate_requests','id'),
('student_certificate_requests','certificate_no'),
('student_certificate_requests','roll_no'),
('student_certificate_requests','cert_type'),
('student_certificate_requests','purpose'),
('student_certificate_requests','status'),
('student_certificate_requests','requested_date'),
('student_certificate_requests','approved_by'),
('student_certificate_requests','approved_at'),
('student_certificate_requests','issued_at'),
('student_certificate_requests','custom_fields_json'),
('student_certificate_requests','remarks'),
('student_certificate_requests','created_at'),
('student_certificate_requests','updated_at'),
('student_certificate_requests','verification_token'),
('student_ces_feedback','id'),
('student_ces_feedback','student_id'),
('student_ces_feedback','subject_id'),
('student_ces_feedback','ces_q1'),
('student_ces_feedback','ces_q2'),
('student_ces_feedback','ces_q3'),
('student_ces_feedback','ces_q4'),
('student_ces_feedback','ces_q5'),
('student_ces_feedback','ces_q6'),
('student_ces_feedback','ces_q7'),
('student_ces_feedback','ces_q8'),
('student_ces_feedback','ces_q9'),
('student_ces_feedback','ces_q10'),
('student_ces_feedback','ces_q11'),
('student_ces_feedback','ces_q12'),
('student_ces_feedback','ces_q13'),
('student_ces_feedback','ces_q14'),
('student_ces_feedback','ces_q15'),
('student_ces_feedback','ces_q16'),
('student_ces_feedback','useful_aspects'),
('student_ces_feedback','improvement_topics'),
('student_ces_feedback','suggestions'),
('student_ces_feedback','is_anonymous'),
('student_ces_feedback','submitted_at'),
('student_co_feedback','id'),
('student_co_feedback','student_id'),
('student_co_feedback','subject_id'),
('student_co_feedback','class_id'),
('student_co_feedback','co_id'),
('student_co_feedback','rating'),
('student_co_feedback','feedback_date'),
('student_custodial_records','id'),
('student_custodial_records','roll_no'),
('student_custodial_records','document_name'),
('student_custodial_records','certificate_serial_no'),
('student_custodial_records','original_received'),
('student_custodial_records','received_date'),
('student_custodial_records','received_by'),
('student_custodial_records','status'),
('student_custodial_records','purpose_of_withdrawal'),
('student_custodial_records','issued_date'),
('student_custodial_records','expected_return_date'),
('student_custodial_records','actual_returned_date'),
('student_custodial_records','ack_signature_token'),
('student_custodial_records','remarks'),
('student_custodial_records','created_at'),
('student_custodial_records','updated_at'),
('student_documents','id'),
('student_documents','roll_no'),
('student_documents','doc_type'),
('student_documents','doc_title'),
('student_documents','file_name'),
('student_documents','file_path'),
('student_documents','file_size'),
('student_documents','mime_type'),
('student_documents','is_original_submitted'),
('student_documents','custody_status'),
('student_documents','custody_location_ref'),
('student_documents','uploaded_at'),
('student_documents','is_verified'),
('student_documents','verified_by'),
('student_documents','verified_at'),
('student_documents','remarks'),
('student_documents','deleted_at'),
('student_documents','deleted_by'),
('student_faculty_feedback','id'),
('student_faculty_feedback','student_id'),
('student_faculty_feedback','subject_id'),
('student_faculty_feedback','faculty_id'),
('student_faculty_feedback','fac_q1'),
('student_faculty_feedback','fac_q2'),
('student_faculty_feedback','fac_q3'),
('student_faculty_feedback','fac_q4'),
('student_faculty_feedback','fac_q5'),
('student_faculty_feedback','fac_q6'),
('student_faculty_feedback','fac_q7'),
('student_faculty_feedback','fac_q8'),
('student_faculty_feedback','fac_q9'),
('student_faculty_feedback','fac_q10'),
('student_faculty_feedback','fac_q11'),
('student_faculty_feedback','fac_q12'),
('student_faculty_feedback','fac_q13'),
('student_faculty_feedback','fac_q14'),
('student_faculty_feedback','fac_q15'),
('student_faculty_feedback','fac_q16'),
('student_faculty_feedback','fac_q17'),
('student_faculty_feedback','fac_q18'),
('student_faculty_feedback','fac_q19'),
('student_faculty_feedback','faculty_strengths'),
('student_faculty_feedback','improvement_areas'),
('student_faculty_feedback','additional_comments'),
('student_faculty_feedback','is_anonymous'),
('student_faculty_feedback','submitted_at'),
('student_marks','id'),
('student_marks','stu_id'),
('student_marks','question_id'),
('student_marks','marks_obtained'),
('student_permissions','id'),
('student_permissions','stu_id'),
('student_permissions','class_id'),
('student_permissions','date'),
('student_permissions','hour'),
('student_permissions','permission_type'),
('student_permissions','reason'),
('student_permissions','document_path'),
('student_permissions','granted_by_hod_id'),
('student_permissions','created_at'),
('student_profiles','id'),
('student_profiles','roll_no'),
('student_profiles','dob'),
('student_profiles','gender'),
('student_profiles','blood_group'),
('student_profiles','aadhar_number'),
('student_profiles','father_name'),
('student_profiles','mother_name'),
('student_profiles','parent_phone'),
('student_profiles','parent_email'),
('student_profiles','admission_category'),
('student_profiles','rank_obtained'),
('student_profiles','hall_ticket_admission'),
('student_profiles','admission_date'),
('student_profiles','permanent_address'),
('student_profiles','current_address'),
('student_profiles','profile_photo_path'),
('student_profiles','is_verified'),
('student_profiles','verified_by'),
('student_profiles','verified_at'),
('student_profiles','created_at'),
('student_profiles','updated_at'),
('student_questionnaire_responses','id'),
('student_questionnaire_responses','student_id'),
('student_questionnaire_responses','subject_id'),
('student_questionnaire_responses','question_id'),
('student_questionnaire_responses','rating'),
('student_questionnaire_responses','submitted_at'),
('student_sub','id'),
('student_sub','stu_id'),
('student_sub','sub_id'),
('student_sub','updatedat'),
('subjects','id'),
('subjects','subject_sno'),
('subjects','subcode'),
('subjects','sub_shortname'),
('subjects','sub_fullname'),
('subjects','group_name'),
('subjects','sub_type'),
('subjects','class_id'),
('subjects','updatedat'),
('subjects','curr_sub_id'),
('subject_attainment_overrides','subject_id'),
('subject_attainment_overrides','config_json'),
('subject_attainment_overrides','version'),
('subject_attainment_overrides','updated_by'),
('subject_attainment_overrides','updated_at'),
('subject_questionnaire_questions','id'),
('subject_questionnaire_questions','subject_id'),
('subject_questionnaire_questions','question_number'),
('subject_questionnaire_questions','question_text'),
('subject_questionnaire_questions','created_by_user_id'),
('subject_questionnaire_questions','created_by_role'),
('subject_questionnaire_questions','created_at'),
('system_feature_modules','id'),
('system_feature_modules','module_key'),
('system_feature_modules','module_name'),
('system_feature_modules','category'),
('system_feature_modules','description'),
('system_feature_modules','is_enabled_globally'),
('system_feature_modules','faculty_visibility'),
('system_feature_modules','student_visibility'),
('system_feature_modules','hod_visibility'),
('system_feature_modules','sort_order'),
('system_feature_modules','updated_by'),
('system_feature_modules','updated_at'),
('temp_internal_assessment_marks','id'),
('temp_internal_assessment_marks','student_id'),
('temp_internal_assessment_marks','subject_id'),
('temp_internal_assessment_marks','assessment_number'),
('temp_internal_assessment_marks','subjective_marks'),
('temp_internal_assessment_marks','objective_marks'),
('temp_internal_assessment_marks','assignment_marks'),
('temp_joiningdates','sno'),
('temp_joiningdates','htno'),
('temp_joiningdates','name'),
('temp_joiningdates','doj'),
('temp_joiningdates','gender'),
('temp_pg_internal_assessment_marks','id'),
('temp_pg_internal_assessment_marks','student_id'),
('temp_pg_internal_assessment_marks','subject_id'),
('temp_pg_internal_assessment_marks','assessment_number'),
('temp_pg_internal_assessment_marks','marks'),
('temp_uglab_internal_assessment_marks','id'),
('temp_uglab_internal_assessment_marks','student_id'),
('temp_uglab_internal_assessment_marks','subject_id'),
('temp_uglab_internal_assessment_marks','assessment_number'),
('temp_uglab_internal_assessment_marks','day_to_day_marks'),
('temp_uglab_internal_assessment_marks','internal_test_marks'),
('temp_ugproject_internal_assessment_marks','id'),
('temp_ugproject_internal_assessment_marks','student_id'),
('temp_ugproject_internal_assessment_marks','subject_id'),
('temp_ugproject_internal_assessment_marks','assessment_number'),
('temp_ugproject_internal_assessment_marks','component1_marks'),
('temp_ugproject_internal_assessment_marks','component2_marks'),
('uglab_internal_assessment_marks','id'),
('uglab_internal_assessment_marks','student_id'),
('uglab_internal_assessment_marks','subject_id'),
('uglab_internal_assessment_marks','assessment_number'),
('uglab_internal_assessment_marks','day_to_day_marks'),
('uglab_internal_assessment_marks','internal_test_marks'),
('ugproject_internal_assessment_marks','id'),
('ugproject_internal_assessment_marks','student_id'),
('ugproject_internal_assessment_marks','subject_id'),
('ugproject_internal_assessment_marks','assessment_number'),
('ugproject_internal_assessment_marks','component1_marks'),
('ugproject_internal_assessment_marks','component2_marks'),
('users','id'),
('users','username'),
('users','password'),
('users','name'),
('users','mobile'),
('users','email'),
('users','role'),
('users','status'),
('users','updatedat'),
('vision_mission','id'),
('vision_mission','dept_id'),
('vision_mission','effective_from_year'),
('vision_mission','vision_statement'),
('vision_mission','mission_statements'),
('vision_mission','is_active'),
('vision_mission','created_by'),
('vision_mission','created_at'),
('vision_mission','updated_at'),
('workflow_review_history','id'),
('workflow_review_history','entity_type'),
('workflow_review_history','entity_id'),
('workflow_review_history','actor_user_id'),
('workflow_review_history','from_status'),
('workflow_review_history','to_status'),
('workflow_review_history','remarks'),
('workflow_review_history','created_at');

SELECT expected.table_name AS missing_table, expected.column_name AS missing_column
FROM cea_migration_expected_columns expected
LEFT JOIN information_schema.COLUMNS actual
  ON actual.TABLE_SCHEMA = DATABASE() AND actual.TABLE_NAME = expected.table_name AND actual.COLUMN_NAME = expected.column_name
WHERE actual.COLUMN_NAME IS NULL;

SET @cea_sql = IF(NOT EXISTS (
    SELECT 1 FROM cea_migration_expected_columns expected
    LEFT JOIN information_schema.COLUMNS actual
      ON actual.TABLE_SCHEMA = DATABASE() AND actual.TABLE_NAME = expected.table_name AND actual.COLUMN_NAME = expected.column_name
    WHERE actual.COLUMN_NAME IS NULL
),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_incomplete_schema_review_missing_columns`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

DROP TEMPORARY TABLE cea_migration_expected_columns;

/* 7. Record covered immutable runner migrations; never overwrite checksums. */

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM schema_migrations WHERE version = 'baseline_20261007_accreditation.sql' AND checksum <> '75acfb11768d34311c2a45ef66c1f218bf7d867e6feb81d0e63da0ef9dd47626'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_migration_checksum_conflict_check_schema_migrations`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

INSERT INTO schema_migrations (version, checksum) VALUES ('baseline_20261007_accreditation.sql', '75acfb11768d34311c2a45ef66c1f218bf7d867e6feb81d0e63da0ef9dd47626') ON DUPLICATE KEY UPDATE version = VALUES(version);

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM schema_migrations WHERE version = '20261008_001_trusted_core.sql' AND checksum <> '9e2889357f665ace4d8cc4769afa1657aef9bce8e0f21a573da30e396ba8209f'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_migration_checksum_conflict_check_schema_migrations`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

INSERT INTO schema_migrations (version, checksum) VALUES ('20261008_001_trusted_core.sql', '9e2889357f665ace4d8cc4769afa1657aef9bce8e0f21a573da30e396ba8209f') ON DUPLICATE KEY UPDATE version = VALUES(version);

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM schema_migrations WHERE version = '20261008_002_document_lifecycle.sql' AND checksum <> '21a0b1339efefd4892396d037a566eca30c4bda314269ccdf339ada3f74ffb1a'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_migration_checksum_conflict_check_schema_migrations`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

INSERT INTO schema_migrations (version, checksum) VALUES ('20261008_002_document_lifecycle.sql', '21a0b1339efefd4892396d037a566eca30c4bda314269ccdf339ada3f74ffb1a') ON DUPLICATE KEY UPDATE version = VALUES(version);

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM schema_migrations WHERE version = '20261008_003_attainment_integrations.sql' AND checksum <> '5d056c406face778f6a5efc823063089828297ddd921cd8b07ee5096551c486a'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_migration_checksum_conflict_check_schema_migrations`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

INSERT INTO schema_migrations (version, checksum) VALUES ('20261008_003_attainment_integrations.sql', '5d056c406face778f6a5efc823063089828297ddd921cd8b07ee5096551c486a') ON DUPLICATE KEY UPDATE version = VALUES(version);

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM schema_migrations WHERE version = '20261008_004_ai_queue_guards.sql' AND checksum <> 'fe59a517ab6e5bfaacbe4dce07a1b77f3afd5d7186fa63fa12586e452e696614'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_migration_checksum_conflict_check_schema_migrations`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

INSERT INTO schema_migrations (version, checksum) VALUES ('20261008_004_ai_queue_guards.sql', 'fe59a517ab6e5bfaacbe4dce07a1b77f3afd5d7186fa63fa12586e452e696614') ON DUPLICATE KEY UPDATE version = VALUES(version);

SET @cea_sql = IF(NOT EXISTS (SELECT 1 FROM schema_migrations WHERE version = '20261013_001_faculty_sort_order.sql' AND checksum <> '5b29ef91f9fe1bde56fad49ebce1854febc54f57ef02ca4f1e9ed72e0f0715d6'),
    'SET @cea_noop = 1',
    'SELECT * FROM `cea_error_migration_checksum_conflict_check_schema_migrations`');
PREPARE cea_stmt FROM @cea_sql;
EXECUTE cea_stmt;
DEALLOCATE PREPARE cea_stmt;

INSERT INTO schema_migrations (version, checksum) VALUES ('20261013_001_faculty_sort_order.sql', '5b29ef91f9fe1bde56fad49ebce1854febc54f57ef02ca4f1e9ed72e0f0715d6') ON DUPLICATE KEY UPDATE version = VALUES(version);

COMMIT;
SET SESSION sql_mode = @cea_old_sql_mode;
SELECT 'COMPLETE: production upgrade 20261014' AS migration_status;
SELECT version, applied_at FROM schema_migrations ORDER BY version;
