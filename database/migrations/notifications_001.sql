-- migration: notifications_001
-- description: Create notification_preferences and notification_messages tables

CREATE TABLE IF NOT EXISTS notification_preferences (
    id         UUID      PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id    UUID      NOT NULL UNIQUE,
    channels   JSONB     NOT NULL DEFAULT '{"email": true, "push": true, "in_app": true}',
    topics     JSONB     NOT NULL DEFAULT '{"news": true, "forum": true, "scholarship": true}',
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS notification_messages (
    id               UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id          UUID        NOT NULL,
    template         VARCHAR(100) NOT NULL,
    channel          VARCHAR(20) NOT NULL CHECK (channel IN ('email', 'push', 'in_app')),
    idempotency_key  VARCHAR(255) NOT NULL UNIQUE,
    status           VARCHAR(20) NOT NULL DEFAULT 'queued'
                     CHECK (status IN ('queued', 'delivered', 'failed', 'skipped')),
    provider_ref     VARCHAR(255),
    payload          JSONB       NOT NULL DEFAULT '{}',
    queued_at        TIMESTAMP   NOT NULL DEFAULT NOW(),
    delivered_at     TIMESTAMP,
    failed_at        TIMESTAMP,
    retry_count      INTEGER     NOT NULL DEFAULT 0,
    created_at       TIMESTAMP   NOT NULL DEFAULT NOW(),
    updated_at       TIMESTAMP   NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_notification_preferences_user_id ON notification_preferences(user_id);
CREATE INDEX IF NOT EXISTS idx_notification_messages_user_id ON notification_messages(user_id);
CREATE INDEX IF NOT EXISTS idx_notification_messages_status ON notification_messages(status);
CREATE INDEX IF NOT EXISTS idx_notification_messages_idempotency_key ON notification_messages(idempotency_key);
