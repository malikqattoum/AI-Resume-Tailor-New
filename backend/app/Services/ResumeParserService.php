<?php

namespace App\Services;

use Smalot\PdfParser\Parser;
use Illuminate\Support\Facades\Storage;

class ResumeParserService
{
    protected Parser $pdfParser;

    public function __construct()
    {
        $this->pdfParser = new Parser();
    }

    /**
     * Extract text content from an uploaded PDF file.
     *
     * @param string $filePath - The path to the PDF file in storage
     * @return string - Extracted text content
     */
    public function extractText(string $filePath): string
    {
        $fullPath = Storage::disk('local')->path($filePath);

        if (!file_exists($fullPath)) {
            throw new \Exception("PDF file not found at: {$fullPath}");
        }

        try {
            $pdf = $this->pdfParser->parseFile($fullPath);
            $text = $pdf->getText();

            if (empty(trim($text))) {
                throw new \Exception('No text could be extracted from the PDF. Please ensure it is not a scanned image or password-protected.');
            }

            return trim($text);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('PDF parsing failed', [
                'path' => $fullPath,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to read resume PDF. Please ensure it is not password-protected and is a valid PDF.');
        }
    }

    /**
     * Extract text from a PDF by its storage URL.
     *
     * @param string $storageUrl - The storage URL or path
     * @return string
     */
    public function extractFromStorage(string $storageUrl): string
    {
        return $this->extractText($storageUrl);
    }
}
