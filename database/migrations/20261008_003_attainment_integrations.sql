CREATE TABLE IF NOT EXISTS attainment_policies (
 id int unsigned AUTO_INCREMENT PRIMARY KEY, regulation_id int NOT NULL, subject_type varchar(40) NOT NULL,
 config_json longtext NOT NULL, version int unsigned NOT NULL DEFAULT 1, updated_by int NOT NULL,
 updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_attainment_policy(regulation_id,subject_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS subject_attainment_overrides (
 subject_id int PRIMARY KEY, config_json longtext NOT NULL, version int unsigned NOT NULL DEFAULT 1,
 updated_by int NOT NULL, updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS attainment_import_batches (
 id bigint unsigned AUTO_INCREMENT PRIMARY KEY, subject_id int NOT NULL, owner_user_id int NOT NULL,
 source_hash char(64) NOT NULL, policy_hash char(64) NOT NULL, payload_json longtext NOT NULL,
 state enum('DRAFT','COMMITTED','CANCELLED') NOT NULL DEFAULT 'DRAFT', committed_at datetime NULL,
 created_at timestamp DEFAULT CURRENT_TIMESTAMP, KEY(subject_id,state), KEY(owner_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE external_assessment_marks ADD COLUMN IF NOT EXISTS raw_marks decimal(12,5) NULL;
ALTER TABLE external_assessment_marks ADD COLUMN IF NOT EXISTS raw_max_marks decimal(12,5) NULL;
ALTER TABLE external_assessment_marks ADD COLUMN IF NOT EXISTS normalisation_json longtext NULL;
ALTER TABLE external_assessment_marks ADD COLUMN IF NOT EXISTS source_batch_id bigint unsigned NULL;
ALTER TABLE external_assessment_marks ADD COLUMN IF NOT EXISTS evidence_basis varchar(40) NOT NULL DEFAULT 'LEGACY';
ALTER TABLE exam_results MODIFY COLUMN grade_points decimal(8,3) DEFAULT NULL;
CREATE TABLE IF NOT EXISTS ai_providers (
 id int unsigned AUTO_INCREMENT PRIMARY KEY, name varchar(100) NOT NULL,
 kind enum('OLLAMA','OPENAI_COMPATIBLE') NOT NULL, endpoint varchar(500) NOT NULL,
 model varchar(150) NOT NULL, credential_env varchar(100) NULL, is_enabled tinyint(1) NOT NULL DEFAULT 0,
 timeout_seconds int NOT NULL DEFAULT 45, updated_by int NOT NULL,
 updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE ai_jobs ADD COLUMN IF NOT EXISTS provider_id int unsigned NULL;
ALTER TABLE ai_jobs ADD COLUMN IF NOT EXISTS payload_json longtext NULL;
ALTER TABLE ai_jobs ADD COLUMN IF NOT EXISTS completed_at datetime NULL;
INSERT IGNORE INTO system_feature_modules(module_key,module_name,category,description,is_enabled_globally,faculty_visibility,student_visibility,hod_visibility,sort_order) VALUES ('MOD_AI_ASSISTANCE','Optional AI assistance','GENERAL','Opt-in drafts using an approved local or API provider; academic decisions remain deterministic.',0,'VISIBLE','HIDDEN','VISIBLE',90);
ALTER TABLE external_assessment_marks ADD COLUMN IF NOT EXISTS is_current tinyint(1) NOT NULL DEFAULT 1;
ALTER TABLE external_assessment_marks MODIFY COLUMN external_marks decimal(12,5) NOT NULL;
ALTER TABLE external_assessment_marks MODIFY COLUMN max_marks decimal(12,5) NOT NULL DEFAULT 70;
ALTER TABLE assessment_questions MODIFY COLUMN marks decimal(12,5) NOT NULL;
ALTER TABLE student_marks MODIFY COLUMN marks_obtained decimal(12,5) NOT NULL;
