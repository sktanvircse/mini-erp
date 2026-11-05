#!/bin/bash
set -e

echo "=== Mini ERP Backend Container Starting ==="

# Wait for database if host is provided
if [ -n "$DB_HOST" ] || [ -n "$MYSQLHOST" ]; then
    HOST="${DB_HOST:-$MYSQLHOST}"
    PORT="${DB_PORT:-${MYSQLPORT:-3306}}"
    echo "Waiting for database at $HOST:$PORT..."
    while ! nc -z "$HOST" "$PORT" 2>/dev/null; do
        sleep 1
    done
    echo "Database is ready!"
fi

# Run database setup & seeding if configured
if [ "$RUN_MIGRATIONS" = "true" ] || [ "$RUN_MIGRATIONS" = "1" ]; then
    echo "Running database setup & seeds..."
    php /var/www/html/database/run_seeds.php || echo "Migration completed or skipped."
fi

echo "Starting Apache Web Server on port ${PORT:-80}..."
exec apache2-foreground
