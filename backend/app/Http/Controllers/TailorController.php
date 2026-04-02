<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class TailorController extends Controller
{
    protected $resumeParserService;
    protected $tailorService;
    protected $pdfGeneratorService;

    public function __construct()
    {
        $this->resumeParserService = new \App\Services\ResumeParserService();
        $this->tailorService = new \App\Services\TailorService();
        $this->pdfGeneratorService = new \App\Services\PdfGeneratorService();
    }

    /**
     * Generate tailored resume and cover letter.
     *
     * POST /api/tailor
     * Body: {
     *   "resume_id": "uuid",
     *   "job_title": "Software Engineer",
     *   "company": "Acme Corp",
     *   "job_description": "We are looking for..."
     * }
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function tailor(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'resume_id' => 'required|uuid',
            'job_title' => 'required|string|max:255',
            'company' => 'required|string|max:255',
            'job_description' => 'required|string|min:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $resumeId = $request->input('resume_id');
        $jobTitle = $request->input('job_title');
        $company = $request->input('company');
        $jobDescription = $request->input('job_description');

        // Find the resume file
        $resumePath = $this->findResumePath($resumeId);

        if (!$resumePath) {
            return response()->json([
                'success' => false,
                'message' => 'Resume not found',
            ], 404);
        }

        // Extract text from PDF
        try {
            $resumeText = $this->resumeParserService->extractText($resumePath);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to parse resume: ' . $e->getMessage(),
            ], 500);
        }

        // Call LLM to tailor
        try {
            $result = $this->tailorService->tailor($resumeText, $jobTitle, $company, $jobDescription);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to tailor resume: ' . $e->getMessage(),
            ], 500);
        }

        // Generate PDFs
        try {
            $tailoredResumePath = $this->pdfGeneratorService->generateResumePdf($result['resume']);
            $coverLetterPath = $this->pdfGeneratorService->generateCoverLetterPdf(
                $result['cover_letter'],
                $jobTitle,
                $company
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PDFs: ' . $e->getMessage(),
            ], 500);
        }

        // Generate result ID for retrieval
        $resultId = Str::uuid()->toString();

        // Store result metadata
        $resultData = [
            'id' => $resultId,
            'resume_id' => $resumeId,
            'job_title' => $jobTitle,
            'company' => $company,
            'tailored_resume_path' => $tailoredResumePath,
            'cover_letter_path' => $coverLetterPath,
            'created_at' => now()->toIso8601String(),
        ];

        Storage::disk('local')->put(
            'results/' . $resultId . '.json',
            json_encode($resultData)
        );

        return response()->json([
            'success' => true,
            'message' => 'Resume tailored successfully',
            'data' => [
                'result_id' => $resultId,
                'tailored_resume_url' => $this->getDownloadUrl($tailoredResumePath),
                'cover_letter_url' => $this->getDownloadUrl($coverLetterPath),
            ],
        ]);
    }

    /**
     * Get tailored result by ID.
     *
     * GET /api/tailored/{id}
     *
     * @param string $id - Result UUID
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $id)
    {
        $resultPath = 'results/' . $id . '.json';

        if (!Storage::disk('local')->exists($resultPath)) {
            return response()->json([
                'success' => false,
                'message' => 'Result not found',
            ], 404);
        }

        $resultData = json_decode(Storage::disk('local')->get($resultPath), true);

        return response()->json([
            'success' => true,
            'data' => [
                'result_id' => $resultData['id'],
                'resume_id' => $resultData['resume_id'],
                'job_title' => $resultData['job_title'],
                'company' => $resultData['company'],
                'tailored_resume_url' => $this->getDownloadUrl($resultData['tailored_resume_path']),
                'cover_letter_url' => $this->getDownloadUrl($resultData['cover_letter_path']),
                'created_at' => $resultData['created_at'],
            ],
        ]);
    }

    /**
     * Download a generated file.
     *
     * GET /api/download/{path}
     *
     * @param string $path - Encoded path
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function download(string $path)
    {
        $decodedPath = urldecode($path);

        if (!Storage::disk('local')->exists($decodedPath)) {
            abort(404, 'File not found');
        }

        $fullPath = Storage::disk('local')->path($decodedPath);
        $fileName = basename($decodedPath);

        return response()->download($fullPath, $fileName, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Find resume path by UUID.
     */
    protected function findResumePath(string $resumeId): ?string
    {
        $resumeDir = 'resumes/';
        $files = Storage::disk('local')->files($resumeDir);

        foreach ($files as $file) {
            if (str_starts_with(basename($file), $resumeId)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Generate a download URL for a file.
     */
    protected function getDownloadUrl(string $path): string
    {
        $encodedPath = urlencode($path);
        return url('api/download/' . $encodedPath);
    }
}
