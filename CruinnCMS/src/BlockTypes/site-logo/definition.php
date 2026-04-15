<?php

use Cruinn\BlockTypes\BlockRegistry;
use Cruinn\App;
use Cruinn\Database;

BlockRegistry::register([
    'slug'       => 'site-logo',
    'label'      => 'Site Logo',
    'tag'        => 'a',
    'dynamic'    => true,
    'container'  => false,
    'isLayout'   => false,
    'hasContent' => false,
    'renderer'   => function (array $config, Database $db): string {
        $logo = App::config('site.logo', '');
        $name = App::config('site.name', 'Portal');
        if ($logo === '') {
            return '';
        }
        return '<img src="' . htmlspecialchars($logo, ENT_QUOTES, 'UTF-8') . '"'
             . ' alt="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"'
             . ' loading="eager">';
    },
]);
