-- migration: news_001
-- description: Create news_articles and news_comments tables

CREATE TABLE IF NOT EXISTS news_articles (
    id           UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    author_id    UUID         NOT NULL,
    title        VARCHAR(300) NOT NULL,
    body         TEXT         NOT NULL,
    category     VARCHAR(100),
    tags         JSONB        NOT NULL DEFAULT '[]',
    status       VARCHAR(20)  NOT NULL DEFAULT 'draft'
                 CHECK (status IN ('draft', 'published', 'archived')),
    published_at TIMESTAMP,
    created_at   TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS news_comments (
    id           UUID      PRIMARY KEY DEFAULT gen_random_uuid(),
    news_id      UUID      NOT NULL REFERENCES news_articles(id) ON DELETE CASCADE,
    commenter_id UUID      NOT NULL,
    body         TEXT      NOT NULL,
    status       VARCHAR(20) NOT NULL DEFAULT 'visible' CHECK (status IN ('visible', 'removed')),
    created_at   TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_news_articles_author_id ON news_articles(author_id);
CREATE INDEX IF NOT EXISTS idx_news_articles_status ON news_articles(status);
CREATE INDEX IF NOT EXISTS idx_news_comments_news_id ON news_comments(news_id);
