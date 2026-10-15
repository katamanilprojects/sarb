CREATE TABLE IF NOT EXISTS role_module_permissions (
 role varchar(30) NOT NULL, module_key varchar(50) NOT NULL,
 action varchar(20) NOT NULL, is_allowed tinyint(1) NOT NULL DEFAULT 0,
 updated_by int NULL, updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (role,module_key,action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS academic_record_snapshots (
 id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, entity_type varchar(50) NOT NULL,
 entity_id varchar(64) NOT NULL, department_id int NOT NULL, actor_user_id int NOT NULL,
 reason text NOT NULL, before_json longtext NOT NULL, after_json longtext NOT NULL,
 created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY(entity_type,entity_id), KEY(department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS certificate_sequences (
 prefix varchar(80) NOT NULL PRIMARY KEY, last_number int unsigned NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE course_completion_audits ADD COLUMN IF NOT EXISTS hod_reviewed_by int NULL;
ALTER TABLE course_cqi_actions ADD COLUMN IF NOT EXISTS reviewed_by int NULL;
ALTER TABLE course_cqi_actions ADD COLUMN IF NOT EXISTS due_date date NULL;
ALTER TABLE course_cqi_actions ADD COLUMN IF NOT EXISTS execution_evidence text NULL;
ALTER TABLE course_cqi_actions ADD COLUMN IF NOT EXISTS completed_at datetime NULL;
ALTER TABLE course_cqi_actions ADD COLUMN IF NOT EXISTS followup_measure text NULL;
ALTER TABLE course_cqi_actions ADD COLUMN IF NOT EXISTS closed_by int NULL;
ALTER TABLE course_cqi_actions ADD COLUMN IF NOT EXISTS closed_at datetime NULL;
ALTER TABLE lab_rubric_evaluations ADD UNIQUE INDEX IF NOT EXISTS uq_lab_evaluation (uglab_mark_id,criterion_name);
ALTER TABLE exam_notifications ADD COLUMN IF NOT EXISTS workflow_status enum('DRAFT','REVIEWED','PUBLISHED') NOT NULL DEFAULT 'DRAFT';
ALTER TABLE exam_notifications ADD COLUMN IF NOT EXISTS reviewed_by int NULL;
ALTER TABLE exam_notifications ADD COLUMN IF NOT EXISTS reviewed_at datetime NULL;
ALTER TABLE exam_notifications ADD COLUMN IF NOT EXISTS exam_type enum('REGULAR','SUPPLEMENTARY','REVALUATION') NOT NULL DEFAULT 'REGULAR';
ALTER TABLE external_assessment_marks ADD COLUMN IF NOT EXISTS source_notification_id int NULL;
CREATE TABLE IF NOT EXISTS workflow_review_history (
 id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, entity_type varchar(40) NOT NULL,
 entity_id int NOT NULL, actor_user_id int NOT NULL, from_status varchar(30) NOT NULL,
 to_status varchar(30) NOT NULL, remarks text NOT NULL, created_at timestamp DEFAULT CURRENT_TIMESTAMP,
 KEY(entity_type,entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS report_snapshots (
 id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, report_key varchar(50) NOT NULL,
 owner_user_id int NOT NULL, department_id int NULL, subject_id int NULL,
 cutoff_at datetime NOT NULL, policy_hash char(64) NOT NULL, metrics_hash char(64) NOT NULL,
 scope_json longtext NOT NULL, metrics_json longtext NOT NULL,
 state enum('DRAFT','REVIEWED','PUBLISHED') NOT NULL DEFAULT 'DRAFT',
 reviewed_by int NULL, reviewed_at datetime NULL, created_at timestamp DEFAULT CURRENT_TIMESTAMP,
 KEY(owner_user_id), KEY(department_id,subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS ai_job_consents (
 user_id int NOT NULL, task_key varchar(50) NOT NULL, is_enabled tinyint(1) NOT NULL DEFAULT 0,
 PRIMARY KEY(user_id,task_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS ai_jobs (
 id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, snapshot_id bigint unsigned NOT NULL,
 owner_user_id int NOT NULL, task_key varchar(50) NOT NULL, input_hash char(64) NOT NULL,
 prompt_version varchar(30) NOT NULL, status enum('QUEUED','RUNNING','DRAFT','FAILED','CANCELLED','APPROVED') NOT NULL DEFAULT 'QUEUED',
 attempts int NOT NULL DEFAULT 0, locked_at datetime NULL, model_id varchar(150) NULL,
 model_digest varchar(100) NULL, output_json longtext NULL, error_message text NULL,
 reviewed_by int NULL, reviewed_at datetime NULL, created_at timestamp DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_ai_job (owner_user_id,task_key,input_hash,prompt_version), KEY(status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS academic_policy_versions (
 id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, regulation_id int NOT NULL,
 policy_hash char(64) NOT NULL, settings_json longtext NOT NULL, recorded_by int NOT NULL,
 created_at timestamp DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY(regulation_id,policy_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
