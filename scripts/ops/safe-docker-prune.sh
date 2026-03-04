#!/usr/bin/env bash

set -euo pipefail

LOCK_FILE="/tmp/matre-safe-docker-prune.lock"
USAGE="Usage: safe-docker-prune.sh [--dry-run]"
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
    printf '%s [safe-docker-prune] %s\n' "$(timestamp_utc)" "$1"
}

if ! command -v flock >/dev/null 2>&1; then
    log "flock binary not found"
    exit 1
fi

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
    log "another cleanup is already running, exiting"
    exit 0
fi

log "starting (dry-run=${DRY_RUN})"
log "docker usage before cleanup:"
docker system df

if [[ $DRY_RUN -eq 1 ]]; then
    log "dry run complete (no changes made)"
    exit 0
fi

run_step() {
    local name="$1"
    shift
    log "running step: ${name}"
    if "$@"; then
        log "step completed: ${name}"
    else
        rc=$?
        log "step failed: ${name} (exit=${rc})"
        exit "$rc"
    fi
}

run_step "builder prune" docker builder prune -af --filter "until=168h"
run_step "image prune" docker image prune -af --filter "until=168h"
run_step "container prune" docker container prune -f --filter "until=168h"
run_step "network prune" docker network prune -f --filter "until=168h"

log "docker usage after cleanup:"
docker system df
log "cleanup complete"
