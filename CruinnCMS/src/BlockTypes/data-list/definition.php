<?php

use Cruinn\BlockTypes\BlockRegistry;
use Cruinn\Database;

BlockRegistry::register([
    'slug'      => 'data-list',
    'label'     => 'Data List',
    'tag'       => 'div',
    'dynamic'   => true,
    'container' => false,
    'isLayout'  => false,
    'renderer'  => function (array $config, Database $db): string {
        $setSlug  = $config['set_slug'] ?? '';
        $view     = $config['view'] ?? 'continuous'; // 'continuous' or 'single'
        $template = $config['card_html'] ?? '';

        if ($setSlug === '') {
            return '<p class="cruinn-data-list-empty">Data list: no content set selected.</p>';
        }

        $set = $db->fetch('SELECT * FROM content_sets WHERE slug = ?', [$setSlug]);
        if (!$set) {
            return '<p class="cruinn-data-list-empty">Data list: content set "' . htmlspecialchars($setSlug, ENT_QUOTES, 'UTF-8') . '" not found.</p>';
        }

        $rows = $db->fetchAll(
            'SELECT * FROM content_set_rows WHERE set_id = ? ORDER BY sort_order ASC, id ASC',
            [(int) $set['id']]
        );

        if (empty($rows)) {
            return '<p class="cruinn-data-list-empty">No records found.</p>';
        }

        if ($view === 'single') {
            $rows = [reset($rows)];
        }

        $html = '<div class="cruinn-data-list" data-view="' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8') . '">';
        foreach ($rows as $row) {
            $data = json_decode($row['data'] ?? '{}', true) ?: [];
            $card = $template;
            foreach ($data as $key => $value) {
                $escaped = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
                $card = str_replace('{{' . $key . '}}', $escaped, $card);
            }
            $html .= '<div class="cruinn-data-list-item">' . $card . '</div>';
        }
        $html .= '</div>';
        return $html;
    },
]);
