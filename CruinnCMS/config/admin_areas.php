<?php
/**
 * CruinnCMS — Admin Area Registry
 *
 * Defines grantable admin sections for sub-admin access control.
 * Each area can be granted to roles or positions via admin_area_grants table.
 *
 * Admin role (level >= 100) always has access to all areas without grants.
 *
 * Reserved areas that are NEVER grantable (admin-only):
 *   - User management (users, roles, groups)
 *   - Platform settings (ACP system)
 *   - Site builder (page structure)
 *   - Migrations and maintenance
 *   - Theme management
 */

return [
    // Core engine areas (always available)
    'blog' => [
        'name'        => 'Blog & Articles',
        'description' => 'Create and edit blog posts and articles',
        'icon'        => 'newspaper',
        'routes'      => ['/admin/blog/*'],
    ],

    'events' => [
        'name'        => 'Events',
        'description' => 'Manage event listings and calendar',
        'icon'        => 'calendar-event',
        'routes'      => ['/admin/events/*'],
    ],

    'documents' => [
        'name'        => 'Documents',
        'description' => 'Upload and manage documents library',
        'icon'        => 'file-text',
        'routes'      => ['/admin/documents/*'],
    ],

    'media' => [
        'name'        => 'Media Library',
        'description' => 'Upload and manage images, files, and media',
        'icon'        => 'photo',
        'routes'      => ['/admin/media/*'],
    ],

    'menus' => [
        'name'        => 'Menus',
        'description' => 'Edit navigation menus',
        'icon'        => 'layout-navbar',
        'routes'      => ['/admin/menus/*'],
    ],

    // Module-provided areas (conditional — loaded if module active)
    'forum' => [
        'name'        => 'Forum Moderation',
        'description' => 'Moderate forum posts and manage categories',
        'icon'        => 'messages',
        'routes'      => ['/admin/forum/*'],
        'module'      => 'forum',
    ],

    'mailout' => [
        'name'        => 'Mailout',
        'description' => 'Send bulk emails and manage campaigns',
        'icon'        => 'mail',
        'routes'      => ['/admin/mailout/*'],
        'module'      => 'mailout',
    ],

    'mailbox' => [
        'name'        => 'Mailbox',
        'description' => 'Internal messaging system',
        'icon'        => 'inbox',
        'routes'      => ['/admin/mailbox/*'],
        'module'      => 'mailbox',
    ],

    'membership' => [
        'name'        => 'Membership',
        'description' => 'Manage memberships and renewals',
        'icon'        => 'user-check',
        'routes'      => ['/admin/membership/*'],
        'module'      => 'membership',
    ],

    'payments' => [
        'name'        => 'Payments',
        'description' => 'Process and track payments',
        'icon'        => 'credit-card',
        'routes'      => ['/admin/payments/*'],
        'module'      => 'payments',
    ],

    'forms' => [
        'name'        => 'Forms',
        'description' => 'Build and manage data collection forms',
        'icon'        => 'forms',
        'routes'      => ['/admin/forms/*'],
        'module'      => 'forms',
    ],

    'social' => [
        'name'        => 'Social Features',
        'description' => 'Moderate user-generated content and social interactions',
        'icon'        => 'users',
        'routes'      => ['/admin/social/*'],
        'module'      => 'social',
    ],

    'organisation' => [
        'name'        => 'Organisation',
        'description' => 'Manage organisation structure and positions',
        'icon'        => 'building',
        'routes'      => ['/admin/organisation/*'],
        'module'      => 'organisation',
    ],
];
