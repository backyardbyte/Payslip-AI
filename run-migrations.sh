#!/bin/bash

# Run migrations on production server
echo "Running database migrations..."

# Use PHP 8.3 explicitly
/opt/plesk/php/8.3/bin/php artisan migrate --force

echo "Migrations completed!"

# Show the current database status
echo ""
echo "Current migration status:"
/opt/plesk/php/8.3/bin/php artisan migrate:status | tail -20 