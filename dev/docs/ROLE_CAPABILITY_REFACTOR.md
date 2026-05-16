# CruinnCMS — Role & Capability Refactor + Widget Dashboards

**Status:** Stage 2 Complete (commit fdaab4f)
**Version target:** v1.0.0-beta.9
**Agreed:** May 2026
**Stage 1 landed:** 16 May 2026
**Stage 2 landed:** 16 May 2026

---

## Background

The engine currently hardcodes non-system role names (`council`, `editor`, `member`) in
`Auth.php` and `Template.php`, using a numeric level system to derive slug labels at
runtime. This violates the engine's instance-agnostic principle — role names above `admin`
and below `public` are instance concerns, not engine concerns.

Alongside this, the admin panel uses `Auth::requireRole('admin')` as a blanket gate for
all backend sections. There is no mechanism for non-admin roles or org positions to access
specific admin areas (blog editing, forum moderation, mailout, etc.) without full admin
privileges.

Finally, dashboard canvases are role-scoped only. There is no mechanism for per-position,
per-user, or context-specific dashboards built from the existing widget and block systems.

---

## Engine Principles (unchanged)

- No role names except `admin` and `public` are permitted in engine code.
- Access checks use numeric levels or IDs — never string slug comparisons.
- All instance-specific role names live in the DB and are display-only to the engine.

---

## Stage 1 — Auth API cleanup

**✓ COMPLETE** (commit cc6c030, 16 May 2026)

### Target API

```php
Auth::isAdmin(): bool           // level >= 100
Auth::isLoggedIn(): bool        // session user_id present
Auth::roleLevel(): int          // user's max role level (cached in session)
Auth::roleId(): ?int            // user's highest role DB id
Auth::positionIds(): array      // user's active org position IDs (cached in session)
Auth::requireAdmin(): void      // 403 if not admin
Auth::requireLoggedIn(): void   // redirect to login if not logged in
Auth::requireLevel(int $min): void    // 403 if roleLevel < $min
Auth::requireAdminArea(string $slug): void  // 403 unless admin or granted (Stage 2)
```

### Remove

- `Auth::role(): ?string` — derives hardcoded slug from level bands. Removed entirely.
- `Auth::hasRole(string $minimumRole): bool` — parses hardcoded slug→level map. Removed.
- `Auth::requireRole(string $role): void` — replaced by `requireAdmin()` or `requireLevel()`.

### Keep (unchanged)

- `Auth::userId(): ?int`
- `Auth::check(): bool`
- `Auth::groupLevel(): int`
- `Auth::loginById()`, `Auth::logout()`, `Auth::flash()`
- The `roles` table, `user_roles`, `roles.level` column — all unchanged.
  `roles.slug` is retained as a display label only. The engine never reads it for logic.

### Template.php visibility check

The hardcoded level map in `Template::isVisibleForRole()` (or equivalent):

```php
$roleLevels = ['public' => 0, 'editor' => 20, 'council' => 50, 'admin' => 100];
```

Replace with a pure numeric check against `Auth::roleLevel()`. The visibility field on
blocks/pages stores a **minimum level integer** rather than a role slug. Migration needed
for any existing rows storing slug strings.

### Schema changes

`instance_core.sql` — seed only:
```sql
INSERT INTO roles (name, slug, level, is_system) VALUES
  ('Administrator', 'admin',  100, 1),
  ('Public',        'public',   0, 1);
```

`themes/default/seed.sql` — add instance defaults:
```sql
INSERT IGNORE INTO roles (name, slug, level, is_system) VALUES
  ('Council', 'council', 50, 0),
  ('Member',  'member',  10, 0);
```

### Migration

`migrations/core/014_role_visibility_levels.sql`
Convert any `visibility` / `min_role` string columns storing slug values to integers
using the legacy map (public=0, editor=20, council=50, admin=100) as a one-time
conversion.

---

## Stage 2 — Admin area grants

**✓ COMPLETE** (commit fdaab4f, 16 May 2026)

### Goal

Allow non-admin roles and org positions to access specific admin sections without full
admin privileges. Each admin section declares a slug. Access is granted per role or
position via a config table.

### Schema

```sql
CREATE TABLE admin_area_grants (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    area_slug    VARCHAR(60)  NOT NULL,
    context_type ENUM('role','position') NOT NULL,
    context_id   INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_grant (area_slug, context_type, context_id)
);
```

### Auth check

```php
Auth::requireAdminArea(string $slug): void
```

Passes if:
- `Auth::isAdmin()` — always passes for admin, OR
- The user's `roleId()` has a grant for `$slug`, OR
- Any of the user's `positionIds()` has a grant for `$slug`

Grant lookup is cached in session for the request lifetime.

### Controller migration

Replace `Auth::requireAdmin()` with `Auth::requireAdminArea('slug')` in controllers
where sub-admin access is meaningful:

| Controller | Area slug |
|---|---|
| Blog / Articles admin | `blog` |
| Forum moderation | `forum` |
| Mailout | `mailout` |
| Events admin | `events` |
| Documents admin | `documents` |
| Payments admin | `payments` |
| Membership admin | `membership` |
| Forms admin | `forms` |

Full admin-only sections (user management, roles, platform settings, migrations) keep
`requireAdmin()` and are never grantable.

### Admin UI

`GET /admin/roles/{id}/areas` — configure which area slugs a role can access.
`GET /admin/organisation/positions/{id}/areas` — configure which area slugs a position can access.
Both render a checklist of registered area slugs.

### Area slug registration

Each module registers its admin area slugs via `ModuleRegistry` or a static declaration.
Engine core areas (blog, forum, etc.) are registered in `config/routes.php` or a
dedicated `config/admin_areas.php`. No hardcoding in `Auth.php`.

---

## Stage 3 — Widget dashboard canvases

### Goal

A widget dashboard is a `pages_index` canvas with `canvas_type='widget-dashboard'`,
built in the block editor using `module-widget` blocks and regular layout blocks.
Any context (role, position, user) can be assigned a dashboard canvas.

### Schema

```sql
-- canvas_type='widget-dashboard' added to pages_index (no schema change needed,
-- canvas_type is already a free VARCHAR on pages_index post-migration 011)

CREATE TABLE context_dashboards (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    context_type ENUM('role','position','user') NOT NULL,
    context_id   INT UNSIGNED NOT NULL,
    page_id      INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_ctx (context_type, context_id),
    FOREIGN KEY (page_id) REFERENCES pages_index(id) ON DELETE CASCADE
);
```

### Render path

`DashboardService::renderWidgetCanvas(int $pageId, array $userContext): string`

- Fetches published blocks for the canvas page via `CruinnRenderService`
- For `module-widget` blocks: calls `DashboardService::callProvider()` with
  `$userContext` (user_id, role_level, position_ids) injected into settings
- For all other blocks: renders normally via `CruinnRenderService`
- Returns complete HTML string

### Resolution order (which dashboard does a user see?)

1. User-specific dashboard (context_type='user', context_id=user_id)
2. Highest-level position dashboard (position with highest sort_order or explicit priority)
3. Role dashboard (context_type='role', context_id=roleId)
4. Default dashboard (context_type='role', context_id= admin role id, used as fallback)

### Admin area access for position dashboard

A position dashboard showing "Go to Mailout" only makes sense if the position holder
can actually reach `/admin/mailout`. This requires Stage 2 grants to be in place.
Position dashboards and admin area grants ship together.

### Admin UI

`GET /admin/site-builder/dashboards` — list all widget dashboard canvases.
`POST /admin/site-builder/dashboards/new` — create a new dashboard canvas, redirect to editor.
Assignment UI lives in role config and org position config (select which dashboard canvas
this role/position uses).

---

## Stage 4 — Position dashboard in org module

The organisation module exposes a position → dashboard assignment UI and the position
area grants UI (from Stage 2). These are module-level features, not engine features.

Details to be designed when Stage 2–3 are complete.

---

## Stage 5 — Notifications widget

A `notifications` widget registered by the mailbox module:

- **Data provider:** `MailboxModule::notificationsData(array $settings, array $userContext)`
  — queries unread counts for mailboxes the user/position has access to
- **Template partial:** `templates/admin/widgets/notifications.php`
- No new block type needed — uses existing `module-widget` block

---

## Files Affected (summary)

| File | Stage | Change |
|---|---|---|
| `src/Auth.php` | 1 | Remove role(), hasRole(), requireRole(); add isAdmin(), requireAdmin(), requireLevel(), requireAdminArea(), positionIds() |
| `src/Template.php` | 1 | Replace hardcoded level map with numeric Auth::roleLevel() check |
| `schema/instance_core.sql` | 1 | Seed only admin + public system roles |
| `themes/default/seed.sql` | 1 | Add council + member as instance defaults |
| `migrations/core/014_role_visibility_levels.sql` | 1 | Convert slug visibility strings to ints |
| All admin controllers using requireRole() | 1, 2 | Replace with requireAdmin() or requireAdminArea() |
| `schema/instance_core.sql` | 2 | Add admin_area_grants table |
| `src/Admin/Controllers/RoleAdminController.php` | 2 | Area grants config UI |
| Organisation module | 2, 4 | Position area grants + dashboard assignment |
| `schema/instance_core.sql` | 3 | Add context_dashboards table |
| `src/Services/DashboardService.php` | 3 | renderWidgetCanvas() + userContext injection |
| `src/Admin/Controllers/SiteBuilderController.php` | 3 | Dashboards tab |
| Mailbox module | 5 | Register notifications widget |

---

## Implementation Order

Stage 1 must land first — it cleans up the auth API that everything else builds on.
Stage 2 depends on Stage 1 (uses the new requireAdminArea + positionIds).
Stage 3 depends on Stage 2 (position dashboards need area grants to be useful).
Stage 4 depends on Stages 2 and 3.
Stage 5 is independent — can land any time after Stage 3.

---

## Known Constraints

- `Auth::requireRole('admin')` calls that guard platform-level and user-management routes
  are replaced with `requireAdmin()` — not grantable, not configurable.
- `roles.slug` is kept in the DB as a display label. The engine reads it only for admin UI
  display (role name in user list, etc.) — never for logic.
- `positionIds()` requires the organisation module to be active. If it is not, the method
  returns `[]` gracefully. No engine dependency on the org module.
