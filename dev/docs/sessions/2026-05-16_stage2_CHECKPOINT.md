# CruinnCMS Session Checkpoint — 16 May 2026 (Stage 2)

**Commit:** TBD
**Version:** `v1.0.0-beta.9` (in progress)
**Branch:** `main`

---

## What Changed

**Stage 2 — Admin Area Grants (Role & Capability Refactor)**

Completed the second stage of the role capability refactor. The system now supports
granular admin access control — non-admin roles can be granted access to specific admin
sections (blog, forum, mailout, etc.) without full admin privileges.

### Schema Changes

**New table: `admin_area_grants`**
```sql
CREATE TABLE `admin_area_grants` (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    area_slug    VARCHAR(60)  NOT NULL,
    context_type ENUM('role','position') NOT NULL,
    context_id   INT UNSIGNED NOT NULL,
    granted_at   DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
    granted_by   INT UNSIGNED DEFAULT NULL,
    UNIQUE KEY uq_grant (area_slug, context_type, context_id),
    ...
);
```

Added to `schema/instance_core.sql` and `migrations/core/015_admin_area_grants.sql`.

### Auth.php Changes

**Modified `adminMiddleware()` to support area grants:**
- Admin role (level >= 100) always passes (unchanged)
- Non-admin users: checks if URI maps to a grantable area
- If area is grantable, checks role-based and position-based grants
- If no admin role and no grant, returns 403

**New private method: `getAreaSlugForUri(string $uri): ?string`**
- Maps request URIs to area slugs using `config/admin_areas.php`
- Uses glob pattern matching (e.g., `/admin/media/*` → `'media'`)
- Returns null for non-grantable routes (users, roles, settings, site builder, etc.)

**Enhanced `requireAdminArea()` implementation:**
- Now fully functional (was a stub in Stage 1)
- Checks admin role first (fast path)
- Checks role grants via `hasAreaGrant()`
- Checks position grants (when positions are implemented in Stage 4)
- Returns 403 if no access

**Enhanced `hasAreaGrant()` implementation:**
- Session-cached grant lookups
- Queries `admin_area_grants` table
- Used by both middleware and explicit `requireAdminArea()` calls

### New File: `config/admin_areas.php`

Defines all grantable admin areas:
- Core areas: blog, events, documents, media, menus
- Module areas: forum, mailout, mailbox, membership, payments, forms, social, organisation
- Each area specifies: name, description, icon, route patterns, module dependency

Areas NOT in this config are admin-only (users, roles, platform settings, site builder, etc.).

### Controller Changes

**MenuController:**
- Replaced `Auth::requireAdmin()` with `Auth::requireAdminArea('menus')` in `blockEditor()` method

**RoleAdminController — new methods:**
- `areaConfig(int $id)` — GET `/admin/roles/{id}/areas`
  - Displays UI with checklist of grantable areas
  - Shows current grants for the role
  - Admin roles (level >= 100) show an info message (cannot configure grants)
- `saveAreaConfig(int $id)` — POST `/admin/roles/{id}/areas`
  - Deletes existing grants for role
  - Inserts new grants based on form submission
  - Blocks configuration for admin-level roles

### New Routes

```php
$router->get('/admin/roles/{id}/areas',  [RoleAdminController::class, 'areaConfig']);
$router->post('/admin/roles/{id}/areas', [RoleAdminController::class, 'saveAreaConfig']);
```

### New Template

**`templates/admin/roles/areas.php`:**
- Simple checkbox list of available areas
- Shows area name, description, and module badge (if module-provided)
- Info alert for admin roles (cannot configure grants)
- Inline CSS for area grant checkboxes

---

## How It Works

### Request Flow for Admin Routes

1. User requests `/admin/media/list`
2. `adminMiddleware()` runs:
   - Checks if logged in (redirects to login if not)
   - If admin (level >= 100), allows immediately
   - If non-admin, calls `getAreaSlugForUri('/admin/media/list')` → `'media'`
   - Checks if role has a `media` grant in `admin_area_grants`
   - If grant exists, allows; otherwise 403
3. Controller method runs (MediaController::listMedia)
4. Response rendered

### Grant Configuration Flow

1. Admin navigates to `/admin/roles/{id}/areas`
2. `RoleAdminController::areaConfig()` loads:
   - Available areas from `config/admin_areas.php`
   - Current grants from `admin_area_grants` table
3. Admin checks boxes for areas to grant
4. Form submits to `/admin/roles/{id}/areas` (POST)
5. `saveAreaConfig()`:
   - Deletes existing grants for role
   - Inserts new grants based on selections
   - Logs activity
   - Flashes success message

---

## Stage 2 Completion Notes

**Fully functional:**
- Area-based middleware gating
- Role area grants (database, logic, UI)
- Admin area registry system
- Migration for existing instances

**Not yet implemented (Stage 4):**
- Position-based grants (requires organisation module integration)
- `Auth::positionIds()` still returns `[]` stub

**Testing required:**
- Manual testing: create a non-admin role, grant 'media' access, verify user can access `/admin/media` but not `/admin/users`
- Edge case: admin role should never have grants configured (UI blocks it)
- Edge case: non-grantable routes (users, roles, settings) should remain admin-only

---

## Files Changed (13 files)

**Schema:**
- `schema/instance_core.sql` — add `admin_area_grants` table
- `migrations/core/015_admin_area_grants.sql` — NEW migration

**Core Logic:**
- `src/Auth.php` — middleware changes, `requireAdminArea()` implementation, `getAreaSlugForUri()`, `hasAreaGrant()`

**Config:**
- `config/admin_areas.php` — NEW admin area registry
- `config/routes.php` — add role area config routes

**Controllers:**
- `src/Controllers/MenuController.php` — replace `requireAdmin()` with `requireAdminArea('menus')`
- `src/Admin/Controllers/RoleAdminController.php` — add `areaConfig()` and `saveAreaConfig()`

**Templates:**
- `templates/admin/roles/areas.php` — NEW template

**Documentation:**
- `dev/docs/ROLE_CAPABILITY_REFACTOR.md` — mark Stage 2 complete
- `dev/docs/sessions/2026-05-16_cc6c030_CHECKPOINT.md` — update (carried forward)

---

## Next Steps (Stage 3)

**Widget dashboard canvases:**
- Add `context_dashboards` table
- Implement `DashboardService::renderWidgetCanvas()`
- Add dashboard resolution order (user → position → role → default)
- Add Site Builder dashboards tab
- Add dashboard assignment UI in role config and org position config

---

## Commit Message

```
feat(auth): Stage 2 — Admin area grants for sub-admin access [v1.0.0-beta.9]

Add granular admin access control via admin_area_grants table:
- adminMiddleware: check area grants before blocking non-admins
- Auth::requireAdminArea(): check admin OR role/position grant
- Auth::getAreaSlugForUri(): map URI to grantable area slug
- Auth::hasAreaGrant(): session-cached grant lookups
- config/admin_areas.php: define grantable areas (blog, forum, mailout, etc.)
- RoleAdminController: area config UI (checkbox list)
- routes: GET/POST /admin/roles/{id}/areas
- templates/admin/roles/areas.php: area grants UI
- migration 015: add admin_area_grants table to existing instances
- MenuController::blockEditor: use requireAdminArea('menus')

Admin role (level >= 100) bypasses grants (always has full access).
Non-admin roles can now access specific sections without full admin.
Position grants stubbed for Stage 4 (organisation module integration).
```

---

## Mode Instructions Update

Update the mode instructions with:
- Current HEAD: TBD (after commit)
- Version: `v1.0.0-beta.9` (in progress)
- Session summary: Stage 2 complete — Admin area grants, middleware + UI + migration
