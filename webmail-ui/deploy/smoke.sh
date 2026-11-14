#!/usr/bin/env bash
# Post-deploy smoke test:  deploy/smoke.sh https://mail.example.org [--insecure] [--http-url http://mail.example.org]
# Checks headers, static caching, that nothing sensitive is reachable, and the upload limit. Exits 1 on any failure.
set -u
BASE="${1:-}"; shift || true
[ -z "$BASE" ] && { echo "usage: $0 BASE_URL [--insecure] [--http-url URL] [--max-upload-mb N]"; exit 2; }
CURL=(curl -sS --max-time 30); HTTP_URL=""; MAXMB=30
while [ $# -gt 0 ]; do case "$1" in --insecure) CURL+=(-k);; --http-url) HTTP_URL="$2"; shift;; --max-upload-mb) MAXMB="$2"; shift;; esac; shift; done
fail=0; pass() { printf '  [ OK ] %s\n' "$1"; }; bad() { printf '  [FAIL] %s\n' "$1"; fail=1; }
status() { "${CURL[@]}" -o /dev/null -w '%{http_code}' "$@" 2>/dev/null; }
hdrs() { "${CURL[@]}" -D - -o /dev/null "$@" 2>/dev/null | tr -d '\r'; }
expect_status() { local want="$1" label="$2"; shift 2; local got; got=$(status "$@"); [[ " $want " == *" $got "* ]] && pass "$label ($got)" || bad "$label: expected $want, got $got"; }

echo "Webmail smoke test: $BASE"
body=$("${CURL[@]}" "$BASE/health"); [ "$body" = '{"ok":true}' ] && pass "/health answers ok" || bad "/health body: $body"
H=$(hdrs "$BASE/login")
for h in 'content-security-policy:' 'x-frame-options: deny' 'x-content-type-options: nosniff' 'referrer-policy: no-referrer' 'cache-control:.*no-store'; do
  echo "$H" | grep -qi "^$h" && pass "login page header: $h" || bad "login page is missing header: $h"; done
echo "$H" | grep -qi '^set-cookie:.*httponly' && pass "session cookie is HttpOnly" || bad "session cookie is not HttpOnly"
echo "$H" | grep -qi '^set-cookie:.*samesite=lax' && pass "session cookie is SameSite=Lax" || bad "session cookie lacks SameSite"
[[ "$BASE" == https://* ]] && { echo "$H" | grep -qi '^set-cookie:.*; secure' && pass "session cookie is Secure" || bad "session cookie is not Secure over HTTPS"; echo "$H" | grep -qi '^strict-transport-security:' && pass "HSTS is sent" || bad "HSTS missing"; }
echo "$H" | grep -qiE '^server: .*[0-9]+\.[0-9]+' && bad "server version is disclosed: $(echo "$H" | grep -i '^server:')" || pass "server version is not disclosed"
echo "$H" | grep -qi '^x-powered-by:' && bad "X-Powered-By is exposed" || pass "X-Powered-By is hidden"

A=$(hdrs -H 'Accept-Encoding: gzip' "$BASE/assets/app.css")
echo "$A" | head -1 | grep -q ' 200' && pass "static asset is served" || bad "static asset not served"
echo "$A" | grep -qi '^cache-control:.*max-age' && pass "static asset is cacheable" || bad "static asset has no cache header"
echo "$A" | grep -qi '^content-encoding: gzip' && pass "static text is compressed" || bad "static text is not compressed"
echo "$A" | grep -qi '^x-content-type-options: nosniff' && pass "static asset has nosniff" || bad "static asset lacks nosniff"

expect_status "404" "index.php cannot be requested directly" "$BASE/index.php"
expect_status "404" "other .php files are not executable/served" "$BASE/assets/x.php"
expect_status "403 404" ".env is not served" "$BASE/.env"
expect_status "403 404" ".git is not served" "$BASE/.git/config"
expect_status "403 404" "dot-files under assets are not served" "$BASE/assets/.env"
expect_status "403 404" "path traversal to .env is blocked" --path-as-is "$BASE/assets/../.env"
expect_status "404" "source tree is not reachable (src)" "$BASE/src/App.php"
expect_status "404" "source tree is not reachable (config)" "$BASE/config/routes.php"
expect_status "404" "storage is not reachable" "$BASE/storage/sessions/"
expect_status "404" "directory listing of assets is off" "$BASE/assets/"
expect_status "303" "protected pages redirect to sign-in" "$BASE/mail/inbox"
loc=$(hdrs "$BASE/mail/inbox" | grep -i '^location:' | tr -d ' ' ); [[ "$loc" == *"/login" ]] && pass "redirect target is /login" || bad "unexpected redirect: $loc"
expect_status "403 404 405 501" "TRACE is refused" -X TRACE "$BASE/login"
expect_status "404" "unknown path is a clean 404" "$BASE/definitely-not-here"
big=$(mktemp); head -c $(( (MAXMB + 1) * 1048576 )) /dev/zero > "$big"
code=$(status -X POST --data-binary @"$big" -H 'Content-Type: application/octet-stream' "$BASE/compose"); rm -f "$big"
[ "$code" = "413" ] && pass "request over ${MAXMB} MB is refused (413)" || bad "oversized request returned $code, expected 413"
page=$("${CURL[@]}" -X POST --data-binary @<(head -c $(( (MAXMB + 1) * 1048576 )) /dev/zero) -H 'Content-Type: application/octet-stream' "$BASE/compose" 2>/dev/null); echo "$page" | grep -qi 'too large' && pass "413 shows a friendly page" || bad "413 page is not friendly"
if [ -n "$HTTP_URL" ]; then
  expect_status "301" "HTTP redirects to HTTPS" "$HTTP_URL/login"
  hdrs "$HTTP_URL/login" | grep -qi '^location: https://' && pass "redirect goes to https" || bad "redirect target is not https"
fi
echo; [ $fail -eq 0 ] && echo "All checks passed." || echo "Some checks FAILED."; exit $fail
