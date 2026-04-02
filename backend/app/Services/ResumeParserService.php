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

        $pdf = $this->pdfParser->parseFile($fullPath);
        $text = $pdf->getText();

        return trim($text);
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
