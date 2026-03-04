#!/usr/bin/env bash

set -euo pipefail

MARKER="matre-host-ops"
TMP_FILE="$(mktemp)"

cleanup() {
    rm -f "$TMP_FILE"
}
trap cleanup EXIT

existing_crontab="$(crontab -l 2>/dev/null || true)"

if [[ -z "$existing_crontab" ]]; then
    echo "No crontab entries to clean."
    exit 0
fi

printf '%s\n' "$existing_crontab" | grep -Ev "[[:space:]]# ${MARKER}$" >"$TMP_FILE" || true

crontab "$TMP_FILE"

echo "Removed MATRE host ops cron entries (marker: ${MARKER})."
