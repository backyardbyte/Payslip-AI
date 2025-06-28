#!/bin/bash

# Stop Telegram Bots Script
# This script stops all running Telegram bot processes safely
# Works in both local development and production environments

echo "🔄 Stopping all Telegram bot processes..."
echo "================================================"

# Function to kill processes and report results
kill_processes() {
    local pattern="$1"
    local description="$2"
    
    # Find processes matching the pattern (excluding grep and this script)
    local pids=$(ps aux | grep -E "$pattern" | grep -v grep | grep -v "stop-telegram-bots" | awk '{print $2}')
    
    if [ -n "$pids" ]; then
        echo "🔹 Stopping $description processes..."
        for pid in $pids; do
            if ps -p $pid > /dev/null 2>&1; then
                echo "   Killing process $pid: $(ps -p $pid -o command --no-headers | cut -c1-80)..."
                kill $pid
                sleep 1
                # Force kill if still running
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

# Stop all telegram-bot-simple.php processes
kill_processes "telegram-bot-simple\.php" "telegram-bot-simple.php"

# Stop all artisan telegram:bot processes
kill_processes "artisan telegram:bot" "artisan telegram:bot"

# Stop any generic telegram bot processes
kill_processes "telegram.*bot" "generic telegram bot"

# Stop any PHP processes with telegram in the command
kill_processes "php.*telegram" "PHP telegram"

# Check for daemon PID files and clean them up
echo "🔹 Checking for daemon PID files..."
if [ -f "storage/telegram-bot.pid" ]; then
    PID=$(cat storage/telegram-bot.pid)
    if ps -p $PID > /dev/null 2>&1; then
        echo "   Killing daemon process $PID..."
        kill $PID
        sleep 1
        if ps -p $PID > /dev/null 2>&1; then
            kill -9 $PID
        fi
    fi
    rm -f storage/telegram-bot.pid
    echo "   ✅ Daemon PID file cleaned"
else
    echo "   No daemon PID file found"
fi

# Wait a moment for processes to terminate
sleep 2

# Final verification
echo ""
echo "🔍 Final verification..."
REMAINING=$(ps aux | grep -E "(telegram-bot|telegram:bot)" | grep -v grep | grep -v "stop-telegram-bots")

if [ -n "$REMAINING" ]; then
    echo "⚠️  Some processes may still be running:"
    echo "$REMAINING"
    echo ""
    echo "🔨 Attempting force termination..."
    
    # Extract PIDs and force kill
    FORCE_PIDS=$(echo "$REMAINING" | awk '{print $2}')
    for pid in $FORCE_PIDS; do
        echo "   Force killing $pid..."
        kill -9 $pid 2>/dev/null
    done
    
    sleep 1
    
    # Check again
    STILL_REMAINING=$(ps aux | grep -E "(telegram-bot|telegram:bot)" | grep -v grep | grep -v "stop-telegram-bots")
    if [ -n "$STILL_REMAINING" ]; then
        echo "❌ Some processes could not be terminated:"
        echo "$STILL_REMAINING"
        echo "You may need to manually kill these with sudo or restart the system"
    else
        echo "✅ All processes successfully terminated"
    fi
else
    echo "✅ All Telegram bot processes have been stopped successfully!"
fi

echo ""
echo "================================================"
echo "🎯 Summary:"
echo "   - Stopped telegram-bot-simple.php processes"
echo "   - Stopped artisan telegram:bot processes" 
echo "   - Cleaned up daemon PID files"
echo "   - Verified all processes terminated"
echo ""
echo "💡 To start the bot again:"
echo "   Local:  php telegram-bot-simple.php poll"
echo "   Prod:   nohup php telegram-bot-simple.php poll > /dev/null 2>&1 &"
echo ""

# Optional: Also show queue worker status
QUEUE_WORKERS=$(ps aux | grep "queue:work" | grep -v grep)
if [ -n "$QUEUE_WORKERS" ]; then
    echo "ℹ️  Note: Queue workers are still running (this is usually desired):"
    echo "$QUEUE_WORKERS" | while read line; do
        echo "   $line"
    done
    echo ""
    echo "💡 To stop queue workers too:"
    echo "   pkill -f 'queue:work'"
fi 