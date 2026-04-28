-- ============================================================
-- Migration 007: Social Media Management
-- Adds tables for social account connections, post tracking,
-- and a unified inbox for comments/messages across platforms.
-- ============================================================

-- ── Social platform accounts (OAuth tokens, page IDs, etc.) ──
CREATE TABLE IF NOT EXISTS social_accounts (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    platform        VARCHAR(30)   NOT NULL COMMENT 'facebook, twitter, instagram',
    account_name    VARCHAR(255)  NOT NULL DEFAULT '' COMMENT 'Display name / page name',
    account_id      VARCHAR(255)  NOT NULL DEFAULT '' COMMENT 'Platform-specific account/page ID',
    access_token    TEXT          NULL COMMENT 'OAuth access token (encrypted at rest)',
    refresh_token   TEXT          NULL COMMENT 'OAuth refresh token if applicable',
    token_expires   DATETIME      NULL COMMENT 'When the access token expires',
    page_token      TEXT          NULL COMMENT 'Facebook Page token (separate from user token)',
    extra           JSON          NULL COMMENT 'Platform-specific metadata',
    is_active       TINYINT(1)    NOT NULL DEFAULT 1,
    connected_at    DATETIME      NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_platform (platform)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Posts published / scheduled to social platforms ───────────
CREATE TABLE IF NOT EXISTS social_posts (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    social_account_id INT UNSIGNED NOT NULL,
    platform        VARCHAR(30)   NOT NULL,
    platform_post_id VARCHAR(255) NULL COMMENT 'ID returned by the platform after posting',
    content_type    VARCHAR(50)   NULL COMMENT 'article, event, custom',
    content_id      INT UNSIGNED  NULL COMMENT 'FK to articles/events if content_type set',
    message         TEXT          NOT NULL DEFAULT '',
    media_url       TEXT          NULL COMMENT 'Image/video URL attached',
    link_url        TEXT          NULL COMMENT 'Link included in the post',
    status          ENUM('draft','scheduled','published','failed') NOT NULL DEFAULT 'draft',
    scheduled_at    DATETIME      NULL,
    published_at    DATETIME      NULL,
    platform_data   JSON          NULL COMMENT 'Metrics: likes, shares, comments count',
    error_message   TEXT          NULL,
    created_by      INT UNSIGNED  NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_account  (social_account_id),
    KEY idx_status   (status),
    KEY idx_content  (content_type, content_id),
    CONSTRAINT fk_sp_account FOREIGN KEY (social_account_id) REFERENCES social_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Unified inbox: comments + messages from all platforms ────
CREATE TABLE IF NOT EXISTS social_inbox (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    social_account_id INT UNSIGNED NOT NULL,
    platform        VARCHAR(30)   NOT NULL,
    message_type    ENUM('comment','message','mention','reply') NOT NULL DEFAULT 'comment',
    platform_msg_id VARCHAR(255)  NULL COMMENT 'Platform-specific message/comment ID',
    platform_post_id VARCHAR(255) NULL COMMENT 'Which post the comment is on (if applicable)',
    author_name     VARCHAR(255)  NOT NULL DEFAULT '',
    author_id       VARCHAR(255)  NULL COMMENT 'Platform user ID of the sender',
    author_avatar   TEXT          NULL,
    body            TEXT          NOT NULL DEFAULT '',
    is_read         TINYINT(1)    NOT NULL DEFAULT 0,
    is_starred      TINYINT(1)    NOT NULL DEFAULT 0,
    replied         TINYINT(1)    NOT NULL DEFAULT 0,
    reply_text      TEXT          NULL,
    platform_data   JSON          NULL COMMENT 'Extra metadata (likes, attachments, etc.)',
    received_at     DATETIME      NOT NULL COMMENT 'When the message was received on the platform',
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_inbox_account (social_account_id),
    KEY idx_inbox_read    (is_read),
    KEY idx_inbox_type    (message_type),
    KEY idx_inbox_date    (received_at),
    CONSTRAINT fk_si_account FOREIGN KEY (social_account_id) REFERENCES social_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Track which content was distributed where ────────────────
CREATE TABLE IF NOT EXISTS content_distributions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    content_type    VARCHAR(50)   NOT NULL COMMENT 'article, event, custom',
    content_id      INT UNSIGNED  NULL,
    channel_type    VARCHAR(30)   NOT NULL COMMENT 'social, email',
    channel_id      INT UNSIGNED  NULL COMMENT 'social_account.id or mailing_list.id',
    channel_name    VARCHAR(255)  NOT NULL DEFAULT '',
    status          ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    sent_at         DATETIME      NULL,
    error_message   TEXT          NULL,
    created_by      INT UNSIGNED  NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_cd_content (content_type, content_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
