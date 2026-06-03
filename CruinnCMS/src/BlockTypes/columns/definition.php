<?php

use Cruinn\BlockTypes\BlockRegistry;

BlockRegistry::register([
    'slug'      => 'columns',
    'label'     => 'Columns',
    'tag'       => 'div',
    'dynamic'   => false,
    'container' => true,
    'isLayout'  => true,
    'group'          => 'layout',
    'group_label'    => 'Layout &amp; Containers',
    'palette_entries'=> [['label' => 'Columns']],
]);
