<?php

declare(strict_types=1);

/**
 * Check if code coverage driver is available and run coverage tests
 */
echo "Checking for code coverage drivers...\n";

$hasXdebug = extension_loaded('xdebug');
$hasPcov = extension_loaded('pcov');

if ($hasXdebug) {
    echo "✓ Xdebug extension found\n";
    $driver = 'Xdebug';
} elseif ($hasPcov) {
    echo "✓ PCOV extension found\n";
    $driver = 'PCOV';
} else {
    echo "✗ No code coverage driver available\n";
    echo "Install Xdebug or PCOV to enable code coverage reporting\n";
    echo "\nRunning tests without coverage...\n";
    passthru('./vendor/bin/pest --parallel');
    exit(0);
}

echo "Using {$driver} for code coverage\n";
echo "Running tests with coverage (minimum 80%)...\n";

// Run tests with coverage
$command = './vendor/bin/pest --parallel --coverage --min=80';
$exitCode = 0;
passthru($command, $exitCode);

if ($exitCode === 0) {
    echo "\n✓ All tests passed with sufficient coverage!\n";
} else {
    echo "\n✗ Tests failed or coverage below 80%\n";
}

exit($exitCode);
