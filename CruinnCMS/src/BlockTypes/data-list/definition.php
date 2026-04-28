<?php

use Cruinn\BlockTypes\BlockRegistry;
use Cruinn\Database;
use Cruinn\Services\CruinnRenderService;
use Cruinn\Services\QueryBuilderService;

BlockRegistry::register([
    'slug'      => 'data-list',
    'label'     => 'Data List',
    'tag'       => 'div',
    'dynamic'   => true,
    'container' => false,
    'isLayout'  => false,
    'renderer'  => function (array $config, Database $db): string {
        $setSlug      = $config['set_slug']     ?? '';
        $view         = $config['view']         ?? 'continuous';
        $template     = $config['card_html']    ?? '';
        $templateSlug = $config['template_slug'] ?? '';

        if ($setSlug === '') {
            return '<p class="cruinn-data-list-empty">Data list: no content set selected.</p>';
        }

        $set = $db->fetch('SELECT * FROM content_sets WHERE slug = ?', [$setSlug]);
        if (!$set) {
            return '<p class="cruinn-data-list-empty">Data list: content set "' . htmlspecialchars($setSlug, ENT_QUOTES, 'UTF-8') . '" not found.</p>';
        }

        $setType = $set['type'] ?? 'manual';

        // Fetch rows
        if ($setType === 'query') {
            $queryConfig = json_decode($set['query_config'] ?? '{}', true) ?: [];
            if (empty($queryConfig['table'])) {
                return '<p class="cruinn-data-list-empty">Data list: query set has no table configured.</p>';
            }
            try {
                $svc  = new QueryBuilderService($db);
                $rows = $svc->run($queryConfig);
            } catch (\Throwable $e) {
                error_log('data-list QueryBuilderService error: ' . $e->getMessage());
                return '<p class="cruinn-data-list-empty">Data list: query error — ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
            }
        } else {
            $rawRows = $db->fetchAll(
                'SELECT * FROM content_set_rows WHERE set_id = ? ORDER BY sort_order ASC, id ASC',
                [(int) $set['id']]
            );
            $rows = [];
            foreach ($rawRows as $raw) {
                $rows[] = json_decode($raw['data'] ?? '{}', true) ?: [];
            }
        }

        if (empty($rows)) {
            return '<p class="cruinn-data-list-empty">No records found.</p>';
        }

        if ($view === 'single') {
            $rows = [reset($rows)];
        }

        $html = '<div class="cruinn-data-list" data-view="' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8') . '">';

        // ── Content template rendering ────────────────────────────
        if ($templateSlug !== '') {
            $tpl = $db->fetch(
                'SELECT pt.canvas_page_id FROM page_templates pt WHERE pt.slug = ? AND pt.template_type = ? LIMIT 1',
                [$templateSlug, 'content']
            );
            if ($tpl && !empty($tpl['canvas_page_id'])) {
                $svc = new CruinnRenderService();
                foreach ($rows as $row) {
                    $svc->setContext($row);
                    $html .= '<div class="cruinn-data-list-item">' . $svc->buildHtml((int) $tpl['canvas_page_id']) . '</div>';
                }
                $html .= '</div>';
                return $html;
            }
        }

        // ── Token substitution fallback ───────────────────────────
        foreach ($rows as $row) {
            $card = $template;
            foreach ($row as $key => $value) {
                $escaped = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
                $card    = str_replace('{{' . $key . '}}', $escaped, $card);
            }
            $html .= '<div class="cruinn-data-list-item">' . $card . '</div>';
        }
        $html .= '</div>';
        return $html;
    },
]);

