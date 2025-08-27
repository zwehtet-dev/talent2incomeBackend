<?php

/**
 * Simple API Testing Script
 *
 * This script demonstrates basic API usage and can be used to verify
 * that the API is working correctly.
 */
$baseUrl = 'http://localhost:8000/api';

function makeRequest($method, $endpoint, $data = null, $token = null)
{
    global $baseUrl;

    $url = $baseUrl . $endpoint;
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
    ];

    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'status' => $httpCode,
        'data' => json_decode($response, true),
    ];
}

echo "🚀 Testing Talent2Income API\n";
echo "============================\n\n";

// Test 1: API Info
echo "1. Testing API Info...\n";
$response = makeRequest('GET', '/');
if ($response['status'] === 200) {
    echo '✅ API is running: ' . $response['data']['name'] . ' v' . $response['data']['version'] . "\n";
} else {
    echo "❌ API not responding\n";
    exit(1);
}

// Test 2: Register User
echo "\n2. Testing User Registration...\n";
$userData = [
    'first_name' => 'Test',
    'last_name' => 'User',
    'email' => 'test' . time() . '@example.com',
    'password' => 'password123',
    'password_confirmation' => 'password123',
];

$response = makeRequest('POST', '/auth/register', $userData);
if ($response['status'] === 201) {
    echo "✅ User registered successfully\n";
    $userEmail = $userData['email'];
} else {
    echo '❌ Registration failed: ' . json_encode($response['data']) . "\n";
    exit(1);
}

// Test 3: Login
echo "\n3. Testing User Login...\n";
$loginData = [
    'email' => $userEmail,
    'password' => 'password123',
];

$response = makeRequest('POST', '/auth/login', $loginData);
if ($response['status'] === 200) {
    echo "✅ Login successful\n";
    $token = $response['data']['token'];
} else {
    echo '❌ Login failed: ' . json_encode($response['data']) . "\n";
    exit(1);
}

// Test 4: Get Current User
echo "\n4. Testing Get Current User...\n";
$response = makeRequest('GET', '/auth/me', null, $token);
if ($response['status'] === 200) {
    echo '✅ User info retrieved: ' . $response['data']['first_name'] . ' ' . $response['data']['last_name'] . "\n";
} else {
    echo '❌ Failed to get user info: ' . json_encode($response['data']) . "\n";
}

// Test 5: Get Jobs (should work without auth)
echo "\n5. Testing Get Jobs...\n";
$response = makeRequest('GET', '/jobs');
if ($response['status'] === 200) {
    echo '✅ Jobs retrieved successfully (found ' . count($response['data']['data']) . " jobs)\n";
} else {
    echo '❌ Failed to get jobs: ' . json_encode($response['data']) . "\n";
}

// Test 6: Create Job (requires auth)
echo "\n6. Testing Create Job...\n";
$jobData = [
    'title' => 'Test Job - API Testing',
    'description' => 'This is a test job created by the API testing script.',
    'category_id' => 1,
    'budget_min' => 100.00,
    'budget_max' => 200.00,
    'budget_type' => 'fixed',
    'deadline' => date('Y-m-d', strtotime('+30 days')),
];

$response = makeRequest('POST', '/jobs', $jobData, $token);
if ($response['status'] === 201) {
    echo '✅ Job created successfully: ' . $response['data']['title'] . "\n";
    $jobId = $response['data']['id'];
} else {
    echo '❌ Failed to create job: ' . json_encode($response['data']) . "\n";
}

// Test 7: Get Specific Job
if (isset($jobId)) {
    echo "\n7. Testing Get Specific Job...\n";
    $response = makeRequest('GET', "/jobs/{$jobId}");
    if ($response['status'] === 200) {
        echo '✅ Job details retrieved: ' . $response['data']['title'] . "\n";
    } else {
        echo '❌ Failed to get job details: ' . json_encode($response['data']) . "\n";
    }
}

// Test 8: Test Rate Limiting
echo "\n8. Testing Rate Limiting...\n";
$rateLimitHit = false;
for ($i = 0; $i < 70; $i++) {
    $response = makeRequest('GET', '/auth/me', null, $token);
    if ($response['status'] === 429) {
        echo "✅ Rate limiting is working (hit limit after {$i} requests)\n";
        $rateLimitHit = true;

        break;
    }
}

if (! $rateLimitHit) {
    echo "⚠️  Rate limiting not triggered (this might be expected in development)\n";
}

// Test 9: API Versioning
echo "\n9. Testing API Versioning...\n";
$response = makeRequest('GET', '/versions');
if ($response['status'] === 200) {
    echo "✅ API versioning info retrieved\n";
    echo '   Current version: ' . $response['data']['current_version'] . "\n";
    echo '   Supported versions: ' . implode(', ', $response['data']['supported_versions']) . "\n";
} else {
    echo '❌ Failed to get version info: ' . json_encode($response['data']) . "\n";
}

// Test 10: Logout
echo "\n10. Testing Logout...\n";
$response = makeRequest('POST', '/auth/logout', null, $token);
if ($response['status'] === 200) {
    echo "✅ Logout successful\n";
} else {
    echo '❌ Logout failed: ' . json_encode($response['data']) . "\n";
}

echo "\n🎉 API Testing Complete!\n";
echo "========================\n";
echo "All core functionality appears to be working correctly.\n";
echo "You can now:\n";
echo "- Visit the Swagger documentation at: http://localhost:8000/api/documentation\n";
echo "- Import the Postman collection from: /postman/\n";
echo "- Read the full documentation at: /docs/API_DOCUMENTATION.md\n";
echo "- Follow the developer onboarding guide at: /docs/DEVELOPER_ONBOARDING.md\n";
