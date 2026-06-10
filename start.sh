#!/usr/bin/env bash
# Start the ZapSheets local dev server.
# Usage: ./start.sh [port]
#
# The router.php script is required for short URLs like /{id}/view.
# Without it, PHP's built-in server can't route those requests.

PORT="${1:-8000}"
cd "$(dirname "$0")"  # always run from project root regardless of where you call it from

echo "Starting ZapSheets at http://localhost:${PORT}/"
echo "Press Ctrl+C to stop."
php -S "0.0.0.0:${PORT}" router.php
