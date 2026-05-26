# 2026-05-26 CHECKPOINT — Conversion Safety, Social Boundary, and Subject Thread Provisioning

Date: 2026-05-26
Branch: main
Status: checkpoint committed (follow-up required)

## Scope

This checkpoint captures three tightly-scoped engine changes completed in one session:

1. Conversion safety hardening for HTML-to-block migration so mode switching only occurs at publish.
2. Social boundary correction so Social remains off-site distribution only.
3. Core-owned forum subject-thread provisioning moved out of Social and wired to blog/event publish flows.

## Why These Changes Were Made

### 1) Conversion safety

The previous flow switched `render_mode` from `html` to `block` during conversion bootstrap. That caused draft-state behavior to become inconsistent and exposed a destructive discard path before publish. The fix defers mode switching until publish succeeds.

### 2) Social boundary

Social had begun handling on-site forum-thread provisioning, which crossed module responsibilities and created UI/flow drift. Social is now restricted to social/email distribution only.

### 3) Forum provisioning ownership

Subject-linked forum thread creation was relocated to an engine/service path and attached to blog/event publish flows. This keeps provisioning tied to content publication, not social distribution actions.

## Implemented Changes

### A. Conversion safety (publish-time mode switch)

- Removed immediate `render_mode = 'block'` flip from HTML conversion endpoint.
- Added publish-time mode switch in HTML publish branch after successful snapshot publish.

Files:

- `CruinnCMS/src/Admin/Controllers/AdminPageController.php`
- `CruinnCMS/src/Controllers/CruinnController.php`

### B. Social boundary refactor (off-site only)

- Removed forum provisioning logic from Social controller.
- Removed forum destination UI from Social distribute page.
- Updated channel validation/messages to social/email only.

Files:

- `CruinnCMS/modules/social/src/Controllers/SocialController.php`
- `CruinnCMS/modules/social/templates/admin/social/distribute.php`

### C. Core subject-thread provisioning

- Added `SubjectThreadProvisionService` in engine service layer.
- Hooked provisioning into blog publish create/update paths.
- Hooked provisioning into event publish create/update paths.
- Added forum module setting `subject_thread_category_id`.
- Extended ACP modules settings options to populate active forum categories for this setting.

Files:

- `CruinnCMS/src/Services/SubjectThreadProvisionService.php` (new)
- `CruinnCMS/modules/blog/src/Controllers/ArticleController.php`
- `CruinnCMS/modules/events/src/Controllers/EventController.php`
- `CruinnCMS/modules/forum/module.php`
- `CruinnCMS/src/Admin/Controllers/AcpSystemController.php`

## Behavioral Outcomes

1. HTML conversion no longer changes page mode pre-publish.
2. Publishing converted HTML now performs the mode switch to block.
3. Social distribution cannot create/reuse forum threads.
4. Forum thread provisioning happens when content is published and subject-linked.
5. Provisioning is idempotent via `subject_id` lookup (reuse if already present).
6. Provisioning category is configurable in forum module settings, with safe fallback to first active category.

## Diff Footprint (session total)

- 9 files touched
- 1 new file
- Conversion + boundary + provisioning slices contained to requested scope

## Validation

- Diagnostics check: no reported errors in touched files.
- Runtime execution was not performed in-container due known OpenSSL mismatch in this environment.

## Deferred / Pinned

- Subject-level active family/category mapping is intentionally deferred.
- Current model uses module-level default category selection for auto-provisioned subject threads.

## Addendum: Dashboard Module Data Cards (Partial Rollout)

Date: 2026-05-26
Commit: `e7d8b60`
State: partially working, requires refinement

### What Was Shipped

1. Extended dashboard `module-widget` catalog/render path to support provider-backed module cards.
2. Added provider wiring and templates for quick-links/status-summary cards in:
	- blog
	- events
	- forms
	- forum
	- mailout
	- mailbox
3. Updated dashboard builder copy to reflect module data-card usage.

### Current Operator Feedback

- Selection/drop-in flow is functional.
- Overall behavior is not finished quality yet and needs additional iteration.

### Follow-up Required

1. Review card UX/content quality module-by-module and tighten output consistency.
2. Validate real-data edge cases for empty/low-permission datasets.
3. Confirm officer-facing dashboard usefulness and adjust card density/priority.
4. Continue expanding via provider pattern only (no hardcoded per-instance behavior).
