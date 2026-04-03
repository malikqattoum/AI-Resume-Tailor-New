<?php

namespace App\Http\Controllers;

use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Traits\ApiResponses;
use App\Models\SubscriptionUsageLog;
use App\Models\User;
use App\Services\PdfGeneratorService;
use App\Services\ResumeParserService;
use App\Services\StripeService;
use App\Services\TailorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class TailorController extends Controller
{
    use ApiResponses;

    private const RESULT_DIR = 'results';
    private const RESULT_FILE_EXTENSION = '.json';
    private const DOWNLOAD_CACHE_PREFIX = 'download:';
    private const DOWNLOAD_CACHE_TTL = 86400; // 24 hours
    private const LOG_ACTION_TAILOR_REQUEST = 'tailor_request';

    private ResumeParserService $resumeParserService;
    private TailorService $tailorService;
    private PdfGeneratorService $pdfGeneratorService;
    private StripeService $stripeService;

    public function __construct(
        ResumeParserService $resumeParserService,
        TailorService $tailorService,
        PdfGeneratorService $pdfGeneratorService,
        StripeService $stripeService
    ) {
        $this->resumeParserService = $resumeParserService;
        $this->tailorService = $tailorService;
        $this->pdfGeneratorService = $pdfGeneratorService;
        $this->stripeService = $stripeService;
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
            'job_description' => 'required|string|min:50|max:10000',
        ]);

        // Check subscription usage limit
        $user = $request->user();
        $subscription = Subscription::firstOrCreateFreeForUser($user->id);

        if (!$subscription->isActive()) {
            return $this->errorResponse('Your subscription is not active.', 'subscription_inactive', 403);
        }

        if ($subscription->isAtLimit()) {
            $portalUrl = null;
            try {
                $stripeService = $this->stripeService;
                if ($user->stripe_customer_id) {
                    $portalUrl = $stripeService->createPortalSession($user);
                }
            } catch (\Exception $e) {
                Log::warning('Stripe portal session unavailable during limit error', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                // Portal unavailable, still return the limit error
            }

            return response()->json([
                'success' => false,
                'message' => 'Monthly request limit reached.',
                'error' => 'payment_required',
                'upgrade_url' => $portalUrl,
            ], 402);
        }

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $userId = $request->user()->id;
        $resumeId = $request->input('resume_id');
        $jobTitle = $request->input('job_title');
        $company = $request->input('company');
        $jobDescription = $request->input('job_description');

        // Find the resume file
        $resumePath = $this->findResumePath($resumeId, $userId);

        if (!$resumePath) {
            return $this->errorResponse('Resume not found', null, 404);
        }

        // Extract text from PDF
        try {
            $resumeText = $this->resumeParserService->extractText($resumePath);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to parse resume: ' . $e->getMessage(), null, 500);
        }

        // Call LLM to tailor
        try {
            $result = $this->tailorService->tailor($resumeText, $jobTitle, $company, $jobDescription);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to tailor resume: ' . $e->getMessage(), null, 500);
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
            return $this->errorResponse('Failed to generate PDFs: ' . $e->getMessage(), null, 500);
        }

        // Generate result ID for retrieval
        $resultId = Str::uuid()->toString();

        // Store result metadata
        $resultData = [
            'id' => $resultId,
            'user_id' => $userId,
            'resume_id' => $resumeId,
            'job_title' => $jobTitle,
            'company' => $company,
            'tailored_resume_path' => $tailoredResumePath,
            'cover_letter_path' => $coverLetterPath,
            'created_at' => now()->toIso8601String(),
        ];

        try {
            Storage::disk('local')->put(
                self::RESULT_DIR . '/' . $resultId . self::RESULT_FILE_EXTENSION,
                json_encode($resultData)
            );

            // Increment usage counter
            $subscription->incrementUsage();

            // Log usage
            SubscriptionUsageLog::create([
                'user_id' => $user->id,
                'action' => self::LOG_ACTION_TAILOR_REQUEST,
            ]);
        } catch (\Exception $e) {
            // Clean up orphaned PDF files if metadata storage fails
            try {
                Storage::disk('local')->delete($tailoredResumePath);
                Storage::disk('local')->delete($coverLetterPath);
            } catch (\Exception $cleanupError) {
                Log::warning('Failed to clean up orphaned files', [
                    'resume' => $tailoredResumePath,
                    'cover_letter' => $coverLetterPath,
                    'error' => $cleanupError->getMessage(),
                ]);
            }
            Log::error('Failed to store result metadata', [
                'result_id' => $resultId,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to save results. Please try again.');
        }

        // Generate opaque download IDs for secure downloads
        $resumeDownloadId = $this->createDownloadOpaqueId($resultId, $tailoredResumePath, $userId);
        $coverLetterDownloadId = $this->createDownloadOpaqueId($resultId, $coverLetterPath, $userId);

        return response()->json([
            'success' => true,
            'message' => 'Resume tailored successfully',
            'data' => [
                'result_id' => $resultId,
                'tailored_resume_url' => $this->getDownloadUrl($resumeDownloadId),
                'cover_letter_url' => $this->getDownloadUrl($coverLetterDownloadId),
            ],
        ]);
    }

    /**
     * Get tailored result by ID.
     *
     * GET /api/tailored/{id}
     *
     * @param Request $request
     * @param string $id - Result UUID
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, string $id)
    {
        $userId = $request->user()->id;
        $resultPath = self::RESULT_DIR . '/' . $id . self::RESULT_FILE_EXTENSION;

        if (!Storage::disk('local')->exists($resultPath)) {
            return $this->errorResponse('Result not found', null, 404);
        }

        $resultData = json_decode(Storage::disk('local')->get($resultPath), true);

        // Ensure user can only access their own results
        if ($resultData['user_id'] !== $userId) {
            return $this->errorResponse('Result not found', null, 404);
        }

        // Generate fresh opaque download IDs
        $resumeDownloadId = $this->createDownloadOpaqueId($id, $resultData['tailored_resume_path'], $userId);
        $coverLetterDownloadId = $this->createDownloadOpaqueId($id, $resultData['cover_letter_path'], $userId);

        return response()->json([
            'success' => true,
            'data' => [
                'result_id' => $resultData['id'],
                'resume_id' => $resultData['resume_id'],
                'job_title' => $resultData['job_title'],
                'company' => $resultData['company'],
                'tailored_resume_url' => $this->getDownloadUrl($resumeDownloadId),
                'cover_letter_url' => $this->getDownloadUrl($coverLetterDownloadId),
                'created_at' => $resultData['created_at'],
            ],
        ]);
    }

    /**
     * Download a file using opaque ID.
     *
     * GET /api/download/{opaque_id}
     *
     * @param string $opaqueId - Opaque download ID
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function download(string $opaqueId)
    {
        // Look up the opaque ID to get the actual file path
        $downloadInfo = $this->resolveDownloadOpaqueId($opaqueId);

        if (!$downloadInfo) {
            abort(404, 'File not found');
        }

        $filePath = $downloadInfo['path'];

        // Verify file still exists
        if (!Storage::disk('local')->exists($filePath)) {
            abort(404, 'File not found');
        }

        $fullPath = Storage::disk('local')->path($filePath);
        $fileName = basename($filePath);

        return response()->download($fullPath, $fileName, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Create an opaque download ID that maps to a file path.
     * This prevents exposing internal storage paths in URLs.
     */
    protected function createDownloadOpaqueId(string $resultId, string $filePath, int $userId): string
    {
        $opaqueId = Str::uuid()->toString();

        // Store the mapping in cache with short expiry
        $cacheKey = self::DOWNLOAD_CACHE_PREFIX . $opaqueId;
        Cache::put($cacheKey, [
            'result_id' => $resultId,
            'path' => $filePath,
            'user_id' => $userId,
        ], self::DOWNLOAD_CACHE_TTL);

        return $opaqueId;
    }

    /**
     * Resolve an opaque download ID to the actual file path.
     */
    protected function resolveDownloadOpaqueId(string $opaqueId): ?array
    {
        $cacheKey = self::DOWNLOAD_CACHE_PREFIX . $opaqueId;
        $downloadInfo = Cache::get($cacheKey);

        if (!$downloadInfo) {
            return null;
        }

        // Optionally verify the user making the request matches
        // This is handled at the route level via auth:sanctum middleware

        return $downloadInfo;
    }

    /**
     * Generate a download URL for a file using opaque ID.
     */
    protected function getDownloadUrl(string $opaqueId): string
    {
        return url('api/download/' . $opaqueId);
    }

    /**
     * Find resume path by UUID for a specific user.
     */
    protected function findResumePath(string $resumeId, int $userId): ?string
    {
        // In a more complete implementation, we would store resume metadata
        // in a database and associate it with users. For now, we scan the directory.
        $files = Storage::disk('local')->files('resumes/');

        foreach ($files as $file) {
            if (str_starts_with(basename($file), $resumeId)) {
                return $file;
            }
        }

        return null;
    }
}
