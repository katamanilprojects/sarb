-- Pipeline schema v2. Apply explicitly to the selected database after a backup.
-- Compatible with MySQL 5.7+ / MariaDB 10.4+. No automatic provider activation.
-- CREATE IF NOT EXISTS does not upgrade an old incompatible table: the verifier
-- MUST pass after application. Retain/rename legacy tables before migrating them.
CREATE TABLE IF NOT EXISTS student_risk_snapshots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT NOT NULL,
    class_id INT NOT NULL,
    cutoff_date DATE NOT NULL,
    policy_hash CHAR(64) NOT NULL,
    category VARCHAR(24) NOT NULL,
    metrics_json LONGTEXT NOT NULL,
    calculated_at DATETIME NOT NULL COMMENT 'UTC',
    PRIMARY KEY (id),
    UNIQUE KEY uq_risk_snapshot (student_id,class_id,cutoff_date,policy_hash),
    KEY idx_risk_class_cutoff (class_id,cutoff_date),
    KEY idx_risk_calculated (calculated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Reserved for the future feedback task, not currently consumed by the batch.
CREATE TABLE IF NOT EXISTS ai_analysis_reports (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_type VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL,
    source_hash CHAR(64) NOT NULL,
    analysis_json LONGTEXT NOT NULL,
    model_used VARCHAR(150) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    created_at DATETIME NOT NULL COMMENT 'UTC',
    PRIMARY KEY (id),
    UNIQUE KEY uq_analysis_source (report_type,entity_type,entity_id,source_hash),
    KEY idx_analysis_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Deliberately no INSERT/UPDATE on ai_providers, system_feature_modules,
-- ai_job_consents or ai_jobs. Configure providers via existing administrator UI.
-- Foreign keys are deferred until legacy ID signedness/engines are verified;
-- student_id references students.id, class_id references classes.id logically.
