# CruinnCMS Session Checkpoint — 16 May 2026 (Stage 3)

**Commit:** `862f8ed`
**Version:** `v1.0.0-beta.9` (in progress)
**Branch:** `main`

---

## What Changed

**Stage 3 — Widget Dashboard Canvases (Role & Capability Refactor)**

Completed the third stage of the role capability refactor. The system now supports block-based
widget dashboards that can be assigned to roles, positions, or individual users, with resolution
order: user → position → role → default admin dashboard.

### Schema Changes

**New table: `context_dashboards`**
```sql
CREATE TABLE `context_dashboards` (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    context_type ENUM('role','position','user') NOT NULL,
    context_id   INT UNSIGNED NOT NULL,
    page_id      INT UNSIGNED NOT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_by   INT UNSIGNED DEFAULT NULL,
    UNIQUE KEY uq_context (context_type, context_id),
    ...
);
```

Added to `schema/instance_core.sql` and `migrations/core/016_context_dashboards.sql`.

### DashboardService Changes

**New Methods:**
- `renderWidgetCanvas(int $pageId, array $userContext): string`
  - Fetches published blocks for dashboard page
  - For `module-widget` blocks: injects userContext into properties
  - For other blocks: renders normally via CruinnRenderService
  - Returns complete HTML string

- `resolveDashboardForUser(int $userId): ?int`
  - Resolution order: user-specific → position → role → admin default
  - Returns dashboard page ID or null if none configured

- `listDashboardCanvases(): array`
  - Returns all pages where `canvas_type='widget-dashboard'`

- `getDashboardForContext(string $type, int $id): ?int`
  - Returns assigned dashboard page ID for a context

- `assignDashboard(string $type, int $id, int $pageId): void`
  - Assigns dashboard to role/position/user
  - Updates if exists, inserts if new

- `removeDashboard(string $type, int $id): void`
  - Removes dashboard assignment for context

### SiteBuilderController Changes

**New Methods:**
- `builderDashboards()` — GET `/admin/site-builder/dashboards`
  - Lists all widget dashboard canvases
  - Shows assignment counts for each dashboard
  
- `builderCreateDashboard()` — POST `/admin/site-builder/dashboards/new`
  - Creates new page with `canvas_type='widget-dashboard'`
  - Redirects to block editor

- `builderDeleteDashboard(int $id)` — POST `/admin/site-builder/dashboards/{id}/delete`
  - Deletes dashboard page and all assignments
  - Removes blocks (published and draft)

- `generateUniqueSlug(string $title): string` (private helper)
  - Creates unique slug for dashboard pages

### RoleAdminController Changes

**New Methods:**
- `dashboardCanvasConfig(int $id)` — GET `/admin/roles/{id}/dashboard-canvas`
  - Shows dropdown of available dashboard canvases
  - Displays current assignment for role

- `saveDashboardCanvasConfig(int $id)` — POST `/admin/roles/{id}/dashboard-canvas`
  - Assigns selected dashboard to role
  - Removes assignment if "None" selected
  - Logs activity

### New Routes

```php
// Site Builder
$router->get('/admin/site-builder/dashboards', [SiteBuilderController::class, 'builderDashboards']);
$router->post('/admin/site-builder/dashboards/new', [SiteBuilderController::class, 'builderCreateDashboard']);
$router->post('/admin/site-builder/dashboards/{id}/delete', [SiteBuilderController::class, 'builderDeleteDashboard']);

// Role Config
$router->get('/admin/roles/{id}/dashboard-canvas', [RoleAdminController::class, 'dashboardCanvasConfig']);
$router->post('/admin/roles/{id}/dashboard-canvas', [RoleAdminController::class, 'saveDashboardCanvasConfig']);
```

### New Templates

**`templates/admin/site-builder/dashboards.php`:**
- Lists all widget dashboard canvases
- Form to create new dashboard
- Shows assignment counts
- "Edit Blocks" and "Delete" actions
- Usage instructions

**`templates/admin/roles/dashboard-canvas.php`:**
- Dropdown to select dashboard for role
- Shows current assignment
- Link to edit blocks in assigned dashboard
- Instructions for building dashboards

---

## How It Works

### Dashboard Creation Flow

1. Admin navigates to `/admin/site-builder/dashboards`
2. Creates new dashboard with a title
3. System creates `pages_index` entry with `canvas_type='widget-dashboard'`
4. Admin is redirected to block editor
5. Admin adds `module-widget` blocks and layout blocks
6. Publishes dashboard

### Dashboard Assignment Flow

1. Admin navigates to `/admin/roles/{id}/dashboard-canvas`
2. Selects dashboard from dropdown
3. Form submits to POST endpoint
4. `DashboardService::assignDashboard()` stores assignment
5. Assignment saved in `context_dashboards` table

### Dashboard Resolution Flow

1. User visits `/admin/dashboard`
2. System calls `DashboardService::resolveDashboardForUser(userId)`
3. Checks in order:
   - User-specific dashboard (`context_type='user', context_id=userId`)
   - Position dashboard (`context_type='position', context_id=positionId`)
   - Role dashboard (`context_type='role', context_id=roleId`)
   - Admin role default dashboard
4. Returns page ID or null
5. Dashboard rendered via `renderWidgetCanvas(pageId, userContext)`

### Widget Block Context Injection

When rendering `module-widget` blocks:
```php
$props = json_decode($block['properties'] ?? '{}', true) ?? [];
$props['_userContext'] = [
    'user_id'      => Auth::userId(),
    'role_id'      => Auth::roleId(),
    'role_level'   => Auth::roleLevel(),
    'position_ids' => Auth::positionIds(),
];
$block['properties'] = json_encode($props);
```

Module widget data providers can access `$settings['_userContext']` to provide
user-specific, role-specific, or position-specific data.

---

## Stage 3 Completion Notes

**Fully functional:**
- Dashboard canvas creation and editing
- Dashboard assignment to roles
- Dashboard resolution logic
- Widget canvas rendering
- Context injection for module-widget blocks

**Not yet implemented (Stage 4):**
- Position-based dashboard assignment UI (requires organisation module)
- User-specific dashboard assignment UI
- `Auth::positionIds()` still returns `[]` stub

**Not yet implemented (Stage 5):**
- `notifications` widget (requires mailbox module integration)

**Testing required:**
- Create a widget dashboard with several blocks
- Assign to a role
- Log in as user with that role
- Verify dashboard displays correctly
- Test resolution order (user → position → role)

---

## Files Changed (10 files)

**Schema:**
- `schema/instance_core.sql` — add `context_dashboards` table
- `migrations/core/016_context_dashboards.sql` — NEW migration

**Services:**
- `src/Services/DashboardService.php` — add widget canvas methods

**Controllers:**
- `src/Admin/Controllers/SiteBuilderController.php` — add dashboard management methods
- `src/Admin/Controllers/RoleAdminController.php` — add dashboard assignment methods

**Routes:**
- `config/routes.php` — add dashboard routes

**Templates:**
- `templates/admin/site-builder/dashboards.php` — NEW template
- `templates/admin/roles/dashboard-canvas.php` — NEW template

**Documentation:**
- `dev/docs/ROLE_CAPABILITY_REFACTOR.md` — mark Stage 3 complete
- This checkpoint file

---

## Next Steps (Stage 4)

**Position dashboard in org module:**
- Implement `Auth::positionIds()` backed by organisation module
- Add position area grants UI (uses Stage 2 infrastructure)
- Add position dashboard assignment UI (uses Stage 3 infrastructure)

---

## Commit Message

```
feat(dashboard): Stage 3 — Widget dashboard canvases [v1.0.0-beta.9]

Add block-based widget dashboard system with context assignment:
- context_dashboards table: assign dashboards to role/position/user
- DashboardService::renderWidgetCanvas(): render blocks with userContext injection
- DashboardService::resolveDashboardForUser(): user → position → role → default
- SiteBuilder dashboards UI: list, create, delete dashboard canvases
- RoleAdminController: dashboard-canvas assignment UI
- Routes: /admin/site-builder/dashboards, /admin/roles/{id}/dashboard-canvas
- Templates: dashboards.php, dashboard-canvas.php
- migration 016: add context_dashboards to existing instances

Widget dashboards are pages_index with canvas_type='widget-dashboard'
built in block editor using module-widget + layout blocks.
Module widgets receive userContext in properties for personalized data.
Position assignment UI deferred to Stage 4 (org module integration).
```

---

## Mode Instructions Update

Update the mode instructions with:
- Current HEAD: `862f8ed`
- Version: `v1.0.0-beta.9` (in progress)
- Session summary: Stage 3 complete — Widget dashboard canvases, assignment + resolution
