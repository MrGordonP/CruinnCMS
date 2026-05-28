# CruinnCMS — Outstanding Bug List

Ongoing log of known issues. Add new entries at the top of each section. Mark resolved with ~~strikethrough~~ and the fixing commit.

---

## Pages Admin

### ~~[FIXED] No UI to delete pages~~
Backend route (`POST /admin/pages/{id}/delete`) and controller method existed, but delete button was missing from pages admin detail panel. Added delete button with confirmation to `public_html/js/admin/pages.js`.

---

## Editor

### ~~[FIXED 6e67c0ff] Article editor shows Page Settings panel in right-hand properties~~
~~When editing a blog post via `/admin/article-editor/{id}/edit`, the right-hand properties panel includes a **Page Settings** accordion (template selector, render mode, etc.) that is meaningless for articles.~~
Fixed by guarding the section with `empty($page['_is_article'])` in `templates/admin/editor.php`.

### ~~[FIXED] AJAX URLs doubled ID — all editor actions 404 when editing articles~~
`editor.js` constructs AJAX URLs as `API_BASE + '/' + PAGE_ID + '/action'`. `apiBase` was set to `/admin/article-editor/157`, producing `/admin/article-editor/157/157/action`. Fixed: `apiBase` for articles is now `/admin/article-editor` (no ID), matching the pages pattern.

---

## Blog Module

### ~~[FIXED d122cab] 500 error on `/admin/article-editor/{id}/edit`~~
`ArticleEditorController::edit()` was a stale shadow of `CruinnController::edit()`, missing variables added to the shared editor template. Resolved by routing the GET through `CruinnController::editArticle()` and stripping `ArticleEditorController` to AJAX-only.

### ~~[FIXED d122cab] Creating new blog post sends user to 404~~
`adminCreate()` redirected to `/admin/blog/{id}/edit` — a route that does not exist. Correct route is `/admin/article-editor/{id}/edit`.

### ~~[FIXED] All blog article fallback redirects pointed to `/admin/blog`~~
Not-found and delete redirects in `ArticleController` all pointed to `/admin/blog` (no such route). Fixed to `/admin/articles`.

---

## Media

### ~~[FIXED d122cab] No upload progress indicator in media browser modal~~
Uploading a hero image via the media browser modal showed no feedback — the button stayed active during the `fetch`. Fixed in `media-browser.js` — button is disabled and shows "Uploading…" for the duration.

---

## Platform / General

### [PINNED] System files must be instance-owned (not platform-owned)
Current behavior allows editing shared system/source files in ways that effectively apply platform-wide. This conflicts with instance isolation.

Required direction:
1. Treat system/template/source files used by an instance as instance-owned assets.
2. Seed/copy required system files into each instance separately at provision time (and for existing instances via migration/tooling).
3. Update editor/file resolution paths so instance editing targets instance-owned copies, not shared platform originals.
4. Define a safe update strategy for engine upgrades (explicit sync/diff workflow), rather than implicit global overwrite.

### [PINNED] Named Blocks — dedicated management page still needed
Current state: `/admin/blocks/named` is an API endpoint, not a full management UI. We need a proper ACP management page for listing, editing, deleting, and creating named blocks, and nav links should target that page rather than raw JSON routes.

### [PINNED] Reduce storage JSON usage (low QoL impact, high maintenance value)
This is a large engine-level tidy-up rather than an urgent bug fix. Keep JSON where structure is genuinely variable (editor block configs/style payloads), but progressively move relational/business data out of JSON blobs into proper tables/columns.

Outline:
1. Audit and classify JSON fields into:
	- Keep as JSON (variable config/state)
	- Migrate to relational schema (query/report/business data)
2. Prioritize low-risk, high-value candidates first (for example dynamic content set row/query data where filtering/reporting is needed).
3. Add migration path per target area:
	- Add new relational schema
	- Backfill from JSON
	- Dual-write for one release
	- Switch reads to relational path
	- Remove/retire legacy JSON reads
4. Keep transport JSON (AJAX/API responses) unchanged unless specifically required; focus this effort on DB storage only.
5. Track each sub-task here as separate entries so this work does not fall out of use again.

---

## Mailout Module

### ~~[FIXED e7a9d01] Mailing list audience option — UX not obvious~~
The "Mailing List Subscribers" flow now includes inline helper guidance under list selection and active-state emphasis so users can clearly see the required step sequence.

### ~~[FIXED fee957d] Mailout subject field — allow selection from Subjects list~~
Mailout compose/edit now exposes a "Pick from Subjects" selector that can prefill the subject line while keeping free-text editing available.

### [PINNED] Mailout — additional import sources
Currently only blog post import is supported. Desired additional sources: Word documents (.docx), platform Documents module pages, and other misc HTML sources.

### [PINNED] Mailout — email attachments
No attachment support on broadcasts. Users want to be able to attach files (PDFs etc.) to mailouts before sending.
