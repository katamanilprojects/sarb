ALTER TABLE ai_jobs ADD COLUMN IF NOT EXISTS claim_token char(32) NULL;
ALTER TABLE ai_jobs ADD COLUMN IF NOT EXISTS review_remarks text NULL;
ALTER TABLE ai_jobs ADD COLUMN IF NOT EXISTS provider_config_hash char(64) NULL;
ALTER TABLE ai_jobs ADD COLUMN IF NOT EXISTS next_attempt_at datetime NULL;
ALTER TABLE ai_providers ADD COLUMN IF NOT EXISTS version int unsigned NOT NULL DEFAULT 1;
