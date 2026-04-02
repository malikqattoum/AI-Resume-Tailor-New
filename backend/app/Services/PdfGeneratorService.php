<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PdfGeneratorService
{
    private const DIRECTORY = 'tailored';
    private const PAPER_SIZE = 'A4';
    private const PAPER_ORIENTATION = 'portrait';

    // Common styling constants
    private const FONT_FAMILY = "'Helvetica Neue', Arial, sans-serif";
    private const FONT_SIZE_BODY = '11pt';
    private const FONT_SIZE_LARGE = '24pt';
    private const FONT_SIZE_MEDIUM = '12pt';
    private const FONT_SIZE_SMALL = '10pt';
    private const COLOR_PRIMARY = '#2563eb';
    private const COLOR_TEXT = '#333';
    private const COLOR_SECONDARY = '#666';
    private const COLOR_LIGHT = '#555';

    /**
     * Generate a professional resume PDF from structured data.
     */
    public function generateResumePdf(array $resumeData): string
    {
        $html = $this->buildResumeHtml($resumeData);

        return $this->savePdf($html, 'tailored_resume_' . Str::uuid() . '.pdf');
    }

    /**
     * Generate a cover letter PDF.
     */
    public function generateCoverLetterPdf(string $coverLetter, string $jobTitle, string $company): string
    {
        $html = $this->buildCoverLetterHtml($coverLetter, $jobTitle, $company);

        return $this->savePdf($html, 'cover_letter_' . Str::uuid() . '.pdf');
    }

    /**
     * Save HTML content as a PDF file.
     */
    private function savePdf(string $html, string $fileName): string
    {
        $this->ensureDirectoryExists(self::DIRECTORY);

        $fullPath = self::DIRECTORY . '/' . $fileName;

        try {
            $pdf = Pdf::loadHTML($html);
            $pdf->setPaper(self::PAPER_SIZE, self::PAPER_ORIENTATION);
            $pdf->save(Storage::disk('local')->path($fullPath));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('PDF generation failed', [
                'path' => $fullPath,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to generate PDF. Please try again.');
        }

        return $fullPath;
    }

    /**
     * Ensure the storage directory exists.
     */
    private function ensureDirectoryExists(string $directory): void
    {
        if (!Storage::disk('local')->exists($directory)) {
            Storage::disk('local')->makeDirectory($directory);
        }
    }

    /**
     * Build professional resume HTML template.
     */
    protected function buildResumeHtml(array $data): string
    {
        $name = htmlspecialchars($data['name'] ?? 'Your Name');
        $email = htmlspecialchars($data['email'] ?? '');
        $phone = htmlspecialchars($data['phone'] ?? '');
        $location = htmlspecialchars($data['location'] ?? '');
        $summary = htmlspecialchars($data['summary'] ?? '');

        $experienceHtml = '';
        foreach ($data['experience'] ?? [] as $exp) {
            $title = htmlspecialchars($exp['title'] ?? '');
            $company = htmlspecialchars($exp['company'] ?? '');
            $dates = htmlspecialchars($exp['dates'] ?? '');
            $description = htmlspecialchars($exp['description'] ?? '');

            $experienceHtml .= "
                <div class=\"experience-item\" style=\"margin-bottom: 15px;\">
                    <div style=\"display: flex; justify-content: space-between; font-weight: bold;\">
                        <span>{$title}</span>
                        <span>{$dates}</span>
                    </div>
                    <div style=\"color: #555;\">{$company}</div>
                    <div style=\"margin-top: 5px; line-height: 1.5;\">{$description}</div>
                </div>
            ";
        }

        $educationHtml = '';
        foreach ($data['education'] ?? [] as $edu) {
            $degree = htmlspecialchars($edu['degree'] ?? '');
            $school = htmlspecialchars($edu['school'] ?? '');
            $year = htmlspecialchars($edu['year'] ?? '');

            $educationHtml .= "
                <div class=\"education-item\" style=\"margin-bottom: 10px;\">
                    <div style=\"font-weight: bold;\">{$degree}</div>
                    <div style=\"color: #555;\">{$school} {$year}</div>
                </div>
            ";
        }

        $skillsHtml = '';
        if (!empty($data['skills'])) {
            $skillsArray = array_map('htmlspecialchars', (array)$data['skills']);
            $skillsHtml = '<p>' . implode(' • ', $skillsArray) . '</p>';
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #333;
            margin: 40px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .name {
            font-size: 24pt;
            font-weight: bold;
            color: #1e3a8a;
            margin: 0;
        }
        .contact {
            color: #666;
            font-size: 10pt;
            margin-top: 5px;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 12pt;
            font-weight: bold;
            color: #2563eb;
            text-transform: uppercase;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .skills-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="name">{$name}</h1>
        <div class="contact">
            {$email} | {$phone} | {$location}
        </div>
    </div>

    <div class="section">
        <div class="section-title">Professional Summary</div>
        <p>{$summary}</p>
    </div>

    <div class="section">
        <div class="section-title">Work Experience</div>
        {$experienceHtml}
    </div>

    <div class="section">
        <div class="section-title">Education</div>
        {$educationHtml}
    </div>

    <div class="section">
        <div class="section-title">Skills</div>
        {$skillsHtml}
    </div>
</body>
</html>
HTML;
    }

    /**
     * Build cover letter HTML template.
     */
    protected function buildCoverLetterHtml(string $content, string $jobTitle, string $company): string
    {
        $today = date('F j, Y');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.8;
            color: #333;
            margin: 40px 60px;
        }
        .header {
            margin-bottom: 30px;
        }
        .date {
            margin-bottom: 20px;
        }
        .recipient {
            margin-bottom: 20px;
        }
        .subject {
            font-weight: bold;
            margin-bottom: 20px;
        }
        .content p {
            margin-bottom: 15px;
            text-align: justify;
        }
        .signature {
            margin-top: 30px;
        }
        .closing {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="date">{$today}</div>
        <div class="recipient">
            <strong>Re: Application for {$jobTitle} at {$company}</strong>
        </div>
    </div>

    <div class="content">
        {$this->formatParagraphs($content)}
    </div>

    <div class="signature">
        <div class="closing">Sincerely,</div>
        <div style="margin-top: 40px;">[Your Name]</div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Convert plain text paragraphs into HTML paragraphs.
     */
    protected function formatParagraphs(string $text): string
    {
        $paragraphs = array_filter(array_map('trim', explode("\n\n", $text)));
        $html = '';

        foreach ($paragraphs as $paragraph) {
            $html .= '<p>' . nl2br(htmlspecialchars($paragraph)) . '</p>';
        }

        return $html;
    }
}
