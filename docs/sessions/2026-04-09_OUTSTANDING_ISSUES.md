# Outstanding Issues - 9 April 2026

## Runtime / Live Errors

- Organisation workspace layout can throw on asset versioning:
  - `filemtime(): stat failed for .../modules/organisation/templates/organisation/../../public/css/admin-base.css`
  - Root cause: path is resolved relative to module template directory, not engine root.
  - Affected file: `modules/organisation/templates/organisation/layout.php`

- Organisation workspace previously failed due namespace pollution (`IGA` in Cruinn runtime):
  - Fixed in local working tree for `modules/organisation/templates/organisation/layout.php`
  - Fixed in local working tree for `modules/organisation/templates/organisation/documents/show.php`
  - Additional scan/fix still needed across non-organisation modules.

## Architecture / Scope Risks

- Organisation module currently includes its own discussion thread system (`discussions`, `discussion_posts`).
- This duplicates forum capability and should be consolidated with forum ownership of threaded discussion.
- Proposed direction: connect organisation email, posts, and forum topics through subjects as canonical link entities.

## Codebase Hygiene

- Mixed namespace lineage exists in module code (`Cruinn\...` and `IGA\...` references both present in modules tree).
- A full module namespace cleanup pass is still outstanding.

## Deployment Notes

- Current working tree includes broad in-progress refactor changes in core and modules.
- `modules/` is currently untracked as a tree in this workspace and needs intentional deployment selection.
