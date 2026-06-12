#!/bin/bash
set -e

DB_FILE="${DB_PATH:-/app/database/amims.db}"
DB_DIR=$(dirname "$DB_FILE")

mkdir -p "$DB_DIR"

if [ ! -f "$DB_FILE" ]; then
    echo "First run: initializing database at $DB_FILE..."
    php database/init_db.php
fi

echo "Starting AMIMS on port ${PORT:-8080}..."
exec php -S 0.0.0.0:${PORT:-8080}
