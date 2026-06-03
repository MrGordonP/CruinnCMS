<?php

use Cruinn\BlockTypes\BlockRegistry;

BlockRegistry::register([
    'slug'      => 'list',
    'label'     => 'List',
    'tag'       => 'ul',
    'dynamic'   => false,
    'container' => true,
    'isLayout'  => true,
    'group'          => 'text',
    'palette_entries'=> [
        ['label' => 'List (UL)', 'tag' => 'ul'],
        ['label' => 'List (OL)', 'tag' => 'ol'],
    ],
]);
