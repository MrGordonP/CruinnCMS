-- Article editor state (mirrors cruinn_page_state)
CREATE TABLE IF NOT EXISTS article_edit_state (
    article_id      INT UNSIGNED NOT NULL,
    current_edit_seq INT UNSIGNED NOT NULL DEFAULT 0,
    max_edit_seq     INT UNSIGNED NOT NULL DEFAULT 0,
    last_edited_at   DATETIME     DEFAULT NULL,
    PRIMARY KEY (article_id),
    CONSTRAINT fk_aes_article FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Article draft blocks (mirrors cruinn_draft_blocks)
CREATE TABLE IF NOT EXISTS article_draft_blocks (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    article_id      INT UNSIGNED    NOT NULL,
    edit_seq        INT UNSIGNED    NOT NULL,
    block_id        VARCHAR(20)     NOT NULL,
    block_type      VARCHAR(40)     NOT NULL,
    inner_html      MEDIUMTEXT      DEFAULT NULL,
    css_props       LONGTEXT        DEFAULT NULL,
    block_config    LONGTEXT        DEFAULT NULL,
    sort_order      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    parent_block_id VARCHAR(20)     DEFAULT NULL,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    is_deletion     TINYINT(1)      NOT NULL DEFAULT 0,
    prev_id         INT UNSIGNED    DEFAULT NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_adb_article_active (article_id, is_active),
    KEY idx_adb_prev (prev_id),
    CONSTRAINT fk_adb_article FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
