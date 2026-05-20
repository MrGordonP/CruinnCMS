/**
 * Zone Block Type — Editor Registration
 *
 * Defines a zone slot in template layouts. Zones are containers that reference
 * external zone canvas pages (headers, footers, sidebars, etc.).
 *
 * The zone block render logic and canvas assignment are handled in:
 * - PHP: src/BlockTypes/zone/definition.php
 * - JS properties: editor.js (zone canvas dropdown)
 * - CSS: editor.css (zone placeholder styling)
 */

Cruinn.BlockTypes.register('zone', {
    label: 'Zone',
    tag: 'div',
    isLayout: true,
    hasContent: false,
    getContent: function () { return {}; },
});
