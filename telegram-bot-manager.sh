#!/bin/bash

# Telegram Bot Manager Script
# Usage: ./telegram-bot-manager.sh {start|stop|restart|status|logs}

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BOT_SCRIPT="$SCRIPT_DIR/telegram-bot-simple.php"
PID_FILE="$SCRIPT_DIR/storage/telegram-bot.pid"
LOG_FILE="$SCRIPT_DIR/storage/logs/telegram-bot.log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if bot is running
is_running() {
    if [ -f "$PID_FILE" ]; then
        PID=$(cat "$PID_FILE")
        if ps -p "$PID" > /dev/null 2>&1; then
            return 0
        else
            # Stale PID file
            rm -f "$PID_FILE"
            return 1
        fi
    else
        return 1
    fi
}

# Start the bot
start_bot() {
    if is_running; then
        PID=$(cat "$PID_FILE")
        print_warning "Telegram bot is already running with PID: $PID"
        return 1
    fi
    
    print_status "Starting Telegram bot..."
    
    # Create logs directory if it doesn't exist
    mkdir -p "$(dirname "$LOG_FILE")"
    
    # Start bot in background
    nohup php "$BOT_SCRIPT" poll > /dev/null 2>&1 &
    BOT_PID=$!
    
    # Save PID
    echo "$BOT_PID" > "$PID_FILE"
    
    # Wait a moment to check if it started successfully
    sleep 2
    
    if is_running; then
        print_status "Telegram bot started successfully with PID: $BOT_PID"
        print_status "Logs: $LOG_FILE"
        return 0
    else
        print_error "Failed to start Telegram bot"
        rm -f "$PID_FILE"
        return 1
    fi
}

# Stop the bot
stop_bot() {
    if ! is_running; then
        print_warning "Telegram bot is not running"
        return 1
    fi
    
    PID=$(cat "$PID_FILE")
    print_status "Stopping Telegram bot (PID: $PID)..."
    
    # Send TERM signal for graceful shutdown
    kill -TERM "$PID" 2>/dev/null
    
    # Wait for process to stop
    for i in {1..10}; do
        if ! ps -p "$PID" > /dev/null 2>&1; then
            break
        fi
        sleep 1
    done
    
    # Force kill if still running
    if ps -p "$PID" > /dev/null 2>&1; then
        print_warning "Graceful shutdown failed, force killing..."
        kill -KILL "$PID" 2>/dev/null
        sleep 1
    fi
    
    rm -f "$PID_FILE"
    print_status "Telegram bot stopped"
}

# Restart the bot
restart_bot() {
    print_status "Restarting Telegram bot..."
    stop_bot
    sleep 2
    start_bot
}

# Show bot status
show_status() {
    echo "Telegram Bot Status:"
    echo "===================="
    
    if is_running; then
        PID=$(cat "$PID_FILE")
        print_status "Bot is RUNNING with PID: $PID"
        
        # Show process info
        echo
        echo "Process Information:"
        ps -p "$PID" -o pid,ppid,cmd,etime,pcpu,pmem
        
    else
        print_warning "Bot is NOT RUNNING"
    fi
    
    echo
    echo "Configuration:"
    echo "  Script: $BOT_SCRIPT"
    echo "  PID File: $PID_FILE"
    echo "  Log File: $LOG_FILE"
    
    if [ -f "$LOG_FILE" ]; then
        echo "  Log Size: $(du -h "$LOG_FILE" | cut -f1)"
    else
        echo "  Log Size: N/A (no log file)"
    fi
}

# Show recent logs
show_logs() {
    if [ -f "$LOG_FILE" ]; then
        print_status "Recent log entries (last 20 lines):"
        echo "====================================="
        tail -20 "$LOG_FILE"
    else
        print_warning "No log file found at: $LOG_FILE"
    fi
}

# Show live logs
follow_logs() {
    if [ -f "$LOG_FILE" ]; then
        print_status "Following live logs (Ctrl+C to stop):"
        echo "======================================"
        tail -f "$LOG_FILE"
    else
        print_warning "No log file found at: $LOG_FILE"
        print_status "Waiting for log file to be created..."
        while [ ! -f "$LOG_FILE" ]; do
            sleep 1
        done
        tail -f "$LOG_FILE"
    fi
}

# Main script logic
case "$1" in
    start)
        start_bot
        ;;
    stop)
        stop_bot
        ;;
    restart)
        restart_bot
        ;;
    status)
        show_status
        ;;
    logs)
        show_logs
        ;;
    follow|tail)
        follow_logs
        ;;
    test)
        print_status "Testing bot configuration..."
        php "$BOT_SCRIPT" test
        ;;
    *)
        echo "Telegram Bot Manager"
        echo "Usage: $0 {start|stop|restart|status|logs|follow|test}"
        echo ""
        echo "Commands:"
        echo "  start    - Start the bot in background"
        echo "  stop     - Stop the bot gracefully"
        echo "  restart  - Restart the bot"
        echo "  status   - Show bot status and process info"
        echo "  logs     - Show recent log entries"
        echo "  follow   - Follow live logs"
        echo "  test     - Test bot configuration"
        echo ""
        exit 1
        ;;
esac

exit $? 