<?php $blogNav = $blogNav ?? 'overview'; ?>
<nav class="blog-admin-nav" aria-label="Blog admin navigation">
    <a href="/admin/blog" class="blog-admin-nav-link<?= $blogNav === 'overview' ? ' is-active' : '' ?>">Overview</a>
    <a href="/admin/blog/posts" class="blog-admin-nav-link<?= $blogNav === 'posts' ? ' is-active' : '' ?>">Posts</a>
    <a href="/admin/blog/settings" class="blog-admin-nav-link<?= $blogNav === 'settings' ? ' is-active' : '' ?>">Settings</a>
    <a href="/admin/blog/profiles" class="blog-admin-nav-link<?= $blogNav === 'profiles' ? ' is-active' : '' ?>">Profiles</a>
</nav>

<style>
.blog-admin-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin: 0 0 1.5rem;
}

.blog-admin-nav-link {
    display: inline-flex;
    align-items: center;
    padding: 0.55rem 0.9rem;
    border: 1px solid var(--border-color, #d1d5db);
    border-radius: 999px;
    color: var(--color-text, #111827);
    text-decoration: none;
    font-weight: 600;
    background: #fff;
}

.blog-admin-nav-link.is-active {
    border-color: var(--color-primary, #1d9e75);
    color: var(--color-primary, #1d9e75);
    background: color-mix(in srgb, var(--color-primary, #1d9e75) 8%, white);
}

</style>
