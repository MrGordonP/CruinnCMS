---
name: "CruinnCMS"
description: "Use for work on the CruinnCMS platform engine — architecture, core systems, block editor, routing, conventions, schema, platform dashboard, multi-instance design, deployment. Topics: PHP, block-based CMS architecture, custom router, PDO, template engine, BlockRegistry, platform auth, shared hosting deployment, Nginx, multi-instance design."
tools: [execute, read, edit, search, todo]
---

**CruinnCMS** is a custom PHP CMS platform built by Gordon. *"Upgrading your website? Don't ruin it, Cruinn it."*

The engine is intentionally instance-agnostic — no hardcoded instance assumptions belong in platform-layer code.

**Codebase:** `MrGordonP/CruinnCMS` (public) — this IS the platform repo. Instance-specific work lives in separate instance repos.

**Local path (Windows):** `G:\Programming\Workspaces\CruinnCMS`
**Local path (Linux/Fedora):** `/mnt/MyMedia/Programming/Workspace/CruinnCMS`

**Current version:** `v1.0.0-beta.4`
**HEAD:** `(see git log)` — fix(deploy): remove unused deps, harden .htaccess, add cPanel deployment docs
**Schema:** `schema/platform.sql` (platform tables) + `schema/instance_core.sql` (per-instance, applied at provisioning)

---

## Behavioral Rules

These apply before every action. No exceptions.

1. **Read before touching.** Always read a file in full before editing it. Never assume its contents based on context or prior knowledge.
2. **Minimum footprint.** Only change exactly what was requested. Do not add unrequested UI elements, features, styles, logic, routes, or methods.
3. **Describe intent before ALL code changes.** State specifically what you will add, remove, or modify and why, then wait for Gordon's approval before writing any code. If the scope is unclear, ask.
4. **No scope creep.** A request to fix X is not a request to also improve Y. Do not clean up surrounding code unless explicitly asked.
5. **One change at a time.** Complete and verify the requested change before proposing or making further changes.
6. **Ask on ambiguity.** If a request could be interpreted in more than one way, ask. Do not pick the most expansive reading.
7. **Never introduce new dependencies** (JS libraries, Composer packages, CSS frameworks) without explicit approval.
8. **Instance vs engine boundary.** Changes to core routing, template engine, BlockRegistry, platform auth, database wrapper, or conventions are engine changes — make them here. Instance-specific features belong in the instance repo. Flag explicitly: *"Engine change required: [description]"* before switching context.
9. **No type gatekeeping in the editor.** The editor works with DOM elements. No whitelists restricting which elements are selectable, editable, or decomposed. Every element is individually addressable.
10. **No origin discrimination.** Never treat "foreign" / imported blocks as second-class. Same editor behaviour applies to all `[data-block]` elements equally.
11. **Decompose fully.** When importing HTML into blocks, recurse into every element. `<li>`, `<a>`, `<span>`, `<img>`, `<label>`, `<button>` all become their own block records.

---

## Session Startup

1. `git log --oneline -5` — confirm HEAD
2. `git status` — check for uncommitted work
3. Ask Gordon what the engine change or platform task is
4. Read the relevant source file(s) before proposing any change

---

## Architecture

- **No frameworks.** Custom PHP, custom router (~200 lines), PDO + MySQL 8, no ORM.
- **Namespace:** `Cruinn\` → `src/` (Composer PSR-4)
- **No JS frameworks.** Vanilla JS only. CSS Grid/Flexbox. No build step.
- **No Composer package deps.** `composer install` generates only the autoloader. The fallback SPL autoloader in `public/index.php` covers deploys without Composer.
- **Entry point:** `public/index.php` → `src/App.php` → `src/Router.php` → Controller → Template

### Directory Map

```
public/          ← Web root
  index.php      ← Front controller (defines CRUINN_ROOT = dirname(__DIR__))
  .htaccess      ← Apache rewrite rules + Options -Indexes
  css/           ← Per-page CSS (admin split into multiple files)
  brand/         ← Cruinn CMS production web assets (logo, favicon, wordmark)
  js/            ← editor.js, main.js, admin/ (block-editor/, block-types/)
  storage/       ← Writable; generated files
  uploads/       ← Writable; user uploads
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
                   AdminImportController, MaintenanceController
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
  platform.example.php    ← Legacy stub (deprecated — see CruinnCMS.example.php)
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
  council/                ← Council workspace templates
  components/             ← Reusable components (block renderer)
  public/                 ← Public-facing page templates
  errors/                 ← 404, 403, CSRF error pages
tools/                    ← CLI scripts
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
- **Brand assets:** `public/brand/` — `cruinn-logo.svg`, `cruinn-favicon.svg`, `cruinn-wordmark.svg` etc.
- **CSS:** `public/css/platform.css` — standalone, Cruinn colour palette (`#0c1614`, `#1d9e75`, `#5dcaa5`, `#e8e4da`)
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

**Block types (22):** `text`, `heading`, `image`, `gallery`, `html`, `section`, `columns`, `site-logo`, `site-title`, `nav-menu`, `map`, `event-list`, `php-include`, `anchor`, `document`, `element`, `form`, `inline`, `list`, `list-item`, `table`, `php-code`

**Registry:** Pluggable via `src/BlockTypes/BlockRegistry.php` (PHP) and `public/js/admin/block-types/_registry.js + {slug}.js` (JS). To add a new block type: create `src/BlockTypes/{slug}/definition.php` and `public/js/admin/block-types/{slug}.js` — no changes to core editor code needed.

**Render pipeline:** `CruinnRenderService` → `BlockRegistry::getTag/isDynamic/renderDynamic`

**Editor JS:** `public/js/editor.js` (main canvas editor) + `public/js/admin/block-editor/` (core.js, properties.js, drag.js, undo.js) + `public/js/admin/block-types/{slug}.js`

**EditorRenderService:** Single source of truth for rendering a flat block list into editor canvas HTML + CSS. Used by platform and instance admin. Inerts `script`/`style`/`noscript` as chip elements on canvas.

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

`CRUINN_ROOT = dirname(__DIR__)` in `public/index.php` means the engine root is the parent of `public/`. On cPanel:

- `public/` contents → `public_html/`
- All other dirs (`src/`, `templates/`, `config/`, `schema/`, `instance/`, `vendor/`) → `/home/username/`

**No Composer on the server** — build `vendor/` locally with `composer install --no-dev` and upload. The SPL fallback in `index.php` handles `Cruinn\*` if `vendor/` is absent.

Apache: `public/.htaccess` handles rewrites + directory listing protection (`Options -Indexes`).

---

## Version History

- **v1.0.0-beta.1** (`95d8895`) — Initial public release: full engine extracted from IGAPortal RC. 22 block types, install wizard, multi-instance platform, block editor, DB browser, module registry stub.
- **v1.0.0-beta.2** — Deployment fixes: remove unused Composer deps (phpoffice/phpword, dompdf, smalot/pdfparser), add `Options -Indexes` to `.htaccess`, add `config/CruinnCMS.example.php`, add cPanel/shared-hosting deployment section to SETUP.md.

---

## Session End Checklist

1. Write `docs/sessions/v{VERSION}_CHECKPOINT.md` — exactly what changed and why
2. Update this agent file — version history, HEAD commit
3. Commit with message: `fix|feat|refactor|chore(scope): description [vX.Y.Z-betaN]`
4. Tag: `git tag vX.Y.Z-betaN`
5. Push: `git push origin main --tags`
