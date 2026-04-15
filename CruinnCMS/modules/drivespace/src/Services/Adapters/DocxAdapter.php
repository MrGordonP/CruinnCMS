<?php
/**
 * CruinnCMS â€” DOCX Adapter
 *
 * Isolates phpoffice/phpword dependency behind a simple interface.
 * All PhpWord class references are confined to this file.
 */

namespace Cruinn\Module\Drivespace\Services\Adapters;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html as PhpWordHtml;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\ListItemRun;
use PhpOffice\PhpWord\Element\Image;
use PhpOffice\PhpWord\Element\PageBreak;
use PhpOffice\PhpWord\Style\Font;

class DocxAdapter
{
    /**
     * Parse a .docx file into HTML.
     *
     * @return array{html: string, word_count: int, image_count: int}
     */
    public function toHtml(string $filePath): array
    {
        $phpWord = IOFactory::load($filePath, 'Word2007');

        $html = '';
        $wordCount = 0;
        $imageCount = 0;

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $html .= $this->convertElement($element, $wordCount, $imageCount);
            }
        }

        return [
            'html' => $html,
            'word_count' => $wordCount,
            'image_count' => $imageCount,
        ];
    }

    /**
     * Write HTML content to a .docx file.
     *
     * @return string Absolute path of the generated file
     */
    public function fromHtml(string $html, string $destPath): string
    {
        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection();
        PhpWordHtml::addHtml($section, $html, false, false);

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($destPath);

        return $destPath;
    }

    /**
     * Recursively convert PhpWord elements to HTML.
     */
    private function convertElement(mixed $element, int &$wordCount, int &$imageCount): string
    {
        $html = '';

        if ($element instanceof TextRun) {
            $inner = '';
            foreach ($element->getElements() as $child) {
                $inner .= $this->convertElement($child, $wordCount, $imageCount);
            }
            if (trim(strip_tags($inner)) !== '') {
                $html .= '<p>' . $inner . '</p>';
            }
        } elseif ($element instanceof Text) {
            $text = htmlspecialchars($element->getText(), ENT_QUOTES, 'UTF-8');
            $wordCount += str_word_count($text);

            $font = $element->getFontStyle();
            if ($font instanceof Font) {
                if ($font->isBold()) {
                    $text = '<strong>' . $text . '</strong>';
                }
                if ($font->isItalic()) {
                    $text = '<em>' . $text . '</em>';
                }
                if ($font->isUnderline() && $font->getUnderline() !== 'none') {
                    $text = '<u>' . $text . '</u>';
                }
            }
            $html .= $text;
        } elseif ($element instanceof Title) {
            $depth = min($element->getDepth(), 6);
            $text = htmlspecialchars($element->getText(), ENT_QUOTES, 'UTF-8');
            $wordCount += str_word_count($text);
            $html .= "<h{$depth}>{$text}</h{$depth}>";
        } elseif ($element instanceof Table) {
            $html .= '<table>';
            foreach ($element->getRows() as $row) {
                $html .= '<tr>';
                foreach ($row->getCells() as $cell) {
                    $cellHtml = '';
                    foreach ($cell->getElements() as $child) {
                        $cellHtml .= $this->convertElement($child, $wordCount, $imageCount);
                    }
                    $html .= '<td>' . $cellHtml . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</table>';
        } elseif ($element instanceof ListItemRun) {
            $inner = '';
            foreach ($element->getElements() as $child) {
                $inner .= $this->convertElement($child, $wordCount, $imageCount);
            }
            $html .= '<li>' . $inner . '</li>';
        } elseif ($element instanceof Image) {
            $imageCount++;
            $imageData = $this->extractImage($element);
            if ($imageData) {
                $html .= '<img src="' . htmlspecialchars($imageData, ENT_QUOTES, 'UTF-8') . '" alt="Embedded image">';
            }
        } elseif ($element instanceof PageBreak) {
            $html .= '<hr class="page-break">';
        }

        return $html;
    }

    /**
     * Extract an embedded image from a PhpWord element and save to uploads.
     */
    private function extractImage(Image $image): ?string
    {
        $source = $image->getSource();
        if (!$source || !file_exists($source)) {
            return null;
        }

        $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION)) ?: 'png';
        $filename = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $subdir = 'documents/' . date('Y/m');
        $uploadDir = dirname(__DIR__, 2) . '/public/uploads/' . $subdir;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $dest = $uploadDir . '/' . $filename;
        if (copy($source, $dest)) {
            return '/uploads/' . $subdir . '/' . $filename;
        }
        return null;
    }
}
