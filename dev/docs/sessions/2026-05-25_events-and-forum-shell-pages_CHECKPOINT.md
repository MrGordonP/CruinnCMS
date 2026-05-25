# 2026-05-25 Events And Forum Shell Pages CHECKPOINT

Date: 2026-05-25
Branch: main
Status: working tree

## Scope

This checkpoint captures the Events and Forum follow-up slice after the Blog control-centre work on beta.15:

- Events expanded to the newer shell-page and profile-driven pattern already used by Blog
- remaining hard-coded public `/events` assumptions removed from Events public/admin rendering paths
- Forum public GET rendering moved off legacy hard-coded `/forum` page ownership and onto a resolver-backed shell page
- shared editor and module-content plumbing extended so Events and Forum can participate in the modern module-content flow
- final Forum follow-up hotfixes aligned stale admin/provider auth calls and ensured the forum content widget can populate index data when rendered directly

## What changed

### 1) Events was expanded to the Blog-style architecture

- Events now carries module-owned shell-page resolution instead of relying on hard-coded public `/events` page ownership.
- Added Events admin control-centre surfaces for dashboard, settings, and profiles.
- Added relational `event_profiles` storage and editor-facing profile selection support.
- Added Events module-content rendering paths for list, detail, and combined content behavior.

### 2) Legacy public `/events` assumptions were removed

- Removed the old hard-coded public GET `/events` routes from the Events module.
- Reworked Events public URL generation to derive from the configured shell page rather than falling back to `/events`.
- Removed stale `/events` assumptions from templates, widgets, and controller-driven links touched in this slice.
- Event emails no longer assume the old public `/events` route shape for registration/cancel links.

### 3) Forum public GET rendering was moved onto a shell page

- Added `forum_list_page_id` to Forum module settings and exposed it in the Modules settings panel via the existing published-page selector flow.
- Removed Forum public GET route ownership from the module and added `public_path_resolver` handling instead.
- Added a generic Forum module-content provider so a shell page can render Forum index, category, thread, search, and edit/report views.
- Reworked Forum public links and controller redirects to derive visible GET URLs from the configured shell page.

### 4) Forum POST actions intentionally remain fixed routes

- The current resolver architecture only owns GET rendering through the page catch-all.
- Forum create/reply/edit/delete/report forms therefore still post to fixed `/forum/...` action routes.
- Template data now separates visible shell-page URLs from the retained action base path so public navigation is no longer hard-coded while form submission continues to function.

### 5) Shared editor/module-content plumbing was extended

- `module-content` definition and editor payloads were expanded so Events and Forum module-owned rendering options can surface in the editor.
- The admin editor template and `public_html/js/editor.js` were updated to expose the new module-content options.
- A JS syntax regression introduced during this slice was repaired and revalidated.

### 6) Local defects corrected while touching the Forum slice

- Fixed undefined-variable usage in the touched Forum admin controller queries.
- Removed a duplicated lower half from the Forum thread template.
- Removed the final stale hard-coded Forum category link left behind in the public index template.

### 7) Forum auth contract was brought into line with the platform

- The Forum provider interface had been left behind on the old role-string contract from before the auth-level migration.
- `ForumProviderInterface` now accepts numeric viewer levels on the category visibility methods so it matches `NativeForumProvider` and the existing controller call path.
- This removes the production fatal caused by the stale method signature mismatch.

### 8) Final Forum follow-up hotfixes were applied after deployment checks

- `ForumAdminController` was still passing the old `'admin'` string into `listCategoriesHierarchical()`; that call now uses `Auth::roleLevel()`.
- `contentProviderForumContent()` now falls back to `buildIndexViewData()` when a Forum content block is rendered directly without resolver-supplied `forum_view` context.
- This fixes the direct widget case where the shell-page Forum block was rendering `No forum categories are available yet.` despite active categories existing.

## Files included in this checkpoint

- CruinnCMS/modules/events/migrations/004_event_profiles.sql
- CruinnCMS/modules/events/module.php
- CruinnCMS/modules/events/src/Controllers/EventController.php
- CruinnCMS/modules/events/templates/admin/events/_nav.php
- CruinnCMS/modules/events/templates/admin/events/dashboard.php
- CruinnCMS/modules/events/templates/admin/events/edit.php
- CruinnCMS/modules/events/templates/admin/events/index.php
- CruinnCMS/modules/events/templates/admin/events/settings.php
- CruinnCMS/modules/events/templates/admin/events/show.php
- CruinnCMS/modules/events/templates/admin/events/profiles/index.php
- CruinnCMS/modules/events/templates/admin/events/profiles/edit.php
- CruinnCMS/modules/events/templates/public/events/index.php
- CruinnCMS/modules/events/templates/public/events/register.php
- CruinnCMS/modules/events/templates/public/events/show.php
- CruinnCMS/modules/events/templates/public/events/module-content/list.php
- CruinnCMS/modules/events/templates/public/events/module-content/detail.php
- CruinnCMS/modules/events/templates/public/events/module-content/content.php
- CruinnCMS/modules/forum/module.php
- CruinnCMS/modules/forum/src/Controllers/ForumAdminController.php
- CruinnCMS/modules/forum/src/Controllers/ForumController.php
- CruinnCMS/modules/forum/src/Forum/ForumProviderInterface.php
- CruinnCMS/modules/forum/templates/admin/forum/edit-post.php
- CruinnCMS/modules/forum/templates/admin/forum/index.php
- CruinnCMS/modules/forum/templates/admin/forum/move-thread.php
- CruinnCMS/modules/forum/templates/admin/forum/reports.php
- CruinnCMS/modules/forum/templates/public/forum/category.php
- CruinnCMS/modules/forum/templates/public/forum/edit-post.php
- CruinnCMS/modules/forum/templates/public/forum/edit-thread-title.php
- CruinnCMS/modules/forum/templates/public/forum/index.php
- CruinnCMS/modules/forum/templates/public/forum/new.php
- CruinnCMS/modules/forum/templates/public/forum/report-post.php
- CruinnCMS/modules/forum/templates/public/forum/search.php
- CruinnCMS/modules/forum/templates/public/forum/thread.php
- CruinnCMS/modules/forum/templates/public/forum/module-content/content.php
- CruinnCMS/src/Admin/Controllers/AcpSystemController.php
- CruinnCMS/src/BlockTypes/module-content/definition.php
- CruinnCMS/src/Controllers/CruinnController.php
- CruinnCMS/templates/admin/editor.php
- public_html/js/editor.js

## Validation

- PHP lint passed during the slice on the touched Events and Forum controllers, templates, and shared PHP integration files checked immediately after editing.
- PHP lint also passed on the Forum provider contract files after aligning `ForumProviderInterface` with `NativeForumProvider`.
- PHP lint also passed on the final Forum hotfix files after correcting the stale admin provider call and the direct widget index-data fallback.
- `node --check public_html/js/editor.js` passed after repairing the editor syntax regression introduced during the Events work.
- A residual Forum hard-code scan confirmed that remaining literal `/forum` references are limited to the intentional fixed POST action endpoints and their matching action-base defaults.
- A focused Forum auth sweep found no further stale `viewerRole` contract remnants beyond the interface mismatch.
- Diagnostics reported no relevant new errors in the checked Forum files during the final focused sweep.

## Operational notes

- Forum now requires a published shell page to be selected via `forum_list_page_id` in module settings.
- That shell page must contain a `forum:content` module-content block for the resolver-backed Forum views to render.
- Runtime browser verification of the new Forum shell-page flow has not yet been completed in this checkpoint.
