<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GdprRequest;
use App\Models\UserConsent;
use App\Services\AuditService;
use App\Services\GdprService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class GdprController extends Controller
{
    public function __construct(
        private GdprService $gdprService,
        private AuditService $auditService
    ) {
        $this->middleware('auth:sanctum');
    }

    /**
     * Create a new GDPR request.
     */
    public function createRequest(Request $request): JsonResponse
    {
        $request->validate([
            'request_type' => 'required|string|in:export,delete,rectify,restrict,object',
            'description' => 'nullable|string|max:1000',
            'requested_data' => 'nullable|array',
        ]);

        $user = Auth::user();

        // Check for existing pending requests
        $existingRequest = GdprRequest::where('user_id', $user->id)
            ->where('request_type', $request->request_type)
            ->where('status', GdprRequest::STATUS_PENDING)
            ->first();

        if ($existingRequest) {
            return response()->json([
                'message' => 'You already have a pending request of this type',
                'existing_request' => $existingRequest,
            ], 409);
        }

        $gdprRequest = $this->gdprService->createRequest(
            $user,
            $request->request_type,
            $request->description,
            $request->requested_data ?? []
        );

        // Log the request
        $this->auditService->log(
            'gdpr.request_created',
            $gdprRequest,
            null,
            $gdprRequest->getAttributes(),
            "GDPR {$request->request_type} request created",
            'info',
            true
        );

        return response()->json([
            'message' => 'GDPR request created successfully. Please check your email to verify the request.',
            'request' => $gdprRequest,
        ], 201);
    }

    /**
     * Verify a GDPR request.
     */
    public function verifyRequest(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $gdprRequest = $this->gdprService->verifyRequest($request->token);

        if (! $gdprRequest) {
            return response()->json([
                'message' => 'Invalid or expired verification token',
            ], 404);
        }

        // Log the verification
        $this->auditService->log(
            'gdpr.request_verified',
            $gdprRequest,
            ['verified_at' => null],
            ['verified_at' => $gdprRequest->verified_at],
            'GDPR request verified',
            'info',
            true
        );

        return response()->json([
            'message' => 'GDPR request verified successfully',
            'request' => $gdprRequest,
        ]);
    }

    /**
     * Get user's GDPR requests.
     */
    public function getUserRequests(): JsonResponse
    {
        $user = Auth::user();

        $requests = GdprRequest::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'requests' => $requests,
        ]);
    }

    /**
     * Download export file.
     */
    public function downloadExport(GdprRequest $gdprRequest): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        $user = Auth::user();

        // Check if user owns this request
        if ($gdprRequest->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        // Check if request is completed and has export file
        if ($gdprRequest->status !== GdprRequest::STATUS_COMPLETED || ! $gdprRequest->export_file_path) {
            return response()->json([
                'message' => 'Export file not available',
            ], 404);
        }

        // Check if export has expired
        if ($gdprRequest->isExportExpired()) {
            return response()->json([
                'message' => 'Export file has expired',
            ], 410);
        }

        // Verify file integrity
        if ($gdprRequest->export_file_hash) {
            $currentHash = hash_file('sha256', Storage::path($gdprRequest->export_file_path));
            if ($currentHash !== $gdprRequest->export_file_hash) {
                return response()->json([
                    'message' => 'Export file integrity check failed',
                ], 500);
            }
        }

        // Log the download
        $this->auditService->log(
            'gdpr.export_downloaded',
            $gdprRequest,
            null,
            null,
            'GDPR export file downloaded',
            'info',
            true
        );

        return Storage::download($gdprRequest->export_file_path, "data_export_{$user->id}.zip");
    }

    /**
     * Record user consent.
     */
    public function recordConsent(Request $request): JsonResponse
    {
        $request->validate([
            'consent_type' => 'required|string|in:privacy_policy,terms_of_service,marketing,cookies,data_processing,third_party_sharing',
            'consent_version' => 'required|string|max:20',
            'is_granted' => 'required|boolean',
            'consent_method' => 'nullable|string|in:checkbox,signature,verbal,implied,opt_out',
            'metadata' => 'nullable|array',
        ]);

        $user = Auth::user();

        $consent = $this->gdprService->recordConsent(
            $user,
            $request->consent_type,
            $request->consent_version,
            $request->is_granted,
            array_merge($request->metadata ?? [], [
                'consent_method' => $request->consent_method ?? 'checkbox',
            ])
        );

        // Log the consent
        $this->auditService->log(
            'gdpr.consent_recorded',
            $consent,
            null,
            $consent->getAttributes(),
            "User consent {$request->consent_type}: " . ($request->is_granted ? 'granted' : 'withdrawn'),
            'info',
            true
        );

        return response()->json([
            'message' => 'Consent recorded successfully',
            'consent' => $consent,
        ], 201);
    }

    /**
     * Get user's consent status.
     */
    public function getConsentStatus(): JsonResponse
    {
        $user = Auth::user();
        $consentStatus = $this->gdprService->getUserConsentStatus($user);

        return response()->json([
            'consent_status' => $consentStatus,
            'has_required_consents' => $this->gdprService->hasRequiredConsents($user),
        ]);
    }

    /**
     * Get consent history for a specific type.
     */
    public function getConsentHistory(Request $request): JsonResponse
    {
        $request->validate([
            'consent_type' => 'required|string',
        ]);

        $user = Auth::user();
        $history = UserConsent::getHistory($user->id, $request->consent_type);

        return response()->json([
            'consent_type' => $request->consent_type,
            'history' => $history,
        ]);
    }

    /**
     * Admin: Get all GDPR requests.
     */
    public function adminGetRequests(Request $request): JsonResponse
    {
        $this->middleware('admin');

        $request->validate([
            'status' => 'nullable|string|in:pending,processing,completed,rejected,cancelled',
            'request_type' => 'nullable|string|in:export,delete,rectify,restrict,object',
            'per_page' => 'integer|min:1|max:100',
        ]);

        $query = GdprRequest::with(['user', 'processedBy'])
            ->orderBy('created_at', 'desc');

        if ($request->status) {
            $query->status($request->status);
        }

        if ($request->request_type) {
            $query->type($request->request_type);
        }

        $requests = $query->paginate($request->integer('per_page', 50));

        return response()->json($requests);
    }

    /**
     * Admin: Process a GDPR request.
     */
    public function adminProcessRequest(Request $request, GdprRequest $gdprRequest): JsonResponse
    {
        $this->middleware('admin');

        $request->validate([
            'action' => 'required|string|in:approve,reject',
            'notes' => 'nullable|string|max:1000',
            'rejection_reason' => 'required_if:action,reject|string|max:500',
        ]);

        $admin = Auth::user();

        if ($request->action === 'approve') {
            $success = match ($gdprRequest->request_type) {
                GdprRequest::TYPE_EXPORT => $this->gdprService->processExportRequest($gdprRequest, $admin->id),
                GdprRequest::TYPE_DELETE => $this->gdprService->processDeletionRequest($gdprRequest, $admin->id),
                default => false,
            };

            if (! $success) {
                return response()->json([
                    'message' => 'Failed to process request',
                ], 500);
            }

            $message = 'Request processed successfully';
        } else {
            $gdprRequest->markAsRejected($request->rejection_reason);
            $message = 'Request rejected';
        }

        // Add admin notes if provided
        if ($request->notes) {
            $gdprRequest->update(['admin_notes' => $request->notes]);
        }

        // Log the admin action
        $this->auditService->logAdmin(
            'gdpr_request_processed',
            $gdprRequest,
            [
                'action' => $request->action,
                'admin_id' => $admin->id,
                'notes' => $request->notes,
            ]
        );

        return response()->json([
            'message' => $message,
            'request' => $gdprRequest->fresh(),
        ]);
    }
}
