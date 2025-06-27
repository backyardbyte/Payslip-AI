<?php

/**
 * System Configuration Checker
 * 
 * This script checks and validates system configuration for Payslip AI
 */

// Suppress PHP warnings/notices for cleaner output
error_reporting(E_ERROR | E_PARSE);

// Set working directory to application root
$appRoot = __DIR__;
chdir($appRoot);

echo "===========================================\n";
echo "    Payslip AI System Configuration Check\n";
echo "===========================================\n\n";

// Check PHP version
$phpVersion = PHP_VERSION;
$phpMajor = (int) explode('.', $phpVersion)[0];
$phpMinor = (int) explode('.', $phpVersion)[1];
echo "PHP Version: $phpVersion ";
if ($phpMajor >= 8 && $phpMinor >= 1) {
    echo "✅\n";
} else {
    echo "❌ (Requires PHP 8.1 or higher)\n";
}

// Check required PHP extensions
$requiredExtensions = [
    'pdo', 'pdo_mysql', 'curl', 'json', 'mbstring', 
    'openssl', 'tokenizer', 'xml', 'ctype', 'fileinfo'
];

echo "\nPHP Extensions:\n";
foreach ($requiredExtensions as $ext) {
    echo "  - $ext: ";
    if (extension_loaded($ext)) {
        echo "✅\n";
    } else {
        echo "❌ (Required)\n";
    }
}

// Check optional extensions
$optionalExtensions = ['imagick', 'gd'];
echo "\nOptional Extensions:\n";
foreach ($optionalExtensions as $ext) {
    echo "  - $ext: ";
    if (extension_loaded($ext)) {
        echo "✅\n";
    } else {
        echo "⚠️  (Recommended for image processing)\n";
    }
}

// Check .env file
echo "\n.env File: ";
if (file_exists($appRoot . '/.env')) {
    echo "✅\n";
    
    // Load and check environment variables
    $envContent = file_get_contents($appRoot . '/.env');
    $envVars = [];
    $lines = explode("\n", $envContent);
    foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line) && strpos($line, '=') !== false && substr($line, 0, 1) !== '#') {
            list($key, $value) = explode('=', $line, 2);
            $envVars[trim($key)] = trim($value, '"\'');
        }
    }
    
    echo "\nEnvironment Variables:\n";
    
    // Check critical variables
    $criticalVars = [
        'APP_KEY' => 'Application key',
        'DB_CONNECTION' => 'Database connection',
        'TELEGRAM_BOT_TOKEN' => 'Telegram bot token',
    ];
    
    foreach ($criticalVars as $var => $desc) {
        echo "  - $var ($desc): ";
        if (isset($envVars[$var]) && !empty($envVars[$var])) {
            echo "✅\n";
        } else {
            echo "❌ (Required)\n";
        }
    }
    
    // Check new configuration variables
    echo "\nSystem Configuration Variables:\n";
    $systemVars = [
        'OCR_METHOD' => ['default' => 'ocrspace', 'desc' => 'OCR method'],
        'OCRSPACE_API_KEY' => ['default' => '', 'desc' => 'OCR.space API key'],
        'PDFTOTEXT_PATH' => ['default' => '/usr/bin/pdftotext', 'desc' => 'PDF to text binary'],
        'PHP_BINARY_PATH' => ['default' => '/usr/bin/php', 'desc' => 'PHP binary path'],
        'QUEUE_WORKER_TIMEOUT' => ['default' => '300', 'desc' => 'Queue timeout (seconds)'],
        'QUEUE_WORKER_MEMORY' => ['default' => '512', 'desc' => 'Queue memory (MB)'],
        'TELEGRAM_BOT_TIMEOUT' => ['default' => '600', 'desc' => 'Telegram timeout (seconds)'],
        'TELEGRAM_BOT_MEMORY' => ['default' => '256', 'desc' => 'Telegram memory (MB)'],
    ];
    
    foreach ($systemVars as $var => $info) {
        echo "  - $var ({$info['desc']}): ";
        if (isset($envVars[$var]) && !empty($envVars[$var])) {
            echo $envVars[$var] . " ✅\n";
        } else {
            echo "Not set (default: {$info['default']}) ⚠️\n";
        }
    }
    
} else {
    echo "❌ (Required)\n";
    echo "  Please copy .env.example to .env and configure it.\n";
}

// Check directories
echo "\nRequired Directories:\n";
$directories = [
    'storage' => 'Storage directory',
    'storage/app' => 'App storage',
    'storage/logs' => 'Log files',
    'storage/framework' => 'Framework cache',
    'storage/framework/cache' => 'Cache files',
    'storage/framework/sessions' => 'Session files',
    'storage/framework/views' => 'View cache',
    'bootstrap/cache' => 'Bootstrap cache',
];

foreach ($directories as $dir => $desc) {
    echo "  - $dir ($desc): ";
    $fullPath = $appRoot . '/' . $dir;
    if (is_dir($fullPath)) {
        if (is_writable($fullPath)) {
            echo "✅\n";
        } else {
            echo "❌ (Not writable)\n";
        }
    } else {
        echo "❌ (Not found)\n";
    }
}

// Check external commands
echo "\nExternal Commands:\n";

// Check pdftotext
$pdftotextPath = $envVars['PDFTOTEXT_PATH'] ?? '/usr/bin/pdftotext';
echo "  - pdftotext ($pdftotextPath): ";
if (file_exists($pdftotextPath) && is_executable($pdftotextPath)) {
    echo "✅\n";
} else {
    // Try to find it
    $output = shell_exec('which pdftotext 2>/dev/null');
    if ($output) {
        echo "Found at " . trim($output) . " ⚠️\n";
        echo "    Update PDFTOTEXT_PATH in .env\n";
    } else {
        echo "❌ (Not found)\n";
        echo "    Install with: apt-get install poppler-utils\n";
    }
}

// Check PHP binary
$phpBinaryPath = $envVars['PHP_BINARY_PATH'] ?? '';
echo "  - PHP binary: ";
if (!empty($phpBinaryPath) && file_exists($phpBinaryPath)) {
    echo "$phpBinaryPath ✅\n";
} else {
    // Try to find it
    $phpPaths = [
        '/opt/plesk/php/8.3/bin/php',
        '/opt/plesk/php/8.2/bin/php',
        '/opt/plesk/php/8.1/bin/php',
        '/usr/bin/php8.3',
        '/usr/bin/php8.2',
        '/usr/bin/php8.1',
        '/usr/bin/php',
    ];
    
    $foundPath = null;
    foreach ($phpPaths as $path) {
        if (file_exists($path)) {
            $foundPath = $path;
            break;
        }
    }
    
    if ($foundPath) {
        echo "Found at $foundPath ⚠️\n";
        echo "    Set PHP_BINARY_PATH=$foundPath in .env\n";
    } else {
        echo "Using system default ⚠️\n";
    }
}

// Check database connection
echo "\nDatabase Connection: ";
try {
    require $appRoot . '/vendor/autoload.php';
    $app = require_once $appRoot . '/bootstrap/app.php';
    $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    
    \Illuminate\Support\Facades\DB::connection()->getPdo();
    echo "✅\n";
    
    // Check if settings tables exist
    echo "  - Settings tables: ";
    if (\Illuminate\Support\Facades\Schema::hasTable('setting_definitions') &&
        \Illuminate\Support\Facades\Schema::hasTable('system_settings')) {
        echo "✅\n";
    } else {
        echo "❌ (Run: php artisan migrate)\n";
    }
    
} catch (\Exception $e) {
    echo "❌\n";
    echo "  Error: " . $e->getMessage() . "\n";
}

// Check OCR configuration
echo "\nOCR Configuration:\n";
$ocrMethod = $envVars['OCR_METHOD'] ?? 'ocrspace';
echo "  - Method: $ocrMethod\n";

if ($ocrMethod === 'ocrspace') {
    echo "  - OCR.space API Key: ";
    if (!empty($envVars['OCRSPACE_API_KEY'])) {
        echo "✅ (Configured)\n";
    } else {
        echo "❌ (Required for OCR.space)\n";
        echo "    Get your API key from: https://ocr.space/ocrapi\n";
    }
} elseif ($ocrMethod === 'tesseract') {
    echo "  - Tesseract: ";
    $tesseractPath = shell_exec('which tesseract 2>/dev/null');
    if ($tesseractPath) {
        echo "✅ Found at " . trim($tesseractPath) . "\n";
    } else {
        echo "❌ (Not found)\n";
        echo "    Install with: apt-get install tesseract-ocr\n";
    }
}

// Recommendations
echo "\n===========================================\n";
echo "Recommendations:\n";
echo "===========================================\n";

$recommendations = [];

// Check if running in production
if (($envVars['APP_ENV'] ?? 'production') === 'production') {
    if (($envVars['APP_DEBUG'] ?? 'false') === 'true') {
        $recommendations[] = "Disable debug mode in production (set APP_DEBUG=false)";
    }
}

// Check memory limits
$memoryLimit = ini_get('memory_limit');
if ($memoryLimit !== '-1') {
    $memoryMB = (int) $memoryLimit;
    if ($memoryMB < 512) {
        $recommendations[] = "Increase PHP memory_limit to at least 512M (current: $memoryLimit)";
    }
}

// Check upload limits
$uploadMax = ini_get('upload_max_filesize');
$postMax = ini_get('post_max_size');
$uploadMB = (int) $uploadMax;
$postMB = (int) $postMax;

if ($uploadMB < 10) {
    $recommendations[] = "Increase upload_max_filesize to at least 10M (current: $uploadMax)";
}
if ($postMB < 10) {
    $recommendations[] = "Increase post_max_size to at least 10M (current: $postMax)";
}

// OCR recommendations
if ($ocrMethod === 'ocrspace' && empty($envVars['OCRSPACE_API_KEY'])) {
    $recommendations[] = "Configure OCRSPACE_API_KEY for OCR processing";
}

// System paths
if (empty($envVars['PHP_BINARY_PATH'])) {
    $recommendations[] = "Set PHP_BINARY_PATH in .env for better script compatibility";
}

if (empty($recommendations)) {
    echo "✅ No critical issues found!\n";
} else {
    foreach ($recommendations as $i => $rec) {
        echo ($i + 1) . ". $rec\n";
    }
}

echo "\n===========================================\n";
echo "Run 'php artisan db:seed --class=SettingsSeeder' to update settings\n";
echo "===========================================\n"; 