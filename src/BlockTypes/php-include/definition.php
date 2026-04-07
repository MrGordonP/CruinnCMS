<?php
/**
 * php-include block type.
 *
 * Renders a PHP template partial at publish time.
 * The template file path (relative to /templates/, excluding admin/ and platform/)
 * is stored in block_config['template'].
 *
 * Any extra config keys (e.g. ['title' => 'My heading']) are passed as variables
 * into the partial alongside the active $db handle.
 *
 * During editing (admin canvas), the block renders as a stub so the user sees
 * it in context.  On the live site, the template executes with all config vars.
 */

use Cruinn\BlockTypes\BlockRegistry;
use Cruinn\Database;

BlockRegistry::register([
    'slug'      => 'php-include',
    'label'     => 'PHP Include',
    'tag'       => 'div',
    'dynamic'   => true,
    'container' => false,
    'isLayout'  => false,
    'renderer'  => function (array $config, Database $db): string {
        $rel = trim($config['template'] ?? '');
        if ($rel === '') {
            return '<p class="php-include-empty" style="color:#9ca3af;font-size:0.8rem;padding:0.5rem">PHP Include — no template selected</p>';
        }

        $base    = realpath(dirname(__DIR__, 3) . '/templates');
        $exclude = ['/admin/', '/platform/'];

        // Prevent path traversal
        if (str_contains($rel, '..') || str_contains($rel, "\0")) {
            return '';
        }
        $fullPath = realpath($base . '/' . $rel);
        if ($fullPath === false || !str_starts_with($fullPath, $base . DIRECTORY_SEPARATOR)) {
            return '';
        }
        foreach ($exclude as $ex) {
            if (str_contains('/' . $rel, $ex)) {
                return '';
            }
        }

        // Gather variables to pass into the template.
        // Remove internal/editor keys; pass everything else as named vars + $db.
        $vars = $config;
        unset($vars['template'], $vars['childStyles']);
        $vars['db'] = $db;

        // ── Edit mode ───────────────────────────────────────────────────────────
        // Execute the template with PHP notice/warning suppression so that missing
        // live-data variables (e.g. $member, $current_user) don't abort rendering.
        // Then annotate every element that carries a class attribute with two
        // data attributes so the editor JS can make them individually selectable:
        //   data-phpi-el="N"         — sequential index (click target)
        //   data-phpi-classes="..."  — original class value (used for style keying)
        if (BlockRegistry::isEditMode()) {
            // Suppress undefined-variable notices without masking real errors.
            set_error_handler(static function (int $errno, string $errstr): bool {
                // Only suppress notices and warnings; let everything else propagate.
                return ($errno & (E_NOTICE | E_WARNING | E_USER_NOTICE | E_USER_WARNING)) !== 0;
            });

            ob_start();
            try {
                extract($vars, EXTR_SKIP);
                include $fullPath;
                $output = ob_get_clean();
            } catch (\Throwable $e) {
                $output = ob_get_clean() ?: '';
                $output .= '<div style="color:#b91c1c;font-size:0.75rem;padding:0.25rem 0.5rem;'
                         . 'background:#fef2f2;border:1px solid #fecaca;margin-top:0.25rem">'
                         . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
                         . '</div>';
            } finally {
                restore_error_handler();
            }

            // Annotate elements with class attributes using DOMDocument so we handle
            // arbitrary HTML reliably without regex fragility.
            if (trim($output) !== '') {
                $dom = new \DOMDocument('1.0', 'UTF-8');
                libxml_use_internal_errors(true);
                // Encoding hint prevents DOMDocument mangling non-ASCII content.
                $dom->loadHTML(
                    '<html><head><meta charset="UTF-8"></head><body>' . $output . '</body></html>',
                    LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
                );
                libxml_clear_errors();

                $xpath = new \DOMXPath($dom);
                $idx   = 0;
                foreach ($xpath->query('//*[@class]') as $el) {
                    /** @var \DOMElement $el */
                    $el->setAttribute('data-phpi-el', (string) $idx++);
                    $el->setAttribute('data-phpi-classes', $el->getAttribute('class'));
                }

                // Extract only the <body> children to avoid the html/head wrapper.
                $body   = $dom->getElementsByTagName('body')->item(0);
                $output = '';
                if ($body) {
                    foreach ($body->childNodes as $child) {
                        $output .= $dom->saveHTML($child);
                    }
                }
            }

            return $output;
        }

        // ── Live site ──────────────────────────────────────────────────────────
        extract($vars, EXTR_SKIP);
        ob_start();
        try {
            include $fullPath;
        } catch (\Throwable $e) {
            ob_end_clean();
            return '<div class="php-include-error" style="color:#b91c1c;font-size:0.8rem;padding:0.5rem;background:#fef2f2;border:1px solid #fecaca">'
                 . htmlspecialchars('PHP Include error: ' . $e->getMessage())
                 . '</div>';
        }
        return ob_get_clean();
    },
]);
