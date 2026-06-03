<?php

use Cruinn\BlockTypes\BlockRegistry;

BlockRegistry::register([
    'slug'      => 'section',
    'label'     => 'Section',
    'tag'       => 'section',
    'dynamic'   => false,
    'container' => true,
    'isLayout'  => true,
    'group'          => 'layout',
    'group_label'    => 'Layout &amp; Containers',
    'palette_entries'=> [['label' => 'Section']],
]);
