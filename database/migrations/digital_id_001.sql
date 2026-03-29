-- migration: digital_id_001
-- description: Create digital_identities table

CREATE TABLE IF NOT EXISTS digital_identities (
    id            UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id       UUID        NOT NULL UNIQUE,
    artifact_path VARCHAR(500) NOT NULL,
    checksum      VARCHAR(64)  NOT NULL,
    status        VARCHAR(20)  NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'revoked')),
    generated_at  TIMESTAMP   NOT NULL DEFAULT NOW(),
    revoked_at    TIMESTAMP,
    created_at    TIMESTAMP   NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMP   NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_digital_identities_user_id ON digital_identities(user_id);
