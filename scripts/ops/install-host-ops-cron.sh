#!/usr/bin/env bash

set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-/home/ubuntu/matre}"
MARKER="matre-host-ops"
TMP_FILE="$(mktemp)"

cleanup() {
    rm -f "$TMP_FILE"
}
trap cleanup EXIT

existing_crontab="$(crontab -l 2>/dev/null || true)"

{
    if [[ -n "$existing_crontab" ]]; then
        printf '%s\n' "$existing_crontab" | grep -Ev "[[:space:]]# ${MARKER}$" || true
    fi

    cat <<EOF
*/10 * * * * ${PROJECT_DIR}/scripts/ops/disk-monitor-alert.sh >> ${PROJECT_DIR}/var/log/disk-monitor.log 2>&1 # ${MARKER}
25 2 * * * ${PROJECT_DIR}/scripts/ops/artifact-retention.sh >> ${PROJECT_DIR}/var/log/artifact-retention.log 2>&1 # ${MARKER}
40 3 * * 0 ${PROJECT_DIR}/scripts/ops/safe-docker-prune.sh >> ${PROJECT_DIR}/var/log/safe-docker-prune.log 2>&1 # ${MARKER}
EOF
} >"$TMP_FILE"

crontab "$TMP_FILE"

echo "Installed MATRE host ops cron entries:"
if ! crontab -l | grep -E "[[:space:]]# ${MARKER}$"; then
    echo "Warning: no MATRE host ops entries detected after install." >&2
    exit 1
fi
