<?php

use Cruinn\BlockTypes\BlockRegistry;

// Document-level metadata block types (file mode only).
// These are never rendered to the canvas — they hold <html>, <head>, and
// <body> attribute data and are surfaced via the Document panel above the canvas.
BlockRegistry::register([
    'slug'      => 'doc-html',
    'label'     => 'Document: HTML',
    'tag'       => 'span',
    'dynamic'   => false,
    'container' => false,
    'isLayout'  => false,
]);
BlockRegistry::register([
    'slug'      => 'doc-head',
    'label'     => 'Document: Head',
    'tag'       => 'span',
    'dynamic'   => false,
    'container' => false,
    'isLayout'  => false,
]);
BlockRegistry::register([
    'slug'      => 'doc-body',
    'label'     => 'Document: Body',
    'tag'       => 'span',
    'dynamic'   => false,
    'container' => false,
    'isLayout'  => false,
]);
