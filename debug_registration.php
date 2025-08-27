<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

try {
    echo "Testing user creation...\n";

    $userData = [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'debug_test_' . time() . '@example.com',
        'password' => 'password123',
    ];

    echo 'Creating user with data: ' . json_encode($userData) . "\n";

    $user = User::create($userData);

    echo "User created successfully!\n";
    echo 'User ID: ' . $user->id . "\n";
    echo 'User Email: ' . $user->email . "\n";

} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    echo 'File: ' . $e->getFile() . "\n";
    echo 'Line: ' . $e->getLine() . "\n";
    echo 'Trace: ' . $e->getTraceAsString() . "\n";
}
