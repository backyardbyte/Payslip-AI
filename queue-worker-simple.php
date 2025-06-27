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

// Set execution time and memory limits
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '512M');

// Log start time
$startTime = date('Y-m-d H:i:s');
echo "[$startTime] Starting Laravel Queue Worker...\n";

// Build the artisan command with correct PHP version
$phpBinary = '/opt/plesk/php/8.3/bin/php'; // Plesk PHP 8.3 path
if (!file_exists($phpBinary)) {
    $phpBinary = '/usr/bin/php8.3'; // Alternative path
}
if (!file_exists($phpBinary)) {
    $phpBinary = 'php'; // Fallback to system default
}

$command = $phpBinary . ' artisan queue:work';
$command .= ' --stop-when-empty';    // Exit when no jobs
$command .= ' --sleep=3';            // Sleep 3 seconds when no jobs
$command .= ' --tries=3';            // Max 3 attempts per job
$command .= ' --max-time=300';       // Max 5 minutes execution
$command .= ' --memory=512';         // 512MB memory limit
$command .= ' --timeout=120';        // 2 minutes timeout per job

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