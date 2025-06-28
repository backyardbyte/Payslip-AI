#!/bin/bash

# Payslip AI Production Update Script
# This script applies the OCR.space API fixes and configuration updates

echo "🚀 Payslip AI Production Update Script"
echo "======================================"

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo "❌ Error: artisan file not found. Please run this script from your Laravel project root."
    exit 1
fi

echo "📍 Current directory: $(pwd)"
echo ""

# Step 1: Backup current state
echo "1️⃣ Creating backup..."
BACKUP_DIR="backup-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"
cp .env "$BACKUP_DIR/.env.backup" 2>/dev/null || echo "   ⚠️  .env not found"
cp -r storage/logs "$BACKUP_DIR/logs" 2>/dev/null || echo "   ⚠️  logs directory not found"
echo "   ✅ Backup created in $BACKUP_DIR"

# Step 2: Clear caches
echo ""
echo "2️⃣ Clearing caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
echo "   ✅ Caches cleared"

# Step 3: Run migrations for new settings
echo ""
echo "3️⃣ Updating database settings..."
php artisan db:seed --class=SettingsSeeder --force
echo "   ✅ Settings seeder updated"

# Step 4: Test OCR.space API configuration
echo ""
echo "4️⃣ Testing OCR.space API configuration..."
php artisan ocr:test-api

# Step 5: Check system configuration
echo ""
echo "5️⃣ Checking system configuration..."
if [ -f "system-config-check.php" ]; then
    php system-config-check.php
else
    echo "   ⚠️  system-config-check.php not found"
fi

# Step 6: Restart queue workers (if running)
echo ""
echo "6️⃣ Managing queue workers..."
echo "   🔄 Stopping existing queue workers..."
php artisan queue:restart

# Check if any queue workers are running and restart them
if pgrep -f "queue:work" > /dev/null; then
    echo "   ⚠️  Some queue workers may still be running. Consider manually restarting them."
else
    echo "   ✅ Queue workers restarted"
fi

# Step 7: Test Telegram bot (if configured)
echo ""
echo "7️⃣ Testing Telegram bot configuration..."
TELEGRAM_TOKEN=$(grep TELEGRAM_BOT_TOKEN .env | cut -d '=' -f2 | tr -d '"')
if [ -n "$TELEGRAM_TOKEN" ] && [ "$TELEGRAM_TOKEN" != "your_telegram_bot_token_here" ]; then
    echo "   ✅ Telegram bot token is configured"
    echo "   💡 To test the bot, run: php artisan telegram:bot run --simple"
else
    echo "   ⚠️  Telegram bot token not configured"
fi

# Step 8: Final recommendations
echo ""
echo "8️⃣ Update Summary & Recommendations"
echo "=================================="
echo ""
echo "✅ Applied Fixes:"
echo "   - Fixed OCR.space API parameter format (boolean → string)"
echo "   - Added connection timeout and better error handling"
echo "   - Improved Telegram bot timeout configuration"
echo "   - Added OCR.space API key validation"
echo "   - Enhanced error logging and debugging"
echo ""
echo "🔧 Next Steps:"
echo "   1. Check your OCR.space API key:"
echo "      Current key: $(grep OCRSPACE_API_KEY .env | cut -d '=' -f2 | tr -d '"' | head -c 10)..."
echo "      Run: php artisan ocr:test-api --show-key"
echo ""
echo "   2. If your API key is invalid, get a new one from:"
echo "      https://ocr.space/ocrapi (Free tier: 25,000 requests/month)"
echo ""
echo "   3. Monitor your logs for any remaining issues:"
echo "      tail -f storage/logs/laravel.log"
echo ""
echo "   4. Test payslip processing with a sample file"
echo ""
echo "   5. If using Telegram bot, restart it:"
echo "      php telegram-bot-simple.php poll"
echo ""
echo "🎉 Production update completed!"
echo ""
echo "📋 Issue Summary:"
echo "   - The main issue was OCR.space API parameter format"
echo "   - Your API key ($(grep OCRSPACE_API_KEY .env | cut -d '=' -f2 | tr -d '"' | wc -c) chars) may be too short"
echo "   - Telegram timeouts should be reduced with new settings"
echo ""
echo "If you continue to have issues, please:"
echo "1. Verify your OCR.space API key is valid"
echo "2. Check your account has sufficient credits"
echo "3. Review the logs for any new error patterns" 