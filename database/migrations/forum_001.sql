-- migration: forum_001
-- description: Create forum_topics and forum_replies tables

CREATE TABLE IF NOT EXISTS forum_topics (
    id          UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    author_id   UUID         NOT NULL,
    title       VARCHAR(300) NOT NULL,
    body        TEXT         NOT NULL,
    tags        JSONB        NOT NULL DEFAULT '[]',
    status      VARCHAR(20)  NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'closed', 'removed')),
    reply_count INTEGER      NOT NULL DEFAULT 0,
    created_at  TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS forum_replies (
    id         UUID      PRIMARY KEY DEFAULT gen_random_uuid(),
    topic_id   UUID      NOT NULL REFERENCES forum_topics(id) ON DELETE CASCADE,
    author_id  UUID      NOT NULL,
    body       TEXT      NOT NULL,
    status     VARCHAR(20) NOT NULL DEFAULT 'visible' CHECK (status IN ('visible', 'removed')),
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_forum_topics_author_id ON forum_topics(author_id);
CREATE INDEX IF NOT EXISTS idx_forum_replies_topic_id ON forum_replies(topic_id);
CREATE INDEX IF NOT EXISTS idx_forum_replies_author_id ON forum_replies(author_id);
