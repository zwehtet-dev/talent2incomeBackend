<?php

/**
 * Comprehensive API Testing and Optimization Script
 * Tests all endpoints, validates responses, and provides optimization recommendations
 */
class ComprehensiveApiTester
{
    private $baseUrl = 'http://localhost:8000/api';
    private $token = null;
    private $userId = null;
    private $results = [];
    private $performanceMetrics = [];

    public function __construct()
    {
        echo "🚀 Comprehensive API Testing & Optimization for Talent2Income\n";
        echo '=' . str_repeat('=', 65) . "\n\n";
    }

    public function runComprehensiveTests()
    {
        try {
            // Phase 1: Core Authentication & Setup
            $this->testHealthAndSetup();
            $this->authenticateUser();

            if (! $this->token) {
                echo "❌ Cannot proceed without authentication. Stopping tests.\n";

                return;
            }

            // Phase 2: Core API Functionality
            $this->testCoreApis();

            // Phase 3: Advanced Features
            $this->testAdvancedFeatures();

            // Phase 4: Security & Performance
            $this->testSecurityFeatures();
            $this->testPerformance();

            // Phase 5: Generate Report
            $this->generateComprehensiveReport();

        } catch (Exception $e) {
            echo '❌ Critical error during testing: ' . $e->getMessage() . "\n";
        }
    }

    private function testHealthAndSetup()
    {
        echo "🏥 Phase 1: Health Check & Setup\n";
        echo '-' . str_repeat('-', 40) . "\n";

        // Test health endpoints
        $this->testEndpoint('GET', '/health', [], 'Health Check');
        $this->testEndpoint('GET', '/health/detailed', [], 'Detailed Health Check');
        $this->testEndpoint('GET', '/', [], 'API Info');
        $this->testEndpoint('GET', '/versions', [], 'API Versions');

        echo "\n";
    }

    private function authenticateUser()
    {
        echo "🔐 Phase 2: Authentication Setup\n";
        echo '-' . str_repeat('-', 40) . "\n";

        // Try to register a new user
        $userData = [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'comprehensive_test_' . time() . '@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->testEndpoint('POST', '/auth/register', $userData, 'User Registration');

        if ($response && isset($response['body']['token'])) {
            $this->token = $response['body']['token'];
            $this->userId = $response['body']['user']['id'];
            echo "✅ Authentication successful - Token acquired\n";
        } else {
            // Try login with existing user
            $loginData = [
                'email' => 'john@example.com',
                'password' => 'password',
            ];

            $response = $this->testEndpoint('POST', '/auth/login', $loginData, 'User Login');

            if ($response && isset($response['body']['token'])) {
                $this->token = $response['body']['token'];
                $this->userId = $response['body']['user']['id'];
                echo "✅ Login successful - Token acquired\n";
            }
        }

        echo "\n";
    }

    private function testCoreApis()
    {
        echo "🔧 Phase 3: Core API Functionality\n";
        echo '-' . str_repeat('-', 40) . "\n";

        // User Management
        $this->testEndpoint('GET', '/auth/me', [], 'Get Current User', true);
        $this->testEndpoint('GET', '/users/profile', [], 'Get User Profile', true);

        // Categories
        $this->testEndpoint('GET', '/categories', [], 'Get Categories', true);
        $this->testEndpoint('GET', '/categories/with-skill-counts', [], 'Categories with Skill Counts', true);

        // Skills
        $this->testEndpoint('GET', '/skills', [], 'Get Skills', true);
        $this->testEndpoint('GET', '/skills/my-skills', [], 'Get My Skills', true);

        // Jobs
        $this->testEndpoint('GET', '/jobs', [], 'Get Jobs', true);
        $this->testEndpoint('GET', '/jobs/my-jobs', [], 'Get My Jobs', true);

        // Messages
        $this->testEndpoint('GET', '/messages/conversations', [], 'Get Conversations', true);
        $this->testEndpoint('GET', '/messages/unread-count', [], 'Get Unread Count', true);

        // Reviews
        $this->testEndpoint('GET', '/reviews', [], 'Get Reviews');

        echo "\n";
    }

    private function testAdvancedFeatures()
    {
        echo "🚀 Phase 4: Advanced Features\n";
        echo '-' . str_repeat('-', 40) . "\n";

        // Search functionality
        $this->testEndpoint('GET', '/search/jobs?q=web', [], 'Job Search', true);
        $this->testEndpoint('GET', '/search/suggestions', [], 'Search Suggestions', true);

        // Ratings
        $this->testEndpoint('GET', '/ratings/my-stats', [], 'My Rating Stats', true);
        $this->testEndpoint('GET', '/ratings/top-rated', [], 'Top Rated Users', true);

        // Saved Searches
        $this->testEndpoint('GET', '/saved-searches', [], 'Saved Searches', true);

        // OAuth Status
        $this->testEndpoint('GET', '/auth/oauth/status', [], 'OAuth Status', true);
        $this->testEndpoint('GET', '/auth/phone/status', [], 'Phone Verification Status', true);

        echo "\n";
    }

    private function testSecurityFeatures()
    {
        echo "🔒 Phase 5: Security Features\n";
        echo '-' . str_repeat('-', 40) . "\n";

        // Test rate limiting
        $this->testRateLimiting();

        // Test authentication requirements
        $this->testAuthenticationRequirements();

        // Test input validation
        $this->testInputValidation();

        echo "\n";
    }

    private function testPerformance()
    {
        echo "⚡ Phase 6: Performance Testing\n";
        echo '-' . str_repeat('-', 40) . "\n";

        // Test response times for key endpoints
        $keyEndpoints = [
            '/health',
            '/categories',
            '/skills',
            '/jobs',
            '/auth/me',
        ];

        foreach ($keyEndpoints as $endpoint) {
            $this->measurePerformance($endpoint);
        }

        echo "\n";
    }

    private function testEndpoint($method, $endpoint, $data = [], $description = '', $requiresAuth = false)
    {
        $startTime = microtime(true);

        try {
            $response = $this->makeRequest($method, $endpoint, $data, $requiresAuth);
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            // Store performance metrics
            $this->performanceMetrics[$endpoint] = $responseTime;

            if ($response['status_code'] >= 200 && $response['status_code'] < 300) {
                echo "  ✅ {$description}: {$response['status_code']} ({$responseTime}ms)\n";
                $this->results['success'][] = $description;

                return $response;
            } else {
                echo "  ❌ {$description}: {$response['status_code']} ({$responseTime}ms)\n";
                $this->results['error'][] = $description;

                return;
            }

        } catch (Exception $e) {
            echo "  ❌ {$description}: Exception - {$e->getMessage()}\n";
            $this->results['error'][] = $description;

            return;
        }
    }

    private function makeRequest($method, $endpoint, $data = [], $requiresAuth = false)
    {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init();

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($requiresAuth && $this->token) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: $error");
        }

        return [
            'status_code' => $httpCode,
            'body' => json_decode($response, true),
            'raw_body' => $response,
        ];
    }

    private function testRateLimiting()
    {
        echo "  🛡️ Testing Rate Limiting...\n";

        // Make multiple rapid requests to test rate limiting
        $rapidRequests = 0;
        for ($i = 0; $i < 10; $i++) {
            $response = $this->makeRequest('GET', '/health');
            if ($response['status_code'] === 200) {
                $rapidRequests++;
            } elseif ($response['status_code'] === 429) {
                echo "  ✅ Rate limiting active (triggered after {$rapidRequests} requests)\n";

                return;
            }
        }

        echo "  ⚠️ Rate limiting may not be properly configured\n";
    }

    private function testAuthenticationRequirements()
    {
        echo "  🔐 Testing Authentication Requirements...\n";

        // Test protected endpoint without token
        $response = $this->makeRequest('GET', '/users/profile');

        if ($response['status_code'] === 401) {
            echo "  ✅ Authentication properly required for protected endpoints\n";
        } else {
            echo "  ❌ Protected endpoints may be accessible without authentication\n";
        }
    }

    private function testInputValidation()
    {
        echo "  ✅ Input validation testing (basic)\n";

        // Test invalid registration data
        $invalidData = [
            'first_name' => '',
            'email' => 'invalid-email',
            'password' => '123',
        ];

        $response = $this->makeRequest('POST', '/auth/register', $invalidData);

        if ($response['status_code'] === 422) {
            echo "  ✅ Input validation working correctly\n";
        } else {
            echo "  ⚠️ Input validation may need improvement\n";
        }
    }

    private function measurePerformance($endpoint)
    {
        $times = [];

        // Make 5 requests to get average response time
        for ($i = 0; $i < 5; $i++) {
            $startTime = microtime(true);
            $this->makeRequest('GET', $endpoint, [], $endpoint === '/auth/me');
            $times[] = (microtime(true) - $startTime) * 1000;
        }

        $avgTime = round(array_sum($times) / count($times), 2);
        $minTime = round(min($times), 2);
        $maxTime = round(max($times), 2);

        echo "  📊 {$endpoint}: avg {$avgTime}ms (min: {$minTime}ms, max: {$maxTime}ms)\n";

        if ($avgTime > 1000) {
            echo "  ⚠️ Slow response time detected\n";
        } elseif ($avgTime < 100) {
            echo "  ✅ Excellent response time\n";
        }
    }

    private function generateComprehensiveReport()
    {
        echo "\n" . str_repeat('=', 65) . "\n";
        echo "📊 COMPREHENSIVE API TEST REPORT\n";
        echo str_repeat('=', 65) . "\n";

        $successCount = count($this->results['success'] ?? []);
        $errorCount = count($this->results['error'] ?? []);
        $totalTests = $successCount + $errorCount;

        echo "\n📈 OVERALL RESULTS:\n";
        echo "Total Tests: {$totalTests}\n";
        echo "✅ Passed: {$successCount}\n";
        echo "❌ Failed: {$errorCount}\n";

        $successRate = $totalTests > 0 ? round(($successCount / $totalTests) * 100, 2) : 0;
        echo "📊 Success Rate: {$successRate}%\n";

        echo "\n⚡ PERFORMANCE ANALYSIS:\n";
        if (! empty($this->performanceMetrics)) {
            $avgResponseTime = round(array_sum($this->performanceMetrics) / count($this->performanceMetrics), 2);
            $slowestEndpoint = array_keys($this->performanceMetrics, max($this->performanceMetrics))[0];
            $fastestEndpoint = array_keys($this->performanceMetrics, min($this->performanceMetrics))[0];

            echo "Average Response Time: {$avgResponseTime}ms\n";
            echo "Fastest Endpoint: {$fastestEndpoint} (" . $this->performanceMetrics[$fastestEndpoint] . "ms)\n";
            echo "Slowest Endpoint: {$slowestEndpoint} (" . $this->performanceMetrics[$slowestEndpoint] . "ms)\n";
        }

        echo "\n🔧 OPTIMIZATION RECOMMENDATIONS:\n";
        $this->generateOptimizationRecommendations($successRate);

        if ($errorCount > 0) {
            echo "\n❌ FAILED TESTS:\n";
            foreach ($this->results['error'] as $test) {
                echo "  - {$test}\n";
            }
        }

        echo "\n🎯 FINAL ASSESSMENT:\n";
        if ($successRate >= 90) {
            echo "🎉 EXCELLENT! Your API is production-ready with minimal issues.\n";
        } elseif ($successRate >= 75) {
            echo "✅ GOOD! Your API is mostly functional with some areas for improvement.\n";
        } elseif ($successRate >= 50) {
            echo "⚠️ FAIR! Your API has core functionality but needs attention in several areas.\n";
        } else {
            echo "🚨 NEEDS WORK! Several critical issues need to be addressed before production.\n";
        }

        echo "\n🚀 API Testing Complete!\n";
    }

    private function generateOptimizationRecommendations($successRate)
    {
        if (! empty($this->performanceMetrics)) {
            $avgTime = array_sum($this->performanceMetrics) / count($this->performanceMetrics);

            if ($avgTime > 500) {
                echo "  • Consider implementing response caching for frequently accessed endpoints\n";
                echo "  • Optimize database queries and add proper indexing\n";
                echo "  • Consider using a CDN for static assets\n";
            }

            if ($avgTime > 200) {
                echo "  • Review and optimize slow database queries\n";
                echo "  • Implement pagination for large data sets\n";
            }
        }

        if ($successRate < 80) {
            echo "  • Fix failing authentication and authorization issues\n";
            echo "  • Ensure all required routes are properly defined\n";
            echo "  • Implement proper error handling for all endpoints\n";
        }

        echo "  • Implement comprehensive API documentation with Swagger\n";
        echo "  • Add comprehensive logging for debugging and monitoring\n";
        echo "  • Consider implementing API versioning for future updates\n";
        echo "  • Set up monitoring and alerting for production deployment\n";
    }
}

// Run the comprehensive tests
$tester = new ComprehensiveApiTester();
$tester->runComprehensiveTests();
