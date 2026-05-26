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

**Current version:** `v1.0.0-beta.15` (follow-up work in progress)
**HEAD:** `3d98e5c` — fix(editor,social,forum): conversion safety and boundary-owned subject threads [v1.0.0-beta.15]
**Schema:** `schema/platform.sql` (platform tables) + `schema/instance_core.sql` (per-instance, applied at provisioning)

**Canonical technical reference:** `.github/agents/CRUINNCMS_REFERENCE.md`

Use this agent file for behavioral rules and workflow constraints.
Use the reference document for architecture maps, platform internals, theme/editor details, and extended version notes.

---

## Behavioral Rules

These apply before every action. No exceptions.

1. **Read before touching.** Always read a file in full before editing it. Never assume its contents based on context or prior knowledge.
2. **Read before debugging.** Always read a file in full before attempting to debug it. Never assume its contents based on context or prior knowledge.
3. **Always debug properly** Never try to hypothesise an issue without reading the files or code flow in question.
4. **Always consider the code is at fault** Most of the time issues are with broken or missing code or missing pointers. It is rarely because a file has not been uploaded.
5. **Minimum footprint.** Only change exactly what was requested. Do not add unrequested UI elements, features, styles, logic, routes, or methods.
6. **Describe intent before ALL code changes.** State specifically what you will add, remove, or modify and why, then wait for Gordon's approval before writing any code. If the scope is unclear, ask.
7. **No scope creep.** A request to fix X is not a request to also improve Y. Do not clean up surrounding code unless explicitly asked.
8. **One change at a time.** Complete and verify the requested change before proposing or making further changes.
9. **Ask on ambiguity.** If a request could be interpreted in more than one way, ask. Do not pick the most expansive reading.
10. **Never introduce new dependencies** (JS libraries, Composer packages, CSS frameworks) without explicit approval.
11. **Instance vs engine boundary.** Changes to core routing, template engine, BlockRegistry, platform auth, database wrapper, or conventions are engine changes — make them here. Instance-specific features belong in the instance repo. Flag explicitly: *"Engine change required: [description]"* before switching context.
12. **No type gatekeeping in the editor.** The editor works with DOM elements. No whitelists restricting which elements are selectable, editable, or decomposed. Every element is individually addressable.
13. **No origin discrimination.** Never treat "foreign" / imported blocks as second-class. Same editor behaviour applies to all `[data-block]` elements equally.
14. **Decompose fully.** When importing HTML into blocks, recurse into every element. `<li>`, `<a>`, `<span>`, `<img>`, `<label>`, `<button>` all become their own block records.

---

## Session Startup

1. `git log --oneline -5` — confirm HEAD
2. `git status` — check for uncommitted work
3. Ask Gordon what the engine change or platform task is
4. Read the relevant source file(s) before proposing any change

---

## Technical Reference

Detailed architecture, directory maps, platform/editor internals, theme system, deployment notes, and extended release history are maintained in:

- `.github/agents/CRUINNCMS_REFERENCE.md`

Keep this file focused on session behavior and execution workflow rules.

---

## Session End Checklist

1. Write `docs/sessions/v{VERSION}_CHECKPOINT.md` — exactly what changed and why
2. Update this agent file — version history, HEAD commit
3. Commit with message: `fix|feat|refactor|chore(scope): description [vX.Y.Z-betaN]`
4. Tag: `git tag vX.Y.Z-betaN`
5. Push: `git push origin main --tags`
