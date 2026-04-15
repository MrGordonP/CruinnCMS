<?php

namespace Cruinn\Module\Forum\Controllers;

use Cruinn\Auth;
use Cruinn\Controllers\BaseController;
use Cruinn\Module\Forum\Forum\ForumManager;

class ForumController extends BaseController
{
    public function index(): void
    {
        $categories = ForumManager::provider()->listCategories(Auth::role());

        $this->render('public/forum/index', [
            'title' => 'Forum',
            'categories' => $categories,
        ]);
    }

    public function category(string $slug): void
    {
        $provider = ForumManager::provider();
        $category = $provider->getCategoryBySlug($slug, Auth::role());

        if (!$category) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Category Not Found']);
            return;
        }

        $subcategories = $provider->getSubcategories((int)$category['id'], Auth::role());
        $breadcrumbs = $provider->getCategoryBreadcrumbs((int)$category['id']);

        $page = max(1, (int)$this->query('page', 1));
        $perPage = 25;
        $threads = $provider->listThreadsByCategory((int)$category['id'], $page, $perPage);
        $total = $provider->countThreadsByCategory((int)$category['id']);
        $totalPages = (int)max(1, ceil($total / $perPage));

        $this->render('public/forum/category', [
            'title' => $category['title'] . ' — Forum',
            'category' => $category,
            'subcategories' => $subcategories,
            'breadcrumbs' => $breadcrumbs,
            'threads' => $threads,
            'page' => $page,
            'totalPages' => $totalPages,
            'canPost' => Auth::check() && Auth::hasRole($category['access_role']),
        ]);
    }

    public function thread(string $id): void
    {
        $provider = ForumManager::provider();
        $thread = $provider->getThread((int)$id);

        if (!$thread) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Thread Not Found']);
            return;
        }

        if (!Auth::hasRole($thread['access_role'])) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Access Denied']);
            return;
        }

        $page = max(1, (int)$this->query('page', 1));
        $perPage = 50;
        $posts = $provider->listPosts((int)$thread['id'], $page, $perPage);
        $postCount = $provider->countPosts((int)$thread['id']);
        $totalPages = (int)max(1, ceil($postCount / $perPage));
        $breadcrumbs = $provider->getCategoryBreadcrumbs((int)$thread['category_id']);

        $this->render('public/forum/thread', [
            'title' => $thread['title'] . ' — Forum',
            'thread' => $thread,
            'breadcrumbs' => $breadcrumbs,
            'posts' => $posts,
            'page' => $page,
            'totalPages' => $totalPages,
            'canReply' => Auth::check() && Auth::hasRole($thread['access_role']) && !$thread['is_locked'],
        ]);
    }

    public function newThreadForm(string $slug): void
    {
        Auth::requireLogin();

        $provider = ForumManager::provider();
        $category = $provider->getCategoryBySlug($slug, Auth::role());

        if (!$category) {
            Auth::flash('error', 'Category not found or access denied.');
            $this->redirect('/forum');
        }

        $breadcrumbs = $provider->getCategoryBreadcrumbs((int)$category['id']);

        $this->render('public/forum/new', [
            'title' => 'New Thread — ' . $category['title'],
            'category' => $category,
            'breadcrumbs' => $breadcrumbs,
            'errors' => [],
            'old' => ['title' => '', 'body_html' => ''],
        ]);
    }

    public function createThread(string $slug): void
    {
        Auth::requireLogin();

        $provider = ForumManager::provider();
        $category = $provider->getCategoryBySlug($slug, Auth::role());

        if (!$category) {
            Auth::flash('error', 'Category not found or access denied.');
            $this->redirect('/forum');
        }

        $title = trim((string)$this->input('title', ''));
        $bodyHtml = trim((string)$this->input('body_html', ''));

        $errors = [];
        if ($title === '' || mb_strlen($title) < 5) {
            $errors['title'] = 'Title must be at least 5 characters.';
        }
        if ($bodyHtml === '' || mb_strlen(strip_tags($bodyHtml)) < 10) {
            $errors['body_html'] = 'Post content must be at least 10 characters.';
        }

        if ($errors) {
            $breadcrumbs = $provider->getCategoryBreadcrumbs((int)$category['id']);
            $this->render('public/forum/new', [
                'title' => 'New Thread — ' . $category['title'],
                'category' => $category,
                'breadcrumbs' => $breadcrumbs,
                'errors' => $errors,
                'old' => ['title' => $title, 'body_html' => $bodyHtml],
            ]);
            return;
        }

        $threadId = $provider->createThread(
            (int)$category['id'],
            (int)Auth::userId(),
            $title,
            sanitise_html($bodyHtml)
        );

        $this->logActivity('create', 'forum_thread', $threadId, $title);
        Auth::flash('success', 'Thread created.');
        $this->redirect('/forum/thread/' . $threadId);
    }

    public function reply(string $id): void
    {
        Auth::requireLogin();

        $provider = ForumManager::provider();
        $thread = $provider->getThread((int)$id);

        if (!$thread) {
            Auth::flash('error', 'Thread not found.');
            $this->redirect('/forum');
        }

        if (!Auth::hasRole($thread['access_role'])) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Access Denied']);
            return;
        }

        if ((int)$thread['is_locked'] === 1) {
            Auth::flash('error', 'This thread is locked.');
            $this->redirect('/forum/thread/' . (int)$thread['id']);
        }

        $bodyHtml = trim((string)$this->input('body_html', ''));
        if ($bodyHtml === '' || mb_strlen(strip_tags($bodyHtml)) < 2) {
            Auth::flash('error', 'Reply cannot be empty.');
            $this->redirect('/forum/thread/' . (int)$thread['id']);
        }

        $postId = $provider->createReply((int)$thread['id'], (int)Auth::userId(), sanitise_html($bodyHtml));
        $this->logActivity('create', 'forum_post', $postId, 'Reply in thread #' . (int)$thread['id']);

        Auth::flash('success', 'Reply posted.');
        $this->redirect('/forum/thread/' . (int)$thread['id']);
    }
}
