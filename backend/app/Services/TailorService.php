<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TailorService
{
    private const BASE_URL = 'https://openrouter.ai/api/v1';
    private const DEFAULT_MODEL = 'anthropic/claude-3-haiku';
    private const TEMPERATURE = 0.7;
    private const MAX_TOKENS = 4000;

    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.openrouter.api_key', env('OPENROUTER_API_KEY', ''));
        $this->model = config('services.openrouter.model', self::DEFAULT_MODEL);
    }

    /**
     * Expected JSON structure for the LLM response.
     */
    private const RESPONSE_SCHEMA = '{
    "resume": {
        "name": "Full Name (from resume)",
        "email": "email@example.com",
        "phone": "phone number",
        "location": "City, State",
        "summary": "2-3 sentence professional summary tailored to the job",
        "experience": [
            {
                "title": "Job Title",
                "company": "Company Name",
                "dates": "Start Date - End Date",
                "description": "Description highlighting relevant achievements"
            }
        ],
        "education": [
            {
                "degree": "Degree Name",
                "school": "School Name",
                "year": "Year"
            }
        ],
        "skills": ["Skill 1", "Skill 2", "Skill 3"]
    },
    "cover_letter": "Full cover letter text with proper paragraphs..."
}';

    /**
     * Tailor a resume and generate a cover letter based on job description.
     *
     * @param string $resumeText - The extracted text from the resume PDF
     * @param string $jobTitle - The job title
     * @param string $company - The company name
     * @param string $jobDescription - The job description text
     * @return array - Contains 'resume' (array) and 'cover_letter' (string)
     */
    public function tailor(string $resumeText, string $jobTitle, string $company, string $jobDescription): array
    {
        // Sanitize user input to prevent prompt injection
        $jobTitle = $this->sanitizeInput($jobTitle);
        $company = $this->sanitizeInput($company);
        $jobDescription = $this->sanitizeInput($jobDescription);
        $resumeText = $this->sanitizeInput($resumeText);

        $prompt = $this->buildPrompt($resumeText, $jobTitle, $company, $jobDescription);

        $response = $this->callLlm($prompt);

        return $this->parseResponse($response);
    }

    /**
     * Build the prompt for the LLM.
     */
    protected function buildPrompt(string $resumeText, string $jobTitle, string $company, string $jobDescription): string
    {
        $schema = self::RESPONSE_SCHEMA;

        return <<<PROMPT
You are a professional resume writer and career consultant. Given a candidate's resume and a job description, your task is to:

1. Create a tailored resume that highlights the most relevant skills and experience for the specific job
2. Write a compelling cover letter

**JOB DETAILS:**
Job Title: {$jobTitle}
Company: {$company}

**JOB DESCRIPTION:**
{$jobDescription}

**CANDIDATE'S ORIGINAL RESUME:**
{$resumeText}

**OUTPUT FORMAT:**
Return a JSON object with this exact structure:
{$schema}

IMPORTANT: Return ONLY valid JSON. No markdown, no explanation, just the JSON object.
PROMPT;
    }

    /**
     * Call the OpenRouter API.
     */
    protected function callLlm(string $prompt): string
    {
        if (empty($this->apiKey)) {
            throw new \Exception('OpenRouter API key is not configured. Please set OPENROUTER_API_KEY in your .env file.');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
            'HTTP-Referer' => env('APP_URL', 'http://localhost'),
            'X-Title' => 'AI Resume Tailor',
        ])->post(self::BASE_URL . '/chat/completions', [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => self::TEMPERATURE,
            'max_tokens' => self::MAX_TOKENS,
        ]);

        if ($response->failed()) {
            Log::error('OpenRouter API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to get response from LLM: ' . $response->body());
        }

        $data = $response->json();

        if (!isset($data['choices'][0]['message']['content'])) {
            throw new \Exception('Invalid response format from LLM');
        }

        return $data['choices'][0]['message']['content'];
    }

    /**
     * Parse the LLM response into structured data.
     */
    protected function parseResponse(string $response): array
    {
        // Extract JSON from the response (in case there's any wrapping text)
        $jsonString = $this->extractJson($response);

        $decoded = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('JSON parse error', [
                'error' => json_last_error_msg(),
                'response' => $response,
            ]);
            throw new \Exception('Failed to parse LLM response as JSON');
        }

        // Validate required fields
        if (!isset($decoded['resume']) || !isset($decoded['cover_letter'])) {
            throw new \Exception('LLM response missing required fields');
        }

        return $decoded;
    }

    /**
     * Extract JSON object from a string that may contain extra text.
     */
    protected function extractJson(string $text): string
    {
        if (preg_match('/\{[\s\S]*?\}/', $text, $matches)) {
            return $matches[0];
        }

        return $text;
    }

    /**
     * Sanitize user input to prevent prompt injection attacks.
     */
    protected function sanitizeInput(string $input): string
    {
        // Remove control characters that could manipulate prompt
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
        // Trim whitespace
        $input = trim($input);
        // Limit length to prevent resource exhaustion (100KB max)
        if (strlen($input) > 100000) {
            $input = substr($input, 0, 100000);
        }
        return $input;
    }
}
