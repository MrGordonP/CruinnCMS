<?php

namespace Cruinn\Services;

use Cruinn\App;
use Cruinn\Auth;
use Cruinn\Database;
use Cruinn\Modules\ModuleRegistry;
use Cruinn\Module\Forum\Forum\ForumManager;

class SubjectThreadProvisionService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function ensurePublishedContentThread(
        string $contentType,
        int $contentId,
        int $subjectId,
        string $title,
        string $slug,
        string $summary = '',
        ?int $authorId = null
    ): ?int {
        if ($contentId <= 0 || $subjectId <= 0 || trim($title) === '' || trim($slug) === '') {
            return null;
        }

        if (!ModuleRegistry::isActive('forum')) {
            return null;
        }

        $provider = ForumManager::provider();
        $existing = $provider->getThreadBySubjectId($subjectId, 100);
        if ($existing) {
            return (int) ($existing['id'] ?? 0) ?: null;
        }

        $categoryId = $this->resolveForumCategoryId();
        if ($categoryId <= 0) {
            return null;
        }

        $userId = (int) ($authorId ?? Auth::userId());
        if ($userId <= 0) {
            return null;
        }

        $threadBody = $this->buildThreadBody($contentType, $title, $slug, $summary);
        $threadId = (int) $provider->createThread($categoryId, $userId, $title, $threadBody, $subjectId);

        return $threadId > 0 ? $threadId : null;
    }

    private function resolveForumCategoryId(): int
    {
        $settingsRaw = $this->db->fetchColumn('SELECT settings FROM module_config WHERE slug = ? LIMIT 1', ['forum']);
        $settings = is_string($settingsRaw) ? (json_decode($settingsRaw, true) ?: []) : [];

        $configuredCategoryId = (int) ($settings['subject_thread_category_id'] ?? 0);
        if ($configuredCategoryId > 0) {
            $exists = (int) $this->db->fetchColumn(
                'SELECT COUNT(*) FROM forum_categories WHERE id = ? AND is_active = 1',
                [$configuredCategoryId]
            );
            if ($exists > 0) {
                return $configuredCategoryId;
            }
        }

        return (int) ($this->db->fetchColumn(
            'SELECT id FROM forum_categories WHERE is_active = 1 ORDER BY sort_order ASC, title ASC LIMIT 1'
        ) ?: 0);
    }

    private function buildThreadBody(string $contentType, string $title, string $slug, string $summary): string
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $parts = [];
        $parts[] = '<p>Discussion thread for ' . $safeTitle . '.</p>';

        $trimmedSummary = trim(strip_tags($summary));
        if ($trimmedSummary !== '') {
            if (mb_strlen($trimmedSummary) > 280) {
                $trimmedSummary = rtrim(mb_substr($trimmedSummary, 0, 277)) . '...';
            }
            $parts[] = '<p>' . htmlspecialchars($trimmedSummary, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        $url = $this->buildContentPublicUrl($contentType, $slug);
        if ($url !== '') {
            $parts[] = '<p><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">View the original content</a></p>';
        }

        return implode("\n", $parts);
    }

    private function buildContentPublicUrl(string $contentType, string $slug): string
    {
        $siteUrl = rtrim((string) App::config('site.url', ''), '/');
        if ($siteUrl === '') {
            return '';
        }

        $basePath = match ($contentType) {
            'article' => $this->resolveContentBasePath('blog.list_page_id', '/blog'),
            'event' => $this->resolveContentBasePath('events.list_page_id', '/events'),
            default => '',
        };

        if ($basePath === '') {
            return '';
        }

        return $siteUrl . rtrim($basePath, '/') . '/' . ltrim($slug, '/');
    }

    private function resolveContentBasePath(string $settingKey, string $fallback): string
    {
        $pageId = (int) ($this->db->fetchColumn(
            "SELECT `value` FROM settings WHERE `group` IN ('blog', 'events') AND `key` = ? LIMIT 1",
            [$settingKey]
        ) ?: 0);

        if ($pageId <= 0) {
            return $fallback;
        }

        $slug = (string) ($this->db->fetchColumn('SELECT slug FROM pages_index WHERE id = ? LIMIT 1', [$pageId]) ?: '');
        return $slug !== '' ? '/' . trim($slug, '/') : $fallback;
    }
}
