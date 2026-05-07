# CruinnCMS — Outstanding Bug List

Ongoing log of known issues. Add new entries at the top of each section. Mark resolved with ~~strikethrough~~ and the fixing commit.

---

## Editor

### [OPEN] Article editor shows Page Settings panel in right-hand properties
~~When editing a blog post via `/admin/article-editor/{id}/edit`, the right-hand properties panel includes a **Page Settings** accordion (template selector, render mode, etc.) that is meaningless for articles.~~  
**Fixed** in commit following this entry — guard updated to check `empty($page['_is_article'])`.

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

*(none currently logged)*

---

## Mailout Module

### [PINNED] Mailing list audience option — UX not obvious
The "Mailing List Subscribers" radio on `/admin/mailout/new` does show a named-list dropdown, but it is not clear to the user that selecting the radio then choosing from the dropdown is how it works. Consider a helper label or inline description under the dropdown once selected.

### [PINNED] Mailout subject field — allow selection from Subjects list
The Subject field on `/admin/mailout/new` (and edit) is free text only. It would be useful to allow selecting from the existing Subjects list (geology topics / subject registry) to pre-fill or constrain the subject line.
