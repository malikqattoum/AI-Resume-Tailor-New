<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PdfGeneratorService
{
    /**
     * Generate a professional resume PDF from structured data.
     *
     * @param array $resumeData - Structured resume data
     * @return string - The path to the generated PDF
     */
    public function generateResumePdf(array $resumeData): string
    {
        $html = $this->buildResumeHtml($resumeData);

        $fileName = 'tailored_resume_' . Str::uuid() . '.pdf';
        $directory = 'tailored';

        // Ensure directory exists
        if (!Storage::disk('local')->exists($directory)) {
            Storage::disk('local')->makeDirectory($directory);
        }

        $fullPath = $directory . '/' . $fileName;

        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->save(Storage::disk('local')->path($fullPath));

        return $fullPath;
    }

    /**
     * Generate a cover letter PDF.
     *
     * @param string $coverLetter - The cover letter text
     * @param string $jobTitle - The job title
     * @param string $company - The company name
     * @return string - The path to the generated PDF
     */
    public function generateCoverLetterPdf(string $coverLetter, string $jobTitle, string $company): string
    {
        $html = $this->buildCoverLetterHtml($coverLetter, $jobTitle, $company);

        $fileName = 'cover_letter_' . Str::uuid() . '.pdf';
        $directory = 'tailored';

        if (!Storage::disk('local')->exists($directory)) {
            Storage::disk('local')->makeDirectory($directory);
        }

        $fullPath = $directory . '/' . $fileName;

        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->save(Storage::disk('local')->path($fullPath));

        return $fullPath;
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
