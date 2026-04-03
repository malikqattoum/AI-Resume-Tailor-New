<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

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

        $userId = $request->user()->id;
        $file = $request->file('resume');
        $originalName = $file->getClientOriginalName();

        // Generate unique ID for this resume
        $resumeId = Str::uuid()->toString();

        // Store file with unique name
        $extension = $file->getClientOriginalExtension();
        $fileName = $resumeId . '.' . $extension;

        // Store in resumes directory
        $path = $file->storeAs('resumes', $fileName, 'local');

        // Store metadata for user association
        $metadataPath = 'resumes/' . $resumeId . '.meta.json';
        Storage::disk('local')->put($metadataPath, json_encode([
            'resume_id' => $resumeId,
            'user_id' => $userId,
            'original_filename' => $originalName,
            'stored_path' => 'resumes/' . $fileName,
            'created_at' => now()->toIso8601String(),
        ]));

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
     * @param Request $request
     * @param string $id - Resume UUID
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, string $id)
    {
        $userId = $request->user()->id;
        $resumeService = new \App\Services\ResumeParserService();

        // First check metadata file
        $metadataPath = 'resumes/' . $id . '.meta.json';
        $metadata = null;

        if (Storage::disk('local')->exists($metadataPath)) {
            $metadata = json_decode(Storage::disk('local')->get($metadataPath), true);

            // Verify ownership
            if ($metadata && $metadata['user_id'] !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resume not found',
                ], 404);
            }
        }

        // Find the resume file
        $resumePath = null;
        $files = Storage::disk('local')->files('resumes/');

        foreach ($files as $file) {
            // Skip metadata files
            if (str_ends_with($file, '.meta.json')) {
                continue;
            }
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

        // Verify ownership if metadata exists
        if ($metadata && $metadata['user_id'] !== $userId) {
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
                    'original_filename' => $metadata['original_filename'] ?? 'unknown.pdf',
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
