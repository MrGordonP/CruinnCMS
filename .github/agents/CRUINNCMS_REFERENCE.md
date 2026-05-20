# CruinnCMS Technical Reference

Reference documentation for the CruinnCMS platform engine. This file is for context lookup — the agent reads it when architectural knowledge is needed.

---

## Core Engine Principles

These are non-negotiable constraints. Any engine code that violates them is a bug, not a feature.

### No Structural Assumptions
The CruinnCMS engine makes zero assumptions about the structure, layout, or content of any instance. No zones, templates, pages, headers, footers, or any structural element may be hardcoded in the engine layer. The engine provides the *mechanism* to define and render these things; what they are is always instance- or theme-defined. Any engine code that references a specific slug, zone name, page structure, or layout arrangement — except as a configurable default with a clear override path — is a violation of this principle.

**Corollary:** When something is difficult to edit through the proper mechanism, the correct fix is to fix the mechanism — not to hardcode the thing as a workaround. Workarounds of this kind poison the engine's core concept and compound over time.

### Canvas Agnosticism
The editor makes no assumptions about what kind of content a canvas holds. A canvas is a named collection of blocks. Whether that canvas is page content, a header zone, a footer zone, a sidebar, a widget, or a template shell is a labelling and routing concern — not an editor concern. The editor must treat all canvases identically.

### Zone / Page / Template Model
- **Block:** A `div` (or element) with a unique ID, CSS properties stored against that ID, a parent/child relationship, and optional inner content.
- **Page:** A named, ordered collection of block IDs. Stored in `pages` / `pages_draft` under a `pages_index` row.
- **Zone:** A named slot within a template. Zones define *where* content lives, not *what* it contains. Zone content is a canvas (block collection) assigned to that slot.
- **Template:** A block structure defining the layout shell. Certain blocks within it are zone markers. A page declares which template it uses and which zone its blocks are injected into (`page_zone`).
- **Header / Footer / Sidebar:** These are zones — named slots in a template. Their content is a canvas like any other. They are not special-cased in the engine.
- **Install state:** A fresh install has no zones, templates, pages, headers, or footers. These are always provided by a theme or created by the user. The engine seeds nothing structural.

---

## Architecture

- **No frameworks.** Custom PHP, custom router (~200 lines), PDO + MySQL 8, no ORM.
- **Namespace:** `Cruinn\` → `src/` (Composer PSR-4)
- **No JS frameworks.** Vanilla JS only. CSS Grid/Flexbox. No build step.
- **No Composer package deps.** `composer install` generates only the autoloader. The fallback SPL autoloader in `public_html/index.php` covers deploys without Composer.
- **Entry point:** `public_html/index.php` → `CruinnCMS/src/App.php` → `Router.php` → Controller → Template

---

## Directory Map

```
public_html/     ← Web root (matches cPanel structure)
  index.php      ← Front controller (defines CRUINN_ROOT)
  .htaccess      ← Apache rewrite rules + Options -Indexes
  css/           ← Per-page CSS (admin split into multiple files)
    themes/      ← Per-theme CSS custom property files (default.css, …)
  brand/         ← Cruinn CMS production web assets (logo, favicon, wordmark)
  js/            ← editor.js, main.js, admin/ (block-editor/, block-types/)
  storage/       ← Writable; generated files
  uploads/       ← Writable; user uploads
CruinnCMS/       ← Engine code (non-web)
  src/
    App.php        ← Bootstrap, error handlers, middleware, uninitialized/no-instance guards
    Router.php     ← URL routing, middleware pipeline
    Database.php   ← PDO wrapper (getInstance connects to active instance DB)
    Template.php   ← Template engine + helpers (?string $layout for nullable setLayout)
    Auth.php       ← Authentication, roles, brute-force lockout
    CSRF.php       ← CSRF token protection (field name: csrf_token; method: CSRF::getToken())
    Mailer.php     ← PHPMailer wrapper (no Composer dep — PHPMailer must be added if mail needed)
    Admin/
      Controllers/ ← AcpSystemController, AcpInstanceController, SiteBuilderController,
                     AdminPageController, BlockController, MediaController,
                     UserAdminController, RoleAdminController, GroupController,
                     AdminImportController, MaintenanceController,
                     ThemeController
    BlockTypes/    ← BlockRegistry.php + {slug}/definition.php × 22
    Controllers/   ← AcpController, AdminController, AuthController, BaseController,
                     CruinnController, MenuController, PageController, SubjectController
    Modules/       ← ModuleRegistry.php (drop-in module system; no modules bundled)
    Platform/      ← PlatformAuth.php + Controllers/PlatformController.php
    Services/      ← CruinnRenderService, DashboardService, EditorRenderService,
                     HtmlImportService, ImportService, NavService, OAuthService,
                     RoleService, SettingsService
  config/
    config.php              ← Defaults
    config.local.example.php← Template for local overrides (wizard writes config.local.php)
    CruinnCMS.example.php   ← Template for wizard-generated platform config
    routes.php              ← Route definitions (core + platform)
    nginx.conf              ← VPS Nginx config
  schema/
    platform.sql            ← Applied once by /cms/install wizard
    instance_core.sql       ← Applied per-instance at provisioning
  instance/                 ← Per-instance config and state (gitignored at runtime)
    .active                 ← Slug of the currently active instance (gitignored)
    {slug}/config.php       ← Per-instance DB credentials (gitignored)
  templates/
    layout.php              ← Public master layout
    admin/                  ← Admin panel templates
    platform/               ← /cms/ platform dashboard templates
    components/             ← Reusable components (block renderer)
    public/                 ← Public-facing page templates
    errors/                 ← 404, 403, CSRF error pages
  modules/                  ← Drop-in module system
dev/                        ← Build scripts, docs
  docs/sessions/            ← Version checkpoints
```

---

## Platform Dashboard (/cms/)

Top-level CMS layer, above all instances. File-based credential, entirely separate from instance DB.

- **Auth:** `src/Platform/PlatformAuth.php` — session key `cms_platform_auth`, reads `config/CruinnCMS.php`
- **Config file:** `config/CruinnCMS.php` — generated by install wizard (gitignored). Contains: `initialized`, `username`, `password_hash`, `db` (platform DB connection). See `config/CruinnCMS.example.php`.
- **Uninitialized guard:** `App::run()` redirects all non-`/cms/*` requests to `/cms/install` if `PlatformAuth::isInitialized()` is false.
- **No-instance guard:** If initialized but no `instance/.active`, all non-`/cms/*` requests redirect to `/cms/dashboard`.
- **Routes:** GET/POST `/cms/install`, GET/POST `/cms/login`, GET `/cms/logout`, GET `/cms/dashboard`, GET/POST `/cms/settings`, GET/POST `/cms/instances/new`, POST `/cms/instances/{name}/activate`, GET `/cms/editor`, GET `/cms/database`, and DB browser sub-routes.
- **Passthrough:** GET `/admin/platform-passthrough?to=/admin/*` — platform session → instance admin login via `Auth::loginById()`, exempt from `adminMiddleware`
- **Brand assets:** `public_html/brand/` — `cruinn-logo.svg`, `cruinn-favicon.svg`, `cruinn-wordmark.svg` etc.
- **CSS:** `public_html/css/platform.css` — standalone, Cruinn colour palette (`#0c1614`, `#1d9e75`, `#5dcaa5`, `#e8e4da`)
- **Template rendering:** `setLayout(null)` — platform templates are standalone HTML, never wrapped in instance layout

---

## Multi-Instance Architecture

- `Database::getInstance()` connects to the **active instance** DB (resolved from `instance/.active` → `instance/{slug}/config.php`)
- Platform DB (platform_settings, instances table) is a separate connection, never the instance DB
- `instance/.active` contains the slug of the live instance; only one active at a time
- `App::instanceDir()` returns the active instance directory path, or `null` if none set
- Instance-specific data must stay fully isolated between instances
- Do not hardcode instance-specific assumptions into platform-layer code

---

## Block-Based Page Editor

Pages are composed of ordered blocks. Each block has a type, properties, and optional content.

**Block types (25):** `text`, `heading`, `image`, `gallery`, `html`, `section`, `columns`, `site-logo`, `site-title`, `nav-menu`, `map`, `event-list`, `php-include`, `anchor`, `document`, `element`, `form`, `inline`, `list`, `list-item`, `table`, `php-code`, `data-list`, `module-widget`, `module-content`

**Registry:** Pluggable via `CruinnCMS/src/BlockTypes/BlockRegistry.php` (PHP) and `public_html/js/admin/block-types/_registry.js + {slug}.js` (JS). To add a new block type: create `CruinnCMS/src/BlockTypes/{slug}/definition.php` and `public_html/js/admin/block-types/{slug}.js` — no changes to core editor code needed.

**Render pipeline:** `CruinnRenderService` → `BlockRegistry::getTag/isDynamic/renderDynamic`

**Editor JS:** `public_html/js/editor.js` (main canvas editor — single IIFE, handles init, selection, DnD, properties, palette, media, serialise, undo/redo, publish, code view) + `public_html/js/admin/block-types/{slug}.js`

**Code view:** `enterCodeView()` / `exitCodeView()` in editor.js. Textarea `#editor-code-area` appended inside `#editor-canvas`. For file-mode pages (`startInCodeView=1`), the editor opens directly in code view with raw file content. CSS files, JS files, and other non-PHP/HTML files use this path.

**EditorRenderService:** Single source of truth for rendering a flat block list into editor canvas HTML + CSS. Used by platform and instance admin. Inerts `script`/`style`/`noscript` as chip elements on canvas.

---

## Theme System

Instance-level site theming via flat CSS custom property files.

- **Theme files:** `public_html/css/themes/{name}.css` — each contains only a `:root {}` block of CSS custom properties. Loaded after `style.css` in `layout.php` so they override the base palette.
- **Active theme:** `settings` table key `site.active_theme` (default: `'default'`). Seeded in `schema/instance_core.sql`.
- **Theme Editor entry point:** A page with `slug = '_typography'` triggers `isThemePage=true` in `CruinnController::edit()`. The block editor canvas becomes a live preview; the right panel becomes Theme controls.
- **ThemeController** (`CruinnCMS/src/Admin/Controllers/ThemeController.php`):
  - `GET /admin/theme` → redirects to `_typography` page in block editor
  - `POST /admin/theme` → writes edited CSS var values back into `{name}.css` via `applyVariables()`
  - Static helpers: `activeTheme()`, `themeFilePath(string $theme)`, `parseVariables(string $css)`, `applyVariables(string $css, array $values)`
- **Live preview:** JS updates `<style id="theme-preview-vars">` on every input event. Preview shows colour swatches, typography, buttons, card, spacing.
- **Section headings:** `parseVariables()` uses `/* Comment */` lines above variable groups as section labels for accordion grouping.

---

## Key Conventions

- **Controllers** handle HTTP only — validate input, call Services, render template or redirect. No DB queries in controllers.
- **Services** hold business logic. Access DB via the `Database` PDO wrapper, not raw PDO.
- **Templates** are plain PHP files. Use `$this->escape()` for all output. No logic beyond loops and conditionals.
- **Schema** files are in `schema/`. Applied by the install wizard or provisioning — never edit applied schema, add new migration files.
- **Config** uses `config.php` as defaults, `config.local.php` for local/VPS overrides, `CruinnCMS.php` for platform credentials.
- **CSRF** tokens: field name is `csrf_token`. Use `CSRF::getToken()`.
- **Auth** roles: `admin`, `council`, `member`, `public`. Role checks via `Auth::requireRole()` in controllers.
- **Template layout:** `Template::$layout` is `?string` — call `setLayout(null)` for standalone pages (platform, AJAX).

---

## Deployment Model (cPanel / Shared Hosting)

Local dev structure mirrors cPanel deployment:

- `public_html/` — web root (same name locally and on cPanel)
- `CruinnCMS/` — engine code (`src/`, `templates/`, `config/`, `schema/`, `instance/`) → `/home/username/CruinnCMS/` on cPanel
- `CRUINN_ROOT` in `public_html/index.php` points to the engine directory
- **CRUINN_PUBLIC** = `__DIR__` in `index.php` = the web root

**Local dev server:** `php -S localhost:8000 -t public_html/`

**No Composer on the server** — build `vendor/` locally with `composer install --no-dev` and upload. The SPL fallback in `index.php` handles `Cruinn\*` if `vendor/` is absent.

Apache: `public_html/.htaccess` handles rewrites + directory listing protection (`Options -Indexes`).

---

## Version History

- **v1.0.0-beta.1** (`95d8895`) — Initial public release: full engine extracted from IGAPortal RC. 22 block types, install wizard, multi-instance platform, block editor, DB browser, module registry stub.
- **v1.0.0-beta.2** — Deployment fixes: remove unused Composer deps, add `Options -Indexes` to `.htaccess`, add `config/CruinnCMS.example.php`, add cPanel/shared-hosting deployment section to SETUP.md.
- **v1.0.0-beta.3** (`bc70dd2`) — Release tooling: `dev/build-release.sh`, hostname-based instance routing, per-instance online toggle, `CRUINN_ROOT` depth fix for cPanel.
- **v1.0.0-beta.4** — Editor overhaul: killed Editor 2 completely (deleted 11 files, stubbed `content_blocks` refs), removed council templates, platform editor CSS file editing via `?file=` handler with cPanel path resolution, code view toggle fix, code view CSS layout improvements (`:has(#editor-code-area)` rules), block tree + properties panel scroll constraints (in progress).
- **v1.0.0-beta.5** — Editor UX: Properties panel accordions start collapsed (except Identity), code view shows clean publishable HTML (block→tag serialization via `blocksToHtml()`), CSS class persistence through `css_props._class`, Collapsed checkbox in Identity panel.
- **v1.0.0-beta.6** — User profile (`/profile` GET/POST), cross-domain passthrough tokens (HMAC-signed, 60s validity), editor visible outlines + layout container min-sizes + resize handles, module migration renumbering to `001_*_core.sql`, ImportService fragment file support, MySQL 8 information_schema case fix.
- **v1.0.0-beta.7** (`cf8fda1`) — Theme system: `public_html/css/themes/{name}.css`, `ThemeController`, Theme Editor integrated into block editor (canvas=live preview, right panel=controls), `site.active_theme` settings seed. Module path fixes (documents, drivespace). `setHomePage` UPSERT fix. Platform migrations re-run feature.
