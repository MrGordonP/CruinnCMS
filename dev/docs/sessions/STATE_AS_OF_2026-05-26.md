# STATE AS OF 2026-05-26 — CruinnCMS Engine Review Snapshot

Date: 2026-05-26
Repository: MrGordonP/CruinnCMS
Branch at review start: main
Context: comprehensive commit-based catch-up and architecture/boundary analysis performed before implementation work.

## Executive Summary

The platform remains a custom, frameworkless PHP engine with clear core abstractions (router, template engine, PDO wrapper, block registry, module registry), but the reviewed working path revealed boundary drift between Social and Forum responsibilities and a high-risk conversion/publish sequence in editor flows.

Two high-priority risks identified at review time were:

1. HTML-to-block conversion changed page mode too early, exposing draft/discard data-loss behavior.
2. Social distribution had absorbed forum-thread provisioning responsibilities, blurring module ownership.

## Architecture Map (as reviewed)

### Core engine

- Entry: `public_html/index.php` → `src/App.php` → `src/Router.php`.
- Data: `src/Database.php` PDO wrapper.
- Rendering: `src/Template.php`, `src/Services/CruinnRenderService.php`, `src/Services/EditorRenderService.php`.
- Editing orchestration: `src/Controllers/CruinnController.php` + `public_html/js/editor.js`.
- Pages/content model: `pages_index`, `pages`, `pages_draft` snapshot/sequence model.

### Platform layer (/cms)

- Platform auth/session and install lifecycle are isolated from instance auth.
- Multi-instance active resolution remains anchored to `instance/.active` and instance DB config.

### Modules and integrations

- Blog and Events support `subject_id` linkage.
- Forum provider abstraction supports subject-linked thread lookup/create with uniqueness protections.
- Module content provider flows connect Blog/Events/Forum rendering via module-content block settings.
- Social module provides account sync, inbox, and distribution surfaces.

## Major Review Findings

### 1) Conversion/publish risk path

Observed flow during review:

- Convert endpoint imported blocks and switched `render_mode` immediately.
- Editor draft detection for HTML/file relied on `edit_seq` semantics and mode branch behavior.
- Discard draft could clear state under changed mode conditions before successful publish.

Impact:

- Possible user-facing "glitch" behavior and recoverability risk after conversion/discard sequences.

### 2) Social/Forum boundary drift

Observed flow during review:

- Social distribute path included forum-thread create/reuse logic.
- UI and backend constraints diverged (forum-only path possible server-side while message UI expectations differed).
- Social template included malformed/placeholder forum label text, indicating unstable coupling.

Impact:

- Responsibility ambiguity, harder maintenance, and behavior drift across modules.

### 3) Core ownership gap for cross-content discussion provisioning

Observed flow during review:

- Forum had the provider primitives for subject-thread behavior.
- No dedicated core orchestration layer had ownership for "publish content -> ensure discussion thread".

Impact:

- Feature logic gravitated into unrelated surfaces (Social) rather than canonical publish flows.

## Integration Surface Inventory (review snapshot)

### Blog

- Admin CRUD and publish metadata in blog controller.
- Subject linkage present in forms and persistence.
- List/post public routing and module-content provider integrated.

### Events

- Admin CRUD and event registration flows.
- Subject linkage present in forms and persistence.
- Public path resolution and event profile settings integrated.

### Forum

- Native provider with category/thread/post operations.
- Subject-thread methods available:
  - `getThreadBySubjectId(...)`
  - `createThread(..., ?subjectId)` with existing-thread guard.

### Social

- Dashboard/inbox/accounts/distribute surfaces.
- Distribution history persisted to `content_distributions`.
- Review-time issue: forum provisioning logic had been embedded in distribute flow.

## Risk Register (at start-of-session)

1. Data integrity risk in conversion/discard/publish transitions.
2. Module boundary regression (Social doing Forum work).
3. UI/backend rule mismatch in distribution pathways.
4. Runtime validation limitations in this environment (OpenSSL mismatch) requiring static-first validation.

## Recommended Direction (as assessed)

1. Keep Social strictly off-site distribution (social/email).
2. Keep on-site discussion provisioning in core/forum ownership paths.
3. Tie subject-thread provisioning to publish events in content owners (blog/events), not manual distribution side paths.
4. Preserve idempotent subject-thread semantics through provider-layer checks.

## Implementation Status Relative To This Review

Following this review, the session implemented:

1. Conversion safety hardening (deferred mode switch until publish).
2. Social boundary cleanup (forum provisioning removed).
3. Core-owned subject-thread provisioning attached to blog/event publish flows.

Deferred by request:

- Subject-level "active family/category per subject" mapping UI + persistence.
