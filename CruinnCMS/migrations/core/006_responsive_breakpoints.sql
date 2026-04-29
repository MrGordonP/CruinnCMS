-- Migration 006: Responsive breakpoints
-- Adds tablet and mobile CSS property columns to pages and pages_draft.
-- Desktop styles stay in css_props (unchanged).
-- Tablet overrides: 600px – 1023px -> css_props_tablet (NULL = inherit desktop)
-- Mobile overrides: ≤ 599px         -> css_props_mobile (NULL = inherit desktop)

ALTER TABLE `pages`
    ADD COLUMN IF NOT EXISTS `css_props_tablet` JSON NULL AFTER `css_props`,
    ADD COLUMN IF NOT EXISTS `css_props_mobile` JSON NULL AFTER `css_props_tablet`;

ALTER TABLE `pages_draft`
    ADD COLUMN IF NOT EXISTS `css_props_tablet` JSON NULL AFTER `css_props`,
    ADD COLUMN IF NOT EXISTS `css_props_mobile` JSON NULL AFTER `css_props_tablet`;
