#!/usr/bin/env bash
# Levanta el entorno y ejecuta la suite completa.
# Uso: ./tests/run_all.sh [--no-reset]
set -euo pipefail
cd "$(dirname "$0")/.."
docker compose up -d --wait
echo ""
docker compose exec -T app php tests/run.php "$@"