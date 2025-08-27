<?php

namespace App\Services;

use Illuminate\Http\Request;

class ApiVersionService
{
    /**
     * Supported API versions
     */
    public const SUPPORTED_VERSIONS = ['v1'];

    /**
     * Default API version
     */
    public const DEFAULT_VERSION = 'v1';

    /**
     * Current stable version
     */
    public const CURRENT_VERSION = 'v1';

    /**
     * Get the API version from request
     */
    public function getVersionFromRequest(Request $request): string
    {
        // Check Accept header first (preferred method)
        $acceptHeader = $request->header('Accept');
        if ($acceptHeader && preg_match('/application\/vnd\.talent2income\.v(\d+)\+json/', $acceptHeader, $matches)) {
            $version = 'v' . $matches[1];
            if (in_array($version, self::SUPPORTED_VERSIONS)) {
                return $version;
            }
        }

        // Check X-API-Version header
        $versionHeader = $request->header('X-API-Version');
        if ($versionHeader && in_array($versionHeader, self::SUPPORTED_VERSIONS)) {
            return $versionHeader;
        }

        // Check query parameter
        $versionParam = $request->query('version');
        if ($versionParam && in_array($versionParam, self::SUPPORTED_VERSIONS)) {
            return $versionParam;
        }

        // Return default version
        return self::DEFAULT_VERSION;
    }

    /**
     * Check if a version is supported
     */
    public function isVersionSupported(string $version): bool
    {
        return in_array($version, self::SUPPORTED_VERSIONS);
    }

    /**
     * Get version-specific response format
     */
    public function formatResponse(array $data, string $version): array
    {
        switch ($version) {
            case 'v1':
            default:
                return $this->formatV1Response($data);
        }
    }

    /**
     * Get deprecation notice for version
     */
    public function getDeprecationNotice(string $version): ?array
    {
        $deprecationMap = [
            // Future versions can be marked as deprecated here
            // 'v1' => [
            //     'message' => 'API v1 is deprecated. Please migrate to v2.',
            //     'sunset_date' => '2025-12-31',
            //     'migration_guide' => 'https://docs.talent2income.com/api/migration/v1-to-v2'
            // ]
        ];

        return $deprecationMap[$version] ?? null;
    }

    /**
     * Get all supported versions with their status
     */
    public function getSupportedVersions(): array
    {
        return [
            'v1' => [
                'status' => 'stable',
                'released' => '2024-01-01',
                'deprecated' => false,
                'sunset_date' => null,
            ],
        ];
    }

    /**
     * Format response for API v1
     */
    private function formatV1Response(array $data): array
    {
        return $data;
    }
}
