#!/usr/bin/env bash

set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-/home/ubuntu/matre}"
ENV_FILE="${ENV_FILE:-${PROJECT_DIR}/.env}"
STATE_FILE="${STATE_FILE:-/tmp/matre-disk-alert.state}"
LOCK_FILE="${LOCK_FILE:-/tmp/matre-disk-alert.lock}"
WARN_THRESHOLD="${WARN_THRESHOLD:-85}"
CRIT_THRESHOLD="${CRIT_THRESHOLD:-92}"
MAX_VAR_LINES="${MAX_VAR_LINES:-12}"
MAX_DOCKER_LINES="${MAX_DOCKER_LINES:-10}"

timestamp_utc() {
    date -u +"%Y-%m-%dT%H:%M:%SZ"
}

log() {
    printf '%s [disk-monitor] %s\n' "$(timestamp_utc)" "$1"
}

json_escape() {
    local value="$1"
    value="${value//\\/\\\\}"
    value="${value//\"/\\\"}"
    value="${value//$'\t'/\\t}"
    value="${value//$'\n'/\\n}"
    value="${value//$'\r'/}"
    printf '%s' "$value"
}

read_env_value() {
    local key="$1"
    local file="$2"
    local line

    if [[ ! -f "$file" ]]; then
        return 1
    fi

    line="$(awk -v k="$key" 'index($0, k"=") == 1 {line = $0} END {print line}' "$file")"
    if [[ -z "$line" ]]; then
        return 1
    fi

    line="${line#*=}"
    line="${line%\"}"
    line="${line#\"}"
    line="${line%\'}"
    line="${line#\'}"
    printf '%s' "$line"
}

send_slack_message() {
    local webhook_url="$1"
    local message="$2"
    local payload

    payload="$(printf '{"text":"%s"}' "$(json_escape "$message")")"

    curl --silent --show-error --fail \
        --connect-timeout 10 \
        --max-time 20 \
        --request POST \
        --header "Content-Type: application/json" \
        --data "$payload" \
        "$webhook_url" >/dev/null
}

if ! command -v flock >/dev/null 2>&1; then
    log "flock binary not found"
    exit 1
fi

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
    log "another monitor run is already active, exiting"
    exit 0
fi

disk_usage_pct="$(df -P / | awk 'NR==2 {gsub("%", "", $5); print $5}')"
host_name="$(hostname)"

if [[ -z "$disk_usage_pct" || ! "$disk_usage_pct" =~ ^[0-9]+$ ]]; then
    log "failed to read disk usage"
    exit 1
fi

current_level="ok"
if (( disk_usage_pct >= CRIT_THRESHOLD )); then
    current_level="critical"
elif (( disk_usage_pct >= WARN_THRESHOLD )); then
    current_level="warning"
fi

previous_level="unknown"
if [[ -f "$STATE_FILE" ]]; then
    previous_level="$(cat "$STATE_FILE" 2>/dev/null || echo unknown)"
fi

if [[ "$current_level" == "$previous_level" ]]; then
    log "status unchanged (${current_level}, ${disk_usage_pct}%), no alert"
    exit 0
fi

if [[ "$current_level" == "ok" && "$previous_level" == "unknown" ]]; then
    echo "$current_level" >"$STATE_FILE"
    log "initial state is ok (${disk_usage_pct}%), no alert"
    exit 0
fi

webhook_url="$(read_env_value "SLACK_WEBHOOK_URL" "$ENV_FILE" || true)"
if [[ -z "$webhook_url" ]]; then
    log "SLACK_WEBHOOK_URL missing, cannot notify for transition ${previous_level} -> ${current_level}"
    exit 1
fi

df_output="$(df -h /)"
docker_output="$(docker system df 2>/dev/null | sed -n "1,${MAX_DOCKER_LINES}p" || true)"
var_output="$(du -sh "${PROJECT_DIR}/var"/* 2>/dev/null | sort -hr | sed -n "1,${MAX_VAR_LINES}p" || true)"

message_prefix="MATRE disk status"
if [[ "$current_level" == "warning" ]]; then
    message_prefix=":warning: MATRE disk warning"
elif [[ "$current_level" == "critical" ]]; then
    message_prefix=":rotating_light: MATRE disk critical"
elif [[ "$current_level" == "ok" ]]; then
    message_prefix=":white_check_mark: MATRE disk recovered"
fi

message=$(cat <<EOF
${message_prefix}
Host: ${host_name}
Time (UTC): $(timestamp_utc)
State: ${previous_level} -> ${current_level}
Usage: ${disk_usage_pct}% (warn=${WARN_THRESHOLD}%, critical=${CRIT_THRESHOLD}%)

df -h /:
${df_output}

docker system df:
${docker_output}

Top ${MAX_VAR_LINES} entries under ${PROJECT_DIR}/var:
${var_output}
EOF
)

if send_slack_message "$webhook_url" "$message"; then
    echo "$current_level" >"$STATE_FILE"
    log "alert sent (${previous_level} -> ${current_level}, ${disk_usage_pct}%)"
else
    log "failed to send alert"
    exit 1
fi
