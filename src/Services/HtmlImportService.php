<?php
/**
 * Cruinn CMS — HTML Import Service
 *
 * Parses an uploaded HTML file (or a zip of HTML files) and either:
 *   a) Stores them as file-mode pages (raw, served as-is), or
 *   b) Converts them to Cruinn blocks (best-effort structural mapping)
 *
 * The conversion strategy is honest: recognisable structure becomes Cruinn
 * primitives; everything else becomes an `html` block. Imported blocks carry
 * source='imported' so the editor can colour-code them differently.
 */

namespace Cruinn\Services;

class HtmlImportService
{
    // ── File-mode import ──────────────────────────────────────────

    /**
     * Import a single HTML file as a file-mode page.
     * Stores the file in storage/pages/ and returns metadata for the caller
     * to create the pages record.
     *
     * Returns: ['slug' => string, 'title' => string, 'file_path' => string (web path)]
     */
    public function importAsFile(string $htmlContent, string $suggestedSlug, string $rootPublicPath): array
    {
        $title = $this->extractTitle($htmlContent) ?: $suggestedSlug;
        $slug  = $this->slugify($suggestedSlug ?: $title);

        $pagesDir = $rootPublicPath . '/storage/pages';
        if (!is_dir($pagesDir)) {
            mkdir($pagesDir, 0755, true);
        }

        // Unique filename
        $filename = $slug . '.html';
        $dest     = $pagesDir . '/' . $filename;
        if (file_exists($dest)) {
            $filename = $slug . '-' . date('Ymd') . '.html';
            $dest     = $pagesDir . '/' . $filename;
        }

        file_put_contents($dest, $htmlContent);

        return [
            'title'     => $title,
            'slug'      => $slug,
            'file_path' => '/storage/pages/' . $filename,
        ];
    }

    /**
     * Parse a zip file containing HTML pages.
     * Returns array of ['filename', 'slug', 'title', 'content'] for each .html file found.
     */
    public function parseZip(string $zipPath): array
    {
        if (!class_exists('\ZipArchive')) {
            throw new \RuntimeException('ZipArchive extension is required for zip imports.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Could not open zip file.');
        }

        $pages = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!str_ends_with(strtolower($name), '.html') && !str_ends_with(strtolower($name), '.htm')) {
                continue;
            }
            // Skip Mac/Windows metadata
            if (str_starts_with(basename($name), '.') || str_starts_with($name, '__MACOSX')) {
                continue;
            }

            $content  = $zip->getFromIndex($i);
            $basename = pathinfo($name, PATHINFO_FILENAME);
            $slug     = $this->slugify($basename);
            $title    = $this->extractTitle($content) ?: $basename;

            $pages[] = [
                'filename' => $name,
                'slug'     => $slug,
                'title'    => $title,
                'content'  => $content,
            ];
        }

        $zip->close();

        // Sort: index/home page first, then alphabetically
        usort($pages, function ($a, $b) {
            $aIsIndex = in_array(strtolower($a['slug']), ['index', 'home', 'default']);
            $bIsIndex = in_array(strtolower($b['slug']), ['index', 'home', 'default']);
            if ($aIsIndex !== $bIsIndex) return $aIsIndex ? -1 : 1;
            return strcmp($a['slug'], $b['slug']);
        });

        return $pages;
    }

    // ── Cruinn conversion ─────────────────────────────────────────

    /**
     * Convert an HTML document to a series of Cruinn block definitions.
     *
     * Strategy:
     *   - <h1>/<h2>/<h3>/<h4> → heading blocks (recognised, source='native')
     *   - <p> with only text content → text block (recognised)
     *   - <img> standalone → image block (recognised)
     *   - Everything else → html block with source='imported'
     *
     * Returns array of block definition arrays ready to be inserted into cruinn_blocks.
     */
    public function convertToCruinnBlocks(string $html): array
    {
        $body = $this->extractBody($html);
        if (empty($body)) {
            return [['block_type' => 'html', 'properties' => ['content' => $html, 'source' => 'imported'], 'content' => $html]];
        }

        $blocks = [];
        $sortOrder = 0;

        // Walk top-level elements in <body>
        $dom = new \DOMDocument('1.0', 'UTF-8');
        // Suppress warnings for HTML5 elements
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $body, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $bodyNode = $dom->getElementsByTagName('body')->item(0);
        $children = $bodyNode ? iterator_to_array($bodyNode->childNodes) : [];

        $htmlBuffer = '';

        $flushHtmlBuffer = function () use (&$blocks, &$htmlBuffer, &$sortOrder) {
            $trimmed = trim($htmlBuffer);
            if ($trimmed !== '') {
                $blocks[] = [
                    'block_type' => 'html',
                    'content'    => $trimmed,
                    'properties' => ['source' => 'imported'],
                    'sort_order' => $sortOrder++,
                ];
                $htmlBuffer = '';
            }
        };

        foreach ($children as $node) {
            if ($node->nodeType === XML_TEXT_NODE) {
                $text = trim($node->textContent);
                if ($text !== '') $htmlBuffer .= $node->textContent;
                continue;
            }
            if ($node->nodeType !== XML_ELEMENT_NODE) continue;

            $tag = strtolower($node->nodeName);

            if (in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
                $flushHtmlBuffer();
                $level = (int) substr($tag, 1);
                $blocks[] = [
                    'block_type' => 'heading',
                    'content'    => $node->textContent,
                    'properties' => ['level' => $level, 'source' => 'native'],
                    'sort_order' => $sortOrder++,
                ];
            } elseif ($tag === 'p' && $this->isPlainText($node)) {
                $flushHtmlBuffer();
                $blocks[] = [
                    'block_type' => 'text',
                    'content'    => trim($dom->saveHTML($node)),
                    'properties' => ['source' => 'native'],
                    'sort_order' => $sortOrder++,
                ];
            } elseif ($tag === 'img') {
                $flushHtmlBuffer();
                $src = $node->getAttribute('src');
                $alt = $node->getAttribute('alt');
                $blocks[] = [
                    'block_type' => 'image',
                    'content'    => '',
                    'properties' => ['src' => $src, 'alt' => $alt, 'source' => 'native'],
                    'sort_order' => $sortOrder++,
                ];
            } else {
                // Anything else accumulates into an html block
                $htmlBuffer .= $dom->saveHTML($node);
            }
        }

        $flushHtmlBuffer();

        return $blocks;
    }

    // ── Hierarchy detection ───────────────────────────────────────

    /**
     * Scan HTML content for navigation links and attempt to build a page hierarchy.
     * Returns array of ['label' => string, 'href' => string] from <nav> elements.
     */
    public function detectNavLinks(string $html): array
    {
        $links = [];

        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $navNodes = $dom->getElementsByTagName('nav');
        if ($navNodes->length === 0) {
            // Fall back to any <ul>/<ol> containing only links
            $navNodes = $dom->getElementsByTagName('ul');
        }

        foreach ($navNodes as $nav) {
            $anchors = $nav->getElementsByTagName('a');
            foreach ($anchors as $a) {
                $href  = trim($a->getAttribute('href'));
                $label = trim($a->textContent);
                if ($href && $label && !str_starts_with($href, '#') && !str_starts_with($href, 'mailto:')) {
                    $links[] = ['label' => $label, 'href' => $href];
                }
            }
        }

        return $links;
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function extractTitle(string $html): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $m)) {
            return trim(strip_tags($m[1]));
        }
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/si', $html, $m)) {
            return trim(strip_tags($m[1]));
        }
        return '';
    }

    private function extractBody(string $html): string
    {
        if (preg_match('/<body[^>]*>(.*?)<\/body>/si', $html, $m)) {
            return trim($m[1]);
        }
        return $html; // Already a fragment
    }

    private function isPlainText(\DOMNode $node): bool
    {
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $tag = strtolower($child->nodeName);
                if (!in_array($tag, ['a', 'strong', 'em', 'b', 'i', 'span', 'code', 'br', 'abbr'])) {
                    return false;
                }
            }
        }
        return true;
    }

    private function slugify(string $input): string
    {
        $s = strtolower(trim($input));
        $s = preg_replace('/[^a-z0-9-]/', '-', $s);
        $s = preg_replace('/-+/', '-', $s);
        return trim($s, '-') ?: 'page';
    }
}
