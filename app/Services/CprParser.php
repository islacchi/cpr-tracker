<?php

namespace App\Services;

use Smalot\PdfParser\Parser;
use thiagoalessio\TesseractOCR\TesseractOCR;

class CprParser
{
    public function parse(string $filePath): array
    {
        try {
            $text = $this->extractTextFromPdf($filePath);
            \Log::info('Text length: ' . strlen(trim($text)) . ' | File: ' . basename($filePath));

            $hasUsefulText = strlen(trim($text)) >= 50
                && preg_match('/Brand\s*Name|Registration\s*Number/i', $text);

            if (!$hasUsefulText) {
                $text = $this->extractTextWithOcr($filePath);
            }

            if (empty(trim($text))) {
                return $this->errorResult();
            }

            return [
                'registration_number' => $this->extractRegistrationNumber($text),
                'generic_name'        => $this->extractGenericName($text),
                'brand_name'          => $this->extractBrandName($text),
                'expiry_date'         => $this->extractExpiryDate($text),
                'status'              => 'Unknown',
                'days_remaining'      => null,
            ];

        } catch (\Exception $e) {
            return $this->errorResult();
        }
    }

    // ── Text Extraction ───────────────────────────────────────

    private function extractTextFromPdf(string $filePath): string
    {
        $parser = new Parser();
        $pdf    = $parser->parseFile($filePath);
        $pages  = $pdf->getPages();

        if (empty($pages)) {
            return '';
        }

        // Read up to 5 pages instead of page 1 only.
        // CPRs vary — expiry date and registration number are not
        // always on the first page.
        $text = '';
        foreach (array_slice($pages, 0, 5) as $page) {
            $text .= $page->getText() . "\n";
        }

        return $text;
    }

    private function extractTextWithOcr(string $filePath): string
    {
        try {
            $tempDir    = sys_get_temp_dir();
            $safePdf    = $tempDir . DIRECTORY_SEPARATOR . 'cpr_input_' . md5($filePath) . '.pdf';
            $outputBase = $tempDir . DIRECTORY_SEPARATOR . 'cpr_out_' . md5($filePath);
            $imagePath  = $outputBase . '-1.png';

            // Copy to a safe filename — no spaces, parens, or apostrophes
            // that would break the shell command on Windows
            copy($filePath, $safePdf);

            $command = sprintf(
                '"%s" -png -f 1 -l 1 -r 300 "%s" "%s"',
                'C:\poppler-26.02.0\Library\bin\pdftoppm.exe',
                $safePdf,
                $outputBase
            );

            exec($command, $output, $returnCode);

            \Log::info('Poppler exit code: ' . $returnCode . ' | File: ' . basename($filePath));

            if (file_exists($safePdf)) {
                unlink($safePdf);
            }

            if (!file_exists($imagePath)) {
                \Log::error('Poppler did not create image: ' . $imagePath . ' | File: ' . basename($filePath));
                return '';
            }

            $text = (new TesseractOCR($imagePath))
                ->lang('eng')
                ->executable('C:\Program Files\Tesseract-OCR\tesseract.exe')
                ->run();

            if (file_exists($imagePath)) {
                unlink($imagePath);
            }

            return $text;

        } catch (\Exception $e) {
            \Log::error('OCR failed: ' . $e->getMessage() . ' | File: ' . basename($filePath));
            return '';
        }
    }

    // ── Field Extractors ──────────────────────────────────────

    private function extractRegistrationNumber(string $text): ?string
    {
        if (preg_match('/Registration\s+Number\s*:\s*([A-Z0-9\-]+)/i', $text, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function extractGenericName(string $text): ?string
    {
        if (preg_match('/Generic\s+Name\s*:\s*(.+)/i', $text, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function extractBrandName(string $text): ?string
    {
        if (preg_match('/Brand\s+Name\s*:\s*(.+)/i', $text, $m)) {
            return trim($m[1]);
        }
        return null;
    }

private function extractExpiryDate(string $text): ?string
{
    $patterns = [
        // Format 1: "valid until 15 November 2026"
        '/valid\s+until\s+(\d{1,2}\s+\w+\s+\d{4})/i',
        // Format 2: "shall be valid 07 September 2031"
        '/shall\s+be\s+valid\s+(\d{1,2}\s+\w+\s+\d{4})/i',
        // Format 3: "valid until 07 September 2031 subject"
        '/be\s+valid\s+(\d{1,2}\s+\w+\s+\d{4})/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $m)) {
            try {
                $date = \Carbon\Carbon::createFromFormat('d F Y', trim($m[1]));
                return $date ? $date->toDateString() : null;
            } catch (\Exception $e) {
                continue;
            }
        }
    }

    return null;
}

    // ── Helpers ───────────────────────────────────────────────

    private function errorResult(): array
    {
        return [
            'registration_number' => null,
            'generic_name'        => null,
            'brand_name'          => null,
            'expiry_date'         => null,
            'status'              => 'Parse Error',
            'days_remaining'      => null,
        ];
    }
}