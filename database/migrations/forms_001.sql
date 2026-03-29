-- migration: forms_001
-- description: Create forms, form_fields, and form_submissions tables

CREATE TABLE IF NOT EXISTS forms (
    id          UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    title       VARCHAR(200) NOT NULL,
    description TEXT,
    created_by  UUID         NOT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS form_fields (
    id         UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    form_id    UUID        NOT NULL REFERENCES forms(id) ON DELETE CASCADE,
    field_key  VARCHAR(100) NOT NULL,
    field_type VARCHAR(20) NOT NULL CHECK (field_type IN ('text', 'textarea', 'select', 'checkbox', 'date')),
    label      VARCHAR(300) NOT NULL,
    is_required BOOLEAN    NOT NULL DEFAULT FALSE,
    options    JSONB,
    sort_order INTEGER     NOT NULL DEFAULT 0,
    created_at TIMESTAMP   NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS form_submissions (
    id           UUID      PRIMARY KEY DEFAULT gen_random_uuid(),
    form_id      UUID      NOT NULL REFERENCES forms(id) ON DELETE RESTRICT,
    submitter_id UUID      NOT NULL,
    answers      JSONB     NOT NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_form_fields_form_id ON form_fields(form_id);
CREATE INDEX IF NOT EXISTS idx_form_submissions_form_id ON form_submissions(form_id);
CREATE INDEX IF NOT EXISTS idx_form_submissions_submitter_id ON form_submissions(submitter_id);
