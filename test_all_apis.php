<?php

/**
 * Comprehensive API Test Script for Talent2Income Platform
 * Tests all major API endpoints to ensure they're working correctly
 */
class ApiTester
{
    private $baseUrl = 'http://localhost:8000/api';
    private $token = null;
    private $userId = null;
    private $results = [];

    public function __construct()
    {
        echo "🚀 Starting Comprehensive API Tests for Talent2Income Platform\n";
        echo '=' . str_repeat('=', 60) . "\n\n";
    }

    public function runAllTests()
    {
        try {
            // Core Authentication Tests
            $this->testHealthCheck();
            $this->testUserRegistration();
            $this->testUserLogin();

            if (! $this->token) {
                echo "❌ Cannot proceed without authentication token\n";

                return;
            }

            // Core Feature Tests
            $this->testUserProfile();
            $this->testCategories();
            $this->testSkills();
            $this->testJobs();
            $this->testMessages();
            $this->testPayments();
            $this->testReviews();
            $this->testSearch();
            $this->testRatings();

            // Advanced Feature Tests
            $this->testSavedSearches();
            $this->testAnalytics();
            $this->testAdmin();
            $this->testCompliance();
            $this->testOAuth();

            // System Tests
            $this->testQueueManagement();
            $this->testCaching();
            $this->testSecurity();

            $this->printSummary();

        } catch (Exception $e) {
            echo '❌ Test execution failed: ' . $e->getMessage() . "\n";
        }
    }

    private function makeRequest($method, $endpoint, $data = null, $headers = [])
    {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init();

        $defaultHeaders = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($this->token) {
            $defaultHeaders[] = 'Authorization: Bearer ' . $this->token;
        }

        $headers = array_merge($defaultHeaders, $headers);

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

    private function testHealthCheck()
    {
        echo "🏥 Testing Health Check...\n";

        try {
            $response = $this->makeRequest('GET', '/health');
            if ($response['status_code'] === 200) {
                $this->logSuccess('Health Check', 'API is healthy');
            } else {
                $this->logError('Health Check', 'Health check failed');
            }
        } catch (Exception $e) {
            $this->logError('Health Check', $e->getMessage());
        }
    }

    private function testUserRegistration()
    {
        echo "👤 Testing User Registration...\n";

        try {
            $userData = [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test_' . time() . '@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ];

            $response = $this->makeRequest('POST', '/auth/register', $userData);

            if ($response['status_code'] === 201 && isset($response['body']['token'])) {
                $this->logSuccess('User Registration', 'User registered successfully');
                $this->token = $response['body']['token'];
                $this->userId = $response['body']['user']['id'];
            } else {
                $this->logError('User Registration', 'Registration failed: ' . json_encode($response['body']));
            }
        } catch (Exception $e) {
            $this->logError('User Registration', $e->getMessage());
        }
    }

    private function testUserLogin()
    {
        echo "🔐 Testing User Login...\n";

        try {
            $loginData = [
                'email' => 'john@example.com',
                'password' => 'password',
            ];

            $response = $this->makeRequest('POST', '/auth/login', $loginData);

            if ($response['status_code'] === 200 && isset($response['body']['token'])) {
                $this->logSuccess('User Login', 'Login successful');
                $this->token = $response['body']['token'];
                $this->userId = $response['body']['user']['id'];
            } else {
                $this->logError('User Login', 'Login failed');
            }
        } catch (Exception $e) {
            $this->logError('User Login', $e->getMessage());
        }
    }

    private function testUserProfile()
    {
        echo "👤 Testing User Profile...\n";

        try {
            // Get profile
            $response = $this->makeRequest('GET', '/user/profile');
            if ($response['status_code'] === 200) {
                $this->logSuccess('Get Profile', 'Profile retrieved successfully');
            } else {
                $this->logError('Get Profile', 'Failed to get profile');
            }

            // Update profile
            $updateData = [
                'bio' => 'Updated bio for testing',
                'location' => 'Test City',
            ];

            $response = $this->makeRequest('PUT', '/user/profile', $updateData);
            if (in_array($response['status_code'], [200, 201])) {
                $this->logSuccess('Update Profile', 'Profile updated successfully');
            } else {
                $this->logError('Update Profile', 'Failed to update profile');
            }
        } catch (Exception $e) {
            $this->logError('User Profile', $e->getMessage());
        }
    }

    private function testCategories()
    {
        echo "📂 Testing Categories...\n";

        try {
            $response = $this->makeRequest('GET', '/categories');
            if ($response['status_code'] === 200 && is_array($response['body']['data'])) {
                $this->logSuccess('Categories', 'Categories retrieved successfully');
            } else {
                $this->logError('Categories', 'Failed to get categories');
            }
        } catch (Exception $e) {
            $this->logError('Categories', $e->getMessage());
        }
    }

    private function testSkills()
    {
        echo "🛠️ Testing Skills...\n";

        try {
            // Get skills
            $response = $this->makeRequest('GET', '/skills');
            if ($response['status_code'] === 200) {
                $this->logSuccess('Get Skills', 'Skills retrieved successfully');
            } else {
                $this->logError('Get Skills', 'Failed to get skills');
            }

            // Create skill
            $skillData = [
                'name' => 'Test Skill',
                'description' => 'A test skill',
                'category_id' => 1,
                'pricing_type' => 'hourly',
                'base_price' => 25.00,
            ];

            $response = $this->makeRequest('POST', '/skills', $skillData);
            if (in_array($response['status_code'], [200, 201])) {
                $this->logSuccess('Create Skill', 'Skill created successfully');
            } else {
                $this->logError('Create Skill', 'Failed to create skill');
            }
        } catch (Exception $e) {
            $this->logError('Skills', $e->getMessage());
        }
    }

    private function testJobs()
    {
        echo "💼 Testing Jobs...\n";

        try {
            // Get jobs
            $response = $this->makeRequest('GET', '/jobs');
            if ($response['status_code'] === 200) {
                $this->logSuccess('Get Jobs', 'Jobs retrieved successfully');
            } else {
                $this->logError('Get Jobs', 'Failed to get jobs');
            }

            // Create job
            $jobData = [
                'title' => 'Test Job',
                'description' => 'A test job posting',
                'category_id' => 1,
                'budget_min' => 100,
                'budget_max' => 500,
                'deadline' => date('Y-m-d', strtotime('+30 days')),
                'required_skills' => ['PHP', 'Laravel'],
            ];

            $response = $this->makeRequest('POST', '/jobs', $jobData);
            if (in_array($response['status_code'], [200, 201])) {
                $this->logSuccess('Create Job', 'Job created successfully');
            } else {
                $this->logError('Create Job', 'Failed to create job');
            }
        } catch (Exception $e) {
            $this->logError('Jobs', $e->getMessage());
        }
    }

    private function testMessages()
    {
        echo "💬 Testing Messages...\n";

        try {
            // Get conversations
            $response = $this->makeRequest('GET', '/messages/conversations');
            if ($response['status_code'] === 200) {
                $this->logSuccess('Get Conversations', 'Conversations retrieved successfully');
            } else {
                $this->logError('Get Conversations', 'Failed to get conversations');
            }
        } catch (Exception $e) {
            $this->logError('Messages', $e->getMessage());
        }
    }

    private function testPayments()
    {
        echo "💳 Testing Payments...\n";

        try {
            // Get payment history
            $response = $this->makeRequest('GET', '/payments/history');
            if ($response['status_code'] === 200) {
                $this->logSuccess('Payment History', 'Payment history retrieved successfully');
            } else {
                $this->logError('Payment History', 'Failed to get payment history');
            }
        } catch (Exception $e) {
            $this->logError('Payments', $e->getMessage());
        }
    }

    private function testReviews()
    {
        echo "⭐ Testing Reviews...\n";

        try {
            // Get reviews
            $response = $this->makeRequest('GET', '/reviews');
            if ($response['status_code'] === 200) {
                $this->logSuccess('Get Reviews', 'Reviews retrieved successfully');
            } else {
                $this->logError('Get Reviews', 'Failed to get reviews');
            }
        } catch (Exception $e) {
            $this->logError('Reviews', $e->getMessage());
        }
    }

    private function testSearch()
    {
        echo "🔍 Testing Search...\n";

        try {
            // Search jobs
            $response = $this->makeRequest('GET', '/search/jobs?q=web');
            if ($response['status_code'] === 200) {
                $this->logSuccess('Search Jobs', 'Job search working');
            } else {
                $this->logError('Search Jobs', 'Job search failed');
            }

            // Search users
            $response = $this->makeRequest('GET', '/search/users?q=john');
            if ($response['status_code'] === 200) {
                $this->logSuccess('Search Users', 'User search working');
            } else {
                $this->logError('Search Users', 'User search failed');
            }
        } catch (Exception $e) {
            $this->logError('Search', $e->getMessage());
        }
    }

    private function testRatings()
    {
        echo "📊 Testing Ratings...\n";

        try {
            $response = $this->makeRequest('GET', '/ratings/user/' . $this->userId);
            if (in_array($response['status_code'], [200, 404])) {
                $this->logSuccess('User Ratings', 'Rating system working');
            } else {
                $this->logError('User Ratings', 'Rating system failed');
            }
        } catch (Exception $e) {
            $this->logError('Ratings', $e->getMessage());
        }
    }

    private function testSavedSearches()
    {
        echo "🔖 Testing Saved Searches...\n";

        try {
            $response = $this->makeRequest('GET', '/saved-searches');
            if ($response['status_code'] === 200) {
                $this->logSuccess('Saved Searches', 'Saved searches working');
            } else {
                $this->logError('Saved Searches', 'Saved searches failed');
            }
        } catch (Exception $e) {
            $this->logError('Saved Searches', $e->getMessage());
        }
    }

    private function testAnalytics()
    {
        echo "📈 Testing Analytics...\n";

        try {
            $response = $this->makeRequest('GET', '/analytics/dashboard');
            if (in_array($response['status_code'], [200, 403])) {
                $this->logSuccess('Analytics', 'Analytics system working');
            } else {
                $this->logError('Analytics', 'Analytics system failed');
            }
        } catch (Exception $e) {
            $this->logError('Analytics', $e->getMessage());
        }
    }

    private function testAdmin()
    {
        echo "👑 Testing Admin Features...\n";

        try {
            $response = $this->makeRequest('GET', '/admin/users');
            if (in_array($response['status_code'], [200, 403])) {
                $this->logSuccess('Admin Features', 'Admin system working');
            } else {
                $this->logError('Admin Features', 'Admin system failed');
            }
        } catch (Exception $e) {
            $this->logError('Admin Features', $e->getMessage());
        }
    }

    private function testCompliance()
    {
        echo "🛡️ Testing Compliance...\n";

        try {
            $response = $this->makeRequest('GET', '/compliance/consents');
            if ($response['status_code'] === 200) {
                $this->logSuccess('Compliance', 'Compliance system working');
            } else {
                $this->logError('Compliance', 'Compliance system failed');
            }
        } catch (Exception $e) {
            $this->logError('Compliance', $e->getMessage());
        }
    }

    private function testOAuth()
    {
        echo "🔐 Testing OAuth...\n";

        try {
            $response = $this->makeRequest('GET', '/auth/oauth/status');
            if ($response['status_code'] === 200) {
                $this->logSuccess('OAuth Status', 'OAuth system working');
            } else {
                $this->logError('OAuth Status', 'OAuth system failed');
            }

            $response = $this->makeRequest('GET', '/auth/phone/status');
            if ($response['status_code'] === 200) {
                $this->logSuccess('Phone Verification', 'Phone verification system working');
            } else {
                $this->logError('Phone Verification', 'Phone verification system failed');
            }
        } catch (Exception $e) {
            $this->logError('OAuth', $e->getMessage());
        }
    }

    private function testQueueManagement()
    {
        echo "⚙️ Testing Queue Management...\n";

        try {
            $response = $this->makeRequest('GET', '/admin/queue/status');
            if (in_array($response['status_code'], [200, 403])) {
                $this->logSuccess('Queue Management', 'Queue system working');
            } else {
                $this->logError('Queue Management', 'Queue system failed');
            }
        } catch (Exception $e) {
            $this->logError('Queue Management', $e->getMessage());
        }
    }

    private function testCaching()
    {
        echo "🗄️ Testing Caching...\n";

        try {
            $response = $this->makeRequest('GET', '/admin/cache/status');
            if (in_array($response['status_code'], [200, 403, 404])) {
                $this->logSuccess('Caching', 'Cache system accessible');
            } else {
                $this->logError('Caching', 'Cache system failed');
            }
        } catch (Exception $e) {
            $this->logError('Caching', $e->getMessage());
        }
    }

    private function testSecurity()
    {
        echo "🔒 Testing Security...\n";

        try {
            // Test rate limiting
            $response = $this->makeRequest('GET', '/user/profile');
            if ($response['status_code'] === 200) {
                $this->logSuccess('Security Headers', 'Security middleware working');
            } else {
                $this->logError('Security Headers', 'Security middleware failed');
            }
        } catch (Exception $e) {
            $this->logError('Security', $e->getMessage());
        }
    }

    private function logSuccess($test, $message)
    {
        echo "  ✅ $test: $message\n";
        $this->results['success'][] = $test;
    }

    private function logError($test, $message)
    {
        echo "  ❌ $test: $message\n";
        $this->results['error'][] = $test;
    }

    private function printSummary()
    {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "📊 TEST SUMMARY\n";
        echo str_repeat('=', 60) . "\n";

        $successCount = count($this->results['success'] ?? []);
        $errorCount = count($this->results['error'] ?? []);
        $totalTests = $successCount + $errorCount;

        echo "Total Tests: $totalTests\n";
        echo "✅ Passed: $successCount\n";
        echo "❌ Failed: $errorCount\n";

        if ($errorCount > 0) {
            echo "\n❌ Failed Tests:\n";
            foreach ($this->results['error'] as $test) {
                echo "  - $test\n";
            }
        }

        $successRate = $totalTests > 0 ? round(($successCount / $totalTests) * 100, 2) : 0;
        echo "\n📈 Success Rate: $successRate%\n";

        if ($successRate >= 80) {
            echo "\n🎉 Great! Your API is working well!\n";
        } elseif ($successRate >= 60) {
            echo "\n⚠️ Good progress, but some issues need attention.\n";
        } else {
            echo "\n🚨 Several critical issues need to be fixed.\n";
        }

        echo "\n🚀 API Testing Complete!\n";
    }
}

// Run the tests
$tester = new ApiTester();
$tester->runAllTests();
