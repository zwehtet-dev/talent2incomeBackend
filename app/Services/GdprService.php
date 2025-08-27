<?php

namespace App\Services;

use App\Models\GdprRequest;
use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class GdprService
{
    /**
     * Create a new GDPR request.
     */
    public function createRequest(
        User $user,
        string $requestType,
        string $description = null,
        array $requestedData = []
    ): GdprRequest {
        $request = GdprRequest::create([
            'user_id' => $user->id,
            'request_type' => $requestType,
            'description' => $description,
            'requested_data' => $requestedData,
            'status' => GdprRequest::STATUS_PENDING,
        ]);

        // Send verification email
        $this->sendVerificationEmail($request);

        return $request;
    }

    /**
     * Verify a GDPR request.
     */
    public function verifyRequest(string $token): ?GdprRequest
    {
        $request = GdprRequest::where('verification_token', $token)
            ->where('status', GdprRequest::STATUS_PENDING)
            ->first();

        if ($request) {
            $request->markAsVerified();

            return $request;
        }

        return null;
    }

    /**
     * Process a data export request.
     */
    public function processExportRequest(GdprRequest $request, int $adminId): bool
    {
        if ($request->request_type !== GdprRequest::TYPE_EXPORT) {
            return false;
        }

        $request->markAsProcessing($adminId);

        try {
            $userData = $this->exportUserData($request->user);
            $filePath = $this->createExportFile($request->user, $userData);

            $request->markAsCompleted([
                'export_file_path' => $filePath,
                'export_file_hash' => hash_file('sha256', Storage::path($filePath)),
                'export_expires_at' => now()->addDays(30), // Export expires in 30 days
            ]);

            // Send notification to user
            $this->sendExportReadyEmail($request);

            return true;
        } catch (\Exception $e) {
            $request->update(['admin_notes' => 'Export failed: ' . $e->getMessage()]);

            return false;
        }
    }

    /**
     * Process a data deletion request.
     */
    public function processDeletionRequest(GdprRequest $request, int $adminId): bool
    {
        if ($request->request_type !== GdprRequest::TYPE_DELETE) {
            return false;
        }

        $request->markAsProcessing($adminId);

        try {
            $this->deleteUserData($request->user);
            $request->markAsCompleted();

            return true;
        } catch (\Exception $e) {
            $request->update(['admin_notes' => 'Deletion failed: ' . $e->getMessage()]);

            return false;
        }
    }

    /**
     * Export all user data.
     */
    public function exportUserData(User $user): array
    {
        return [
            'personal_information' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'bio' => $user->bio,
                'location' => $user->location,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
            'jobs' => $user->jobs()->get()->toArray(),
            'skills' => $user->skills()->get()->toArray(),
            'messages_sent' => $user->sentMessages()->get()->toArray(),
            'messages_received' => $user->receivedMessages()->get()->toArray(),
            'payments_made' => $user->paymentsMade()->get()->toArray(),
            'payments_received' => $user->paymentsReceived()->get()->toArray(),
            'reviews_given' => $user->reviewsGiven()->get()->toArray(),
            'reviews_received' => $user->reviewsReceived()->get()->toArray(),
            'consents' => $user->consents()->get()->toArray(),
            'saved_searches' => $user->savedSearches()->get()->toArray(),
        ];
    }

    /**
     * Clean up expired export files.
     */
    public function cleanupExpiredExports(): int
    {
        $expiredRequests = GdprRequest::expiredExports()->get();
        $count = 0;

        foreach ($expiredRequests as $request) {
            if ($request->export_file_path && Storage::exists($request->export_file_path)) {
                Storage::delete($request->export_file_path);
                $count++;
            }

            $request->update([
                'export_file_path' => null,
                'export_file_hash' => null,
            ]);
        }

        return $count;
    }

    /**
     * Get user's consent status.
     */
    public function getUserConsentStatus(User $user): array
    {
        $consents = UserConsent::forUser($user->id)->get()->groupBy('consent_type');
        $status = [];

        foreach (UserConsent::class::getConsentTypes() as $type) {
            $latestConsent = $consents->get($type)?->first();
            $status[$type] = [
                'granted' => $latestConsent?->isActive() ?? false,
                'granted_at' => $latestConsent?->granted_at,
                'version' => $latestConsent?->consent_version,
            ];
        }

        return $status;
    }

    /**
     * Record user consent.
     */
    public function recordConsent(
        User $user,
        string $consentType,
        string $consentVersion,
        bool $isGranted,
        array $metadata = []
    ): UserConsent {
        // Withdraw any existing consent for this type
        UserConsent::forUser($user->id)
            ->type($consentType)
            ->active()
            ->each(function ($consent) {
                $consent->withdraw('New consent recorded');
            });

        // Create new consent record
        return UserConsent::create([
            'user_id' => $user->id,
            'consent_type' => $consentType,
            'consent_version' => $consentVersion,
            'is_granted' => $isGranted,
            'granted_at' => $isGranted ? now() : null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'consent_method' => 'checkbox', // Default method
            'metadata' => $metadata,
        ]);
    }

    /**
     * Check if user has all required consents.
     */
    public function hasRequiredConsents(User $user): bool
    {
        return UserConsent::hasRequiredConsents($user->id);
    }

    /**
     * Create export file.
     */
    private function createExportFile(User $user, array $userData): string
    {
        $fileName = "user_data_export_{$user->id}_" . now()->format('Y-m-d_H-i-s');
        $jsonFile = "gdpr_exports/{$fileName}.json";
        $zipFile = "gdpr_exports/{$fileName}.zip";

        // Create JSON file
        Storage::put($jsonFile, json_encode($userData, JSON_PRETTY_PRINT));

        // Create ZIP file
        $zip = new ZipArchive();
        $zipPath = Storage::path($zipFile);

        if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
            $zip->addFile(Storage::path($jsonFile), 'user_data.json');

            // Add any user files (avatars, etc.)
            if ($user->avatar) {
                $avatarPath = Storage::path($user->avatar);
                if (file_exists($avatarPath)) {
                    $zip->addFile($avatarPath, 'avatar' . pathinfo($user->avatar, PATHINFO_EXTENSION));
                }
            }

            $zip->close();
        }

        // Clean up temporary JSON file
        Storage::delete($jsonFile);

        return $zipFile;
    }

    /**
     * Delete user data (anonymize or hard delete based on requirements).
     */
    private function deleteUserData(User $user): void
    {
        // Anonymize user data instead of hard delete to maintain referential integrity
        $user->update([
            'first_name' => 'Deleted',
            'last_name' => 'User',
            'email' => 'deleted_' . $user->id . '@example.com',
            'phone' => null,
            'bio' => null,
            'location' => null,
            'avatar' => null,
            'is_active' => false,
        ]);

        // Delete or anonymize related data
        $user->messages()->delete();
        $user->consents()->delete();
        $user->savedSearches()->delete();

        // Mark jobs and skills as deleted but keep for business records
        $user->jobs()->update(['status' => 'cancelled']);
        $user->skills()->update(['is_active' => false]);
    }

    /**
     * Send verification email for GDPR request.
     */
    private function sendVerificationEmail(GdprRequest $request): void
    {
        // In a real implementation, you would send an actual email
        // For now, we'll just log it
        \Log::info('GDPR verification email sent', [
            'user_id' => $request->user_id,
            'request_id' => $request->id,
            'token' => $request->verification_token,
        ]);
    }

    /**
     * Send export ready email.
     */
    private function sendExportReadyEmail(GdprRequest $request): void
    {
        // In a real implementation, you would send an actual email
        \Log::info('GDPR export ready email sent', [
            'user_id' => $request->user_id,
            'request_id' => $request->id,
        ]);
    }
}
