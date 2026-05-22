# 2026-05-22 Blog Control Centre And Navigation CHECKPOINT

Date: 2026-05-22
Branch: main
Status: pushed with accompanying commit

## Scope

This checkpoint captures the current Blog module follow-up slice on top of beta.15:

- public blog presentation moved fully into the Blog module
- Blog admin cut over from `Articles` to a Blog-owned control centre
- Blog settings moved out of Modules into typed settings rows
- relational Blog Profiles added and exposed in the editor
- blog module-content provider wiring extended for combined/list/post behavior
- single-post navigation and return-to-list UX improved and then tightened to stay within template containment

## What changed

### 1) Blog now owns its public rendering path

- Public blog wrappers now live in the module instead of shared legacy article templates.
- Module content providers now supply:
  - `blog:list`
  - `blog:post`
  - `blog:content`
- Combined mode now context-switches correctly between list and single-post rendering instead of trying to render both indiscriminately.
- Legacy public article template surfaces were removed where they no longer matched the active module-owned rendering path.

### 2) Blog admin is now a real control centre

- Added Blog admin landing page at `/admin/blog`.
- Added Blog settings page at `/admin/blog/settings`.
- Shifted user-facing admin routes from `Articles` paths to `Blog` paths:
  - `/admin/blog`
  - `/admin/blog/posts`
  - `/admin/blog/editor/{id}/edit`
- Updated widgets and editor navigation so Blog opens the control-centre flow rather than the old article-centric surface.

### 3) Blog settings moved out of Modules

- Blog runtime settings now read from typed rows in the instance `settings` table under group `blog`.
- Current typed keys:
  - `blog.list_page_id`
  - `blog.post_page_id`
  - `blog.default_posts_per_page`
  - `blog.show_return_to_list`
  - `blog.show_post_navigation`
- Modules UI no longer acts as the primary editorial settings surface for Blog.
- Added fallback import from legacy `module_config.settings` so existing instances retain their live containment page mapping until explicitly re-saved in Blog settings.

### 4) Relational Blog Profiles added

- Added `blog_profiles` as typed relational storage.
- Added Blog admin CRUD for profiles under `/admin/blog/profiles`.
- Profiles currently carry reusable rendering defaults for:
  - display mode
  - posts per page
  - show return-to-list
  - show previous/next navigation
- The editor now exposes Blog Profile selection on module-content blocks for Blog providers.
- Runtime settings merge is profile-aware, while block-level overrides still win when explicitly set.

### 5) Editor and provider integration expanded

- `module-content` block config now supports Blog-specific `per_page` and `blog_profile_id`.
- The editor properties UI was updated so the Content accordion sits higher and Blog module-content blocks expose the relevant controls.
- `CruinnController` now supplies the Blog profile catalog into the generic editor payload so the editor template remains generic while still allowing module-owned enhancements.

### 6) Single-post UX improved

- Added top and bottom `Return to list` links.
- Added previous/next post navigation above and below the post body.
- Corrected the adjacent-post semantics so:
  - `Previous` means older
  - `Next` means newer
- Changed nav link markup to present the label on one line and the related post title beneath it.

### 7) Post navigation containment fixes

- Initial nav-card sizing still pushed some templates beyond their inline-block main/sidebar split.
- The final minimal containment fix was:
  - keep `.blog-post-nav` as a flex row
  - set `.blog-post-nav-link` width to `48%`
  - set `.blog-post` to `overflow-wrap: anywhere`
- This was intentionally chosen as the smallest broadly effective fix for the current template architecture.

## Files included in this checkpoint

- CruinnCMS/modules/blog/migrations/schema.sql
- CruinnCMS/modules/blog/migrations/005_blog_profiles.sql
- CruinnCMS/modules/blog/module.php
- CruinnCMS/modules/blog/src/Controllers/ArticleController.php
- CruinnCMS/modules/blog/src/Controllers/ArticleEditorController.php
- CruinnCMS/modules/blog/templates/admin/articles/edit.php
- CruinnCMS/modules/blog/templates/admin/articles/index.php
- CruinnCMS/modules/blog/templates/admin/blog/_nav.php
- CruinnCMS/modules/blog/templates/admin/blog/dashboard.php
- CruinnCMS/modules/blog/templates/admin/blog/settings.php
- CruinnCMS/modules/blog/templates/admin/blog/profiles/index.php
- CruinnCMS/modules/blog/templates/admin/blog/profiles/edit.php
- CruinnCMS/modules/blog/templates/public/articles/module-content/list.php
- CruinnCMS/modules/blog/templates/public/articles/module-content/content.php
- CruinnCMS/modules/blog/templates/public/articles/module-content/post.php
- CruinnCMS/modules/blog/templates/public/blog.list.php
- CruinnCMS/modules/blog/templates/public/blog.post.php
- CruinnCMS/src/Admin/Controllers/AcpSystemController.php
- CruinnCMS/src/BlockTypes/module-content/definition.php
- CruinnCMS/src/Controllers/CruinnController.php
- CruinnCMS/src/Controllers/PageController.php
- CruinnCMS/src/Modules/ModuleRegistry.php
- CruinnCMS/templates/admin/editor.php
- CruinnCMS/templates/admin/settings/modules.php
- CruinnCMS/templates/admin/widgets/communications.php
- CruinnCMS/templates/admin/widgets/comms-social.php
- CruinnCMS/templates/admin/widgets/stats-overview.php
- CruinnCMS/templates/layout.php
- public_html/css/blog.css
- public_html/css/style.css
- public_html/js/editor.js

## Validation

- PHP lint passed during the slice on key touched files including:
  - `CruinnCMS/modules/blog/module.php`
  - `CruinnCMS/modules/blog/src/Controllers/ArticleController.php`
  - `CruinnCMS/modules/blog/src/Controllers/ArticleEditorController.php`
  - `CruinnCMS/modules/blog/templates/admin/blog/_nav.php`
  - `CruinnCMS/modules/blog/templates/admin/blog/dashboard.php`
  - `CruinnCMS/modules/blog/templates/admin/blog/settings.php`
  - `CruinnCMS/modules/blog/templates/admin/blog/profiles/index.php`
  - `CruinnCMS/modules/blog/templates/admin/blog/profiles/edit.php`
  - `CruinnCMS/modules/blog/templates/public/articles/module-content/post.php`
  - `CruinnCMS/src/BlockTypes/module-content/definition.php`
  - `CruinnCMS/src/Controllers/CruinnController.php`
  - `CruinnCMS/templates/admin/editor.php`
- Diagnostics reported no relevant new errors in the touched PHP, JS, or CSS files when checked during the slice.
- Manual browser verification confirmed:
  - Blog posts visible after the admin cutover
  - Blog settings present in the new Blog control centre
  - profiles visible and working
  - the final nav containment fix behaves correctly in the current template setup

## Deferred next slices

### 1) Blog search widget

- Add a dedicated Blog search widget intended for sidebar use.
- Keep search behavior module-owned rather than overloading the current post-nav/content provider path.
- Expected future concerns:
  - keyword search
  - filtered result summaries
  - compatibility with sidebar placement and existing widget architecture

### 2) Subject integration

- Integrate instance-wide `subjects` more deliberately into Blog filtering and browse surfaces.
- Treat subjects as cross-instance content aggregation keys, not as blog-only taxonomy.
- Future Blog filters should be able to include subjects without collapsing the broader subject system into a blog-specific concept.

### 3) Blog-specific filters beyond subjects

- Add additional content filters such as:
  - categories
  - tags
  - future thematic groupings like `News`, `Events`, or ad hoc editorial focuses
- These should support Blog list rendering without hardcoding instance-specific assumptions into engine-layer code.

### 4) Module-content block filter strategy

- The likely next design direction is to avoid turning the editor block into a dense field list.
- Instead, consolidate Blog-specific rendering/filter decisions into Blog Profiles where practical, leaving the editor block relatively clean.
- Current likely shape:
  - provider
  - optional explicit overrides
  - selected Blog Profile carrying the reusable browse/render/filter defaults

## Notes

- The user explicitly rejected JSON blobs for the new Blog settings/profile work; this slice follows that requirement.
- The final nav containment fix is intentionally small and pragmatic. It stabilizes the current inline-block page template structure without reopening wider layout work.
- Search, subject-aware filtering, and richer browse widgets are intentionally deferred so the just-landed Blog control-centre slice remains coherent and shippable.