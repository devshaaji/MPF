-- migration: projects_001
-- description: Create projects table with status enum

CREATE TABLE IF NOT EXISTS projects (
    id             UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    owner_id       UUID         NOT NULL,
    title          VARCHAR(200) NOT NULL,
    description    TEXT         NOT NULL,
    tags           JSONB        NOT NULL DEFAULT '[]',
    repository_url VARCHAR(500),
    status         VARCHAR(20)  NOT NULL DEFAULT 'draft'
                   CHECK (status IN ('draft', 'published', 'archived')),
    published_at   TIMESTAMP,
    created_at     TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_at     TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_projects_owner_id ON projects(owner_id);
CREATE INDEX IF NOT EXISTS idx_projects_status ON projects(status);
