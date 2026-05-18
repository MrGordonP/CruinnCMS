# CruinnCMS — Role & Capability Refactor + Widget Dashboards

**Status:** Stage 6 Complete (commit TBD)
**Version target:** v1.0.0-beta.9
**Agreed:** May 2026
**Stage 1 landed:** 16 May 2026
**Stage 2 landed:** 16 May 2026
**Stage 3 landed:** 16 May 2026
**Stage 4 landed:** 18 May 2026
**Stage 5 landed:** 18 May 2026
**Stage 6 landed:** 18 May 2026

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

**✓ COMPLETE** (commit 862f8ed, 16 May 2026)

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

**✓ COMPLETE** (commit 968c1e1, 18 May 2026)

Integrate organisation_officers table to provide position-based authorization. Users holding officer positions inherit access grants and dashboard assignments from those positions.

### Implementation

**Modified:** `src/Auth.php`

**Added methods:**
- `Auth::positionIds(): array` — Returns array of active `organisation_officers.id` where user_id matches, cached in session
- `Auth::loadPositionIds(int $userId): array` — Queries organisation_officers table (called at login)
- `Auth::refreshPositionIds(): void` — Reloads position IDs in session (call after modifying officer assignments)

**Login integration:**
- `Auth::attempt()` and `Auth::loginById()` now call `loadPositionIds()` and store in session
- Position IDs cached alongside role_level and group_level for performance

**Access control:**
- `Auth::requireAdminArea()` already checks position grants (implemented in Stage 2)
- Checks role grants first, then iterates position grants
- Admin-level users bypass all grants (100% access)

### Organisation Module Changes

**New routes:**
- GET/POST `/admin/organisation/officers/{id}/areas` — Configure admin area grants
- GET/POST `/admin/organisation/officers/{id}/dashboard` — Assign widget dashboard canvas

**New controller methods:** `OrganisationAdminController`
- `officerAreas(int $id)` — Render area grants UI
- `saveOfficerAreas(int $id)` — Save grants to admin_area_grants (context_type='position', context_id=officer_id)
- `officerDashboard(int $id)` — Render dashboard assignment UI
- `saveOfficerDashboard(int $id)` — Save to context_dashboards (context_type='position')

**New templates:**
- `modules/organisation/templates/admin/organisation/officer-areas.php` — Checkbox grid of grantable areas
- `modules/organisation/templates/admin/organisation/officer-dashboard.php` — Dashboard dropdown selector

**Modified template:**
- `officers.php` — Added "Areas" and "Dashboard" config links to actions column

### How It Works

1. **Position assignment:** Admin assigns user to officer position in organisation module
2. **Login:** User logs in → `Auth::loadPositionIds()` queries organisation_officers → stores IDs in session
3. **Area grants:** Admin grants "blog" area to "Secretary" position → stored in admin_area_grants (context_type='position', context_id=officer_id)
4. **Access check:** User visits `/admin/blog` → `Auth::requireAdminArea('blog')` checks position grants → access granted
5. **Dashboard resolution:** `DashboardService::resolveDashboardForUser()` checks position dashboard → renders custom dashboard if assigned

### Data Flow

```
organisation_officers (user_id, position, active)
  ↓
Auth::positionIds() [cached in session]
  ↓
Auth::requireAdminArea() → checks admin_area_grants WHERE context_type='position'
  ↓
Access granted to position holder
```

### Result

Position-based access control fully operational:
- Users holding officer positions inherit grants from those positions
- Position dashboards override role dashboards in resolution cascade
- Admin areas accessible via position without admin role required
- Session-cached for performance (one DB query at login)

---

## Stage 5 — Notifications widget

**✓ COMPLETE** (commit 252c18d, 18 May 2026)

A `notifications` widget registered by the mailbox module that queries unread mailbox counts with access control.

### Implementation

**New files:**
- `modules/mailbox/src/Widgets/NotificationsWidget.php` — Data provider class
- `modules/mailbox/templates/widgets/notifications.php` — Widget template partial

**Modified files:**
- `src/Modules/ModuleRegistry.php` — Added `widget_providers` support + `renderProviderWidget()`
- `src/BlockTypes/module-widget/definition.php` — Enhanced to check for `_userContext` and call providers
- `modules/mailbox/module.php` — Registered notifications widget in `widget_providers` array

### How It Works

1. **Provider registration:** Modules define `widget_providers` array with provider class/method + template path
2. **userContext injection:** `DashboardService::renderWidgetCanvas()` injects `_userContext` into module-widget block properties
3. **Provider calling:** module-widget block renderer checks for `_userContext`, calls provider with `(settings, userContext)`
4. **Template rendering:** Provider returns data array, template renders with extracted variables
5. **Fallback:** If no userContext or no provider, falls back to simple widget (pre-rendered HTML)

### Widget Provider Pattern

```php
'widget_providers' => [
    [
        'slug'     => 'notifications',
        'label'    => 'Mailbox Notifications',
        'provider' => 'Cruinn\\Module\\Mailbox\\Widgets\\NotificationsWidget::getData',
        'template' => 'widgets/notifications',
    ],
]
```

Provider signature: `public static function getData(array $settings, array $userContext): array`

### Data Provider

`NotificationsWidget::getData()` queries:
- Accessible mailboxes via `MailboxService::getAccessibleMailboxes()`
- Unread counts by comparing `mailbox_messages` vs `mailbox_reads` for user
- Returns `['mailboxes' => [...], 'total_unread' => int]`

### Template

`widgets/notifications.php` renders:
- Mailbox list with unread badges
- Links to `/mail/{mailbox_id}`
- Total unread count in widget title
- Styled with scoped CSS (no external dependencies)

---

## Stage 6 — Module migration from deprecated Auth methods

**✓ COMPLETE** (commit TBD, 18 May 2026)

Migrated all modules from deprecated `Auth::role()`, `Auth::hasRole()`, and `Auth::requireRole()` methods to the new level-based API introduced in Stage 1.

### Modules Migrated

**Forum module:**
- Added `roleSlugToLevel()` helper to `NativeForumProvider.php` and `ForumController.php`
- Updated `listCategories()`, `listCategoriesHierarchical()`, `getCategoryBySlug()`, `getSubcategories()` to accept `?int $viewerLevel` instead of `?string $viewerRole`
- Replaced `Auth::role()` with `Auth::roleLevel()`
- Replaced `Auth::hasRole('admin')` with `Auth::isAdmin()`
- Replaced `Auth::hasRole($roleSlug)` with level comparisons using helper
- Updated `ForumAdminController`: all `Auth::requireRole('admin')` → `Auth::requireAdmin()`

**Drivespace module:**
- Added `roleSlugToLevel()` helper to `GoogleDriveController.php` and `FileManagerController.php`
- Replaced `Auth::requireRole('member')` with `Auth::requireLevel(10)`
- Replaced `Auth::requireRole($this->gdrive->getWriteRole())` with `Auth::requireLevel($this->roleSlugToLevel($this->gdrive->getWriteRole()))`
- Replaced `Auth::hasRole('admin')` with `Auth::isAdmin()` in controllers and templates
- Updated `FileManagerAdminController`: all `Auth::requireRole('admin')` → `Auth::requireAdmin()`

**Membership module:**
- Updated `MembershipAdminController`: all `Auth::requireRole('admin')` → `Auth::requireAdmin()` (20 occurrences)

**Organisation module:**
- Updated templates: replaced `\Cruinn\Auth::hasRole('admin')` with `\Cruinn\Auth::isAdmin()`
- Updated `OrganisationAdminController` and `FinanceController`: all `Auth::requireRole('admin')` → `Auth::requireAdmin()`

**Additional modules (blog, mailout, documents, mailbox):**
- Batch-replaced `Auth::requireRole('admin')` with `Auth::requireAdmin()` across all remaining modules
- Updated `MailboxService`: changed method signatures to accept `bool $isAdmin` instead of `string $role`
  - `getAccessibleMailboxes(int $userId, bool $isAdmin)`
  - `getMailbox(int $mailboxId, int $userId, bool $isAdmin)`
  - `getMessages()` — removed unused `$role` parameter
- Updated `MailboxController`: replaced `Auth::requireRole('member')` with `Auth::requireLevel(10)`, all `Auth::role()` calls with `Auth::isAdmin()`

### Compatibility Shims Removed

Removed the following deprecated methods from `src/Auth.php`:
- `Auth::role(): ?string` — replaced by `Auth::roleLevel(): int`
- `Auth::hasRole(string $minimumRole): bool` — replaced by level comparisons or `Auth::isAdmin()`
- `Auth::requireRole(string $minimumRole): void` — replaced by `Auth::requireAdmin()` or `Auth::requireLevel(int)`

All compatibility notes and warnings removed. The deprecated API is no longer available.

### Result

- Zero occurrences of `Auth::role()`, `Auth::hasRole()`, or `Auth::requireRole()` remain in the codebase
- All modules use the new level-based Auth API consistently
- Engine is fully instance-agnostic — no hardcoded role slug assumptions
- No errors reported

---

## Files Affected (summary)

| File | Stage | Change |
|---|---|---|
| `src/Auth.php` | 1, 6 | Remove role(), hasRole(), requireRole(); add isAdmin(), requireAdmin(), requireLevel(), requireAdminArea(), positionIds() |
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
| `src/Auth.php` | 4 | positionIds(), loadPositionIds(), refreshPositionIds() |
| `modules/organisation/module.php` | 4 | Routes for officer areas/dashboard config |
| `modules/organisation/src/Controllers/OrganisationAdminController.php` | 4 | officerAreas(), saveOfficerAreas(), officerDashboard(), saveOfficerDashboard() |
| `modules/organisation/templates/admin/organisation/officer-areas.php` | 4 | NEW — Area grants UI |
| `modules/organisation/templates/admin/organisation/officer-dashboard.php` | 4 | NEW — Dashboard assignment UI |
| `modules/organisation/templates/admin/organisation/officers.php` | 4 | Add Areas + Dashboard config links |
| `src/Modules/ModuleRegistry.php` | 5 | widget_providers support + renderProviderWidget() |
| `src/BlockTypes/module-widget/definition.php` | 5 | userContext detection + provider calling |
| `modules/mailbox/src/Widgets/NotificationsWidget.php` | 5 | NEW — Data provider for notifications widget |
| `modules/mailbox/templates/widgets/notifications.php` | 5 | NEW — Widget template partial |
| `modules/mailbox/module.php` | 5 | Register notifications widget |
| `modules/forum/src/Forum/NativeForumProvider.php` | 6 | Replace role string methods with level-based helpers |
| `modules/forum/src/Controllers/ForumController.php` | 6 | Replace deprecated Auth calls with level API |
| `modules/forum/src/Controllers/ForumAdminController.php` | 6 | requireRole('admin') → requireAdmin() |
| `modules/drivespace/src/Controllers/GoogleDriveController.php` | 6 | Add roleSlugToLevel(), use Auth::requireLevel() |
| `modules/drivespace/src/Controllers/FileManagerController.php` | 6 | Add roleSlugToLevel(), use Auth::isAdmin() |
| `modules/drivespace/src/Controllers/FileManagerAdminController.php` | 6 | requireRole('admin') → requireAdmin() |
| `modules/drivespace/templates/admin/files/index.php` | 6 | hasRole('admin') → isAdmin(), level-based write check |
| `modules/membership/src/Controllers/MembershipAdminController.php` | 6 | requireRole('admin') → requireAdmin() (20×) |
| `modules/organisation/src/Controllers/OrganisationAdminController.php` | 6 | requireRole('admin') → requireAdmin() |
| `modules/organisation/src/Controllers/FinanceController.php` | 6 | requireRole('admin') → requireAdmin() |
| `modules/organisation/templates/organisation/*.php` | 6 | hasRole('admin') → isAdmin() |
| `modules/blog/src/Controllers/ArticleEditorController.php` | 6 | requireRole('admin') → requireAdmin() |
| `modules/mailout/src/Controllers/*.php` | 6 | requireRole('admin') → requireAdmin() |
| `modules/mailbox/src/Controllers/MailboxAdminController.php` | 6 | requireRole('admin') → requireAdmin() |
| `modules/mailbox/src/Controllers/MailboxController.php` | 6 | requireRole('member') → requireLevel(10), role() → isAdmin() |
| `modules/mailbox/src/Services/MailboxService.php` | 6 | Change signatures: string $role → bool $isAdmin |
| `modules/documents/src/Controllers/DocumentAdminController.php` | 6 | requireRole('admin') → requireAdmin() |

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

---

## Next Session Start (v1.0.0-beta.9 continuation)

**Current status:** 17 May 2026, 23:30
**Commits ahead of origin:** 0 (pushed as bb53dea)
**Live site:** Stable with compatibility shims in place

### Session Startup Checklist

1. `git log --oneline -5` — confirm HEAD at bb53dea or later
2. `git status` — verify clean working tree
3. Review production hotfix notes in `dev/docs/sessions/v1.0.0-beta.9_role-capability-refactor_CHECKPOINT.md`
4. Decide path forward:

### Path Options

**Option A: Tag and Release**
- Tag current state as stable beta.9 checkpoint: `git tag v1.0.0-beta.9.1 && git push origin --tags`
- Rationale: Stages 1–3 complete, hotfix stable, good stopping point before larger work

**Option B: Stage 5 — Notifications Widget (Recommended)**
- Low-risk, high-value feature demonstrating widget dashboard system
- Self-contained mailbox module work, no engine changes needed
- Creates visible benefit for users immediately
- **Files to create:**
  - `modules/mailbox/src/Widgets/NotificationsWidget.php` (data provider class)
  - `modules/mailbox/templates/widgets/notifications.php` (widget partial)
- **Files to modify:**
  - `modules/mailbox/module.php` — register widget in `ModuleRegistry::registerWidget()`
- **Expected duration:** 1–2 hours
- **Migration needed:** No

**Option C: Stage 4 — Position Integration**
- Medium-risk, requires coordination across Auth + Organisation module
- Implements `Auth::positionIds()` backed by `organisation.user_positions` table
- Adds position-based admin area grants UI
- Adds position dashboard canvas assignment UI
- **Files to create:**
  - `modules/organisation/templates/positions/areas.php` (area grants UI)
  - `modules/organisation/templates/positions/dashboard-canvas.php` (canvas assignment UI)
- **Files to modify:**
  - `src/Auth.php` — implement `positionIds()` method with session caching
  - `modules/organisation/src/Controllers/PositionController.php` — add config routes
  - `modules/organisation/module.php` — register new routes
- **Expected duration:** 3–4 hours
- **Migration needed:** No (uses existing `user_positions` table)

**Option D: Stage 6 — Module Migration (Tedious but Necessary)**
- Replace deprecated `Auth::role()`, `hasRole()`, `requireRole()` calls in modules
- ~100+ locations across forum, drivespace, membership, organisation
- Once complete, remove compatibility shims from `Auth.php`
- **Can be done incrementally:** One module per session
- **Recommended order:**
  1. `modules/forum/` (~20 locations)
  2. `modules/drivespace/` (~15 locations)
  3. `modules/membership/` (~30 locations)
  4. `modules/organisation/` (~40 locations)
- **Expected duration per module:** 1–2 hours
- **Migration needed:** No

### Recommended Sequence

1. **Stage 5** (notifications widget) — quick win, demonstrates system, builds momentum
2. **Stage 4** (position integration) — completes the authorization model
3. **Stage 6** (module migration) — clean up technical debt, remove deprecated code
4. **Tag v1.0.0-beta.10** — full authorization system shipped

### Known Issues to Address

- **Canvas vs Dashboard terminology:** UI uses "Canvas" in site builder, "Dashboard" in role config. Consider standardizing to "Widget Dashboard Template" and "Dashboard" for consistency.
- **Area grant UI feedback:** No visual confirmation when saving area grants (only flash message). Consider adding a success banner or toast.
- **Widget canvas empty state:** If no dashboard assigned, user sees empty dashboard. Should show placeholder/welcome message instead.
- **Documentation:** No user-facing docs for widget system yet. Consider adding help text or tooltips in UI.

### Pre-flight Checks Before Starting Work

```bash
# Verify engine state
git log --oneline -5
git status
php public_html/index.php  # Quick syntax check

# Check live site (if deployed)
curl -I https://igaportal.ie  # Should return 200, not 500

# Review completed work
cat dev/docs/sessions/v1.0.0-beta.9_role-capability-refactor_CHECKPOINT.md
```

---

**Last updated:** 17 May 2026, 23:30
**Next session:** TBD — awaiting path decision (recommend Stage 5)
