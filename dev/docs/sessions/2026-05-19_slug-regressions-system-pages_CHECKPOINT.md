# 2026-05-19 Slug Regressions and System Pages Checkpoint

**Base commit:** `85f4af0`  
**Date:** 2026-05-19  
**Status:** Investigation only. No code changes made in this pass.  
**Purpose:** Capture the architectural regressions introduced after the slug-removal refactor so the next session can repair them without repeating the same tracing work.

---

## Core Rule Reconfirmed

Slugs persist as public display and public routing only.

They must **not** be used as internal architectural identifiers for:

- engine-owned system pages
- template ownership
- canvas ownership
- zone canvas resolution
- internal template selection
- any engine/controller/service decision where a stable page ID, template ID, block ID, or explicit typed field already exists

This document lists the places where recent work regressed back to slug-coupled control flow.

---

## Immediate Live Bug Context

The login OAuth buttons stopped appearing after the PHP-page rejig that moved system pages from direct template rendering to DB-backed system page rendering.

Before:

- `/login` rendered `templates/public/login.php` directly from `AuthController`

Now:

- `AuthController::showLogin()` calls `renderSystemPage('login', ...)`
- `BaseController::renderSystemPage()` resolves the controlling page by `pages_index.slug = 'login'`
- the page content is expected to come from a DB-backed `php-include` block

This means engine behaviour for login now depends on a mutable slug-bound `pages_index` row instead of a stable engine-owned identity.

That is the highest-risk regression for the current live problem.

---

## Confirmed Regression Sites

### 1. Engine system pages resolved by slug

**File:** `src/Controllers/BaseController.php`

**Problem:**

- `renderSystemPage(string $slug, array $data = [])`
- queries `SELECT * FROM pages_index WHERE slug = ? LIMIT 1`
- if any row exists, it takes over rendering
- fallback to direct PHP render only happens when no row exists at all

**Why this is wrong:**

- engine-owned pages like login/register/profile are now controlled by a public-facing slug field
- stale or conflicting rows can silently hijack engine behaviour
- this reintroduces the exact identifier coupling the refactor was supposed to remove

**Why it matters now:**

- this is the most likely architectural reason the login page behaviour changed after the PHP-page migration

---

### 2. System page migration seeds identity by slug

**File:** `migrations/core/017_system_pages.sql`

**Problem:**

- inserts `pages_index` rows with slugs like `login`, `register`, `profile`
- then inserts `pages` rows by selecting `id` from `pages_index WHERE slug = 'login'` etc.
- uses `INSERT IGNORE`

**Why this is wrong:**

- system page identity is bootstrapped through public slugs
- `INSERT IGNORE` makes the migration non-authoritative
- any pre-existing row or stale row wins silently

**Why it matters now:**

- live may be using an older or unexpected `login` page row/block arrangement, and the engine now trusts it automatically

---

### 3. Zone canvas runtime still has slug fallback

**File:** `src/Services/CruinnRenderService.php`

**Problem:**

- `resolveZoneCanvasId()` correctly checks:
  - `pages_index.zone_overrides`
  - `page_templates.zone_canvases`
  - `pages_index.canvas_type = 'zone' AND zone_name = ?`
- but then still falls back to `pages_index.slug = '_' . $zone`

**Why this is wrong:**

- migration 011 introduced explicit typed zone resolution
- the slug fallback keeps legacy underscore naming alive in the runtime control path

**Risk:**

- old slug-shaped pages can still influence zone resolution after the refactor

---

### 4. Template canvas identity reconstructed from slug conventions

**File:** `src/Admin/Controllers/SiteBuilderController.php`

**Problem:**

- `builderEnsureCanvas()` derives `_tpl_{templateSlug}` and looks up the canvas page by slug
- `builderEnsureZoneCanvas()` creates `_zone_{templateSlug}_{zoneName}` slug identities for zone canvases

**Why this is wrong:**

- the newer schema already provides stable IDs: `page_templates.id`, `page_templates.canvas_page_id`, `page_templates.zone_canvases`
- slug-based synthetic identities should not be needed for internal canvas ownership anymore

**Risk:**

- internal template/canvas repair logic can still drift if template slugs change or legacy slug-shaped pages exist

---

### 5. Internal content template selection by template slug

**File:** `src/Services/CruinnRenderService.php`

**Problem:**

- `buildContentTemplate(string $slug, array $context)` resolves `page_templates` by slug

**Why this is wrong:**

- content-template selection inside the engine should prefer stable template IDs once selected upstream
- slugs should be presentation-facing, not internal selection keys

**Risk:**

- internal template rendering remains coupled to mutable template slugs

---

### 6. Page-template associations still rely on template slug fields

**Files:**

- `src/Admin/Controllers/SiteBuilderController.php`
- `src/Admin/Controllers/AdminPageController.php`

**Problem:**

- page usage counts and page-template lookups still use `pages_index.template = page_templates.slug`
- template-zone resolution still fetches template data by slug

**Why this is wrong:**

- the architecture still has template identity split between ID-based and slug-based paths
- internal ownership is not fully migrated to IDs

---

## What Was Checked and Ruled Out

These were checked during investigation and are **not** the strongest failure point for the login OAuth regression:

### `php-include` context handoff

Checked and confirmed:

- `renderSystemPage()` pushes `$data` into `Template::addGlobal(...)`
- `renderSystemPage()` also calls `CruinnRenderService::setContext($data)`
- `CruinnRenderService` passes `$this->context` into `BlockRegistry::renderDynamic(...)`
- `php-include` merges `Template::globals()`, `$context`, and block vars before `extract()`

Conclusion:

- the variable handoff path for `$oauth_providers` is wired correctly in code
- the bigger problem is that the engine now trusts the wrong DB-backed system page identity too early

### Login OAuth route wiring

Checked and confirmed:

- `/auth/{provider}` is registered by `modules/oauth/module.php`
- no core route shadows it
- route existence depends on the oauth module being active

Conclusion:

- route syntax/order is not the primary regression here

---

## Most Likely Root Cause for the Current Login OAuth Break

The system-page migration changed the controlling identity of `/login` from:

- direct PHP template render

to:

- DB-backed page resolved by public slug

Because `renderSystemPage()` trusts any `pages_index.slug = 'login'` row, a stale or mismatched system page can silently take over login rendering.

That is the cleanest architectural explanation for why login broke during the PHP-page rejig.

---

## Repair Guide

### Phase 1: Stop the active regression

Goal: make engine system pages safe again without broad schema churn.

1. Replace slug-driven trust in `BaseController::renderSystemPage()`.
2. Do not accept any `pages_index` row purely because its slug matches.
3. Require a stable engine-owned identity or a validated page contract before using DB-backed rendering.
4. If the contract is not satisfied, fall back to direct PHP render.

Practical minimum fix if a full ID migration is not done immediately:

- require `status = 'published'`
- require published blocks to exist
- require the expected system-page block contract to exist
- otherwise fall back to `render('public/' . $slug, $data)`

This is a containment fix, not the full architectural repair.

### Phase 2: Give system pages stable internal identity

Goal: remove slug from system-page resolution entirely.

Options:

1. Add a dedicated field on `pages_index` such as `system_key` with values like:
   - `login`
   - `register`
   - `profile`
   - `forgot-password`
   - `reset-password`
   - `verify-email-sent`

2. Or add a dedicated engine-owned mapping table from system route key to page ID.

Preferred direction:

- `pages_index.system_key` for engine-owned pages
- unique where not null
- controllers resolve by `system_key`, never by slug

Then update:

- `renderSystemPage()` to query by `system_key`
- migration 017 replacement/backfill to seed by `system_key`
- any repair migration to assign `system_key` to existing rows

### Phase 3: Remove remaining slug-based internal canvas resolution

Goal: finish the canvas refactor properly.

1. Remove runtime fallback from `resolveZoneCanvasId()` to underscore slug pages.
2. Ensure all required zone canvases are resolved through:
   - `zone_overrides`
   - `zone_canvases`
   - `canvas_type = 'zone' + zone_name`
3. Update any migration/backfill needed so instances no longer depend on underscore slug conventions.

### Phase 4: Remove template/canvas slug coupling in builder flows

Goal: stop reconstructing internal template/canvas ownership from slugs.

1. Stop using `_tpl_{templateSlug}` as the authoritative canvas identity.
2. Stop using `_zone_{templateSlug}_{zoneName}` as the authoritative zone-canvas identity.
3. Use `page_templates.canvas_page_id` and `page_templates.zone_canvases` as the sole ownership links.
4. Keep any slug-like values only as optional labels if truly needed for display.

### Phase 5: Remove template selection by slug in internal services

Goal: internal content/template rendering should operate on IDs after selection.

1. Audit service methods that currently accept template slug.
2. Move upstream selection to IDs where possible.
3. Keep slug resolution only at public-facing routing boundaries.

---

## Suggested Next Session Order

1. Repair `renderSystemPage()` so it no longer trusts slug rows blindly.
2. Introduce stable internal identity for system pages.
3. Write a migration/backfill to assign the new identity to existing instances.
4. Remove or quarantine legacy slug fallback in zone canvas resolution.
5. Refactor template/canvas ownership code to use IDs only.

---

## Files Most Likely To Need Repair

- `src/Controllers/BaseController.php`
- `src/Controllers/AuthController.php`
- `src/Services/CruinnRenderService.php`
- `src/Admin/Controllers/SiteBuilderController.php`
- `src/Admin/Controllers/AdminPageController.php`
- `schema/instance_core.sql`
- `migrations/core/017_system_pages.sql`
- follow-up migration(s) for stable system-page identity and canvas cleanup

---

## Bottom Line

The slug-removal refactor was not carried through.

The PHP-page/system-page rejig reintroduced slug-based internal control in the exact area that now appears to be breaking login behaviour. The next repair session should treat this as an architectural regression, not just an OAuth bug.