#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
DB_FILE="${DB_PATH:-$SCRIPT_DIR/database/amims.db}"
DB_DIR=$(dirname "$DB_FILE")
SEED_FILE="$SCRIPT_DIR/database/amims_sqlite.sql"
HASH_FILE="$DB_DIR/.seed_hash"

mkdir -p "$DB_DIR"

if command -v sha256sum >/dev/null 2>&1; then
  SEED_HASH=$(sha256sum "$SEED_FILE" | awk '{print $1}')
elif command -v shasum >/dev/null 2>&1; then
  SEED_HASH=$(shasum -a 256 "$SEED_FILE" | awk '{print $1}')
else
  SEED_HASH=$(python3 - <<'PY'
import hashlib
with open('$SEED_FILE'.replace('$SCRIPT_DIR', '/app'), 'rb') as f:
    print(hashlib.sha256(f.read()).hexdigest())
PY
)
fi

if [ ! -f "$DB_FILE" ] || [ ! -f "$HASH_FILE" ] || [ "$SEED_HASH" != "$(cat "$HASH_FILE")" ]; then
    echo "Initializing database because DB is missing or seed changed..."
    rm -f "$DB_FILE"
    php "$SCRIPT_DIR/database/init_db.php"
    echo "$SEED_HASH" > "$HASH_FILE"
else
    echo "Database already initialized and seed unchanged."
fi

echo "Starting AMIMS on port ${PORT:-8080}..."
exec php -S 0.0.0.0:${PORT:-8080}
