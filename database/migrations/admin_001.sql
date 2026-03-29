-- migration: admin_001
-- description: Create admin_moderation_cases table

CREATE TABLE IF NOT EXISTS admin_moderation_cases (
    id          UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    target_type VARCHAR(50) NOT NULL,
    target_id   UUID        NOT NULL,
    reporter_id UUID,
    reason      TEXT        NOT NULL,
    status      VARCHAR(20) NOT NULL DEFAULT 'open'
                CHECK (status IN ('open', 'resolved', 'escalated')),
    resolution  VARCHAR(20)
                CHECK (resolution IS NULL OR resolution IN ('approved', 'rejected', 'escalated')),
    resolver_id UUID,
    notes       TEXT,
    resolved_at TIMESTAMP,
    created_at  TIMESTAMP   NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMP   NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_admin_moderation_cases_status ON admin_moderation_cases(status);
CREATE INDEX IF NOT EXISTS idx_admin_moderation_cases_target ON admin_moderation_cases(target_type, target_id);
