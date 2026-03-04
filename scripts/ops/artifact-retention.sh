#!/usr/bin/env bash

set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-/home/ubuntu/matre}"
LOCK_FILE="/tmp/matre-artifact-retention.lock"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
USAGE="Usage: artifact-retention.sh [--dry-run]"
DRY_RUN=0

if [[ "${1:-}" == "--dry-run" ]]; then
    DRY_RUN=1
elif [[ $# -gt 0 ]]; then
    echo "$USAGE" >&2
    exit 1
fi

timestamp_utc() {
    date -u +"%Y-%m-%dT%H:%M:%SZ"
}

log() {
    printf '%s [artifact-retention] %s\n' "$(timestamp_utc)" "$1"
}

if ! command -v flock >/dev/null 2>&1; then
    log "flock binary not found"
    exit 1
fi

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
    log "another retention cleanup is already running, exiting"
    exit 0
fi

cd "$PROJECT_DIR"

if ! command -v docker >/dev/null 2>&1; then
    log "docker binary not found"
    exit 1
fi

cmd=(
    docker compose exec -T php
    php bin/console app:test:cleanup
    "--days=${RETENTION_DAYS}"
)

if [[ $DRY_RUN -eq 1 ]]; then
    cmd+=(--dry-run)
fi

log "starting (dry-run=${DRY_RUN}, days=${RETENTION_DAYS})"
if "${cmd[@]}"; then
    :
else
    rc=$?
    log "cleanup command failed (exit=${rc}); check docker compose, php container state, and app:test:cleanup command"
    exit "$rc"
fi
log "cleanup complete"
