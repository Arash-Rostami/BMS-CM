#!/usr/bin/env bash
set -Eeuo pipefail

readonly ARTISAN_PATH="/home/communit/import/artisan"
readonly PHP_PATH="/usr/local/bin/ea-php82"
readonly BASE_PATH="/home/communit/import"

readonly QUEUE_CONNECTION="database"
readonly QUEUE_NAME="default"

readonly LOG_DIR="${BASE_PATH}/storage/logs/smart"
readonly LOG_FILE="${LOG_DIR}/queue_monitor.log"
readonly PID_FILE="${LOG_DIR}/queue_worker.pid"
readonly LOCK_FILE="${LOG_DIR}/queue_monitor.lock"
readonly LOAD_THRESHOLD=4.0
readonly DISK_THRESHOLD=85

log() { echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"; }

system_pressure_high() {
  local load
  load=$(awk '{print $1}' /proc/loadavg)

  local disk
  disk=$(df --output=pcent "$BASE_PATH" | tail -1 | tr -dc '0-9')

  awk -v l="$load" -v t="$LOAD_THRESHOLD" 'BEGIN{exit !(l>t)}'
  if [ $? -eq 0 ]; then
    log "Skipping worker start due to high system load: ${load}"
    return 0
  fi

  if [ -n "$disk" ] && [ "$disk" -ge "$DISK_THRESHOLD" ]; then
    log "Skipping worker start due to high disk usage: ${disk}%"
    return 0
  fi

  return 1
}

start_worker() {
  mkdir -p "$LOG_DIR"
  cd "$BASE_PATH" || { log "Cannot cd to ${BASE_PATH}"; return 1; }

  nohup "$PHP_PATH" "$ARTISAN_PATH" queue:work "$QUEUE_CONNECTION" \
    --queue="$QUEUE_NAME" \
    --stop-when-empty \
    --tries=3 \
    --timeout=60 \
    --sleep=3 \
    >> "$LOG_FILE" 2>&1 &

  echo $! > "$PID_FILE"
  log "Started queue worker (pid $(cat "$PID_FILE")) in stop-when-empty mode."
}

main() {
  exec 200>"$LOCK_FILE"
  flock -n 200 || { log "Another monitor instance running; exiting."; exit 0; }

  mkdir -p "$LOG_DIR"

  if system_pressure_high; then
    exec 200>&-   # release lock before exit
    exit 0
  fi

  cd "$BASE_PATH" || { log "Cannot cd to ${BASE_PATH}"; exec 200>&-; exit 1; }

  local running_workers
  running_workers=$(pgrep -af "artisan queue:work" | grep -c -- '--stop-when-empty' || true)

  if [ "$running_workers" -eq 0 ]; then
    start_worker
  else
    log "Worker already running (count=${running_workers}); no action."
  fi

  exec 200>&-
}

trap 'exec 200>&- || true; rm -f "$LOCK_FILE" 2>/dev/null || true' EXIT
main
