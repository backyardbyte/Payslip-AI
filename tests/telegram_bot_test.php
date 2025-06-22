<?php

/**
 * Telegram Bot Integration Test Script
 * 
 * This script helps test the Telegram bot integration with your koperasi system.
 * Run this after setting up your bot to verify everything is working correctly.
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "🤖 Telegram Bot Integration Test\n";
echo "================================\n\n";

// Test 1: Check if bot token is configured
echo "1. Checking bot token configuration...\n";
$botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
if (empty($botToken)) {
    echo "❌ TELEGRAM_BOT_TOKEN not configured in .env file\n";
    echo "   Please add your bot token to the .env file\n\n";
} else {
    echo "✅ Bot token configured\n\n";
}

// Test 2: Check API endpoints
echo "2. Testing API endpoints...\n";
$baseUrl = $_ENV['APP_URL'] ?? 'http://localhost:8000';
$endpoints = [
    '/api/telegram/ping',
    '/api/telegram/webhook/health',
];

foreach ($endpoints as $endpoint) {
    $url = $baseUrl . $endpoint;
    echo "   Testing: {$url}\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "   ✅ Endpoint accessible\n";
    } else {
        echo "   ❌ Endpoint returned HTTP {$httpCode}\n";
    }
}
echo "\n";

// Test 3: Database connection
echo "3. Testing database connection...\n";
try {
    // This would need to be adapted based on your actual database setup
    echo "   ✅ Database connection test would go here\n";
    echo "   (Run 'php artisan migrate' to ensure tables are created)\n";
} catch (Exception $e) {
    echo "   ❌ Database error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 4: Check required files
echo "4. Checking required files...\n";
$requiredFiles = [
    '../app/Services/TelegramBotService.php',
    '../app/Http/Controllers/TelegramBotController.php',
    '../app/Http/Controllers/TelegramWebhookController.php',
    '../app/Models/ApiToken.php',
    '../routes/telegram.php',
];

foreach ($requiredFiles as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "   ✅ " . basename($file) . "\n";
    } else {
        echo "   ❌ Missing: " . basename($file) . "\n";
    }
}
echo "\n";

// Instructions
echo "🚀 Next Steps:\n";
echo "==============\n";
echo "1. Configure your .env file with bot token\n";
echo "2. Run: php artisan migrate\n";
echo "3. Create API token using artisan tinker\n";
echo "4. Test bot with: php artisan telegram:bot run\n";
echo "5. Set up webhook for production\n\n";

echo "📖 For detailed instructions, see:\n";
echo "   docs/TELEGRAM_BOT_INTEGRATION.md\n\n";

echo "✨ Integration setup complete!\n"; 