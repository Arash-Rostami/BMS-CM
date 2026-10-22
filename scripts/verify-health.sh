#!/bin/bash
APP=/var/www/html
DOMAIN=https://import.bmsflow.org
U=import-bms
PHPV=8.4
cd $APP || exit 1
as() { su -s /bin/bash $U -c "$*"; }
ok() { echo "[OK]   $*"; }
bad() { echo "[FAIL] $*"; }
chk() { if eval "$2" >/dev/null 2>&1; then ok "$1"; else bad "$1"; fi; }
env_val() { grep -E "^$1=" .env | head -1 | cut -d= -f2- | tr -d '"'; }
RP=$(env_val REDIS_PASSWORD)
[ "$RP" = "null" ] && RP=
R() { if [ -n "$RP" ]; then redis-cli -a "$RP" "$@" 2>/dev/null; else redis-cli "$@" 2>/dev/null; fi; }

echo "== SERVICES =="
chk "apache running" "pgrep -x apache2"
chk "php-fpm running" "pgrep -f 'php-fpm: master'"
chk "redis running" "[ \"\$(R ping)\" = PONG ]"
chk "supervisord running" "pgrep -x supervisord"
chk "cron running" "pgrep -x cron"
chk "apache config valid" "apache2ctl configtest"
chk "php-fpm config valid" "php-fpm$PHPV -t"

echo "== APACHE (modules + cache conf) =="
apache2ctl -M 2>/dev/null | grep -iE 'deflate|headers|expires' | sed 's/^/module: /'
a2query -c perf 2>/dev/null; a2query -c servername 2>/dev/null

echo "== PHP-FPM / OPCACHE (effective config) =="
php-fpm$PHPV -tt 2>&1 | grep -E 'pm\.(max_children|start_servers|min_spare_servers|max_spare_servers|max_requests)|request_terminate_timeout' | sed 's/.*NOTICE: *//'
LOGF=$(ls /var/log/php$PHPV-fpm.log 2>/dev/null)
[ -n "$LOGF" ] && { c=$(grep -c 'reached pm.max_children setting' $LOGF); [ "$c" = 0 ] && ok "max_children never reached" || bad "max_children reached $c times: raise it or check slow requests"; }
php-fpm$PHPV -i 2>/dev/null | grep -E '^(opcache\.(enable|memory_consumption|validate_timestamps|max_accelerated_files|jit) |upload_max_filesize|post_max_size|memory_limit|max_execution_time)'

echo "== OPCACHE (live FPM memory usage - self-deleting probe) =="
cat > public/_opcache-probe.php << 'EOF'
<?php
header('Content-Type: application/json');
echo json_encode(opcache_get_status(false));
EOF
chown $U:$U public/_opcache-probe.php
curl -s $DOMAIN/_opcache-probe.php -o /tmp/ops.json
rm -f public/_opcache-probe.php
php -r '$s=json_decode(file_get_contents("/tmp/ops.json"),true); if($s) printf("used: %.1f MB / free: %.1f MB / wasted: %.1f MB (%.1f%% wasted) / cached scripts: %d / hit rate: %.2f%%\n", $s["memory_usage"]["used_memory"]/1048576, $s["memory_usage"]["free_memory"]/1048576, $s["memory_usage"]["wasted_memory"]/1048576, $s["memory_usage"]["current_wasted_percentage"], $s["opcache_statistics"]["num_cached_scripts"], $s["opcache_statistics"]["opcache_hit_rate"]); else print("probe failed - check DOMAIN is reachable\n");'
rm -f /tmp/ops.json

echo "== REDIS =="
R config get maxmemory | tail -1 | awk '{printf "maxmemory: %d MB\n", $1/1048576}'
echo "policy: $(R config get maxmemory-policy | tail -1)  (expect volatile-lru)"
R info memory | grep -E '^used_memory_human'
R info stats | grep -E '^evicted_keys'
echo "queue length: $(R llen queues:default)"

echo "== WORKERS / SCHEDULER =="
supervisorctl status
echo "failed jobs: $(as 'php artisan queue:failed' | grep -cE '^\s*[0-9a-f-]{8,}')"
tail -n 5 storage/logs/worker.log 2>/dev/null
crontab -u $U -l 2>/dev/null | grep -q schedule:run && ok "scheduler cron present for $U" || bad "scheduler cron missing"
as 'php artisan schedule:list' | head -12

echo "== PERMISSIONS / UPLOADS =="
as 'touch storage/app/public/t storage/app/livewire-tmp/t storage/framework/cache/t bootstrap/cache/t && rm storage/app/public/t storage/app/livewire-tmp/t storage/framework/cache/t bootstrap/cache/t' && ok "$U can write storage, cache, Livewire uploads" || bad "write test failed: chown -R $U:$U storage bootstrap/cache public/build"
[ -f public/build/manifest.json ] && ok "vite manifest present" || bad "vite manifest missing: npm run build"
find storage bootstrap/cache public/build ! -user $U 2>/dev/null | head -3 | sed "s/^/[WARN] not owned by $U: /"

echo "== APP =="
echo "local HTTP: $(curl -s -o /dev/null -w '%{http_code}' http://localhost)  (expect 200 or 302)"
echo "public HTTP: $(curl -sL -o /dev/null -w '%{http_code}' $DOMAIN)  (expect 200)"
grep -E '^(APP_ENV|APP_DEBUG|LOG_LEVEL|SESSION_DRIVER|CACHE_STORE|QUEUE_CONNECTION)=' .env | tr '\n' ' '; echo
echo "shell env APP_DEBUG: ${APP_DEBUG}  (must be blank or false)"
as 'php artisan about' | grep -iE 'debug|cache|session|queue'
as "php artisan tinker --execute=\"echo DB::select('select 1 as ok')[0]->ok === 1 ? 'DB_OK' : 'DB_FAIL';\"" 2>/dev/null | grep -q DB_OK && ok "database reachable" || bad "database unreachable"
CSS=$(ls public/build/assets/app-*.css 2>/dev/null | head -1 | xargs -r basename)
[ -n "$CSS" ] && curl -s -o /dev/null -D - -H 'Accept-Encoding: gzip' -w 'size: %{size_download}\n' "$DOMAIN/build/assets/$CSS" | grep -iE '^HTTP|content-encoding|cache-control|^size' | tr -d '\r'
echo "errors in laravel.log (last 200 lines): $(tail -n 200 storage/logs/laravel.log 2>/dev/null | grep -c '\.ERROR')"

echo "== RESOURCES =="
echo "memory: $(( $(cat /sys/fs/cgroup/memory.current) / 1048576 )) MB of $(( $(cat /sys/fs/cgroup/memory.max) / 1048576 )) MB"
echo "load average: $(awk '{print $1, $2, $3}' /proc/loadavg)  cpu.max: $(cat /sys/fs/cgroup/cpu.max)"
