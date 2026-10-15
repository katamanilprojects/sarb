ALTER TABLE student_documents ADD COLUMN IF NOT EXISTS deleted_at datetime NULL;
ALTER TABLE student_documents ADD COLUMN IF NOT EXISTS deleted_by int NULL;
ALTER TABLE student_certificate_requests ADD COLUMN IF NOT EXISTS verification_token char(64) NULL;
ALTER TABLE student_certificate_requests ADD UNIQUE INDEX IF NOT EXISTS uq_certificate_verification_token (verification_token);
