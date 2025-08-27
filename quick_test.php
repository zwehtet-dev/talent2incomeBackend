<?php

// Quick test for the fixed endpoints
$baseUrl = 'http://localhost:8000/api';

// Test health check
echo "Testing health check...\n";
$response = file_get_contents($baseUrl . '/health/detailed');
$health = json_decode($response, true);
echo 'Health Status: ' . $health['status'] . "\n";

// Get a token first
echo "\nGetting authentication token...\n";
$loginData = json_encode([
    'email' => 'john@example.com',
    'password' => 'password',
]);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $loginData,
    ],
]);

$response = file_get_contents($baseUrl . '/auth/login', false, $context);
$loginResult = json_decode($response, true);

if (isset($loginResult['token'])) {
    $token = $loginResult['token'];
    echo "Token acquired successfully\n";

    // Test search suggestions
    echo "\nTesting search suggestions...\n";
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer $token\r\nContent-Type: application/json\r\n",
        ],
    ]);

    $response = file_get_contents($baseUrl . '/search/suggestions?query=web', false, $context);
    $suggestions = json_decode($response, true);

    if (isset($suggestions['data'])) {
        echo 'Search suggestions working: ' . json_encode($suggestions['data']) . "\n";
    } else {
        echo 'Search suggestions failed: ' . json_encode($suggestions) . "\n";
    }
} else {
    echo 'Failed to get token: ' . json_encode($loginResult) . "\n";
}

echo "\nQuick test complete!\n";
