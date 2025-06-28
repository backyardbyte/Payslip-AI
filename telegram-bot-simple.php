<?php

/**
 * Simple Telegram Bot Script for Plesk
 * 
 * This script manages Telegram bot operations using artisan commands
 * and is designed to be run in Plesk scheduled tasks or as a daemon.
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
$telegramTimeout = getenv('TELEGRAM_BOT_TIMEOUT') ?: 300; // Default 5 minutes per cycle
$telegramMemory = getenv('TELEGRAM_BOT_MEMORY') ?: 256; // Default 256MB
$phpBinaryPath = getenv('PHP_BINARY_PATH') ?: '';
$maxRestarts = getenv('TELEGRAM_BOT_MAX_RESTARTS') ?: 50; // Maximum restarts before giving up
$restartDelay = getenv('TELEGRAM_BOT_RESTART_DELAY') ?: 5; // Seconds to wait before restart

// Set memory limit (but NOT execution time limit for continuous operation)
ini_set('memory_limit', $telegramMemory . 'M');

// Get action from command line argument or default to 'poll'
$action = $argv[1] ?? 'poll';

// Log start time
$startTime = date('Y-m-d H:i:s');
echo "[$startTime] Starting Telegram Bot ($action)...\n";
echo "Configuration: Memory={$telegramMemory}MB, MaxRestarts={$maxRestarts}\n";

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

/**
 * Function to write log messages
 */
function writeLog($message, $logFile) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    echo $logMessage;
}

/**
 * Function to run continuous polling with error recovery
 */
function runContinuousPolling($phpBinary, $logFile, $telegramTimeout, $maxRestarts, $restartDelay) {
    $restartCount = 0;
    
    writeLog("Starting continuous Telegram bot polling...", $logFile);
    
    while ($restartCount < $maxRestarts) {
        writeLog("Bot cycle #" . ($restartCount + 1) . " starting...", $logFile);
        
        // Set execution time limit for this cycle only
        set_time_limit($telegramTimeout + 60); // Add buffer time
        
        $command = $phpBinary . ' artisan telegram:bot run';
        
        // Execute command
        $output = [];
        $returnCode = 0;
        
        $cycleStart = time();
        exec($command . ' 2>&1', $output, $returnCode);
        $cycleEnd = time();
        $cycleDuration = $cycleEnd - $cycleStart;
        
        writeLog("Bot cycle #" . ($restartCount + 1) . " completed in {$cycleDuration}s with return code: $returnCode", $logFile);
        
        if (!empty($output)) {
            $outputStr = implode(' | ', array_slice($output, -5)); // Last 5 lines only
            writeLog("Output: $outputStr", $logFile);
        }
        
        // Check if we should restart
        if ($returnCode === 0 && $cycleDuration >= ($telegramTimeout - 30)) {
            // Normal completion, restart immediately
            writeLog("Normal cycle completion, restarting...", $logFile);
        } elseif ($returnCode !== 0) {
            // Error occurred, increment restart count and add delay
            $restartCount++;
            writeLog("Error occurred (code: $returnCode), restart #{$restartCount} in {$restartDelay}s...", $logFile);
            
            if ($restartCount >= $maxRestarts) {
                writeLog("Maximum restart limit reached ({$maxRestarts}). Stopping bot.", $logFile);
                exit(1);
            }
            
            sleep($restartDelay);
        } else {
            // Unexpected short completion, might be configuration issue
            writeLog("Unexpected short completion ({$cycleDuration}s), restarting in {$restartDelay}s...", $logFile);
            sleep($restartDelay);
        }
        
        // Reset execution time limit for next iteration
        set_time_limit(0);
    }
    
    writeLog("Bot stopped after {$maxRestarts} restarts", $logFile);
}

switch ($action) {
    case 'poll':
    case 'polling':
        // Remove execution time limit for continuous operation
        set_time_limit(0);
        
        echo "Running Telegram bot with continuous polling...\n";
        echo "To stop the bot, use Ctrl+C or kill the process\n";
        echo "Bot will restart automatically on errors (max {$maxRestarts} restarts)\n\n";
        
        // Set up signal handlers for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, function() use ($logFile) {
                writeLog("Received SIGTERM, shutting down gracefully...", $logFile);
                exit(0);
            });
            pcntl_signal(SIGINT, function() use ($logFile) {
                writeLog("Received SIGINT (Ctrl+C), shutting down gracefully...", $logFile);
                exit(0);
            });
        }
        
        runContinuousPolling($phpBinary, $logFile, $telegramTimeout, $maxRestarts, $restartDelay);
        break;
        
    case 'poll-once':
        // Single poll cycle (original behavior)
        set_time_limit($telegramTimeout);
        $command = $phpBinary . ' artisan telegram:bot run';
        echo "Running single Telegram bot polling cycle...\n";
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
        
        writeLog("Telegram bot test completed successfully", $logFile);
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
        
    case 'daemon':
        // Run as daemon (background process)
        echo "Starting Telegram bot as daemon...\n";
        
        // Check if already running
        $pidFile = $appRoot . '/storage/telegram-bot.pid';
        if (file_exists($pidFile)) {
            $pid = file_get_contents($pidFile);
            if (posix_kill($pid, 0)) {
                echo "Telegram bot is already running with PID: $pid\n";
                exit(1);
            } else {
                // Stale PID file, remove it
                unlink($pidFile);
            }
        }
        
        // Fork process to background
        $pid = pcntl_fork();
        if ($pid == -1) {
            echo "Could not fork process\n";
            exit(1);
        } elseif ($pid) {
            // Parent process
            file_put_contents($pidFile, $pid);
            echo "Telegram bot started as daemon with PID: $pid\n";
            exit(0);
        } else {
            // Child process
            set_time_limit(0);
            runContinuousPolling($phpBinary, $logFile, $telegramTimeout, $maxRestarts, $restartDelay);
        }
        break;
        
    case 'stop':
        // Stop daemon
        $pidFile = $appRoot . '/storage/telegram-bot.pid';
        if (file_exists($pidFile)) {
            $pid = file_get_contents($pidFile);
            if (posix_kill($pid, SIGTERM)) {
                echo "Sent stop signal to PID: $pid\n";
                unlink($pidFile);
            } else {
                echo "Could not stop process with PID: $pid\n";
                unlink($pidFile); // Remove stale PID file
            }
        } else {
            echo "No daemon PID file found\n";
        }
        exit(0);
        
    default:
        echo "Unknown action: $action\n";
        echo "Available actions:\n";
        echo "  poll       - Run continuous polling (recommended for production)\n";
        echo "  poll-once  - Run single polling cycle\n";
        echo "  daemon     - Run as background daemon\n";
        echo "  stop       - Stop background daemon\n";
        echo "  webhook    - Setup webhook\n";
        echo "  setup      - Setup bot\n";
        echo "  test       - Test configuration\n";
        echo "  status     - Check status\n";
        exit(1);
}

// Execute single command (for non-continuous modes)
if (!empty($command)) {
    echo "Executing: $command\n";
    
    $output = [];
    $returnCode = 0;
    exec($command . ' 2>&1', $output, $returnCode);
    
    $endTime = date('Y-m-d H:i:s');
    echo "[$endTime] Command finished with return code: $returnCode\n";
    
    if (!empty($output)) {
        echo "Output:\n";
        foreach ($output as $line) {
            echo "  $line\n";
        }
    }
    
    // Write log entry
    $logMessage = "[$startTime to $endTime] Command ($action) executed (code: $returnCode)\n";
    if (!empty($output)) {
        $logMessage .= "Output: " . implode(' | ', array_slice($output, -3)) . "\n"; // Last 3 lines only
    }
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    
    exit($returnCode);
} 