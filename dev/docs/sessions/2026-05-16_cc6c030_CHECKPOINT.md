# CruinnCMS Session Checkpoint — 16 May 2026

**Commit:** `cc6c030`
**Version:** `v1.0.0-beta.9` (in progress)
**Branch:** `main`

---

## What Changed

**Stage 1 — Auth API Cleanup (Role & Capability Refactor)**

Completed the first stage of the role capability refactor planned in `ROLE_CAPABILITY_REFACTOR.md`. The engine is now fully instance-agnostic with respect to role names — all logic uses numeric levels, no hardcoded slugs.

### Auth.php API Changes

**Removed (hardcoded slug dependencies):**
- `Auth::role(): ?string` — derived slug from level bands (admin/council/editor/member)
- `Auth::hasRole(string $minimumRole): bool` — accepted slug strings
- `Auth::requireRole(string $minimumRole): void` — gated on slug strings

**Added (instance-agnostic numeric checks):**
- `Auth::isAdmin(): bool` — checks `roleLevel() >= 100`
- `Auth::isLoggedIn(): bool` — alias for `check()`, semantic clarity
- `Auth::requireAdmin(): void` — 403 if not admin
- `Auth::requireLevel(int $minimumLevel): void` — 403 if level insufficient
- `Auth::requireAdminArea(string $slug): void` — stub for Stage 2 (admin area grants)
- `Auth::positionIds(): array` — stub for Stage 4 (organisation module integration)

**Renamed:**
- `Auth::requireLogin()` → `Auth::requireLoggedIn()` (consistency with `isLoggedIn()`)

### Template.php Visibility Check

Replaced hardcoded role slug→level map with pure numeric check:
- Before: `$roleLevels = ['public' => 0, 'editor' => 20, 'council' => 50, 'admin' => 100];`
- After: `$userLevel = Auth::roleLevel(); $reqLevel = (int) $row['min_role'];`

Menu visibility now checks `roleLevel()` instead of `groupLevel()` — this is a **CMS admin role** check, not a content-access group check.

### Controller Updates

Replaced all 48 instances of `Auth::requireRole('admin')` with `Auth::requireAdmin()` across:
- `src/Controllers/` (MenuController, CruinnController)
- `src/Admin/Controllers/` (all admin controllers)

### Schema Changes

**menu_items table:**
- `min_role` column: `VARCHAR(20)` → `SMALLINT UNSIGNED`
- Now stores numeric levels (0, 10, 50, 100) instead of slug strings

**instance_core.sql:**
- Only seeds **system roles** (`admin`, `public`) with `is_system=1`
- Removed `editor` role from core schema (was previously marked as system role)
- Updated column definition with comment: `'Minimum role level (0=public, 10=member, 50=council, 100=admin)'`

**themes/default/seed.sql:**
- Added **instance-specific roles**: `council` (level 50), `member` (level 10)
- Both marked as `is_system=0` so they can be customised or removed per instance
- Uses `INSERT IGNORE` for idempotent seeding

### Migration 014

Created `migrations/core/014_role_visibility_levels.sql`:
- Converts existing `menu_items.min_role` slug values to numeric levels
- Maps: `public` → 0, `member` → 10, `editor` → 20, `council` → 50, `admin` → 100
- Alters column type to `SMALLINT UNSIGNED`
- Safe to run on live instances (uses temp column during conversion)

---

## Why This Matters

**Instance-agnostic engine:**
The platform no longer hardcodes role names beyond `admin` (level 100) and `public` (level 0). Instance-specific roles like `council`, `member`, or `editor` are now instance data, not engine assumptions.

**Numeric levels everywhere:**
All role checks use `Auth::roleLevel()` and integer comparisons. No slug parsing, no string→level maps in code.

**Prepares for Stages 2–3:**
- Stage 2 will add `admin_area_grants` table for sub-admin access (blog, forum, mailout) without full admin privileges
- Stage 3 will add `context_dashboards` for role/position/user-specific widget dashboards
- Stage 4 will integrate organisation module positions into the auth flow

---

## Next Steps (Stages 2–5)

**Stage 2 — Admin Area Grants:**
- Add `admin_area_grants` table (area_slug, context_type, context_id)
- Implement `Auth::requireAdminArea()` grant checking
- Replace `requireAdmin()` with `requireAdminArea('slug')` in grantable controllers
- Add UI for assigning area grants to roles and positions

**Stage 3 — Widget Dashboard Canvases:**
- Add `context_dashboards` table (context_type, context_id, page_id)
- Add `DashboardService::renderWidgetCanvas()` with userContext injection
- Add dashboard resolution order (user → position → role → default)
- Add SiteBuilder dashboards tab and assignment UI

**Stage 4 — Position Dashboard in Org Module:**
- Implement `Auth::positionIds()` backed by organisation module
- Add position area grants UI
- Add position dashboard assignment UI

**Stage 5 — Notifications Widget:**
- Add `notifications` widget registered by mailbox module
- Query unread mailbox counts for user/position

---

## Files Changed (14 files)

**Core Changes:**
- `src/Auth.php` — API refactor (removed 3, added 6, renamed 1)
- `src/Template.php` — visibility check uses `roleLevel()`
- `schema/instance_core.sql` — system roles only, `min_role` column type change
- `migrations/core/014_role_visibility_levels.sql` — NEW migration

**Controller Updates (all `requireRole()` → `requireAdmin()`):**
- `src/Controllers/MenuController.php`
- `src/Controllers/CruinnController.php`
- `src/Admin/Controllers/AcpSystemController.php`
- `src/Admin/Controllers/AdminImportController.php`
- `src/Admin/Controllers/AdminPageController.php`
- `src/Admin/Controllers/MaintenanceController.php`
- `src/Admin/Controllers/SiteBuilderController.php`
- `src/Admin/Controllers/ThemeController.php`
- `src/Admin/Controllers/UserAdminController.php`

**Theme Seed:**
- `themes/default/seed.sql` — instance roles (council, member)

---

## Testing Notes

**Not yet tested on live instance.**
Changes are schema-compatible but require:
1. Running migration `014_role_visibility_levels.sql` on existing instances
2. Running theme seed to add `council` and `member` roles to existing instances
3. Verifying menu visibility checks still work correctly
4. Checking all admin routes still enforce `requireAdmin()` properly

**Breaking changes for custom instance code:**
Any custom controllers using `Auth::requireRole()`, `Auth::hasRole()`, or `Auth::role()` will break. They must migrate to:
- `Auth::requireAdmin()` or `Auth::requireLevel(int)`
- `Auth::roleLevel()` with manual numeric checks
- `Auth::roleId()` if they need the actual role DB ID

No public-facing template code should be affected (templates never called these methods).

---

## Commit Message

```
refactor(auth): Stage 1 — Auth API cleanup for role capability refactor [v1.0.0-beta.9]

Remove hardcoded role slug dependencies from engine code:
- Auth.php: Remove role(), hasRole(), requireRole() methods
- Auth.php: Add isAdmin(), isLoggedIn(), requireAdmin(), requireLevel()
- Auth.php: Add requireAdminArea() and positionIds() stubs for Stage 2+
- Auth.php: Rename requireLogin() → requireLoggedIn()
- Template.php: Replace hardcoded roleLevels map with Auth::roleLevel()
- Controllers: Replace all Auth::requireRole('admin') with Auth::requireAdmin()
- Schema: menu_items.min_role VARCHAR(20) → SMALLINT UNSIGNED
- Migration 014: Convert existing slug values to numeric levels
- instance_core.sql: Seed only system roles (admin, public)
- themes/default/seed.sql: Add instance roles (council, member)

Engine now uses numeric role levels exclusively. No role slugs in logic.
Stage 2 (admin area grants) and Stage 3 (widget dashboards) next.
```

---

## Mode Instructions Update

Update the mode instructions with:
- Current HEAD: `cc6c030`
- Version: `v1.0.0-beta.9` (in progress)
- Session summary: Stage 1 complete — Auth API cleanup, numeric role levels, migration 014
