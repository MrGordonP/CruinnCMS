<?php

use Cruinn\BlockTypes\BlockRegistry;
use Cruinn\Database;

BlockRegistry::register([
    'slug'      => 'event-list',
    'label'     => 'Events',
    'tag'       => 'div',
    'dynamic'   => true,
    'container' => false,
    'isLayout'  => false,
    'renderer'  => function (array $config, Database $db): string {
        $count  = max(1, (int) ($config['count'] ?? 5));
        $filter = $config['type'] ?? $config['filter'] ?? 'upcoming';

        if ($filter === 'past') {
            $events = $db->fetchAll(
                'SELECT * FROM events WHERE date_start < NOW() AND status = ? ORDER BY date_start DESC LIMIT ?',
                ['published', $count]
            );
        } else {
            $events = $db->fetchAll(
                'SELECT * FROM events WHERE date_start >= NOW() AND status = ? ORDER BY date_start ASC LIMIT ?',
                ['published', $count]
            );
        }

        if (empty($events)) {
            return '<p class="cruinn-no-events">No upcoming events.</p>';
        }

        $html = '<ul class="cruinn-event-list">';
        foreach ($events as $event) {
            $title = htmlspecialchars($event['title'] ?? '', ENT_QUOTES, 'UTF-8');
            $date  = !empty($event['date_start'])
                ? date('j M Y', strtotime($event['date_start']))
                : '';
            $slug  = htmlspecialchars($event['slug'] ?? '', ENT_QUOTES, 'UTF-8');
            $html .= '<li class="cruinn-event-item">';
            $html .= '<span class="cruinn-event-date">' . $date . '</span> ';
            $html .= '<a href="/events/' . $slug . '" class="cruinn-event-title">' . $title . '</a>';
            $html .= '</li>';
        }
        $html .= '</ul>';
        return $html;
    },
]);
