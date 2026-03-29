-- migration: ads_001
-- description: Create ad_campaigns, ad_placements, ad_impressions, and ad_clicks tables

CREATE TABLE IF NOT EXISTS ad_campaigns (
    id            UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    title         VARCHAR(200) NOT NULL,
    advertiser_id UUID         NOT NULL,
    status        VARCHAR(20)  NOT NULL DEFAULT 'draft'
                  CHECK (status IN ('draft', 'pending_review', 'active', 'paused', 'ended')),
    start_date    DATE         NOT NULL,
    end_date      DATE         NOT NULL,
    budget        NUMERIC(15,2) NOT NULL CHECK (budget > 0),
    targeting     JSONB        NOT NULL DEFAULT '{}',
    created_at    TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS ad_placements (
    id          UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    campaign_id UUID         NOT NULL REFERENCES ad_campaigns(id) ON DELETE CASCADE,
    title       VARCHAR(200) NOT NULL,
    image_url   VARCHAR(500) NOT NULL,
    target_url  VARCHAR(500) NOT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS ad_impressions (
    id               UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    ad_id            UUID        NOT NULL REFERENCES ad_placements(id) ON DELETE CASCADE,
    campaign_id      UUID        NOT NULL REFERENCES ad_campaigns(id) ON DELETE CASCADE,
    viewer_id        UUID,
    placement_zone   VARCHAR(100) NOT NULL,
    impression_token VARCHAR(255) NOT NULL UNIQUE,
    recorded_at      TIMESTAMP   NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS ad_clicks (
    id               UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    ad_id            UUID        NOT NULL REFERENCES ad_placements(id) ON DELETE CASCADE,
    campaign_id      UUID        NOT NULL REFERENCES ad_campaigns(id) ON DELETE CASCADE,
    viewer_id        UUID,
    impression_token VARCHAR(255) NOT NULL,
    clicked_at       TIMESTAMP   NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_ad_impressions_campaign_id ON ad_impressions(campaign_id);
CREATE INDEX IF NOT EXISTS idx_ad_impressions_impression_token ON ad_impressions(impression_token);
CREATE INDEX IF NOT EXISTS idx_ad_clicks_campaign_id ON ad_clicks(campaign_id);
CREATE INDEX IF NOT EXISTS idx_ad_clicks_impression_token ON ad_clicks(impression_token);
