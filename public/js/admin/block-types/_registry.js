/**
 * Cruinn Block Type Registry
 *
 * Must be loaded BEFORE any block type definition files.
 * Provides Cruinn.BlockTypes.register() / .get() / .all() used by both
 * the block-editor (core.js / properties.js) and the Cruinn editor.
 */
(function () {
    'use strict';

    window.Cruinn = window.Cruinn || {};

    var _types = {};

    Cruinn.BlockTypes = {
        /**
         * Register a block type definition.
         *
         * @param {string} slug  Block type identifier, e.g. 'text'.
         * @param {object} def   Definition — see keys below:
         *   label        {string}   Human-readable label for the picker UI.
         *   tag          {string}   HTML tag used on the live page ('div', 'nav', …).
         *   isLayout     {boolean}  Show Layout group in block-editor properties panel.
         *   hasContent   {boolean}  Show Content group in block-editor properties panel.
         *   getContent   {function} (blockItem) → plain object — extract inline content for save.
         *   populatePanel {function} (group, blockItem) — fill Content group inputs from inline editor.
         */
        register: function (slug, def) {
            _types[slug] = Object.assign({ slug: slug }, def);
        },

        /**
         * Get the definition for a block type, or null if not registered.
         * @param  {string} slug
         * @returns {object|null}
         */
        get: function (slug) {
            return _types[slug] || null;
        },

        /**
         * Return all registered definitions as an array (stable insertion order).
         * @returns {Array}
         */
        all: function () {
            return Object.keys(_types).map(function (k) { return _types[k]; });
        },
    };
}());
