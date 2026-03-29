ALTER TABLE requests
  ADD COLUMN ai_category VARCHAR(100) NULL,
  ADD COLUMN ai_urgency TINYINT UNSIGNED NULL,
  ADD COLUMN ai_summary VARCHAR(140) NULL,
  ADD COLUMN ai_recommended_member_profile VARCHAR(255) NULL,
  ADD COLUMN ai_raw_json TEXT NULL;
