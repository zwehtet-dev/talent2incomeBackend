<?php

use App\Models\AuditLog;
use App\Models\GdprRequest;
use App\Models\SecurityIncident;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\AuditService;
use App\Services\ComplianceService;
use App\Services\GdprService;
use App\Services\SecurityIncidentService;

test('audit log creation works', function () {
    $user = User::factory()->create();
    $auditService = app(AuditService::class);

    $log = $auditService->log(
        'test.event',
        $user,
        null,
        ['test' => 'data'],
        'Test audit log',
        'info',
        false
    );

    expect($log)->toBeInstanceOf(AuditLog::class);
    expect($log->event_type)->toBe('test.event');
    expect($log->auditable_id)->toBe($user->id);
    expect($log->auditable_type)->toBe(get_class($user));
    expect($log->hash)->not->toBeNull();
});

test('audit log integrity verification works', function () {
    $user = User::factory()->create();
    $auditService = app(AuditService::class);

    $log = $auditService->log('test.event', $user);

    // Verify integrity
    expect($auditService->verifyIntegrity($log))->toBeTrue();

    // Tamper with the log
    $log->update(['description' => 'tampered']);

    // Integrity should fail
    expect($auditService->verifyIntegrity($log))->toBeFalse();
});

test('gdpr request creation works', function () {
    $user = User::factory()->create();
    $gdprService = app(GdprService::class);

    $request = $gdprService->createRequest(
        $user,
        GdprRequest::TYPE_EXPORT,
        'I want to export my data'
    );

    expect($request)->toBeInstanceOf(GdprRequest::class);
    expect($request->user_id)->toBe($user->id);
    expect($request->request_type)->toBe(GdprRequest::TYPE_EXPORT);
    expect($request->status)->toBe(GdprRequest::STATUS_PENDING);
    expect($request->verification_token)->not->toBeNull();
});

test('user consent management works', function () {
    $user = User::factory()->create();
    $gdprService = app(GdprService::class);

    // Record consent
    $consent = $gdprService->recordConsent(
        $user,
        UserConsent::TYPE_PRIVACY_POLICY,
        '1.0',
        true
    );

    expect($consent)->toBeInstanceOf(UserConsent::class);
    expect($consent->isActive())->toBeTrue();
    expect($user->hasConsent(UserConsent::TYPE_PRIVACY_POLICY))->toBeTrue();

    // Withdraw consent
    $user->withdrawConsent(UserConsent::TYPE_PRIVACY_POLICY, 'User requested');

    expect($user->hasConsent(UserConsent::TYPE_PRIVACY_POLICY))->toBeFalse();
});

test('security incident creation works', function () {
    $securityService = app(SecurityIncidentService::class);

    $incident = $securityService->createIncident(
        SecurityIncident::TYPE_BRUTE_FORCE,
        'Brute force attack detected',
        'Multiple failed login attempts',
        SecurityIncident::SEVERITY_HIGH
    );

    expect($incident)->toBeInstanceOf(SecurityIncident::class);
    expect($incident->incident_type)->toBe(SecurityIncident::TYPE_BRUTE_FORCE);
    expect($incident->severity)->toBe(SecurityIncident::SEVERITY_HIGH);
    expect($incident->status)->toBe(SecurityIncident::STATUS_OPEN);
});

test('compliance status check works', function () {
    $complianceService = app(ComplianceService::class);

    $status = $complianceService->checkComplianceStatus();

    expect($status)->toBeArray();
    expect($status)->toHaveKeys(['gdpr_compliance', 'audit_compliance', 'security_compliance', 'data_retention_compliance']);
});

test('admin can access compliance endpoints', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/compliance/status');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'compliance_status' => [
                'gdpr_compliance',
                'audit_compliance',
                'security_compliance',
                'data_retention_compliance',
            ],
            'last_updated',
        ]);
});

test('regular user cannot access admin compliance endpoints', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/admin/compliance/status');

    $response->assertStatus(403);
});

test('user can create gdpr request', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/gdpr/requests', [
            'request_type' => 'export',
            'description' => 'I want to export my data',
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'request' => [
                'id',
                'request_type',
                'status',
                'description',
            ],
        ]);
});

test('user can record consent', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/gdpr/consent', [
            'consent_type' => 'privacy_policy',
            'consent_version' => '1.0',
            'is_granted' => true,
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'consent' => [
                'id',
                'consent_type',
                'is_granted',
                'granted_at',
            ],
        ]);
});
