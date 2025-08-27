<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\GoogleOAuthService;
use App\Services\SmsVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class OAuthController extends Controller
{
    public function __construct(
        private GoogleOAuthService $googleOAuthService,
        private SmsVerificationService $smsService,
        private AuditService $auditService
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/auth/google",
     *     tags={"OAuth Authentication"},
     *     summary="Redirect to Google OAuth",
     *     description="Redirect user to Google OAuth consent screen",
     *     @OA\Response(
     *         response=302,
     *         description="Redirect to Google OAuth",
     *         @OA\Header(
     *             header="Location",
     *             description="Google OAuth URL",
     *             @OA\Schema(type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="OAuth initialization failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Failed to initiate Google OAuth")
     *         )
     *     )
     * )
     */
    public function redirectToGoogle(): \Symfony\Component\HttpFoundation\RedirectResponse|JsonResponse
    {
        try {
            // Log OAuth initiation
            $this->auditService->log(
                'oauth.google_redirect_initiated',
                null,
                null,
                null,
                'Google OAuth redirect initiated',
                'info',
                false,
                ['ip' => request()->ip()]
            );

            return $this->googleOAuthService->redirectToGoogle();

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => 'OAUTH_REDIRECT_FAILED',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/auth/google/callback",
     *     tags={"OAuth Authentication"},
     *     summary="Handle Google OAuth callback",
     *     description="Process Google OAuth callback and authenticate user",
     *     @OA\Parameter(
     *         name="code",
     *         in="query",
     *         required=true,
     *         description="OAuth authorization code from Google",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="state",
     *         in="query",
     *         required=true,
     *         description="OAuth state parameter",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OAuth authentication successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Successfully signed in with Google"),
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="first_name", type="string", example="John"),
     *                 @OA\Property(property="last_name", type="string", example="Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="provider", type="string", example="google")
     *             ),
     *             @OA\Property(property="token", type="string", example="1|abc123..."),
     *             @OA\Property(property="is_new_user", type="boolean", example=false),
     *             @OA\Property(property="requires_phone_verification", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="OAuth callback failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid OAuth state"),
     *             @OA\Property(property="error_code", type="string", example="INVALID_STATE")
     *         )
     *     )
     * )
     */
    public function handleGoogleCallback(): JsonResponse
    {
        $result = $this->googleOAuthService->handleGoogleCallback();

        // Log OAuth callback result
        $this->auditService->log(
            $result['success'] ? 'oauth.google_callback_success' : 'oauth.google_callback_failed',
            null,
            null,
            null,
            'Google OAuth callback processed',
            $result['success'] ? 'info' : 'warning',
            true,
            [
                'success' => $result['success'],
                'error_code' => $result['error_code'] ?? null,
                'is_new_user' => $result['is_new_user'] ?? false,
                'ip' => request()->ip(),
            ]
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/phone/send-verification",
     *     tags={"Phone Verification"},
     *     summary="Send SMS verification code",
     *     description="Send SMS verification code to Myanmar phone number",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone"},
     *             @OA\Property(
     *                 property="phone",
     *                 type="string",
     *                 example="09123456789",
     *                 description="Myanmar phone number (09xxxxxxxx or +959xxxxxxxx format)"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Verification code sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Verification code sent successfully"),
     *             @OA\Property(property="expires_in_minutes", type="integer", example=10),
     *             @OA\Property(property="phone_masked", type="string", example="*****6789")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid phone number or rate limited",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid Myanmar phone number format"),
     *             @OA\Property(property="error_code", type="string", example="INVALID_PHONE_FORMAT")
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="User locked out from too many attempts",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Too many verification attempts"),
     *             @OA\Property(property="error_code", type="string", example="USER_LOCKED_OUT"),
     *             @OA\Property(property="lockout_remaining_minutes", type="integer", example=25)
     *         )
     *     )
     * )
     */
    public function sendPhoneVerification(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => [
                'required',
                'string',
                'regex:/^(\+?959|09)[0-9]{8}$/', // Myanmar phone number format
            ],
        ], [
            'phone.regex' => 'Please enter a valid Myanmar phone number (09xxxxxxxx or +959xxxxxxxx format)',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid phone number format',
                'errors' => $validator->errors(),
                'error_code' => 'VALIDATION_FAILED',
            ], 422);
        }

        $user = $request->user();
        $phone = $request->input('phone');

        $result = $this->smsService->sendVerificationCode($user, $phone);

        // Log SMS verification attempt
        $this->auditService->log(
            $result['success'] ? 'phone.verification_code_sent' : 'phone.verification_code_failed',
            $user,
            null,
            null,
            'SMS verification code send attempt',
            $result['success'] ? 'info' : 'warning',
            true,
            [
                'success' => $result['success'],
                'error_code' => $result['error_code'] ?? null,
                'phone_masked' => $this->maskPhoneNumber($phone),
                'ip' => request()->ip(),
            ]
        );

        $statusCode = match ($result['error_code'] ?? null) {
            'USER_LOCKED_OUT' => 429,
            'PHONE_RATE_LIMITED' => 429,
            'INVALID_PHONE_FORMAT' => 400,
            default => $result['success'] ? 200 : 500
        };

        return response()->json($result, $statusCode);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/phone/verify",
     *     tags={"Phone Verification"},
     *     summary="Verify SMS code",
     *     description="Verify SMS verification code for Myanmar phone number",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code"},
     *             @OA\Property(
     *                 property="code",
     *                 type="string",
     *                 example="123456",
     *                 description="6-digit verification code received via SMS"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Phone number verified successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Phone number verified successfully"),
     *             @OA\Property(property="verified_at", type="string", example="2024-01-01T00:00:00Z")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid or expired code",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid verification code"),
     *             @OA\Property(property="error_code", type="string", example="INVALID_CODE"),
     *             @OA\Property(property="attempts_remaining", type="integer", example=2)
     *         )
     *     )
     * )
     */
    public function verifyPhoneCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => [
                'required',
                'string',
                'regex:/^[0-9]{6}$/', // 6-digit numeric code
            ],
        ], [
            'code.regex' => 'Verification code must be 6 digits',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification code format',
                'errors' => $validator->errors(),
                'error_code' => 'VALIDATION_FAILED',
            ], 422);
        }

        $user = $request->user();
        $code = $request->input('code');

        $result = $this->smsService->verifyCode($user, $code);

        // Log SMS verification attempt
        $this->auditService->log(
            $result['success'] ? 'phone.verification_success' : 'phone.verification_failed',
            $user,
            null,
            null,
            'SMS code verification attempt',
            $result['success'] ? 'info' : 'warning',
            true,
            [
                'success' => $result['success'],
                'error_code' => $result['error_code'] ?? null,
                'ip' => request()->ip(),
            ]
        );

        $statusCode = match ($result['error_code'] ?? null) {
            'USER_LOCKED_OUT', 'MAX_ATTEMPTS_EXCEEDED' => 429,
            'CODE_EXPIRED', 'INVALID_CODE' => 400,
            default => $result['success'] ? 200 : 500
        };

        return response()->json($result, $statusCode);
    }

    /**
     * @OA\Get(
     *     path="/api/auth/phone/status",
     *     tags={"Phone Verification"},
     *     summary="Get phone verification status",
     *     description="Get current phone verification status for authenticated user",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Phone verification status",
     *         @OA\JsonContent(
     *             @OA\Property(property="phone_verified", type="boolean", example=true),
     *             @OA\Property(property="phone_verified_at", type="string", example="2024-01-01T00:00:00Z"),
     *             @OA\Property(property="phone_masked", type="string", example="*****6789"),
     *             @OA\Property(property="is_locked_out", type="boolean", example=false),
     *             @OA\Property(property="has_pending_code", type="boolean", example=false)
     *         )
     *     )
     * )
     */
    public function getPhoneVerificationStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $status = $this->smsService->getVerificationStatus($user);

        return response()->json($status);
    }

    /**
     * @OA\Delete(
     *     path="/api/auth/google/unlink",
     *     tags={"OAuth Authentication"},
     *     summary="Unlink Google account",
     *     description="Unlink Google OAuth account from user profile",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Google account unlinked successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Google account unlinked successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Cannot unlink - password required",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Please set a password before unlinking"),
     *             @OA\Property(property="error_code", type="string", example="PASSWORD_REQUIRED")
     *         )
     *     )
     * )
     */
    public function unlinkGoogle(Request $request): JsonResponse
    {
        $user = $request->user();
        $result = $this->googleOAuthService->unlinkGoogleAccount($user);

        // Log OAuth unlink attempt
        $this->auditService->log(
            $result['success'] ? 'oauth.google_unlinked' : 'oauth.google_unlink_failed',
            $user,
            null,
            null,
            'Google OAuth unlink attempt',
            'info',
            false,
            [
                'success' => $result['success'],
                'error_code' => $result['error_code'] ?? null,
                'ip' => request()->ip(),
            ]
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * @OA\Get(
     *     path="/api/auth/oauth/status",
     *     tags={"OAuth Authentication"},
     *     summary="Get OAuth status",
     *     description="Get current OAuth linking status for authenticated user",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="OAuth status",
     *         @OA\JsonContent(
     *             @OA\Property(property="google_linked", type="boolean", example=true),
     *             @OA\Property(property="provider", type="string", example="google"),
     *             @OA\Property(property="linked_at", type="string", example="2024-01-01T00:00:00Z"),
     *             @OA\Property(property="can_unlink", type="boolean", example=true)
     *         )
     *     )
     * )
     */
    public function getOAuthStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $status = $this->googleOAuthService->getOAuthStatus($user);

        return response()->json($status);
    }

    /**
     * Mask phone number for logging
     */
    private function maskPhoneNumber(string $phoneNumber): string
    {
        if (strlen($phoneNumber) <= 4) {
            return str_repeat('*', strlen($phoneNumber));
        }

        return str_repeat('*', strlen($phoneNumber) - 4) . substr($phoneNumber, -4);
    }
}
