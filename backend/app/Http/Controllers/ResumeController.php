<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Validator;

class ResumeController extends Controller
{
    /**
     * Upload a PDF resume.
     *
     * POST /api/resume/upload
     * Body: multipart/form-data with 'resume' file field
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'resume' => 'required|file|mimes:pdf|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $file = $request->file('resume');
        $originalName = $file->getClientOriginalName();

        // Generate unique ID for this resume
        $resumeId = Str::uuid()->toString();

        // Store file with unique name
        $extension = $file->getClientOriginalExtension();
        $fileName = $resumeId . '.' . $extension;

        // Store in resumes directory
        $path = $file->storeAs('resumes', $fileName, 'local');

        return response()->json([
            'success' => true,
            'message' => 'Resume uploaded successfully',
            'data' => [
                'resume_id' => $resumeId,
                'original_filename' => $originalName,
                'stored_path' => 'resumes/' . $fileName,
            ],
        ]);
    }

    /**
     * Get resume info and extracted text.
     *
     * GET /api/resume/{id}
     *
     * @param string $id - Resume UUID
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $id)
    {
        $resumeService = new \App\Services\ResumeParserService();

        // Find the resume file
        $resumeDir = 'resumes/';
        $files = Storage::disk('local')->files($resumeDir);

        $resumePath = null;
        foreach ($files as $file) {
            if (str_starts_with(basename($file), $id)) {
                $resumePath = $file;
                break;
            }
        }

        if (!$resumePath) {
            return response()->json([
                'success' => false,
                'message' => 'Resume not found',
            ], 404);
        }

        try {
            $text = $resumeService->extractText($resumePath);

            return response()->json([
                'success' => true,
                'data' => [
                    'resume_id' => $id,
                    'text' => $text,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to parse resume: ' . $e->getMessage(),
            ], 500);
        }
    }
}
