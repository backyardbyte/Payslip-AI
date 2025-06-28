#!/bin/bash

# Stop All Services Script
# This script stops ALL bot and queue processes for complete shutdown
# Use this when you want to stop everything (bots + workers)

echo "🛑 Stopping ALL Telegram bots and queue workers..."
echo "================================================"

# Function to kill processes and report results
kill_processes() {
    local pattern="$1"
    local description="$2"
    
    local pids=$(ps aux | grep -E "$pattern" | grep -v grep | grep -v "stop-all-services" | awk '{print $2}')
    
    if [ -n "$pids" ]; then
        echo "🔹 Stopping $description processes..."
        for pid in $pids; do
            if ps -p $pid > /dev/null 2>&1; then
                echo "   Killing process $pid: $(ps -p $pid -o command --no-headers | cut -c1-80)..."
                kill $pid
                sleep 1
                if ps -p $pid > /dev/null 2>&1; then
                    echo "   Force killing process $pid..."
                    kill -9 $pid
                fi
            fi
        done
        echo "   ✅ $description processes stopped"
    else
        echo "🔹 No $description processes found"
    fi
}

# Stop Telegram bots first
kill_processes "telegram-bot-simple\.php" "telegram-bot-simple.php"
kill_processes "artisan telegram:bot" "artisan telegram:bot"
kill_processes "telegram.*bot" "generic telegram bot"

# Stop queue workers
kill_processes "queue:work" "Laravel queue workers"

# Stop WhatsApp bots if any
kill_processes "whatsapp.*bot" "WhatsApp bot"

# Clean up PID files
echo "🔹 Cleaning up PID files..."
if [ -f "storage/telegram-bot.pid" ]; then
    PID=$(cat storage/telegram-bot.pid)
    if ps -p $PID > /dev/null 2>&1; then
        kill -9 $PID 2>/dev/null
    fi
    rm -f storage/telegram-bot.pid
    echo "   ✅ Telegram daemon PID cleaned"
fi

# Wait for processes to terminate
sleep 3

# Final verification
echo ""
echo "🔍 Final verification..."
REMAINING=$(ps aux | grep -E "(telegram-bot|telegram:bot|queue:work)" | grep -v grep | grep -v "stop-all-services")

if [ -n "$REMAINING" ]; then
    echo "⚠️  Some processes may still be running:"
    echo "$REMAINING"
    
    # Force kill any remaining
    FORCE_PIDS=$(echo "$REMAINING" | awk '{print $2}')
    for pid in $FORCE_PIDS; do
        echo "   Force killing $pid..."
        kill -9 $pid 2>/dev/null
    done
else
    echo "✅ All services stopped successfully!"
fi

echo ""
echo "================================================"
echo "🎯 Complete Shutdown Summary:"
echo "   ✅ Telegram bots stopped"
echo "   ✅ Queue workers stopped"
echo "   ✅ PID files cleaned"
echo "   ✅ All processes terminated"
echo ""
echo "💡 To restart services:"
echo "   Telegram Bot: nohup php telegram-bot-simple.php poll > /dev/null 2>&1 &"
echo "   Queue Worker: nohup php artisan queue:work --sleep=3 --tries=3 --timeout=300 > /dev/null 2>&1 &"
echo "" 