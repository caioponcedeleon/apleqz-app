#!/usr/bin/env bash
# Optional manual CLI helper (PHP invokes Node directly with a minimal env).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
NODE="${JOB_SCRAPE_NODE_BINARY:-/usr/bin/node}"
SCRIPT="${JOB_SCRAPE_PLAYWRIGHT_SCRIPT:-${ROOT}/scripts/scrape-page.mjs}"
HOME_DIR="${JOB_SCRAPE_HOME:?JOB_SCRAPE_HOME is required}"
BROWSERS="${PLAYWRIGHT_BROWSERS_PATH:?PLAYWRIGHT_BROWSERS_PATH is required}"
PATH_DIR="${JOB_SCRAPE_PATH:-/usr/local/bin:/usr/bin:/bin}"

ENV_ARGS=(
  HOME="${HOME_DIR}"
  PLAYWRIGHT_BROWSERS_PATH="${BROWSERS}"
  PATH="${PATH_DIR}"
)

if [ -n "${NODE_OPTIONS:-}" ]; then
  ENV_ARGS+=( "NODE_OPTIONS=${NODE_OPTIONS}" )
fi

exec env -i "${ENV_ARGS[@]}" "${NODE}" "${SCRIPT}"
