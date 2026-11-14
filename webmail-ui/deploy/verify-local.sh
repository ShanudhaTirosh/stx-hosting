#!/usr/bin/env bash
# Runs the REAL deploy/nginx.conf + php-fpm pool + php-webmail.ini on this machine (high ports, self-signed cert, temp dirs),
# against a fake WildDuck, then runs deploy/smoke.sh and an end-to-end upload check. Needs root, nginx and php-fpm8.3 installed.
# Only paths and ports are substituted in the config files; every other directive is exactly what you deploy.
set -u
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; W=/tmp/wm-live; APP=/srv/webmail; DATA=/var/lib/webmail
[ "$(id -u)" = 0 ] || { echo "run as root"; exit 2; }
cleanup() { for f in nginx fpm wd; do [ -f $W/$f.pid ] && kill "$(cat $W/$f.pid)" 2>/dev/null; done; sleep 0.5; }
trap cleanup EXIT
rm -rf $W $APP $DATA; mkdir -p $W/conf.d $W/body $W/fcgi $W/proxy $W/uwsgi $W/scgi $APP $DATA/sessions $DATA/ratelimit

# --- install like a real server: code root:www-data (read-only for PHP), data owned by www-data, .env 640
for d in bin src config views public; do cp -r "$ROOT/$d" $APP/; done
KEY=$(php "$ROOT/bin/generate-key.php")
cat > $APP/.env <<ENV
APP_ENV=production
APP_DEBUG=false
APP_URL=https://127.0.0.1:8443
APP_KEY=$KEY
WILDDUCK_API_URL=http://127.0.0.1:18080
WILDDUCK_API_TOKEN=svc-token
WILDDUCK_TIMEOUT=5
SESSION_COOKIE_SECURE=true
STORAGE_PATH=$DATA
MAX_ATTACHMENT_MB=20
MAX_MESSAGE_MB=25
ENV
chown -R root:www-data $APP; chmod -R u=rwX,g=rX,o= $APP; chmod 640 $APP/.env; chown -R www-data:www-data $DATA; chmod 700 $DATA/sessions $DATA/ratelimit

# --- fake WildDuck (test double)
FAKE_WD_STATE=$W/wd.json php -S 127.0.0.1:18080 "$ROOT/tests/Support/fake_wildduck.php" >$W/wd.log 2>&1 & echo $! > $W/wd.pid

# --- php-fpm: real pool + ini, only socket/log paths changed
cp "$ROOT/deploy/php-webmail.ini" $W/conf.d/99-webmail.ini
sed -e "s#^listen = .*#listen = $W/webmail.sock#" -e "s#/var/log/php8.3-fpm-webmail.log#$W/php-errors.log#" "$ROOT/deploy/php-fpm-pool.conf" > $W/pool.conf
printf '[global]\npid = %s/fpm.pid\nerror_log = %s/fpm.log\ndaemonize = no\ninclude = %s/pool.conf\n' $W $W $W > $W/fpm.conf
PHP_INI_SCAN_DIR=/etc/php/8.3/fpm/conf.d:$W/conf.d php-fpm8.3 -y $W/fpm.conf -F >$W/fpm.out 2>&1 &

# --- nginx: real site config, only ports/paths changed
openssl req -x509 -newkey rsa:2048 -nodes -days 2 -subj "/CN=127.0.0.1" -keyout $W/key.pem -out $W/cert.pem >/dev/null 2>&1
printf 'upstream webmail_php { server unix:%s/webmail.sock; }\n' $W > $W/upstream.conf
sed -e "s#include /etc/nginx/webmail-upstream.conf;#include $W/upstream.conf;#" -e 's#listen 80;#listen 8080;#' -e '/listen \[::\]/d' -e 's#listen 443 ssl http2;#listen 8443 ssl http2;#' \
    -e "s#/etc/letsencrypt/live/mail.example.org/fullchain.pem#$W/cert.pem#" -e "s#/etc/letsencrypt/live/mail.example.org/privkey.pem#$W/key.pem#" \
    -e 's#include fastcgi_params;#include /etc/nginx/fastcgi_params;#' "$ROOT/deploy/nginx.conf" > $W/site.conf
cat > $W/nginx.conf <<NG
user www-data; worker_processes 1; pid $W/nginx.pid; error_log $W/nginx-error.log;
events { worker_connections 64; }
http { include /etc/nginx/mime.types; default_type application/octet-stream; access_log $W/nginx-access.log;
  client_body_temp_path $W/body; fastcgi_temp_path $W/fcgi; proxy_temp_path $W/proxy; uwsgi_temp_path $W/uwsgi; scgi_temp_path $W/scgi;
  include $W/site.conf; }
NG
nginx -t -c $W/nginx.conf 2>&1 | sed 's/^/[nginx -t] /'
nginx -c $W/nginx.conf -g 'daemon off;' >$W/nginx.out 2>&1 &
for i in $(seq 1 50); do curl -sk https://127.0.0.1:8443/health >/dev/null 2>&1 && break; sleep 0.2; done

echo "=== smoke test ==="; "$ROOT/deploy/smoke.sh" https://127.0.0.1:8443 --insecure --http-url http://127.0.0.1:8080; SMOKE=$?

echo "=== end-to-end through nginx + php-fpm ==="; E2E=0
B=https://127.0.0.1:8443; J=$W/jar; C=(curl -sk --max-time 60 -b $J -c $J)
ok() { printf '  [ OK ] %s\n' "$1"; }; no() { printf '  [FAIL] %s\n' "$1"; E2E=1; }
tok() { "${C[@]}" "$B$1" | grep -o 'name="_token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
T=$(tok /login); code=$("${C[@]}" -o /dev/null -w '%{http_code}' -d "_token=$T&username=alice@example.com&password=correct-pass" $B/login)
[ "$code" = 303 ] && ok "sign-in works through nginx -> php-fpm -> WildDuck API" || no "sign-in returned $code"
body=$("${C[@]}" $B/mail/inbox); echo "$body" | grep -q 'Weekly report' && ok "inbox renders real messages" || no "inbox did not render"
T=$(tok /compose)
f1=$W/a1.bin; head -c 1048576 /dev/urandom > $f1
resp=$("${C[@]}" -o /dev/null -w '%{http_code}' -F "_token=$T" -F "to=bob@example.org" -F "subject=1MB" --form-string "html=<p>x</p>" -F "do=send" -F "attachments[]=@$f1;filename=a.bin" $B/compose)
[ "$resp" = 303 ] && ok "1 MB attachment is sent" || no "1 MB attachment returned $resp"
f2=$W/a26.bin; head -c $((26*1048576)) /dev/urandom > $f2; T=$(tok /compose)
out=$("${C[@]}" -w '\n%{http_code}' -F "_token=$T" -F "to=bob@example.org" --form-string "html=<p>x</p>" -F "do=send" -F "attachments[]=@$f2;filename=big.bin" $B/compose)
echo "$out" | grep -q 'larger than this server allows' && ok "26 MB file: friendly PHP limit message, form kept ($(echo "$out" | tail -1))" || no "26 MB file: unexpected response ($(echo "$out" | tail -1))"
f3=$W/a31.bin; head -c $((31*1048576)) /dev/urandom > $f3; T=$(tok /compose)
out=$("${C[@]}" -w '\n%{http_code}' -F "_token=$T" -F "to=bob@example.org" -F "do=send" -F "attachments[]=@$f3;filename=huge.bin" $B/compose)
[ "$(echo "$out" | tail -1)" = 413 ] && echo "$out" | grep -qi 'too large' && ok "31 MB request: nginx 413 with the friendly page" || no "31 MB request: $(echo "$out" | tail -1)"
code=$("${C[@]}" -o /dev/null -w '%{http_code}' -d "_token=$T" $B/logout); [ "$code" = 303 ] && ok "sign-out works" || no "sign-out returned $code"
echo "  session files in $DATA/sessions: $(ls $DATA/sessions | wc -l); permissions: $(stat -c '%U:%G %a' $DATA/sessions)"
echo "=== config check as the web user ==="
su -s /bin/bash www-data -c "php $APP/bin/check-config.php --env-file=$APP/.env --php-ini=$W/conf.d/99-webmail.ini" ; CHK=$?
echo "=== logs ==="; echo "php errors:"; cat $W/php-errors.log 2>/dev/null | tail -5; echo "nginx errors:"; grep -v 'client intended to send too large body' $W/nginx-error.log | tail -5
[ $SMOKE -eq 0 ] && [ $E2E -eq 0 ] && [ $CHK -eq 0 ] && echo "VERIFY-LOCAL: ALL OK" || { echo "VERIFY-LOCAL: FAILURES (smoke=$SMOKE e2e=$E2E check=$CHK)"; exit 1; }
