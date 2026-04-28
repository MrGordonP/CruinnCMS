# CruinnCMS — Session Checkpoint
**Date:** 2026-04-24
**Branch:** `editor-overhaul-beta4`
**HEAD at close:** `2896d7f`
**Next planned version:** `v1.0.0-beta.7`

---

## Session Summary

A mixed session covering three areas: mailbox module wiring (continued from previous session), editor block rendering bugs, and a batch of live-site defect fixes discovered during QA on geology.ie.

---

## Completed This Session

### 1. Mailbox Admin — Three-Panel Overview (`0c1bcb0`)

**Files changed:**
- `CruinnCMS/modules/mailbox/src/Controllers/MailboxAdminController.php`
- `CruinnCMS/modules/mailbox/templates/admin/mailbox/index.php`

**What changed:**
- Rewrote `index.php` from a flat `<table>` to a three-panel layout using `admin-panel-layout.css` classes (`panel-layout` / `pl-sidebar` / `pl-main` / `pl-detail`)
- Extended `MailboxAdminController::index()` query to fetch all IMAP/SMTP fields, `user_display_name`, `indexed_count`, and removed the `WHERE imap_host IS NOT NULL` restriction so all officer positions appear regardless of whether credentials have been set
- Left panel: all officer positions as nav items with enabled indicator (✅/○) and message count; click selects mailbox
- Middle panel: IMAP and SMTP connection details (host, port, encryption, username — passwords shown as ••••••••); toolbar with "✏️ Credentials" and "⟳ Sync Now" buttons; sync button hidden if `imap_enabled = 0`
- Right panel: officer identity — position name, linked user display name, email, active/enabled status badges, action buttons
- All panel data embedded as JSON in a `<script>` tag; JS populates middle and right panels on nav item click; first item auto-selected on load

---

### 2. Router — Multi-Segment Slug Support (`deedea3`)

**Files changed:**
- `CruinnCMS/src/Router.php`
- `CruinnCMS/src/App.php`

**Problem:** Public pages with slugs containing `/` (e.g. `about/constitution`) returned 404 locally. The catch-all route `/{slug}` compiled `{slug}` to `[a-zA-Z0-9_-]+` which does not match forward slashes.

**Fix:**
- Added `{param*}` wildcard syntax to `Router::match()` — a trailing `*` on a parameter name compiles to `[a-zA-Z0-9/_-]+` (slashes allowed)
- Changed the public catch-all in `App.php` from `/{slug}` → `/{slug*}`
- Single-segment slugs still work; all other named routes are unaffected (they use `{id}` or plain `{slug}`)

**Note on live site:** The live hosting (cPanel/Apache) was already routing nested slugs correctly via `.htaccess` rewrite rules passing `REQUEST_URI` to `index.php`; this fix brings local dev into parity.

---

### 3. Editor — Zero Height/Width css_props Suppressed (`4ba9f06`)

**Files changed:**
- `public_html/js/editor.js`

**Problem:** Some imported blocks had `height: 0px` stored in their `css_props` (written by the serialiser when a block was measured at zero height during a previous edit session with a collapsed canvas). `restoreCssProps()` applied this as an inline style on canvas load, collapsing the block to invisible.

**Fix:** In `restoreCssProps()`, skip applying `height` or `width` values of `0` / `0px`. These are never valid authored values — any block that needs a fixed zero dimension can use explicit inline styles.

**Data fix required on live** (run in phpMyAdmin):
```sql
UPDATE pages
SET css_props = JSON_REMOVE(css_props, '$.height')
WHERE JSON_UNQUOTE(JSON_EXTRACT(css_props, '$.height')) IN ('0', '0px');

UPDATE pages
SET css_props = JSON_REMOVE(css_props, '$.width')
WHERE JSON_UNQUOTE(JSON_EXTRACT(css_props, '$.width')) IN ('0', '0px');
```

---

### 4. Editor — Restricted-Content-Model Tags Coerced to `div` on Canvas (`89b9453`)

**Files changed:**
- `CruinnCMS/src/Services/EditorRenderService.php`

**Problem:** Imported blocks with `_tag: "p"`, `"h2"`, `"h3"` etc. in their `block_config` were rendered in the editor canvas using those tags. The browser's HTML parser auto-closes `<p>` and heading elements before any block-level child content, leaving the `[data-block]` element empty in the DOM even though `inner_html` has content. This caused all such blocks to appear empty in the editor. The public-facing render (`CruinnRenderService`) was unaffected because its `renderTree` doesn't have this problem (no JS parser involvement).

**Fix:** Added a `$restrictedTags` list to `EditorRenderService::renderTree()`. Tags in this list are silently coerced to `div` for the canvas render. The original tag is preserved in `block_config._tag` and applied correctly by `ImportService::reconstructFragment()` on publish.

**Restricted tags list:** `p`, `h1`–`h6`, `dt`, `figcaption`, `caption`, `pre`

---

### 5. Mailout — Guard `members` Table Queries (`2896d7f`)

**Files changed:**
- `CruinnCMS/modules/mailout/src/Controllers/BroadcastController.php`

**Problem:** `GET /admin/mailout/new` returned 500 on the live site. `newForm()` and `editForm()` both query the `members` table unconditionally to count recipients by status. The `membership` module is not yet active/migrated on the live instance, so the table does not exist — throwing a PDO fatal.

**Fix:** Wrapped both `members` status count loops in `try/catch (\Throwable $e)`. When the table is absent, `$memberStatusCounts` remains an empty array and the form renders without the member-targeting UI. The `users` (portal users) count query is always safe and unchanged.

---

## Files to Deploy to Live (since last deploy)

| File | Commit | Notes |
|------|--------|-------|
| `modules/mailbox/src/Controllers/MailboxAdminController.php` | `0c1bcb0` | Three-panel query |
| `modules/mailbox/templates/admin/mailbox/index.php` | `0c1bcb0` | Full rewrite |
| `src/Router.php` | `deedea3` | Multi-segment slug |
| `src/App.php` | `deedea3` | Catch-all pattern change |
| `public_html/js/editor.js` | `4ba9f06` + `89b9453` | Two editor fixes |
| `src/Services/EditorRenderService.php` | `89b9453` | Restricted tag coercion |
| `modules/mailout/src/Controllers/BroadcastController.php` | `2896d7f` | Guard members query |

**Also run the SQL data fix** for zero-height css_props (see §3 above).

---

## Known Issues / Deferred

### Editor Canvas Aesthetics — Imported Blocks Look Ugly
Imported blocks (e.g. the constitution) display correctly in the editor after the restricted-tag fix, but visually look rough — they render as plain `div` with no styling context. This is cosmetic only; published output is correct. A future improvement would be to apply read-only visual styling to the canvas matching the public-facing template CSS.

### Constitution Page — `height: 0px` Data Still Present on Live
The JS fix suppresses the symptom on load but the bad data remains in the DB. Run the SQL in §3 to fully resolve.

---

## Future Work Scoped This Session

### Documents Module — Centralised Content Objects

**Concept:** A `documents` module providing a shared content object model consumed by Blog, Mailout, Social, and the public documents page.

**Core model:**
```
documents
  id               INT UNSIGNED PK AUTO_INCREMENT
  type             ENUM('article','policy','minutes','mailout-draft','social-post','attachment')
  title            VARCHAR(255)
  status           ENUM('draft','review','published','archived')
  author_id        INT UNSIGNED FK → users.id
  page_id          INT UNSIGNED FK → pages_index.id (nullable — Cruinn editor body)
  file_path        VARCHAR(500) (nullable — uploaded file)
  file_mime        VARCHAR(100)
  tags             JSON
  published_at     DATETIME
  created_at       DATETIME
  updated_at       DATETIME
```

**Key design decision:** Documents *have* a Cruinn editor page (FK), not *are* one. Keeps documents independent of the public routing system; a document can exist without a public URL.

**Consumers:**
- **Blog** — a post references a document of type `article` for its body
- **Mailout** — composer can select a document of type `mailout-draft` as email body instead of a plain textarea
- **Social** — queue a document of type `social-post` to connected accounts
- **Public `/documents` page** — lists published policy/minutes documents for member download

**File type support:** Phase 1 = rich text via Cruinn editor. Phase 2 = file attachments (PDF, DOCX, XLSX). Phase 3 = generated exports (render Cruinn blocks to PDF).

**Status:** Scoped, not started. Needs dedicated session for schema finalisation and module scaffold before any code is written.

---

## Commit Log This Session

```
2896d7f  fix(mailout): guard members table queries against missing module
89b9453  fix(editor): coerce restricted-content-model tags to div on canvas
4ba9f06  fix(editor): skip zero height/width css_props in restoreCssProps
deedea3  fix(router): support multi-segment slugs via {param*} wildcard
0c1bcb0  feat(mailbox): three-panel admin overview, extended index query
```

(Previous session commits `3cad068` and earlier are documented in prior checkpoint.)
