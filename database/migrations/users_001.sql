-- migration: users_001
-- description: Create users, user_profiles, user_roles, and user_points_ledger tables

CREATE TABLE IF NOT EXISTS users (
    id            UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    email         VARCHAR(320) NOT NULL UNIQUE,
    "status        VARCHAR(20)  NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'suspended', 'deactivated')),
    -- active: normal account; suspended: admin-imposed block; deactivated: user-initiated or system deactivation (maps to users.user_deactivated event)
    provider      VARCHAR(50)  NOT NULL,
    provider_subject VARCHAR(255) NOT NULL,
    UNIQUE (provider, provider_subject),
    created_at    TIMESTAMP   NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMP   NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS user_profiles (
    user_id       UUID        PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    display_name  VARCHAR(100) NOT NULL,
    bio           TEXT,
    avatar_path   VARCHAR(500),
    location      VARCHAR(200),
    created_at    TIMESTAMP   NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMP   NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS user_roles (
    id      UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID        NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role    VARCHAR(50) NOT NULL,
    UNIQUE (user_id, role),
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS user_points_ledger (
    id           UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id      UUID        NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    points_delta INTEGER     NOT NULL,
    source       VARCHAR(100) NOT NULL,
    reference_id UUID,
    running_total INTEGER    NOT NULL DEFAULT 0,
    created_at   TIMESTAMP   NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_user_points_ledger_user_id ON user_points_ledger(user_id);
CREATE INDEX IF NOT EXISTS idx_user_roles_user_id ON user_roles(user_id);
