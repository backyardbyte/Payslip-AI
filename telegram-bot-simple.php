<?php

/**
 * Simple Telegram Bot Script for Plesk
 * 
 * This script manages Telegram bot operations using artisan commands
 * and is designed to be run in Plesk scheduled tasks.
 */

// Set working directory to application root
$appRoot = __DIR__;
chdir($appRoot);

// Set execution time and memory limits
set_time_limit(600); // 10 minutes max
ini_set('memory_limit', '256M');

// Get action from command line argument or default to 'poll'
$action = $argv[1] ?? 'poll';

// Log start time
$startTime = date('Y-m-d H:i:s');
echo "[$startTime] Starting Telegram Bot ($action)...\n";

// Check if .env file exists
if (!file_exists($appRoot . '/.env')) {
    echo "ERROR: .env file not found. Please configure your environment first.\n";
    exit(1);
}

$command = '';
$logFile = $appRoot . '/storage/logs/telegram-bot.log';

switch ($action) {
    case 'poll':
    case 'polling':
        // Use artisan command for polling with correct PHP version
        $phpBinary = '/opt/plesk/php/8.3/bin/php'; // Plesk PHP 8.3 path
        if (!file_exists($phpBinary)) {
            $phpBinary = '/usr/bin/php8.3'; // Alternative path
        }
        if (!file_exists($phpBinary)) {
            $phpBinary = 'php'; // Fallback to system default
        }
        $command = $phpBinary . ' artisan telegram:bot run';
        echo "Running Telegram bot with polling...\n";
        break;
        
    case 'webhook':
        // Use artisan command for webhook setup
        $phpBinary = '/opt/plesk/php/8.3/bin/php'; // Plesk PHP 8.3 path
        if (!file_exists($phpBinary)) {
            $phpBinary = '/usr/bin/php8.3'; // Alternative path
        }
        if (!file_exists($phpBinary)) {
            $phpBinary = 'php'; // Fallback to system default
        }
        $command = $phpBinary . ' artisan telegram:bot webhook';
        echo "Setting up Telegram webhook...\n";
        break;
        
    case 'setup':
        // Use artisan command for setup
        $phpBinary = '/opt/plesk/php/8.3/bin/php'; // Plesk PHP 8.3 path
        if (!file_exists($phpBinary)) {
            $phpBinary = '/usr/bin/php8.3'; // Alternative path
        }
        if (!file_exists($phpBinary)) {
            $phpBinary = 'php'; // Fallback to system default
        }
        $command = $phpBinary . ' artisan telegram:bot setup';
        echo "Setting up Telegram bot...\n";
        break;
        
    case 'test':
        // Test bot configuration
        echo "Testing Telegram bot configuration...\n";
        
        // Check if bot token is set
        $envContent = file_get_contents($appRoot . '/.env');
        if (strpos($envContent, 'TELEGRAM_BOT_TOKEN=') === false || 
            preg_match('/TELEGRAM_BOT_TOKEN=\s*$/', $envContent)) {
            echo "❌ TELEGRAM_BOT_TOKEN not configured in .env file\n";
            echo "Please add your bot token to .env file:\n";
            echo "TELEGRAM_BOT_TOKEN=your_bot_token_here\n";
            exit(1);
        }
        
        echo "✅ TELEGRAM_BOT_TOKEN is configured\n";
        
        // Test artisan command
        $phpBinary = '/opt/plesk/php/8.3/bin/php'; // Plesk PHP 8.3 path
        if (!file_exists($phpBinary)) {
            $phpBinary = '/usr/bin/php8.3'; // Alternative path
        }
        if (!file_exists($phpBinary)) {
            $phpBinary = 'php'; // Fallback to system default
        }
        $testCommand = $phpBinary . ' artisan telegram:bot setup 2>&1';
        exec($testCommand, $testOutput, $testReturn);
        
        if ($testReturn === 0) {
            echo "✅ Telegram bot commands are working\n";
        } else {
            echo "❌ Telegram bot test failed:\n";
            foreach ($testOutput as $line) {
                echo "  $line\n";
            }
            exit(1);
        }
        
        $logMessage = "[$startTime] Telegram bot test completed successfully\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        echo "✅ Telegram bot test completed successfully!\n";
        exit(0);
        
    case 'status':
        // Check bot status
        echo "Checking Telegram bot status...\n";
        
        // Check recent logs
        if (file_exists($logFile)) {
            echo "Recent log entries:\n";
            $logs = file($logFile);
            $recentLogs = array_slice($logs, -10); // Last 10 entries
            foreach ($recentLogs as $log) {
                echo "  " . trim($log) . "\n";
            }
        } else {
            echo "No log file found at: $logFile\n";
        }
        
        exit(0);
        
    default:
        echo "Unknown action: $action\n";
        echo "Available actions: poll, webhook, setup, test, status\n";
        exit(1);
}

if (empty($command)) {
    echo "No command to execute\n";
    exit(1);
}

echo "Executing: $command\n";

// Execute the command and capture output
$output = [];
$returnCode = 0;

// For polling, we want to limit execution time
if ($action === 'poll' || $action === 'polling') {
    // Run with timeout to prevent hanging
    $command = "timeout 300 $command"; // 5 minutes max
}

exec($command . ' 2>&1', $output, $returnCode);

// Process results
$endTime = date('Y-m-d H:i:s');
echo "[$endTime] Telegram bot ($action) finished with return code: $returnCode\n";

if (!empty($output)) {
    echo "Output:\n";
    foreach ($output as $line) {
        echo "  $line\n";
    }
}

// Write log entry
$logMessage = "[$startTime to $endTime] Telegram bot ($action) executed (code: $returnCode)\n";
if (!empty($output)) {
    $logMessage .= "Output: " . implode(' | ', $output) . "\n";
}
file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);

// Return appropriate exit code
exit($returnCode); 