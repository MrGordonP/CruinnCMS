# Dry Run — Beta.7 Module Build-out
> Pre-implementation review document. No files have been changed.
> Each section documents exactly what will be added/changed and where, with proposed code.

---

## Current State Summary (from audit)

| Module | Controller | Templates | Schema | Dashboard card | ACP sidebar |
|---|---|---|---|---|---|
| Events | ✅ full | ✅ full | ✅ | ✅ `dashboard_sections` declared | ✅ |
| Membership | ✅ full | partial | ✅ | ✅ `dashboard_sections` declared | ✅ |
| Organisation | partial | partial | minimal | ✅ declared | ✅ |
| DriveSpace | ✅ full (950 lines) | ✅ 4 templates | ✅ | ✅ declared | ✅ |
| Documents | ✅ full | ✅ 3 templates + widget | ✅ | ✅ declared | ✅ |
| GDPR | ✅ functional | public only (no admin) | ✅ | ✅ dashboard_sections | ❌ no `acp_sections` |

**Root cause of missing dashboard cards:** The dashboard template (`templates/dashboard.php`) is **hardcoded** — it does not read `dashboard_sections` from `ModuleRegistry`. Membership, Organisation, DriveSpace, Documents, and GDPR all declare `dashboard_sections` correctly. The template just doesn't use them.

---

## Task 1 — Dashboard: Module View & Missing Cards

### Problem
The admin dashboard template is a static PHP file with hardcoded group tiles. Modules that declare `dashboard_sections` are silently ignored. There is a `ModuleRegistry::acpSections()` method that aggregates them — it is never called.

### Approach
Two sub-tasks:

**1a. Render module-declared dashboard sections** — extend the hardcoded admin view to append a dynamic "Modules" tile for any active module whose `dashboard_sections` entry doesn't match one of the already-hardcoded groups (`Settings`, `Site Builder`, `Content`, `Community`, `Social & Communications`, `People`). This surfaces Membership, Organisation, Documents, DriveSpace, and GDPR immediately without restructuring the existing layout.

**1b. Module view toggle** — add `?view=modules` to the dashboard URL. The controller stores the preference in `$_SESSION`. In modules view each active module gets its own tile.

---

### 1a. Changes to `templates/dashboard.php`

**After the existing `People` widget block, before `</div><!-- .dashboard-widget-stack -->`**, add:

```php
<?php
// Render any active module that has dashboard_sections not already covered above
$coveredGroups = ['Settings', 'Site Builder', 'Content', 'Community', 'Social & Communications', 'People', 'Organisation'];
$moduleLinks   = [];
foreach (\Cruinn\Modules\ModuleRegistry::all() as $slug => $def) {
    if (!\Cruinn\Modules\ModuleRegistry::isActive($slug)) { continue; }
    foreach ($def['dashboard_sections'] ?? [] as $section) {
        if (in_array($section['group'], $coveredGroups, true)) { continue; }
        $moduleLinks[$section['group']][] = $section;
    }
}
foreach ($moduleLinks as $group => $sections): ?>
<div class="dashboard-widget">
    <div class="activity-header">
        <h2><?= e($group) ?></h2>
    </div>
    <div class="dash-quick-grid">
        <?php foreach ($sections as $s): ?>
        <a href="<?= url($s['url']) ?>" class="dash-quick-link">
            <span class="dash-quick-icon"><?= $s['icon'] ?? '🧩' ?></span>
            <span><?= e($s['label']) ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>
```

Then add the **Organisation** group tile explicitly (it is declared in `organisation/module.php` but not in the hardcoded template). Organisation, Documents, and DriveSpace all use `group => 'Organisation'` or `group => 'Community'` — DriveSpace already falls under Community so it would appear there. Wait — DriveSpace declares `group => 'Community'` but Community only checks `isActive('file-manager')` for the Files link, not `drivespace`. Fix that too.

**Changes to the Community widget in `templates/dashboard.php`:**

```php
// Change condition from:
<?php if (\Cruinn\Modules\ModuleRegistry::isActive('events') || \Cruinn\Modules\ModuleRegistry::isActive('forum') || \Cruinn\Modules\ModuleRegistry::isActive('file-manager')): ?>

// To:
<?php if (\Cruinn\Modules\ModuleRegistry::isActive('events') || \Cruinn\Modules\ModuleRegistry::isActive('forum') || \Cruinn\Modules\ModuleRegistry::isActive('file-manager') || \Cruinn\Modules\ModuleRegistry::isActive('drivespace')): ?>
```

And inside the Community grid, add:

```php
<?php if (\Cruinn\Modules\ModuleRegistry::isActive('drivespace')): ?>
<a href="<?= url('/drivespace') ?>" class="dash-quick-link">
    <span class="dash-quick-icon">📁</span><span>Drivespace</span>
</a>
<?php endif; ?>
```

Add a full **Organisation** tile after People:

```php
<?php if (\Cruinn\Modules\ModuleRegistry::isActive('organisation') || \Cruinn\Modules\ModuleRegistry::isActive('documents')): ?>
<div class="dashboard-widget">
    <div class="activity-header">
        <h2>Organisation</h2>
        <?php if (\Cruinn\Modules\ModuleRegistry::isActive('organisation')): ?>
        <a href="<?= url('/organisation') ?>" class="btn btn-primary btn-small">Open Workspace</a>
        <?php endif; ?>
    </div>
    <div class="dash-quick-grid">
        <?php if (\Cruinn\Modules\ModuleRegistry::isActive('organisation')): ?>
        <a href="<?= url('/organisation') ?>" class="dash-quick-link">
            <span class="dash-quick-icon">🏢</span><span>Workspace</span>
        </a>
        <a href="<?= url('/organisation/discussions') ?>" class="dash-quick-link">
            <span class="dash-quick-icon">💬</span><span>Discussions</span>
        </a>
        <?php endif; ?>
        <?php if (\Cruinn\Modules\ModuleRegistry::isActive('documents')): ?>
        <a href="<?= url('/documents') ?>" class="dash-quick-link">
            <span class="dash-quick-icon">📄</span><span>Documents</span>
        </a>
        <?php endif; ?>
        <?php if (\Cruinn\Modules\ModuleRegistry::isActive('membership')): ?>
        <a href="<?= url('/admin/membership') ?>" class="dash-quick-link">
            <span class="dash-quick-icon">👥</span><span>Membership</span>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
```

**Move Membership out of People** (it is currently buried in the hardcoded People tile which only shows Users/Roles/Groups). Remove the `isActive('membership')` check there if present; it doesn't currently exist in the hardcoded People tile — Membership just isn't shown at all. The above Organisation tile picks it up.

**GDPR** — appears in Settings section. Already declared in `dashboard_sections` with `group => 'Settings'`. The Settings tile is fully hardcoded and already has a conditional GDPR link (`/admin/settings/gdpr`). ✅ Already correct.

---

### 1b. Module View Toggle

**`src/Admin/Controllers/AdminController.php`** — in the `dashboard()` method, read the view preference:

```php
$view = $_GET['view'] ?? $_SESSION['dashboard_view'] ?? 'groups';
if (in_array($view, ['groups', 'modules'], true)) {
    $_SESSION['dashboard_view'] = $view;
}
$this->template->assign('dashboardView', $view);
```

**`templates/dashboard.php`** — add toggle buttons at the top of the admin block:

```php
<div class="dashboard-view-toggle">
    <a href="?view=groups" class="btn btn-small <?= ($dashboardView ?? 'groups') === 'groups' ? 'btn-primary' : 'btn-outline' ?>">By Group</a>
    <a href="?view=modules" class="btn btn-small <?= ($dashboardView ?? 'groups') === 'modules' ? 'btn-primary' : 'btn-outline' ?>">By Module</a>
</div>
```

**Module view rendering** — replace the `dashboard-widget-stack` entirely when `$dashboardView === 'modules'`:

```php
<?php if ($dashboardView === 'modules'): ?>
<div class="dashboard-widget-stack">
    <?php foreach (\Cruinn\Modules\ModuleRegistry::all() as $slug => $def):
        if (!\Cruinn\Modules\ModuleRegistry::isActive($slug)) { continue; }
        $sections = $def['dashboard_sections'] ?? [];
        if (empty($sections)) { continue; }
    ?>
    <div class="dashboard-widget">
        <div class="activity-header">
            <h2><?= e($def['name']) ?> <small class="text-muted">v<?= e($def['version']) ?></small></h2>
        </div>
        <div class="dash-quick-grid">
            <?php foreach ($sections as $s): ?>
            <a href="<?= url($s['url']) ?>" class="dash-quick-link">
                <span class="dash-quick-icon"><?= $s['icon'] ?? '🧩' ?></span>
                <span><?= e($s['label']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
    <!-- existing hardcoded group tiles -->
<?php endif; ?>
```

**Files affected:**
- `CruinnCMS/templates/dashboard.php`
- `CruinnCMS/src/Admin/Controllers/AdminController.php` (or whatever controller serves `/admin`)

---

## Task 2 — GDPR: Add `acp_sections`

`gdpr/module.php` declares `dashboard_sections` but no `acp_sections`, so it doesn't appear in the admin sidebar.

**Change in `modules/gdpr/module.php`:**

```php
// Add alongside dashboard_sections:
'acp_sections' => [
    ['group' => 'Settings', 'label' => 'GDPR', 'url' => '/admin/settings/gdpr', 'icon' => '🔒'],
],
```

**Also needed — Admin settings page for GDPR.** Currently there is a GDPR link in the Settings tile going to `/admin/settings/gdpr` but no route for it. Need to add:
- Route: `GET /admin/settings/gdpr` and `POST /admin/settings/gdpr` in `gdpr/module.php` routes
- Handler method: in `GdprController` (or a new `GdprAdminController`)
- Template: `gdpr/templates/admin/settings/gdpr.php`
- Settings to expose: data retention period (days), cookie consent banner enabled (bool), privacy policy page URL, right-to-erasure contact email
- These are stored in `module_config` as settings JSON (already wired via `ModuleRegistry`)

**Files affected:**
- `CruinnCMS/modules/gdpr/module.php`
- `CruinnCMS/modules/gdpr/src/Controllers/GdprController.php` (add `adminSettings()` and `saveAdminSettings()`)
- `CruinnCMS/modules/gdpr/templates/admin/settings/gdpr.php` (new)

---

## Task 3 — DriveSpace: Flesh Out UI

**Current state:** Controller is fully implemented (950 lines, all 14 routes covered). Schema is complete (folders, files, file_versions, file_shares, file_publications). Four templates exist (index, show, upload, compose). Migration seeds 3 default folders.

**Gap assessment:**
- No quota tracking (no `quota_used` / `quota_limit` columns on users or a separate quota table)
- No admin quota management UI
- `dashboard_sections` points to `/drivespace` — this is a user-facing route, not admin. Fine.
- No admin route for managing all users' files or quotas

**Changes:**

### 3a. Quota tracking — new migration `002_drivespace_quotas.sql`

```sql
-- Add per-user quota tracking
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `drivespace_quota_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 524288000 -- 500MB default
        AFTER `last_login_at`,
    ADD COLUMN IF NOT EXISTS `drivespace_used_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0
        AFTER `drivespace_quota_bytes`;
```

> **Note:** Storing `used_bytes` as a denormalised counter is faster than `SUM(file_size)` on every page load. It is incremented on upload and decremented on delete.

### 3b. Admin quota management

Add to `drivespace/module.php` routes:

```php
$router->get('/admin/drivespace',             [FileManagerAdminController::class, 'index']);
$router->post('/admin/drivespace/{id}/quota', [FileManagerAdminController::class, 'setQuota']);
```

New file: `drivespace/src/Controllers/FileManagerAdminController.php`
- `index()` — lists all users with their quota_used / quota_limit, sortable
- `setQuota(int $userId)` — updates `drivespace_quota_bytes`

New template: `drivespace/templates/admin/files/admin-index.php`

### 3c. `acp_sections` addition

```php
'acp_sections' => [
    ['group' => 'Community', 'label' => 'Drivespace', 'url' => '/drivespace', 'icon' => '📁'],
    ['group' => 'Community', 'label' => 'Drivespace Admin', 'url' => '/admin/drivespace', 'icon' => '⚙️'],
],
```

**Files affected:**
- `CruinnCMS/modules/drivespace/module.php`
- `CruinnCMS/modules/drivespace/migrations/002_drivespace_quotas.sql` (new)
- `CruinnCMS/modules/drivespace/src/Controllers/FileManagerAdminController.php` (new)
- `CruinnCMS/modules/drivespace/templates/admin/files/admin-index.php` (new)
- `CruinnCMS/modules/drivespace/src/Controllers/FileManagerController.php` — update `upload()` to increment `drivespace_used_bytes` and `delete()` to decrement it; add quota check on upload

---

## Task 4 — Documents: Gap Check

**Current state:** All 11 routes implemented. Schema complete. Templates: index, show, upload, and a widget. `DocumentDashboardService` exists.

**Gaps:**
- No admin route for managing all documents across users (only user-scoped routes exist)
- No category/tagging on documents (schema has no `category` column)
- No access control beyond implicit (any logged-in user can see all documents)

**Changes:**

### 4a. Categories — new migration `002_documents_categories.sql`

```sql
CREATE TABLE IF NOT EXISTS `document_categories` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL,
    `slug`        VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_doc_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `documents`
    ADD COLUMN IF NOT EXISTS `category_id` INT UNSIGNED NULL AFTER `title`,
    ADD KEY IF NOT EXISTS `idx_documents_category` (`category_id`);

SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND CONSTRAINT_NAME = 'fk_documents_category');
SET @sql = IF(@fk = 0, 'ALTER TABLE `documents` ADD CONSTRAINT `fk_documents_category` FOREIGN KEY (`category_id`) REFERENCES `document_categories` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
```

### 4b. Admin route

Add to `documents/module.php` routes:

```php
$router->get('/admin/documents',              [DocumentAdminController::class, 'index']);
$router->get('/admin/documents/categories',   [DocumentAdminController::class, 'categories']);
$router->post('/admin/documents/categories',  [DocumentAdminController::class, 'createCategory']);
$router->post('/admin/documents/{id}/approve',[DocumentAdminController::class, 'approve']);
$router->post('/admin/documents/{id}/delete', [DocumentAdminController::class, 'delete']);
```

New file: `documents/src/Controllers/DocumentAdminController.php`

**Files affected:**
- `CruinnCMS/modules/documents/module.php`
- `CruinnCMS/modules/documents/migrations/002_documents_categories.sql` (new)
- `CruinnCMS/modules/documents/src/Controllers/DocumentAdminController.php` (new)
- `CruinnCMS/modules/documents/templates/admin/documents/admin-index.php` (new)
- `CruinnCMS/modules/documents/templates/admin/documents/categories.php` (new)

---

## Task 5 — Organisation Workspace: Flesh Out

**Current state:** Discussions and inbox implemented. Dashboard, three discussion templates. Documents sub-section exists in templates but no routes in `module.php` lead to them (they appear to be leftover stubs — documents have their own module).

**What's genuinely missing:**
- Organisation profile (name, address, registration number)
- Officer / committee structure
- Meetings (agenda, minutes, attendance)
- Finance tracking (see Task 6)
- Admin management routes for the above

### 5a. New migration `003_organisation_profile.sql`

```sql
CREATE TABLE IF NOT EXISTS `organisation_profile` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`            VARCHAR(255) NOT NULL,
    `legal_name`      VARCHAR(255) NULL,
    `registration_no` VARCHAR(100) NULL,
    `address_line1`   VARCHAR(255) NULL,
    `address_line2`   VARCHAR(255) NULL,
    `city`            VARCHAR(100) NULL,
    `country`         VARCHAR(100) NULL DEFAULT 'Ireland',
    `email`           VARCHAR(255) NULL,
    `phone`           VARCHAR(50)  NULL,
    `website`         VARCHAR(255) NULL,
    `description`     TEXT NULL,
    `logo_path`       VARCHAR(500) NULL,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `organisation_profile` (`id`, `name`) VALUES (1, 'Organisation');
```

### 5b. New migration `004_organisation_officers.sql`

```sql
CREATE TABLE IF NOT EXISTS `organisation_officer_roles` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `organisation_officers` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_id`     INT UNSIGNED NOT NULL,
    `user_id`     INT UNSIGNED NULL,
    `name`        VARCHAR(255) NULL COMMENT 'Override if user not in system',
    `term_from`   DATE NULL,
    `term_to`     DATE NULL,
    `is_current`  TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_officers_role` (`role_id`),
    KEY `idx_officers_current` (`is_current`),
    CONSTRAINT `fk_officers_role` FOREIGN KEY (`role_id`) REFERENCES `organisation_officer_roles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_officers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 5c. New migration `005_organisation_meetings.sql`

```sql
CREATE TABLE IF NOT EXISTS `organisation_meetings` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(255) NOT NULL,
    `type`        ENUM('agm','egm','committee','council','other') NOT NULL DEFAULT 'committee',
    `held_at`     DATETIME NOT NULL,
    `location`    VARCHAR(255) NULL,
    `agenda`      LONGTEXT NULL,
    `minutes`     LONGTEXT NULL,
    `status`      ENUM('scheduled','held','cancelled') NOT NULL DEFAULT 'scheduled',
    `created_by`  INT UNSIGNED NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_meetings_held_at` (`held_at`),
    KEY `idx_meetings_status` (`status`),
    CONSTRAINT `fk_meetings_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `organisation_meeting_attendance` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `meeting_id`  INT UNSIGNED NOT NULL,
    `user_id`     INT UNSIGNED NULL,
    `name`        VARCHAR(255) NULL COMMENT 'Non-system attendee',
    `present`     TINYINT(1) NOT NULL DEFAULT 1,
    `apologies`   TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_attendance_meeting` (`meeting_id`),
    CONSTRAINT `fk_attendance_meeting` FOREIGN KEY (`meeting_id`) REFERENCES `organisation_meetings` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_attendance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 5d. New routes and controllers

Add to `organisation/module.php` routes:

```php
// Profile
$router->get('/admin/organisation/profile',       [OrganisationAdminController::class, 'profile']);
$router->post('/admin/organisation/profile',      [OrganisationAdminController::class, 'saveProfile']);

// Officers
$router->get('/admin/organisation/officers',      [OrganisationAdminController::class, 'officers']);
$router->post('/admin/organisation/officers',     [OrganisationAdminController::class, 'createOfficer']);
$router->post('/admin/organisation/officers/{id}',[OrganisationAdminController::class, 'updateOfficer']);
$router->post('/admin/organisation/officers/{id}/delete', [OrganisationAdminController::class, 'deleteOfficer']);

// Meetings
$router->get('/admin/organisation/meetings',      [OrganisationAdminController::class, 'meetings']);
$router->get('/admin/organisation/meetings/new',  [OrganisationAdminController::class, 'newMeeting']);
$router->post('/admin/organisation/meetings',     [OrganisationAdminController::class, 'createMeeting']);
$router->get('/admin/organisation/meetings/{id}', [OrganisationAdminController::class, 'showMeeting']);
$router->post('/admin/organisation/meetings/{id}',[OrganisationAdminController::class, 'updateMeeting']);
```

New file: `organisation/src/Controllers/OrganisationAdminController.php`
New templates: `organisation/templates/admin/organisation/profile.php`, `officers.php`, `meetings/index.php`, `meetings/show.php`

**Also update `acp_sections` in `module.php`:**

```php
'acp_sections' => [
    ['group' => 'Organisation', 'label' => 'Workspace',  'url' => '/organisation',               'icon' => '🏢'],
    ['group' => 'Organisation', 'label' => 'Profile',    'url' => '/admin/organisation/profile',  'icon' => '🏛️'],
    ['group' => 'Organisation', 'label' => 'Officers',   'url' => '/admin/organisation/officers', 'icon' => '👔'],
    ['group' => 'Organisation', 'label' => 'Meetings',   'url' => '/admin/organisation/meetings', 'icon' => '📋'],
],
```

**Files affected:**
- `CruinnCMS/modules/organisation/module.php`
- `CruinnCMS/modules/organisation/migrations/003_organisation_profile.sql` (new)
- `CruinnCMS/modules/organisation/migrations/004_organisation_officers.sql` (new)
- `CruinnCMS/modules/organisation/migrations/005_organisation_meetings.sql` (new)
- `CruinnCMS/modules/organisation/src/Controllers/OrganisationAdminController.php` (new)
- `CruinnCMS/modules/organisation/templates/admin/organisation/profile.php` (new)
- `CruinnCMS/modules/organisation/templates/admin/organisation/officers.php` (new)
- `CruinnCMS/modules/organisation/templates/admin/organisation/meetings/index.php` (new)
- `CruinnCMS/modules/organisation/templates/admin/organisation/meetings/show.php` (new)

---

## Task 6 — Finance Tracking (within Organisation)

Finance is a sub-system of Organisation. It reads from Membership and Forms payment records as read-only sources. It does not own those records.

### 6a. New migration `006_organisation_finance.sql`

```sql
-- Budget periods (e.g. financial year)
CREATE TABLE IF NOT EXISTS `finance_periods` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL COMMENT 'e.g. 2024-2025',
    `starts_on`   DATE NOT NULL,
    `ends_on`     DATE NOT NULL,
    `is_current`  TINYINT(1) NOT NULL DEFAULT 0,
    `notes`       TEXT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transaction categories
CREATE TABLE IF NOT EXISTS `finance_categories` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL,
    `type`        ENUM('income','expense') NOT NULL,
    `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `finance_categories` (`name`, `type`, `sort_order`) VALUES
('Membership Fees',    'income',  10),
('Event Income',       'income',  20),
('Donations',          'income',  30),
('Other Income',       'income',  99),
('Operating Expenses', 'expense', 10),
('Event Expenses',     'expense', 20),
('Venue Hire',         'expense', 30),
('Equipment',          'expense', 40),
('Communications',     'expense', 50),
('Other Expense',      'expense', 99);

-- Ledger entries (manual + auto-ingested)
CREATE TABLE IF NOT EXISTS `finance_entries` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `period_id`       INT UNSIGNED NOT NULL,
    `category_id`     INT UNSIGNED NOT NULL,
    `type`            ENUM('income','expense') NOT NULL,
    `amount`          DECIMAL(10,2) NOT NULL,
    `currency`        CHAR(3) NOT NULL DEFAULT 'EUR',
    `description`     VARCHAR(500) NOT NULL,
    `reference`       VARCHAR(100) NULL COMMENT 'Cheque no., receipt ref, etc.',
    `entry_date`      DATE NOT NULL,
    -- Source linkage (nullable — manual entries have no source)
    `source_type`     ENUM('manual','membership_payment','form_payment','event_payment') NOT NULL DEFAULT 'manual',
    `source_id`       INT UNSIGNED NULL COMMENT 'ID in source table',
    `recorded_by`     INT UNSIGNED NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_finance_period`   (`period_id`),
    KEY `idx_finance_category` (`category_id`),
    KEY `idx_finance_date`     (`entry_date`),
    KEY `idx_finance_source`   (`source_type`, `source_id`),
    CONSTRAINT `fk_finance_period`   FOREIGN KEY (`period_id`)   REFERENCES `finance_periods` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_finance_category` FOREIGN KEY (`category_id`) REFERENCES `finance_categories` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_finance_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 6b. New routes in `organisation/module.php`

```php
// Finance
$router->get('/admin/organisation/finance',                  [FinanceController::class, 'index']);
$router->get('/admin/organisation/finance/new',              [FinanceController::class, 'newEntry']);
$router->post('/admin/organisation/finance',                 [FinanceController::class, 'createEntry']);
$router->get('/admin/organisation/finance/{id}/edit',        [FinanceController::class, 'editEntry']);
$router->post('/admin/organisation/finance/{id}',            [FinanceController::class, 'updateEntry']);
$router->post('/admin/organisation/finance/{id}/delete',     [FinanceController::class, 'deleteEntry']);
$router->get('/admin/organisation/finance/report',           [FinanceController::class, 'report']);
$router->get('/admin/organisation/finance/export',           [FinanceController::class, 'exportCsv']);
$router->get('/admin/organisation/finance/periods',          [FinanceController::class, 'periods']);
$router->post('/admin/organisation/finance/periods',         [FinanceController::class, 'createPeriod']);
```

### 6c. New file: `organisation/src/Controllers/FinanceController.php`

Methods: `index()`, `newEntry()`, `createEntry()`, `editEntry()`, `updateEntry()`, `deleteEntry()`, `report()`, `exportCsv()`, `periods()`, `createPeriod()`

The `report()` method will also query `membership_payments` and `form_submissions` (where `payment_status = 'verified'`) to display a reconciled view — but these are read-only joins, not writes.

### 6d. New service: `organisation/src/Services/FinanceService.php`

Handles:
- `getSummary(int $periodId)` — total income, total expense, balance
- `getByCategory(int $periodId)` — breakdown
- `ingestMembershipPayments(int $periodId)` — reads `membership_payments` and creates `finance_entries` with `source_type = 'membership_payment'` (idempotent — checks `source_id` before inserting)
- `ingestFormPayments(int $periodId)` — same for `form_submissions` with `payment_status = 'verified'`
- `exportCsv(int $periodId)` — returns CSV string

### 6e. `acp_sections` addition to `module.php`

```php
['group' => 'Organisation', 'label' => 'Finance',   'url' => '/admin/organisation/finance',  'icon' => '💰'],
```

**Files affected:**
- `CruinnCMS/modules/organisation/module.php`
- `CruinnCMS/modules/organisation/migrations/006_organisation_finance.sql` (new)
- `CruinnCMS/modules/organisation/src/Controllers/FinanceController.php` (new)
- `CruinnCMS/modules/organisation/src/Services/FinanceService.php` (new)
- `CruinnCMS/modules/organisation/templates/admin/organisation/finance/index.php` (new)
- `CruinnCMS/modules/organisation/templates/admin/organisation/finance/form.php` (new)
- `CruinnCMS/modules/organisation/templates/admin/organisation/finance/report.php` (new)
- `CruinnCMS/modules/organisation/templates/admin/organisation/finance/periods.php` (new)

---

## Sidebar: `admin/layout.php` additions

The admin sidebar is also hardcoded and will need an Organisation group added. Currently it has: Settings, Site Builder, Content, Community, Social, People.

**Add after People:**

```php
<?php if (\Cruinn\Modules\ModuleRegistry::isActive('organisation') || \Cruinn\Modules\ModuleRegistry::isActive('documents') || \Cruinn\Modules\ModuleRegistry::isActive('membership')): ?>
<div class="admin-sidebar-group">
    <a href="<?= url('/organisation') ?>" class="admin-sidebar-parent">Organisation <span class="sidebar-caret">▸</span></a>
    <div class="admin-sidebar-flyout">
        <?php if (\Cruinn\Modules\ModuleRegistry::isActive('organisation')): ?>
        <a href="<?= url('/organisation') ?>">Workspace</a>
        <a href="<?= url('/admin/organisation/profile') ?>">Profile</a>
        <a href="<?= url('/admin/organisation/officers') ?>">Officers</a>
        <a href="<?= url('/admin/organisation/meetings') ?>">Meetings</a>
        <a href="<?= url('/admin/organisation/finance') ?>">Finance</a>
        <?php endif; ?>
        <?php if (\Cruinn\Modules\ModuleRegistry::isActive('documents')): ?>
        <a href="<?= url('/documents') ?>">Documents</a>
        <?php endif; ?>
        <?php if (\Cruinn\Modules\ModuleRegistry::isActive('membership')): ?>
        <a href="<?= url('/admin/membership') ?>">Membership</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
```

**File affected:**
- `CruinnCMS/templates/admin/layout.php`

---

## Build Order Summary

| # | Task | Files changed | New files |
|---|---|---|---|
| 1 | Dashboard: Organisation tile + Drivespace in Community | `dashboard.php` | — |
| 2 | Dashboard: Module view toggle | `dashboard.php`, `AdminController.php` | — |
| 3 | Admin sidebar: Organisation group | `admin/layout.php` | — |
| 4 | GDPR: `acp_sections` + admin settings page | `gdpr/module.php`, `GdprController.php` | `gdpr/templates/admin/settings/gdpr.php` |
| 5 | DriveSpace: quota migration + admin controller | `drivespace/module.php`, `FileManagerController.php` | `002_drivespace_quotas.sql`, `FileManagerAdminController.php`, `admin-index.php` |
| 6 | Documents: categories migration + admin controller | `documents/module.php` | `002_documents_categories.sql`, `DocumentAdminController.php`, 2 templates |
| 7 | Organisation: profile + officers + meetings | `organisation/module.php` | 3 migrations, `OrganisationAdminController.php`, 4 templates |
| 8 | Organisation: Finance tracking | `organisation/module.php` | `006_organisation_finance.sql`, `FinanceController.php`, `FinanceService.php`, 4 templates |

---

*Ready to begin on your go-ahead. Recommend starting with tasks 1–3 (all dashboard/nav changes, no new backend) to get visibility right, then moving to 4 (GDPR, small), then 7+8 (Organisation core + Finance, the largest block).*
