-- migration: scholarships_001
-- description: Create scholarships and scholarship_applications tables
-- module: Scholarships
-- Cross-module references (form_id, applicant_id, form_submission_id, awarded_by, created_by)
-- are stored as plain UUIDs with no foreign-key constraints; integration is event-driven only.

CREATE TABLE IF NOT EXISTS scholarships (
    id            UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    title         VARCHAR(255) NOT NULL,
    description   TEXT,
    form_id       UUID         NOT NULL, -- Reference to forms module; no FK (cross-module boundary)
    deadline      DATE,
    max_recipients INT,
    status        VARCHAR(20)  NOT NULL DEFAULT 'open'
                               CHECK (status IN ('draft', 'open', 'closed', 'archived')),
    created_by    UUID         NOT NULL, -- Reference to users module; no FK (cross-module boundary)
    created_at    TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS scholarship_applications (
    id                UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    scholarship_id    UUID         NOT NULL REFERENCES scholarships(id) ON DELETE RESTRICT,
    applicant_id      UUID         NOT NULL, -- Reference to users module; no FK (cross-module boundary)
    form_submission_id UUID        NOT NULL, -- Reference to forms.form_submissions; no FK (cross-module boundary)
    status            VARCHAR(20)  NOT NULL DEFAULT 'submitted'
                                   CHECK (status IN ('submitted', 'awarded', 'rejected')),
    cover_letter      TEXT,
    award_amount      DECIMAL(10,2),
    award_notes       TEXT,
    awarded_by        UUID, -- Reference to users module; no FK (cross-module boundary)
    applied_at        TIMESTAMP    NOT NULL DEFAULT NOW(),
    resolved_at       TIMESTAMP,
    created_at        TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_at        TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_scholarship_applications_scholarship_id ON scholarship_applications(scholarship_id);
CREATE INDEX idx_scholarship_applications_applicant_id  ON scholarship_applications(applicant_id);
CREATE INDEX idx_scholarship_applications_status        ON scholarship_applications(status);
