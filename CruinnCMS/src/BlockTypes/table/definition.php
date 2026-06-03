<?php

use Cruinn\BlockTypes\BlockRegistry;

BlockRegistry::register([
    'slug'      => 'table',
    'label'     => 'Table',
    'tag'       => 'table',
    'dynamic'   => false,
    'container' => true,
    'isLayout'  => true,
    'group'          => 'layout',
    'group_label'    => 'Layout &amp; Containers',
    'palette_entries'=> [['label' => 'Table']],
]);
