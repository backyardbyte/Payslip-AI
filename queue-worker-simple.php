<?php

/**
 * Simple Laravel Queue Worker Script for Plesk
 * 
 * This script runs the Laravel queue worker using artisan command
 * and is designed to be run in Plesk scheduled tasks.
 */

// Set working directory to application root
$appRoot = __DIR__;
chdir($appRoot);

// Load environment variables from .env file
if (file_exists($appRoot . '/.env')) {
    $envContent = file_get_contents($appRoot . '/.env');
    $lines = explode("\n", $envContent);
    foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line) && strpos($line, '=') !== false && substr($line, 0, 1) !== '#') {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, '"\'');
            if (!empty($key)) {
                putenv("$key=$value");
            }
        }
    }
}

// Get configuration from environment
$queueTimeout = getenv('QUEUE_WORKER_TIMEOUT') ?: 300; // Default 5 minutes
$queueMemory = getenv('QUEUE_WORKER_MEMORY') ?: 512; // Default 512MB
$queueSleep = getenv('QUEUE_WORKER_SLEEP') ?: 3; // Default 3 seconds
$queueTries = getenv('QUEUE_WORKER_TRIES') ?: 3; // Default 3 tries
$phpBinaryPath = getenv('PHP_BINARY_PATH') ?: '';

// Set execution time and memory limits
set_time_limit($queueTimeout);
ini_set('memory_limit', $queueMemory . 'M');

// Log start time
$startTime = date('Y-m-d H:i:s');
echo "[$startTime] Starting Laravel Queue Worker...\n";
echo "Configuration: Timeout={$queueTimeout}s, Memory={$queueMemory}MB, Sleep={$queueSleep}s, Tries={$queueTries}\n";

// Determine PHP binary path
if (!empty($phpBinaryPath) && file_exists($phpBinaryPath)) {
    $phpBinary = $phpBinaryPath;
} else {
    // Try common PHP binary paths
    $phpPaths = [
        '/opt/plesk/php/8.3/bin/php',
        '/opt/plesk/php/8.2/bin/php',
        '/opt/plesk/php/8.1/bin/php',
        '/usr/bin/php8.3',
        '/usr/bin/php8.2',
        '/usr/bin/php8.1',
        '/usr/bin/php'
    ];
    
    $phpBinary = 'php'; // Default fallback
    foreach ($phpPaths as $path) {
        if (file_exists($path)) {
            $phpBinary = $path;
            break;
        }
    }
}

echo "Using PHP binary: $phpBinary\n";

// Build the artisan command
$command = $phpBinary . ' artisan queue:work';
$command .= ' --stop-when-empty';    // Exit when no jobs
$command .= ' --sleep=' . $queueSleep;  // Sleep time when no jobs
$command .= ' --tries=' . $queueTries;  // Max attempts per job
$command .= ' --max-time=' . $queueTimeout;  // Max execution time
$command .= ' --memory=' . $queueMemory;  // Memory limit
$command .= ' --timeout=' . intval($queueTimeout * 0.4);  // Job timeout (40% of total)

// Add environment handling
if (file_exists($appRoot . '/.env')) {
    // Production mode - suppress most output
    $command .= ' --quiet';
}

echo "Executing: $command\n";

// Execute the command and capture output
$output = [];
$returnCode = 0;

exec($command . ' 2>&1', $output, $returnCode);

// Process results
$endTime = date('Y-m-d H:i:s');
echo "[$endTime] Queue worker finished with return code: $returnCode\n";

if (!empty($output)) {
    echo "Output:\n";
    foreach ($output as $line) {
        echo "  $line\n";
    }
}

// Write simple log entry
$logMessage = "[$startTime to $endTime] Queue worker executed (code: $returnCode)\n";
file_put_contents($appRoot . '/storage/logs/queue-worker.log', $logMessage, FILE_APPEND | LOCK_EX);

// Return appropriate exit code
exit($returnCode); 