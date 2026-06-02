-- Migration 027: Add 'widget-dashboard' to canvas_type ENUM and backfill existing dashboard rows.
--
-- Prior to this migration, dashboard canvases had no canvas_type value to distinguish them —
-- the column ENUM did not include 'widget-dashboard', so any insert attempt fell through to
-- a slug-convention fallback. The slug convention was removed. This migration makes
-- canvas_type the single source of truth, using row IDs (via context_dashboards.page_id)
-- to identify existing dashboard rows for backfill.

-- Migration 027: Add 'widget-dashboard' to canvas_type ENUM and backfill existing rows.
-- After this migration, canvas_type = 'widget-dashboard' is the sole identifier.
-- Application code must use canvas_type only — never slug patterns.

-- Step 1: Extend the ENUM to include 'widget-dashboard'.
ALTER TABLE `pages_index`
    MODIFY COLUMN `canvas_type`
        ENUM('content','zone','template-shell','typography','widget-dashboard')
        NOT NULL DEFAULT 'content';

-- Step 2: Backfill by row ID from context_dashboards (pages already assigned to a context).
UPDATE `pages_index` pi
INNER JOIN (
    SELECT DISTINCT page_id FROM `context_dashboards`
) cd ON cd.page_id = pi.id
SET pi.canvas_type = 'widget-dashboard'
WHERE pi.canvas_type != 'widget-dashboard';

-- Step 3: One-time backfill for dashboard rows created before canvas_type was supported.
-- These were inserted via the legacy fallback path, which slugged '_dashboard_<title>'
-- through generateUniqueSlug(), producing 'dashboard-<title>' slugs. This slug pattern
-- is used HERE ONLY for schema repair — never in application code.
UPDATE `pages_index`
SET `canvas_type` = 'widget-dashboard'
WHERE `slug` LIKE 'dashboard-%'
  AND `canvas_type` = 'content';
