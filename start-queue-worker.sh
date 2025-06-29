#!/bin/bash

# Simple Queue Worker Starter for Plesk Shared Hosting
# Forces PHP 8.3 to avoid compatibility issues

# Change to application directory
cd "$(dirname "$0")"

# Force PHP 8.3 binary
PHP_BIN="/opt/plesk/php/8.3/bin/php"

# Check if PHP 8.3 exists
if [ ! -f "$PHP_BIN" ]; then
    echo "ERROR: PHP 8.3 not found at $PHP_BIN"
    echo "Please check your Plesk PHP installation"
    exit 1
fi

echo "Starting Laravel Queue Worker with PHP 8.3..."
echo "Using: $PHP_BIN"

# Kill any existing queue workers
echo "Stopping existing queue workers..."
pkill -f "artisan queue:work" 2>/dev/null || true

# Start the queue worker with PHP 8.3
echo "Starting new queue worker..."
$PHP_BIN artisan queue:work database \
    --queue=default \
    --sleep=3 \
    --tries=3 \
    --timeout=120 \
    --memory=512 \
    --verbose

echo "Queue worker stopped." 