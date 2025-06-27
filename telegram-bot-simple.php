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
$telegramTimeout = getenv('TELEGRAM_BOT_TIMEOUT') ?: 600; // Default 10 minutes
$telegramMemory = getenv('TELEGRAM_BOT_MEMORY') ?: 256; // Default 256MB
$phpBinaryPath = getenv('PHP_BINARY_PATH') ?: '';

// Set execution time and memory limits
set_time_limit($telegramTimeout);
ini_set('memory_limit', $telegramMemory . 'M');

// Get action from command line argument or default to 'poll'
$action = $argv[1] ?? 'poll';

// Log start time
$startTime = date('Y-m-d H:i:s');
echo "[$startTime] Starting Telegram Bot ($action)...\n";
echo "Configuration: Timeout={$telegramTimeout}s, Memory={$telegramMemory}MB\n";

// Check if .env file exists
if (!file_exists($appRoot . '/.env')) {
    echo "ERROR: .env file not found. Please configure your environment first.\n";
    exit(1);
}

$command = '';
$logFile = $appRoot . '/storage/logs/telegram-bot.log';

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

switch ($action) {
    case 'poll':
    case 'polling':
        // Use artisan command for polling
        $command = $phpBinary . ' artisan telegram:bot run';
        echo "Running Telegram bot with polling...\n";
        break;
        
    case 'webhook':
        // Use artisan command for webhook setup
        $command = $phpBinary . ' artisan telegram:bot webhook';
        echo "Setting up Telegram webhook...\n";
        break;
        
    case 'setup':
        // Use artisan command for setup
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
    // Run with timeout (use half of script timeout to allow for cleanup)
    $commandTimeout = intval($telegramTimeout / 2);
    $command = "timeout $commandTimeout $command";
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