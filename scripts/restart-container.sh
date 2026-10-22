#!/bin/bash
APP=/var/www/html
U=import-bms
PHPV=8.4
cd $APP || exit 1
as() { su -s /bin/bash $U -c "$*"; }
setkv() { grep -qE "^#? ?$2 " "$1" && sed -i -E "s|^#? ?$2 .*|$2 $3|" "$1" || echo "$2 $3" >> "$1"; }
env_val() { grep -E "^$1=" .env | head -1 | cut -d= -f2- | tr -d '"'; }
redis_up() { if [ -n "$RP" ]; then redis-cli -a "$RP" ping 2>/dev/null | grep -q PONG; else redis-cli ping 2>/dev/null | grep -q PONG; fi; }

for p in redis-server supervisor cron; do dpkg -s $p >/dev/null 2>&1 || { apt-get update -qq; apt-get install -y -qq $p; }; done

RP=$(env_val REDIS_PASSWORD)
[ "$RP" = "null" ] && RP=
[ -n "$RP" ] && setkv /etc/redis/redis.conf requirepass "$RP"
setkv /etc/redis/redis.conf maxmemory 1gb
setkv /etc/redis/redis.conf maxmemory-policy volatile-lru

POOL=$(ls /etc/php/$PHPV/fpm/pool.d/*.conf | head -1)
cat > "$POOL" << 'EOF'
[www]
user = import-bms
group = import-bms
listen = /run/php/php8.4-fpm.sock
listen.owner = import-bms
listen.group = import-bms
pm = dynamic
pm.max_children = 20
pm.start_servers = 6
pm.min_spare_servers = 5
pm.max_spare_servers = 10
pm.max_requests = 1000
pm.status_path = /fpm-status
request_terminate_timeout = 120
clear_env = no
EOF

cat > /etc/php/$PHPV/fpm/conf.d/99-tuning.ini << 'EOF'
opcache.enable=1
opcache.memory_consumption=192
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.jit=off
opcache.save_comments=1
realpath_cache_size=8192K
realpath_cache_ttl=600
upload_max_filesize=250M
post_max_size=250M
memory_limit=512M
max_input_vars=5000
max_input_time=300
max_execution_time=120
EOF
cp /etc/php/$PHPV/fpm/conf.d/99-tuning.ini $APP/chabok-php.ini

cat > /etc/supervisor/conf.d/laravel-worker.conf << 'EOF'
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=2
user=import-bms
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker.log
EOF

crontab -l 2>/dev/null | grep -q schedule:run && crontab -l | grep -v schedule:run | crontab -
crontab -u $U -l 2>/dev/null | grep -q schedule:run || (crontab -u $U -l 2>/dev/null; echo "* * * * * cd $APP && php artisan schedule:run >> /dev/null 2>&1") | crontab -u $U -

echo 'ServerName localhost' > /etc/apache2/conf-available/servername.conf
cat > /etc/apache2/conf-available/perf.conf << 'EOF'
<LocationMatch "^/build/assets/.*\.(css|js|svg|ttf)$">
    SetOutputFilter DEFLATE
    Header set Cache-Control "public, max-age=31536000, immutable"
</LocationMatch>
<LocationMatch "^/build/assets/.*\.(woff|woff2|png|jpg|jpeg|gif|ico)$">
    Header set Cache-Control "public, max-age=31536000, immutable"
</LocationMatch>
<LocationMatch "^/(sw\.js|sw\.mjs|registerSW\.js|build/manifest\.webmanifest)$">
    SetOutputFilter DEFLATE
    Header set Cache-Control "no-cache"
</LocationMatch>
AddOutputFilterByType DEFLATE text/html application/json
EOF
a2enmod -q deflate headers expires >/dev/null
a2enconf -q servername perf >/dev/null

sed -i 's/^APP_DEBUG=.*/APP_DEBUG=false/; s/^LOG_LEVEL=.*/LOG_LEVEL=warning/' .env

mkdir -p storage/app/public storage/app/livewire-tmp storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
npm ci && npm run build
chown -R $U:$U storage bootstrap/cache public/build
find storage bootstrap/cache -type d -exec chmod 775 {} + -o -type f -exec chmod 664 {} +
find storage bootstrap/cache -type d -exec chmod g+s {} +
[ -L public/storage ] || as 'php artisan storage:link'
as 'php artisan filament:assets'

service redis-server restart
for i in $(seq 1 10); do redis_up && break; sleep 1; done

if redis_up; then
  echo "Redis: PONG"
  sed -i 's/^SESSION_DRIVER=file/SESSION_DRIVER=redis/; s/^CACHE_STORE=file/CACHE_STORE=redis/; s/^QUEUE_CONNECTION=database/QUEUE_CONNECTION=redis/' .env
else
  echo "Redis: DOWN - switching to file/database drivers so the site stays up"
  sed -i 's/^SESSION_DRIVER=redis/SESSION_DRIVER=file/; s/^CACHE_STORE=redis/CACHE_STORE=file/; s/^QUEUE_CONNECTION=redis/QUEUE_CONNECTION=database/' .env
fi

as 'php artisan optimize:clear && php artisan optimize'

pgrep -x supervisord >/dev/null || service supervisor start
pgrep -x cron >/dev/null || service cron start
php-fpm$PHPV -t && service php$PHPV-fpm restart
apache2ctl configtest && service apache2 reload
supervisorctl reread >/dev/null; supervisorctl update >/dev/null; supervisorctl restart all

echo "=== VERIFY ==="
redis_up && echo PONG || echo "REDIS DOWN"
if [ -n "$RP" ]; then redis-cli -a "$RP" config get maxmemory-policy 2>/dev/null | tail -1; else redis-cli config get maxmemory-policy | tail -1; fi
supervisorctl status
crontab -u $U -l | grep schedule:run
as 'touch storage/logs/t && rm storage/logs/t' && echo WRITE_OK
curl -sL -o /dev/null -w "HTTP %{http_code}\n" http://localhost
echo "Next: bash scripts/verify-health.sh"
