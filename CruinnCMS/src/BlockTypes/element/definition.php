<?php

use Cruinn\BlockTypes\BlockRegistry;

BlockRegistry::register([
    'slug'      => 'element',
    'label'     => 'Element',
    'tag'       => 'div',
    'dynamic'   => false,
    'container' => false,
    'isLayout'  => true,
    'group'          => 'layout',
    'group_label'    => 'Layout &amp; Containers',
    'palette_entries'=> [['label' => 'Block']],
]);
