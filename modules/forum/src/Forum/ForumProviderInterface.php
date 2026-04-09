<?php

namespace Cruinn\Module\Forum\Forum;

interface ForumProviderInterface
{
    public function listCategories(?string $viewerRole = null): array;

    public function getCategoryBySlug(string $slug, ?string $viewerRole = null): ?array;

    public function getSubcategories(int $parentId, ?string $viewerRole = null): array;

    public function getCategoryBreadcrumbs(int $categoryId): array;

    public function listThreadsByCategory(int $categoryId, int $page = 1, int $perPage = 25): array;

    public function countThreadsByCategory(int $categoryId): int;

    public function getThread(int $threadId): ?array;

    public function listPosts(int $threadId, int $page = 1, int $perPage = 50): array;

    public function countPosts(int $threadId): int;

    public function createThread(int $categoryId, int $userId, string $title, string $bodyHtml): int;

    public function createReply(int $threadId, int $userId, string $bodyHtml): int;
}
