<?php
/**
 * CruinnCMS â€” Document Service
 *
 * Handles parsing uploaded documents (Word, PDF, text) into HTML,
 * composing new documents, and exporting to various formats.
 *
 * Supported import formats: .docx, .doc, .pdf, .txt, .rtf, .html, .md
 * Supported export formats: PDF, DOCX, HTML
 */

namespace Cruinn\Module\Drivespace\Services;

use Cruinn\App;
use Cruinn\Module\Drivespace\Services\Adapters\DocxAdapter;
use Cruinn\Module\Drivespace\Services\Adapters\PdfAdapter;

class DocumentService
{
    private DocxAdapter $docxAdapter;
    private PdfAdapter $pdfAdapter;

    public function __construct()
    {
        $this->docxAdapter = new DocxAdapter();
        $this->pdfAdapter  = new PdfAdapter();
    }

    /**
     * Parse an uploaded file into HTML content.
     *
     * @return array{html: string, metadata: array}
     */
    public function parseFile(string $filePath, string $extension): array
    {
        return match (strtolower($extension)) {
            'docx'       => $this->parseDocx($filePath),
            'doc'        => $this->parseDoc($filePath),
            'pdf'        => $this->parsePdf($filePath),
            'txt', 'csv' => $this->parseText($filePath),
            'rtf'        => $this->parseRtf($filePath),
            'html', 'htm'=> $this->parseHtml($filePath),
            'md'         => $this->parseMarkdown($filePath),
            default      => ['html' => '', 'metadata' => ['format' => $extension, 'parseable' => false]],
        };
    }

    /**
     * Parse a .docx file via the DocxAdapter.
     */
    private function parseDocx(string $filePath): array
    {
        $result = $this->docxAdapter->toHtml($filePath);

        return [
            'html' => $this->cleanHtml($result['html']),
            'metadata' => [
                'format' => 'docx',
                'parseable' => true,
                'word_count' => $result['word_count'],
                'image_count' => $result['image_count'],
            ],
        ];
    }

    /**
     * Parse a .doc file (legacy binary format).
     * Falls back to basic text extraction via antiword or catdoc if available.
     */
    private function parseDoc(string $filePath): array
    {
        $text = '';

        // Try antiword first (better formatting)
        $antiword = trim(shell_exec('which antiword 2>/dev/null') ?? '');
        if ($antiword) {
            $escaped = escapeshellarg($filePath);
            $text = shell_exec("{$antiword} {$escaped} 2>/dev/null") ?? '';
        }

        // Fallback: catdoc
        if (!$text) {
            $catdoc = trim(shell_exec('which catdoc 2>/dev/null') ?? '');
            if ($catdoc) {
                $escaped = escapeshellarg($filePath);
                $text = shell_exec("{$catdoc} {$escaped} 2>/dev/null") ?? '';
            }
        }

        if (!$text) {
            return [
                'html' => '<p><em>Legacy .doc format â€” content preview not available. Please download the file or convert to .docx.</em></p>',
                'metadata' => ['format' => 'doc', 'parseable' => false],
            ];
        }

        $html = $this->textToHtml($text);
        return [
            'html' => $html,
            'metadata' => [
                'format' => 'doc',
                'parseable' => true,
                'word_count' => str_word_count($text),
            ],
        ];
    }

    /**
     * Parse a PDF file via the PdfAdapter.
     */
    private function parsePdf(string $filePath): array
    {
        $result = $this->pdfAdapter->toText($filePath);

        $html = '';
        foreach ($result['pages'] as $i => $pageText) {
            if (trim($pageText)) {
                if ($i > 0) {
                    $html .= '<hr class="page-break">';
                }
                $html .= $this->textToHtml($pageText);
            }
        }

        return [
            'html' => $html ?: '<p><em>No extractable text content in this PDF.</em></p>',
            'metadata' => [
                'format' => 'pdf',
                'parseable' => (bool) trim($result['text']),
                'word_count' => str_word_count($result['text']),
                'page_count' => $result['page_count'],
            ],
        ];
    }

    /**
     * Parse a plain text file.
     */
    private function parseText(string $filePath): array
    {
        $text = file_get_contents($filePath);
        if ($text === false) {
            return ['html' => '', 'metadata' => ['format' => 'txt', 'parseable' => false]];
        }

        // Ensure UTF-8
        $encoding = mb_detect_encoding($text, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $text = mb_convert_encoding($text, 'UTF-8', $encoding);
        }

        return [
            'html' => $this->textToHtml($text),
            'metadata' => [
                'format' => 'txt',
                'parseable' => true,
                'word_count' => str_word_count($text),
            ],
        ];
    }

    /**
     * Parse an RTF file (basic extraction).
     */
    private function parseRtf(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if (!$content) {
            return ['html' => '', 'metadata' => ['format' => 'rtf', 'parseable' => false]];
        }

        // Strip RTF control words â€” basic extraction
        $text = preg_replace('/\{\\\\[^{}]*\}/', '', $content);
        $text = preg_replace('/\\\\[a-z]+(-?\d+)?[ ]?/', '', $text);
        $text = preg_replace('/[{}]/', '', $text);
        $text = trim($text);

        return [
            'html' => $this->textToHtml($text),
            'metadata' => [
                'format' => 'rtf',
                'parseable' => (bool) $text,
                'word_count' => str_word_count($text),
            ],
        ];
    }

    /**
     * Parse an HTML file â€” extract body content.
     */
    private function parseHtml(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if (!$content) {
            return ['html' => '', 'metadata' => ['format' => 'html', 'parseable' => false]];
        }

        // Extract body content if present
        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $content, $matches)) {
            $content = $matches[1];
        }

        // Strip script and style tags
        $content = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $content);
        $content = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $content);

        $text = strip_tags($content);

        return [
            'html' => $this->cleanHtml($content),
            'metadata' => [
                'format' => 'html',
                'parseable' => true,
                'word_count' => str_word_count($text),
            ],
        ];
    }

    /**
     * Parse a Markdown file to HTML.
     */
    private function parseMarkdown(string $filePath): array
    {
        $text = file_get_contents($filePath);
        if (!$text) {
            return ['html' => '', 'metadata' => ['format' => 'md', 'parseable' => false]];
        }

        $html = $this->markdownToHtml($text);

        return [
            'html' => $html,
            'metadata' => [
                'format' => 'md',
                'parseable' => true,
                'word_count' => str_word_count(strip_tags($html)),
            ],
        ];
    }

    /**
     * Simple Markdown to HTML conversion (no external dependency).
     */
    private function markdownToHtml(string $md): string
    {
        $md = htmlspecialchars($md, ENT_QUOTES, 'UTF-8');

        // Headings
        $md = preg_replace('/^######\s+(.+)$/m', '<h6>$1</h6>', $md);
        $md = preg_replace('/^#####\s+(.+)$/m', '<h5>$1</h5>', $md);
        $md = preg_replace('/^####\s+(.+)$/m', '<h4>$1</h4>', $md);
        $md = preg_replace('/^###\s+(.+)$/m', '<h3>$1</h3>', $md);
        $md = preg_replace('/^##\s+(.+)$/m', '<h2>$1</h2>', $md);
        $md = preg_replace('/^#\s+(.+)$/m', '<h1>$1</h1>', $md);

        // Bold and italic
        $md = preg_replace('/\*\*\*(.+?)\*\*\*/', '<strong><em>$1</em></strong>', $md);
        $md = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $md);
        $md = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $md);

        // HR
        $md = preg_replace('/^---+$/m', '<hr>', $md);

        // List items
        $md = preg_replace('/^[\-\*]\s+(.+)$/m', '<li>$1</li>', $md);

        // Wrap consecutive <li> in <ul>
        $md = preg_replace('/(<li>.*?<\/li>\n?)+/s', '<ul>$0</ul>', $md);

        // Paragraphs for remaining text blocks
        $lines = explode("\n", $md);
        $result = '';
        $buffer = '';

        foreach ($lines as $line) {
            if (preg_match('/^<(h[1-6]|ul|ol|li|hr|table|blockquote)/', $line)) {
                if (trim($buffer)) {
                    $result .= '<p>' . trim($buffer) . '</p>';
                    $buffer = '';
                }
                $result .= $line;
            } elseif (trim($line) === '') {
                if (trim($buffer)) {
                    $result .= '<p>' . trim($buffer) . '</p>';
                    $buffer = '';
                }
            } else {
                $buffer .= ($buffer ? ' ' : '') . $line;
            }
        }
        if (trim($buffer)) {
            $result .= '<p>' . trim($buffer) . '</p>';
        }

        return $result;
    }

    // â”€â”€ Export Methods â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Export HTML content to PDF.
     *
     * @return string File path of generated PDF
     */
    public function exportToPdf(string $html, string $title = 'Document'): string
    {
        $fullHtml = $this->wrapForExport($html, $title);
        $filename = $this->generateExportFilename($title, 'pdf');
        $path = $this->getExportDir() . '/' . $filename;

        $this->pdfAdapter->fromHtml($fullHtml, $path);

        return '/uploads/exports/' . date('Y/m') . '/' . $filename;
    }

    /**
     * Export HTML content to DOCX.
     *
     * @return string File path of generated DOCX
     */
    public function exportToDocx(string $html, string $title = 'Document'): string
    {
        $filename = $this->generateExportFilename($title, 'docx');
        $path = $this->getExportDir() . '/' . $filename;

        $this->docxAdapter->fromHtml($html, $path);

        return '/uploads/exports/' . date('Y/m') . '/' . $filename;
    }

    /**
     * Export HTML content as a standalone HTML file.
     *
     * @return string File path of generated HTML
     */
    public function exportToHtml(string $html, string $title = 'Document'): string
    {
        $fullHtml = $this->wrapForExport($html, $title);

        $filename = $this->generateExportFilename($title, 'html');
        $path = $this->getExportDir() . '/' . $filename;
        file_put_contents($path, $fullHtml);

        return '/uploads/exports/' . date('Y/m') . '/' . $filename;
    }

    // â”€â”€ Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Convert plain text into HTML paragraphs.
     */
    private function textToHtml(string $text): string
    {
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        // Split into paragraphs on double newlines
        $paragraphs = preg_split('/\n{2,}/', trim($text));

        $html = '';
        foreach ($paragraphs as $para) {
            $para = trim($para);
            if ($para !== '') {
                $html .= '<p>' . nl2br($para) . '</p>';
            }
        }

        return $html;
    }

    /**
     * Clean up HTML â€” normalize whitespace, remove empty tags.
     */
    private function cleanHtml(string $html): string
    {
        // Remove empty paragraphs
        $html = preg_replace('/<p>\s*<\/p>/', '', $html);

        // Normalize whitespace between tags
        $html = preg_replace('/>\s+</', '> <', $html);

        return trim($html);
    }

    /**
     * Wrap content HTML in a full document for export.
     */
    private function wrapForExport(string $html, string $title): string
    {
        $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $siteName = htmlspecialchars(App::config('site.name', 'CruinnCMS'), ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <title>{$title} â€” {$siteName}</title>
            <style>
                body { font-family: Georgia, 'Times New Roman', serif; line-height: 1.6; max-width: 700px; margin: 2rem auto; padding: 0 1rem; color: #333; }
                h1, h2, h3, h4, h5, h6 { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; margin-top: 1.5em; }
                h1 { font-size: 1.8rem; border-bottom: 2px solid #2c5f7c; padding-bottom: 0.3em; }
                table { border-collapse: collapse; width: 100%; margin: 1em 0; }
                td, th { border: 1px solid #ccc; padding: 0.5em; text-align: left; }
                img { max-width: 100%; height: auto; }
                hr.page-break { page-break-after: always; border: none; }
                .doc-header { text-align: center; margin-bottom: 2em; color: #666; font-size: 0.9em; }
            </style>
        </head>
        <body>
            <div class="doc-header">
                <strong>{$siteName}</strong><br>
                Generated on {$this->formatDate()}
            </div>
            <h1>{$title}</h1>
            {$html}
        </body>
        </html>
        HTML;
    }

    private function formatDate(): string
    {
        return date('j F Y');
    }

    private function generateExportFilename(string $title, string $ext): string
    {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title));
        $slug = trim($slug, '-');
        return date('Ymd-His') . '-' . substr($slug, 0, 50) . '.' . $ext;
    }

    private function getExportDir(): string
    {
        $dir = dirname(__DIR__) . '/public/uploads/exports/' . date('Y/m');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Get list of parseable file extensions.
     */
    public static function parseableExtensions(): array
    {
        return ['docx', 'doc', 'pdf', 'txt', 'csv', 'rtf', 'html', 'htm', 'md'];
    }

    /**
     * Get list of all allowed file extensions (parseable + binary).
     */
    public static function allAllowedExtensions(): array
    {
        return ['docx', 'doc', 'pdf', 'txt', 'csv', 'rtf', 'html', 'htm', 'md',
                'jpg', 'jpeg', 'png', 'gif', 'webp',
                'xls', 'xlsx', 'ppt', 'pptx', 'zip'];
    }

    /**
     * Complete whitelist of MIME types accepted for upload.
     * Any file whose real MIME type (via finfo) is not in this list is rejected.
     */
    public static function allowedMimeTypes(): array
    {
        return [
            // Documents
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/rtf',
            'application/zip',
            'application/x-zip-compressed',
            // Text
            'text/plain',
            'text/csv',
            'text/html',
            'text/markdown',
            // Images
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ];
    }

    /**
     * Return the expected MIME types for a given file extension.
     * Used to cross-check that the file content matches its extension.
     * Returns an empty array if the extension has no strict mapping
     * (in which case only the global whitelist check applies).
     */
    public static function mimesForExtension(string $ext): array
    {
        return match (strtolower($ext)) {
            'pdf'          => ['application/pdf'],
            'doc'          => ['application/msword'],
            'docx'         => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls'          => ['application/vnd.ms-excel'],
            'xlsx'         => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'ppt'          => ['application/vnd.ms-powerpoint'],
            'pptx'         => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            'rtf'          => ['application/rtf'],
            'zip'          => ['application/zip', 'application/x-zip-compressed'],
            'txt'          => ['text/plain'],
            'csv'          => ['text/plain', 'text/csv'],
            'html', 'htm'  => ['text/html'],
            'md'           => ['text/plain'],
            'jpg', 'jpeg'  => ['image/jpeg'],
            'png'          => ['image/png'],
            'gif'          => ['image/gif'],
            'webp'         => ['image/webp'],
            default        => [],
        };
    }

    /**
     * Check whether a file extension is parseable to HTML.
     */
    public static function isParseable(string $ext): bool
    {
        return in_array(strtolower($ext), self::parseableExtensions(), true);
    }

    /**
     * Format file size for display.
     */
    public static function formatSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }

    /**
     * Get MIME type icon class for display.
     */
    public static function fileIcon(string $ext): string
    {
        return match (strtolower($ext)) {
            'pdf'              => 'ðŸ“„',
            'doc', 'docx'      => 'ðŸ“',
            'xls', 'xlsx'      => 'ðŸ“Š',
            'ppt', 'pptx'      => 'ðŸ“Š',
            'txt', 'csv', 'md' => 'ðŸ“ƒ',
            'html', 'htm'      => 'ðŸŒ',
            'jpg', 'jpeg', 'png', 'gif', 'webp' => 'ðŸ–¼ï¸',
            'zip'              => 'ðŸ“¦',
            default            => 'ðŸ“Ž',
        };
    }
}
