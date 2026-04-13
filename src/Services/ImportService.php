<?php
/**
 * CruinnCMS — HTML Import Service
 *
 * Parses raw HTML (file or body fragment) into flat block records for
 * cruinn_draft_blocks. Every HTML element is mapped to a semantic Cruinn
 * block type based on its tag name.
 *
 * Document-level blocks (file mode only):
 *   doc-html  — attributes of the <html> element
 *   doc-head  — full innerHTML of <head>
 *   doc-body  — attributes of <body>
 *
 * Element blocks use sort_order starting at 10. Each element becomes
 * a typed block (heading, text, list, list-item, section, anchor, etc.)
 * with _tag in block_config preserving the original HTML tag.
 *
 * Block nesting:
 *   Elements with child elements become containers (_container:true in
 *   block_config). Their children are recursively decomposed as child
 *   blocks with parent_block_id set.
 *
 * PHP files:
 *   PHP code is preserved losslessly using php-code blocks. The token
 *   stream from token_get_all() is walked via walkTokenStream(), which
 *   maintains an element stack. PHP tokens that arrive while a tag is
 *   being opened are appended to that tag's raw source (handling PHP
 *   inside attributes). PHP tokens between elements become php-code
 *   blocks at the current stack depth.
 *
 *   Elements whose opening tag contained PHP have _raw_open_tag stored
 *   in block_config. reconstructTree() emits that verbatim instead of
 *   re-serialising through attrsToString(), preserving the original PHP.
 *
 * On publish, reconstructDocument() / reconstructFragment() rebuild
 * the source file from the block tree. php-code blocks emit _php
 * verbatim. Elements with _raw_open_tag emit it verbatim. All other
 * elements are re-serialised from _tag and _attrs.
 */

namespace Cruinn\Services;

class ImportService
{
    // ── Sort order constants ───────────────────────────────────────────

    private const SO_DOC_HTML     = 0;
    private const SO_DOC_HEAD     = 1;
    private const SO_DOC_BODY     = 2;
    private const SO_CONTENT_BASE = 10;

    // ── Tag-to-block-type mapping ──────────────────────────────────────

    /**
     * Map HTML tag names to Cruinn block types.
     * Tags not listed here fall through to the generic 'element' type.
     */
    private const TAG_TYPE_MAP = [
        // Headings
        'h1' => 'heading', 'h2' => 'heading', 'h3' => 'heading',
        'h4' => 'heading', 'h5' => 'heading', 'h6' => 'heading',
        // Text
        'p' => 'text',
        // Lists
        'ul' => 'list', 'ol' => 'list',
        'li' => 'list-item',
        'dl' => 'list', 'dt' => 'list-item', 'dd' => 'list-item',
        // Sections / containers
        'div' => 'section', 'section' => 'section', 'article' => 'section',
        'header' => 'section', 'footer' => 'section', 'main' => 'section',
        'aside' => 'section', 'details' => 'section', 'figure' => 'section',
        'blockquote' => 'section', 'pre' => 'section',
        // Navigation
        'nav' => 'nav-menu',
        // Links
        'a' => 'anchor',
        // Images
        'img' => 'image',
        // Forms
        'form' => 'form', 'fieldset' => 'form',
        // Tables
        'table' => 'table',
        // Inline elements (leaf only — if they have children, they become containers)
        'span' => 'inline', 'strong' => 'inline', 'em' => 'inline',
        'b' => 'inline', 'i' => 'inline', 'u' => 'inline',
        'code' => 'inline', 'small' => 'inline', 'sub' => 'inline',
        'sup' => 'inline', 'mark' => 'inline', 'abbr' => 'inline',
        'label' => 'inline', 'button' => 'inline', 'time' => 'inline',
    ];

    /**
     * Return the Cruinn block type for a given HTML tag.
     */
    private function blockTypeForTag(string $tag): string
    {
        return self::TAG_TYPE_MAP[strtolower($tag)] ?? 'element';
    }

    /**
     * Return true when the element has at least one direct child element.
     */
    private function hasAnyElementChildren(\DOMElement $el): bool
    {
        foreach ($el->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                return true;
            }
        }
        return false;
    }

    // ── Public API ────────────────────────────────────────────────────

    /**
     * Determine whether a page needs auto-import and produce its block records.
     *
     * Call this when $flat is empty and render_mode is 'file' or 'html'.
     */
    public function autoImport(array $page, int $pageId, ?string $absFilePath): array
    {
        $renderMode = $page['render_mode'] ?? 'cruinn';
        if (!in_array($renderMode, ['html', 'file'], true)) {
            return [];
        }

        if ($renderMode === 'file') {
            if ($absFilePath === null || !file_exists($absFilePath)) {
                return [];
            }
            // Only import PHP and HTML files into blocks — CSS, JS, etc. are code-only
            $ext = strtolower(pathinfo($absFilePath, PATHINFO_EXTENSION));
            if (!in_array($ext, ['php', 'html', 'htm'], true)) {
                return [];
            }
            $src = file_get_contents($absFilePath);
            if ($src === false) {
                return [];
            }
            // PHP files use the token-stream walker which preserves PHP losslessly.
            // Pure HTML files use the DOMDocument path.
            if (str_contains($src, '<?')) {
                return $this->walkTokenStream($src, $pageId);
            }
            return $this->parseDocument($src, $pageId);
        }

        // html mode
        $srcHtml = $page['body_html'] ?? '';
        if ($srcHtml === '') {
            return [];
        }
        return $this->parseFragment($srcHtml, $pageId);
    }

    /**
     * Persist auto-imported blocks into the DB within a transaction.
     */
    public function persistImportedBlocks(array $blocks, int $pageId, \Cruinn\Database $db): void
    {
        $pdo = $db->pdo();
        $pdo->beginTransaction();
        try {
            $db->execute(
                'INSERT INTO cruinn_page_state
                     (page_id, current_edit_seq, max_edit_seq, last_edited_at)
                 VALUES (?, 1, 1, NOW())
                 ON DUPLICATE KEY UPDATE
                     current_edit_seq = 1, max_edit_seq = 1, last_edited_at = NOW()',
                [$pageId]
            );
            foreach ($blocks as $b) {
                $db->execute(
                    'INSERT INTO cruinn_draft_blocks
                         (page_id, edit_seq, block_id, block_type, inner_html,
                          css_props, block_config, sort_order, parent_block_id,
                          is_active, is_deletion)
                     VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, 1, 0)',
                    [
                        $pageId, $b['block_id'], $b['block_type'],
                        $b['inner_html'], $b['css_props'], $b['block_config'],
                        $b['sort_order'], $b['parent_block_id'],
                    ]
                );
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Parse a full HTML document (render_mode='file') into block records.
     * Returns array of rows ready for INSERT INTO cruinn_draft_blocks.
     */
    public function parseDocument(string $html, int $pageId): array
    {
        $doc    = $this->loadHtml($html);
        $blocks = [];

        // doc-html: <html> element attributes
        $htmlEl    = $doc->documentElement;
        $htmlAttrs = $htmlEl ? $this->elementAttrs($htmlEl) : [];
        $blocks[]  = $this->makeDocBlock(
            'doc-html', 'doc-html-' . $pageId, self::SO_DOC_HTML, null, $htmlAttrs
        );

        // doc-head: full innerHTML of <head>
        $headEl   = $doc->getElementsByTagName('head')->item(0);
        $headHtml = $headEl ? $this->innerHtmlOf($headEl, $doc) : '';
        $blocks[] = $this->makeDocBlock(
            'doc-head', 'doc-head-' . $pageId, self::SO_DOC_HEAD, $headHtml, null
        );

        // doc-body: <body> element attributes
        $bodyEl    = $doc->getElementsByTagName('body')->item(0);
        $bodyAttrs = $bodyEl ? $this->elementAttrs($bodyEl) : [];
        $blocks[]  = $this->makeDocBlock(
            'doc-body', 'doc-body-' . $pageId, self::SO_DOC_BODY, null, $bodyAttrs
        );

        // Content blocks from body (recursive for all elements)
        if ($bodyEl) {
            $counter = 0;
            foreach ($this->walkChildren($bodyEl, $doc, $pageId, null, self::SO_CONTENT_BASE, $counter) as $b) {
                $blocks[] = $b;
            }
        }

        return $blocks;
    }

    /**
     * Parse a body fragment (render_mode='html') into block records.
     * No doc-* records are produced.
     */
    public function parseFragment(string $html, int $pageId): array
    {
        $doc    = $this->loadHtml('<!DOCTYPE html><html><body>' . $html . '</body></html>');
        $bodyEl = $doc->getElementsByTagName('body')->item(0);
        if (!$bodyEl) {
            return [];
        }
        $counter = 0;
        return $this->walkChildren($bodyEl, $doc, $pageId, null, self::SO_CONTENT_BASE, $counter);
    }

    /**
     * Reconstruct a full HTML document from stored block records (file mode publish).
     *
     * Walks the block tree depth-first, emitting each block with its original
     * HTML tag and attributes. Container blocks wrap their children; leaf blocks
     * emit their inner_html content.
     */
    public function reconstructDocument(array $flat): string
    {
        usort($flat, fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);

        $htmlAttrs = [];
        $headHtml  = '';
        $bodyAttrs = [];

        // Index blocks for tree reconstruction
        $byId       = [];
        $childrenOf = [];
        foreach ($flat as $row) {
            switch ($row['block_type']) {
                case 'doc-html':
                    $htmlAttrs = json_decode($row['block_config'] ?? '{}', true) ?: [];
                    continue 2;
                case 'doc-head':
                    $headHtml = $row['inner_html'] ?? '';
                    continue 2;
                case 'doc-body':
                    $bodyAttrs = json_decode($row['block_config'] ?? '{}', true) ?: [];
                    continue 2;
            }
            $byId[$row['block_id']] = $row;
            $pid = $row['parent_block_id'] ?? null;
            $childrenOf[$pid ?? '__root'][] = $row['block_id'];
        }

        $bodyHtml = $this->reconstructTree('__root', $byId, $childrenOf);

        $htmlAttrStr = $this->attrsToString($htmlAttrs);
        $bodyAttrStr = $this->attrsToString($bodyAttrs);

        return '<!DOCTYPE html>' . "\n"
             . '<html' . $htmlAttrStr . '>' . "\n"
             . '<head>' . "\n" . $headHtml . "\n" . '</head>' . "\n"
             . '<body' . $bodyAttrStr . '>' . "\n"
             . $bodyHtml . "\n"
             . '</body>' . "\n"
             . '</html>';
    }

    /**
     * Reconstruct a body fragment from stored block records (html mode publish).
     */
    public function reconstructFragment(array $flat): string
    {
        usort($flat, fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);

        $byId       = [];
        $childrenOf = [];
        foreach ($flat as $row) {
            if (in_array($row['block_type'], ['doc-html', 'doc-head', 'doc-body'], true)) {
                continue;
            }
            $byId[$row['block_id']] = $row;
            $pid = $row['parent_block_id'] ?? null;
            $childrenOf[$pid ?? '__root'][] = $row['block_id'];
        }

        return $this->reconstructTree('__root', $byId, $childrenOf);
    }

    // ── Private helpers ───────────────────────────────────────────────

    /**
     * Parse a PHP source file into block records using a token-stream walker.
     *
     * token_get_all() splits the source into T_INLINE_HTML segments and PHP
     * code segments. We walk those segments maintaining an element stack.
     *
     * Key rules:
     *   - A PHP token that arrives while a tag is being opened (between `<tag`
     *     and the closing `>`) is appended directly to the tag buffer. The
     *     element's opening tag is stored verbatim in _raw_open_tag so that
     *     reconstructTree() can emit it without re-serialising.
     *   - A PHP token that arrives between elements becomes a php-code block
     *     at the current stack depth.
     *
     * doc-html/doc-head/doc-body are extracted from the raw source via regex
     * so that PHP in the document head is preserved exactly.
     */
    private function walkTokenStream(string $src, int $pageId): array
    {
        $blocks  = [];
        $counter = 0;

        // ── Document envelope blocks ───────────────────────────────────

        $htmlAttrs = [];
        if (preg_match('/<html([^>]*)>/i', $src, $m)) {
            $htmlAttrs = $this->parseRawAttrs($m[1]);
        }
        $blocks[] = $this->makeDocBlock(
            'doc-html', 'doc-html-' . $pageId, self::SO_DOC_HTML, null, $htmlAttrs
        );

        $headContent = '';
        if (preg_match('/<head[^>]*>(.*?)<\/head>/is', $src, $m)) {
            $headContent = $m[1];
        }
        $blocks[] = $this->makeDocBlock(
            'doc-head', 'doc-head-' . $pageId, self::SO_DOC_HEAD, $headContent, null
        );

        $bodyAttrs = [];
        if (preg_match('/<body([^>]*)>/i', $src, $m)) {
            $bodyAttrs = $this->parseRawAttrs($m[1]);
        }
        $blocks[] = $this->makeDocBlock(
            'doc-body', 'doc-body-' . $pageId, self::SO_DOC_BODY, null, $bodyAttrs
        );

        // ── Isolate body content ───────────────────────────────────────

        if (!preg_match('/<body[^>]*>(.*)<\/body>/is', $src, $m)) {
            // Fragment file (no <body> wrapper) — treat entire source as body
            $bodySrc = $src;
        } else {
            $bodySrc = $m[1];
        }

        // ── Build flat segment list from token_get_all ─────────────────
        // Prepend an opening+closing PHP tag so token_get_all treats the
        // content as starting outside PHP — inline HTML becomes T_INLINE_HTML.
        // The prefix is assembled at runtime to avoid confusing this file's
        // own parser (PHP open/close tag sequences must not appear literally).
        $phpPrefix = '<' . '?php ?' . '>';
        $tokens = @token_get_all($phpPrefix . $bodySrc);
        $segs   = [];
        $phpBuf = '';
        $skip   = 0; // leading tokens from the prefix

        foreach ($tokens as $tok) {
            if ($skip < 2 && is_array($tok) && $tok[0] !== T_INLINE_HTML) {
                $skip++;
                continue;
            }
            if (is_array($tok)) {
                if ($tok[0] === T_INLINE_HTML) {
                    if ($phpBuf !== '') {
                        $segs[]  = ['type' => 'php', 'src' => $phpBuf];
                        $phpBuf  = '';
                    }
                    $segs[] = ['type' => 'html', 'src' => $tok[1]];
                } else {
                    $phpBuf .= $tok[1];
                }
            } else {
                $phpBuf .= $tok;
            }
        }
        if ($phpBuf !== '') {
            $segs[] = ['type' => 'php', 'src' => $phpBuf];
        }

        // ── Walk segments ──────────────────────────────────────────────

        $stack     = [];   // [ ['tag'=>string, 'block_id'=>string], ... ]
        $sortOrder = self::SO_CONTENT_BASE;
        $inTag     = false;
        $tagBuf    = '';
        $textBuf   = '';

        $voidTags = ['area','base','br','col','embed','hr','img','input',
                     'link','meta','param','source','track','wbr'];

        foreach ($segs as $seg) {
            if ($seg['type'] === 'php') {
                if ($inTag) {
                    // PHP inside an opening tag attribute — append to buffer
                    $tagBuf .= $seg['src'];
                } else {
                    // Flush any pending text first
                    $this->tsFlushText($textBuf, $blocks, $sortOrder, $counter, $pageId, $stack);
                    $textBuf  = '';
                    $parentId = empty($stack) ? null : $stack[count($stack) - 1]['block_id'];
                    $blockId  = 'bl-' . $pageId . '-' . ($counter++);
                    $blocks[] = [
                        'block_id'        => $blockId,
                        'block_type'      => 'php-code',
                        'inner_html'      => null,
                        'css_props'       => null,
                        'block_config'    => json_encode(['_php' => $seg['src']]),
                        'sort_order'      => $sortOrder++,
                        'parent_block_id' => $parentId,
                    ];
                }
                continue;
            }

            // HTML segment — scan character by character for tag boundaries
            $html = $seg['src'];
            $len  = strlen($html);
            $i    = 0;

            while ($i < $len) {
                if ($inTag) {
                    // Looking for the > that closes the current opening tag
                    $gtPos = strpos($html, '>', $i);
                    if ($gtPos === false) {
                        // Tag continues into the next segment
                        $tagBuf .= substr($html, $i);
                        $i = $len;
                    } else {
                        $tagBuf .= substr($html, $i, $gtPos - $i + 1);
                        $i       = $gtPos + 1;
                        $inTag   = false;
                        $this->tsFlushText($textBuf, $blocks, $sortOrder, $counter, $pageId, $stack);
                        $textBuf = '';
                        $this->tsProcessOpenTag($tagBuf, $blocks, $stack, $sortOrder, $counter, $pageId, $voidTags);
                        $tagBuf  = '';
                    }
                    continue;
                }

                // Not in a tag — find next <
                $ltPos = strpos($html, '<', $i);
                if ($ltPos === false) {
                    $textBuf .= substr($html, $i);
                    break;
                }

                if ($ltPos > $i) {
                    $textBuf .= substr($html, $i, $ltPos - $i);
                }
                $i = $ltPos;

                // Closing tag?
                if (substr($html, $i, 2) === '</') {
                    $gtPos = strpos($html, '>', $i);
                    if ($gtPos === false) {
                        $i = $len;
                        break;
                    }
                    $closeRaw = substr($html, $i, $gtPos - $i + 1);
                    $i        = $gtPos + 1;
                    if (preg_match('/<\/([a-zA-Z][a-zA-Z0-9\-]*)/i', $closeRaw, $cnm)) {
                        $this->tsFlushText($textBuf, $blocks, $sortOrder, $counter, $pageId, $stack);
                        $textBuf = '';
                        $this->tsPopStack(strtolower($cnm[1]), $stack, $blocks, $sortOrder);
                    }
                    continue;
                }

                // Comment?
                if (substr($html, $i, 4) === '<!--') {
                    $endPos = strpos($html, '-->', $i + 4);
                    $i      = $endPos !== false ? $endPos + 3 : $len;
                    continue;
                }

                // Opening tag — find its closing >
                $gtPos = strpos($html, '>', $i);
                if ($gtPos === false) {
                    // Tag spans into the next segment
                    $tagBuf = substr($html, $i);
                    $inTag  = true;
                    $i      = $len;
                } else {
                    $rawTag  = substr($html, $i, $gtPos - $i + 1);
                    $i       = $gtPos + 1;
                    $this->tsFlushText($textBuf, $blocks, $sortOrder, $counter, $pageId, $stack);
                    $textBuf = '';
                    $this->tsProcessOpenTag($rawTag, $blocks, $stack, $sortOrder, $counter, $pageId, $voidTags);
                }
            }
        }

        $this->tsFlushText($textBuf, $blocks, $sortOrder, $counter, $pageId, $stack);
        return $blocks;
    }

    /**
     * Flush accumulated text as a text block.
     */
    private function tsFlushText(
        string  $textBuf,
        array   &$blocks,
        int     &$sortOrder,
        int     &$counter,
        int     $pageId,
        array   &$stack
    ): void {
        $t = trim($textBuf);
        if ($t === '') return;
        $parentId = empty($stack) ? null : $stack[count($stack) - 1]['block_id'];
        $blocks[] = [
            'block_id'        => 'bl-' . $pageId . '-' . ($counter++),
            'block_type'      => 'text',
            'inner_html'      => $t,  // raw: text from source file is already valid HTML content
            'css_props'       => null,
            'block_config'    => json_encode(['_tag' => 'span']),
            'sort_order'      => $sortOrder++,
            'parent_block_id' => $parentId,
        ];
    }

    /**
     * Process a complete raw opening tag string, create its block, push to stack if container.
     */
    private function tsProcessOpenTag(
        string  $rawTag,
        array   &$blocks,
        array   &$stack,
        int     &$sortOrder,
        int     &$counter,
        int     $pageId,
        array   $voidTags
    ): void {
        if (!preg_match('/<([a-zA-Z][a-zA-Z0-9\-]*)/i', $rawTag, $tnm)) {
            return; // not a real tag
        }
        $tag         = strtolower($tnm[1]);
        $isVoid      = in_array($tag, $voidTags, true);
        $isSelfClose = str_ends_with(rtrim($rawTag), '/>');
        $blockType   = self::TAG_TYPE_MAP[$tag] ?? 'element';
        $blockId     = 'bl-' . $pageId . '-' . ($counter++);
        $parentId    = empty($stack) ? null : $stack[count($stack) - 1]['block_id'];

        $originalId = null;
        if (preg_match('/\bid=["\']([^"\']*)["\']/', $rawTag, $idm)) {
            $originalId = $idm[1] !== '' ? $idm[1] : null;
        }

        // Always store the raw opening tag verbatim.
        // Parsing attrs then re-serialising via attrsToString() re-encodes HTML
        // entities on every publish→re-import cycle (e.g. &#039; → &amp;#039;).
        // _attrs is still parsed for the properties panel to read but is NOT
        // used during reconstruct — _raw_open_tag is always emitted instead.
        $attrStr = (string) preg_replace('/<[a-zA-Z][a-zA-Z0-9\-]*\s*/i', '', $rawTag);
        $attrStr = rtrim($attrStr, '/>');
        $attrs   = $this->parseRawAttrs($attrStr);
        unset($attrs['id']);
        $cfg = [
            '_tag'          => $tag,
            '_raw_open_tag' => $rawTag,
            '_attrs'        => $attrs,
            '_original_id'  => $originalId,
        ];

        if ($isVoid || $isSelfClose) {
            $blocks[] = [
                'block_id'        => $blockId,
                'block_type'      => $blockType,
                'inner_html'      => null,
                'css_props'       => null,
                'block_config'    => json_encode($cfg),
                'sort_order'      => $sortOrder++,
                'parent_block_id' => $parentId,
            ];
        } else {
            $cfg['_container'] = true;
            $blocks[] = [
                'block_id'        => $blockId,
                'block_type'      => $blockType,
                'inner_html'      => null,
                'css_props'       => null,
                'block_config'    => json_encode($cfg),
                'sort_order'      => $sortOrder++,
                'parent_block_id' => $parentId,
            ];
            $stack[] = ['tag' => $tag, 'block_id' => $blockId];
            $sortOrder = 0; // children sort from 0 within parent
        }
    }

    /**
     * Pop the stack when a closing tag is encountered.
     * Restores sort_order to the next sibling position at the parent level.
     */
    private function tsPopStack(string $closedTag, array &$stack, array &$blocks, int &$sortOrder): void
    {
        for ($s = count($stack) - 1; $s >= 0; $s--) {
            if ($stack[$s]['tag'] === $closedTag) {
                array_splice($stack, $s);
                break;
            }
        }
        $parentId  = empty($stack) ? null : $stack[count($stack) - 1]['block_id'];
        $sortOrder = $this->nextSortOrderAfter($blocks, $parentId);
    }

    /**
     * After closing a tag, calculate the next sort_order for siblings.
     */
    private function nextSortOrderAfter(array &$blocks, ?string $parentId): int
    {
        $max = self::SO_CONTENT_BASE - 1;
        foreach ($blocks as $b) {
            if (($b['parent_block_id'] ?? null) === $parentId) {
                if ($b['sort_order'] > $max) {
                    $max = $b['sort_order'];
                }
            }
        }
        return $max + 1;
    }

    /**
     * Parse a raw HTML attribute string into a key=>value array.
     * Handles double-quoted, single-quoted, unquoted, and standalone attributes.
     * PHP expressions inside values are preserved verbatim.
     */
    private function parseRawAttrs(string $attrStr): array
    {
        $attrs = [];
        // Temporarily replace PHP expressions so they don't confuse the attr regex
        $phpMap  = [];
        $phpIdx  = 0;
        $cleaned = preg_replace_callback('/<\?.*?\?>/s', function ($m) use (&$phpMap, &$phpIdx) {
            $key          = '__PHPATTR_' . ($phpIdx++) . '__';
            $phpMap[$key] = $m[0];
            return $key;
        }, $attrStr) ?? $attrStr;

        preg_match_all(
            '/([a-zA-Z_:][a-zA-Z0-9_:\-.]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|(\S+)))?/',
            $cleaned,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $name  = $match[1];
            $value = $match[2] ?? $match[3] ?? $match[4] ?? null;
            if ($value !== null && $phpMap) {
                $value = strtr($value, $phpMap);
            }
            $attrs[$name] = $value ?? '';
        }

        return $attrs;
    }

    private function loadHtml(string $html): \DOMDocument
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML($html, LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        return $doc;
    }

    /**
     * Walk the direct children of a DOM element, creating typed block records.
     *
     * Every element becomes its own block. Elements with child elements
     * are containers (_container:true); their children are recursively
     * processed. Adjacent text nodes are buffered into a synthetic text block.
     */
    private function walkChildren(
        \DOMElement  $parentEl,
        \DOMDocument $doc,
        int          $pageId,
        ?string      $parentBlockId,
        int          $startSortOrder,
        int          &$counter
    ): array {
        $blocks    = [];
        $sortOrder = $startSortOrder;
        $textBuffer = '';

        $flushText = function () use (&$textBuffer, &$blocks, &$sortOrder, &$counter, $pageId, $parentBlockId): void {
            $trimmed = trim($textBuffer);
            if ($trimmed === '') {
                $textBuffer = '';
                return;
            }
            $blockId  = 'bl-' . $pageId . '-' . ($counter++);
            $blocks[] = [
                'block_id'        => $blockId,
                'block_type'      => 'text',
                'inner_html'      => $textBuffer,
                'css_props'       => null,
                'block_config'    => json_encode(['_tag' => 'span']),
                'sort_order'      => $sortOrder++,
                'parent_block_id' => $parentBlockId,
            ];
            $textBuffer = '';
        };

        foreach ($parentEl->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                if (trim($child->nodeValue) === '') {
                    continue;
                }
                $textBuffer .= htmlspecialchars($child->nodeValue, ENT_QUOTES, 'UTF-8');
                continue;
            }
            if ($child->nodeType === XML_COMMENT_NODE) {
                continue;
            }
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $tag        = strtolower($child->nodeName);
            $blockType  = $this->blockTypeForTag($tag);
            $blockId    = 'bl-' . $pageId . '-' . ($counter++);
            $originalId = $child->getAttribute('id');
            $attrs      = $this->elementAttrs($child);

            $flushText();

            if ($this->hasAnyElementChildren($child)) {
                // ── Container block ────────────────────────────────────
                $blocks[] = [
                    'block_id'        => $blockId,
                    'block_type'      => $blockType,
                    'inner_html'      => null,
                    'css_props'       => null,
                    'block_config'    => json_encode([
                        '_tag'         => $tag,
                        '_attrs'       => $attrs,
                        '_container'   => true,
                        '_original_id' => $originalId !== '' ? $originalId : null,
                    ]),
                    'sort_order'      => $sortOrder++,
                    'parent_block_id' => $parentBlockId,
                ];
                // Recurse: children start at sort_order 0 within this parent
                foreach ($this->walkChildren($child, $doc, $pageId, $blockId, 0, $counter) as $childBlock) {
                    $blocks[] = $childBlock;
                }
            } else {
                // ── Leaf block ─────────────────────────────────────────
                $innerHtml = $this->innerHtmlOf($child, $doc);
                $blocks[] = [
                    'block_id'        => $blockId,
                    'block_type'      => $blockType,
                    'inner_html'      => $innerHtml,
                    'css_props'       => null,
                    'block_config'    => json_encode([
                        '_tag'         => $tag,
                        '_attrs'       => $attrs,
                        '_original_id' => $originalId !== '' ? $originalId : null,
                    ]),
                    'sort_order'      => $sortOrder++,
                    'parent_block_id' => $parentBlockId,
                ];
            }
        }

        $flushText();
        return $blocks;
    }

    private function reconstructTree(string $parentKey, array $byId, array $childrenOf): string
    {
        if (empty($childrenOf[$parentKey])) {
            return '';
        }

        $html = '';
        foreach ($childrenOf[$parentKey] as $blockId) {
            $row = $byId[$blockId];
            $cfg = json_decode($row['block_config'] ?? '{}', true) ?: [];

            // php-code blocks emit their raw PHP source verbatim
            if ($row['block_type'] === 'php-code') {
                $html .= $cfg['_php'] ?? '';
                continue;
            }

            $tag = $cfg['_tag'] ?? 'div';

            // Imported blocks always have _raw_open_tag — emit it verbatim
            // to avoid re-encoding HTML entities in attributes on each roundtrip.
            // User-created blocks (no _raw_open_tag) fall back to _attrs.
            if (!empty($cfg['_raw_open_tag'])) {
                $openTag = $cfg['_raw_open_tag'];
            } else {
                $attrs = $cfg['_attrs'] ?? [];
                if (!empty($cfg['_original_id'])) {
                    $attrs['id'] = $cfg['_original_id'];
                }
                $attrStr = $this->attrsToString($attrs);
                $openTag = "<{$tag}{$attrStr}>";
            }

            // Bake css_props into the style attribute so they survive publish→re-import.
            // Skip tags containing PHP expressions (too risky to regex a mixed HTML/PHP string).
            // Replaces the style attr entirely (not appends) so that cleared properties are
            // actually removed from the file. css_props='{}' means "no inline styles".
            if (!empty($row['css_props']) && !str_contains($openTag, '<?')) {
                $props = json_decode($row['css_props'], true) ?: [];
                $cssText = '';
                foreach ($props as $k => $v) {
                    $k = preg_replace('/[^a-zA-Z0-9\-]/', '', (string) $k);
                    $v = str_replace(['{', '}', ';', '<', '>'], '', (string) $v);
                    if ($k !== '' && $v !== '') {
                        $cssText .= $k . ': ' . $v . '; ';
                    }
                }
                $cssText = trim($cssText);

                $hasDouble = preg_match('/\bstyle\s*=\s*"[^"]*"/i', $openTag);
                $hasSingle = !$hasDouble && preg_match("/\\bstyle\\s*=\\s*'[^']*'/i", $openTag);

                if ($hasDouble || $hasSingle) {
                    if ($cssText !== '') {
                        // Replace existing attr with new value
                        if ($hasDouble) {
                            $openTag = preg_replace_callback(
                                '/\bstyle\s*=\s*"[^"]*"/i',
                                fn() => 'style="' . $cssText . '"',
                                $openTag
                            );
                        } else {
                            $openTag = preg_replace_callback(
                                "/\\bstyle\\s*=\\s*'[^']*'/i",
                                fn() => 'style="' . $cssText . '"',
                                $openTag
                            );
                        }
                    } else {
                        // css_props is empty — user cleared all inline styles; remove attr
                        $openTag = preg_replace('/\s*\bstyle\s*=\s*"[^"]*"/i', '', $openTag);
                        $openTag = preg_replace("/\\s*\\bstyle\\s*=\\s*'[^']*'/i", '', $openTag);
                    }
                } elseif ($cssText !== '') {
                    // No existing style attr — insert new one before the closing >
                    $openTag = preg_replace('/(\s*\/?>)$/', ' style="' . $cssText . '">', rtrim($openTag));
                }
            }

            // Void elements
            if (in_array($tag, ['img', 'br', 'hr', 'input', 'meta', 'link'], true)) {
                $html .= $openTag . "\n";
                continue;
            }

            // Raw-text elements: children are stored as blocks but must be
            // emitted as plain text (not wrapped in child HTML elements).
            if (in_array($tag, ['script', 'style', 'noscript'], true) && !empty($cfg['_container'])) {
                $rawContent = $this->gatherRawText($blockId, $byId, $childrenOf);
                $html .= $openTag . $rawContent . "</{$tag}>\n";
                continue;
            }

            $isContainer = !empty($cfg['_container']);
            if ($isContainer) {
                $childHtml = $this->reconstructTree($blockId, $byId, $childrenOf);
                $html .= $openTag . $childHtml . "</{$tag}>\n";
            } else {
                $inner = $row['inner_html'] ?? '';
                $html .= $openTag . $inner . "</{$tag}>\n";
            }
        }

        return $html;
    }

    /**
     * Collect the raw text content of all descendant blocks without any HTML
     * element wrappers. Used for script/style/noscript reconstruction.
     */
    private function gatherRawText(string $parentKey, array $byId, array $childrenOf): string
    {
        $text = '';
        foreach ($childrenOf[$parentKey] ?? [] as $blockId) {
            $row = $byId[$blockId];
            $cfg = json_decode($row['block_config'] ?? '{}', true) ?: [];
            if ($row['block_type'] === 'php-code') {
                $text .= $cfg['_php'] ?? '';
            } elseif (!empty($childrenOf[$blockId])) {
                $text .= $this->gatherRawText($blockId, $byId, $childrenOf);
            } else {
                $text .= $row['inner_html'] ?? '';
            }
        }
        return $text;
    }

    private function makeDocBlock(
        string  $type,
        string  $blockId,
        int     $sortOrder,
        ?string $innerHtml,
        ?array  $attrs
    ): array {
        return [
            'block_id'        => $blockId,
            'block_type'      => $type,
            'inner_html'      => $innerHtml,
            'css_props'       => null,
            'block_config'    => $attrs !== null ? json_encode($attrs) : null,
            'sort_order'      => $sortOrder,
            'parent_block_id' => null,
        ];
    }

    private function elementAttrs(\DOMElement $el): array
    {
        $attrs = [];
        foreach ($el->attributes as $attr) {
            $attrs[$attr->nodeName] = $attr->nodeValue;
        }
        // Remove id from attrs — stored separately as _original_id
        unset($attrs['id']);
        return $attrs;
    }

    private function innerHtmlOf(\DOMElement $el, \DOMDocument $doc): string
    {
        $html = '';
        foreach ($el->childNodes as $child) {
            $html .= $doc->saveHTML($child);
        }
        return $html;
    }

    private function attrsToString(array $attrs): string
    {
        if (empty($attrs)) {
            return '';
        }
        $parts = [];
        foreach ($attrs as $name => $value) {
            $name = preg_replace('/[^a-zA-Z0-9\-_:]/', '', (string) $name);
            if ($name === '') {
                continue;
            }
            $parts[] = $name . '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"';
        }
        return $parts ? ' ' . implode(' ', $parts) : '';
    }
}
