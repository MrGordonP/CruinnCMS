<?php
/**
 * IGA Portal â€” PDF Adapter
 *
 * Isolates dompdf (HTMLâ†’PDF) and smalot/pdfparser (PDFâ†’text) dependencies
 * behind a simple interface. All vendor class references are confined here.
 */

namespace IGA\Module\FileManager\Services\Adapters;

use Dompdf\Dompdf;
use Smalot\PdfParser\Parser as PdfParser;

class PdfAdapter
{
    /**
     * Extract text and page information from a PDF file.
     *
     * @return array{text: string, pages: string[], page_count: int}
     */
    public function toText(string $filePath): array
    {
        $parser = new PdfParser();
        $pdf = $parser->parseFile($filePath);

        $text = $pdf->getText();
        $pages = [];

        foreach ($pdf->getPages() as $page) {
            $pages[] = $page->getText();
        }

        return [
            'text' => $text,
            'pages' => $pages,
            'page_count' => count($pages),
        ];
    }

    /**
     * Render HTML content to a PDF file.
     *
     * @return string Absolute path of the generated file
     */
    public function fromHtml(string $html, string $destPath): string
    {
        $dompdf = new Dompdf([
            'defaultFont' => 'Helvetica',
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        file_put_contents($destPath, $dompdf->output());

        return $destPath;
    }
}
