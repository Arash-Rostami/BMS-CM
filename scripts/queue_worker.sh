#!/bin/bash
export PATH=/usr/local/bin:/usr/bin:/bin

ARTISAN_PATH="/home/communit/import/artisan"
PHP_BIN="/usr/local/bin/ea-php82"
MONITOR_LOG="/home/communit/import/storage/logs/queue_monitor.log"
WORKER_LOG="/home/communit/import/storage/logs/queue_worker.log"
CRON_LOG="/home/communit/import/storage/logs/cron.log"

log_monitor() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$MONITOR_LOG"
}

log_cron() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$CRON_LOG"
}

log_monitor "Cron job triggered."
log_cron "Script started."

PIDS=$(pgrep -f "[q]ueue:work --daemon")

if [ -z "$PIDS" ]; then
    log_monitor "No active queue worker found. Starting a new worker."
    log_cron "No active worker found. Starting new worker."

    nohup setsid "$PHP_BIN" "$ARTISAN_PATH" queue:work \
        --daemon --sleep=3 --tries=3 \
        >> "$WORKER_LOG" 2>&1 &

    WORKER_PID=$!

    sleep 1

    if ps -p "$WORKER_PID" > /dev/null; then
        log_monitor "New queue worker process initiated (PID: $WORKER_PID). Check $WORKER_LOG for details."
        log_cron "New queue worker initiated (PID: $WORKER_PID)."
    else
        log_monitor "Failed to initiate new queue worker. nohup or setsid might have failed. PID: $WORKER_PID"
        log_cron "Failed to initiate new queue worker. PID: $WORKER_PID."
    fi
else
    log_monitor "Active queue worker found with PIDs: $PIDS. No action taken by cron script (worker is already running)."
    log_cron "Active worker found with PIDs: $PIDS. Script exited (worker running)."
fi

log_monitor "Cron job finished."
log_cron "Script finished."
