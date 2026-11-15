# Nginx, Gateway & Proxy Troubleshooting Guide

This guide details root causes, performance tuning, and diagnostic runbooks for Nginx 502 Bad Gateway, 504 Gateway Timeout, 499 Client Closed Request, and WebSocket connection issues across STX Cloud Platform.

---

## 1. Quick Diagnostic Commands

```bash
# Test Nginx configuration syntax
docker exec coolify nginx -t

# Gracefully reload Nginx workers
docker exec coolify nginx -s reload

# Tail Nginx access and error streams
docker exec coolify tail -f /dev/stdout
docker exec coolify tail -f /dev/stderr

# Inspect active PHP-FPM pool status
docker exec coolify php-fpm-healthcheck
```

---

## 2. Error Signatures & Root Cause Analysis

### 2.1 HTTP 502 Bad Gateway

| Symptom / Log Signature | Root Cause | Solution |
| :--- | :--- | :--- |
| `upstream sent too big header while reading response header from upstream` | Large session cookies, extensive Livewire payloads, or verbose response headers exceed the default 4k/8k FastCGI buffer size. | Tune `fastcgi_buffer_size 64k;` and `fastcgi_buffers 16 32k;`. |
| `connect() failed (111: Connection refused) while connecting to upstream` | PHP-FPM master process stopped or restarted due to OOM kill or segmentation fault. | Verify PHP memory limits (`memory_limit = 256M`), check `/dev/stderr` for segfaults, and confirm `127.0.0.1:9000` is listening. |
| `recv() failed (104: Connection reset by peer) while reading response header` | PHP script exceeded memory limit or execution time and was forcefully terminated by PHP-FPM supervisor. | Inspect `php-fpm.conf` `process_control_timeout` and optimize memory-intensive database queries. |

### 2.2 HTTP 504 Gateway Timeout

| Symptom / Log Signature | Root Cause | Solution |
| :--- | :--- | :--- |
| `upstream timed out (110: Connection timed out) while reading response from upstream` | Long-running queries, database locks, or synchronous external API calls (e.g., DNS verification or provider syncs). | Set `fastcgi_read_timeout 300s;` for long tasks; offload heavy jobs to Laravel Horizon background queues. |
| Reverse proxy (Caddy / Cloudflare) returns 504 while Nginx is still processing | Outer reverse proxy timeout is lower than internal container timeout. | Align timeout chain: Cloudflare (100s default) -> Caddy (300s) -> Nginx (300s) -> PHP-FPM (`request_terminate_timeout = 300s`). |

### 2.3 HTTP 499 Client Closed Request

| Symptom / Log Signature | Root Cause | Solution |
| :--- | :--- | :--- |
| Status code `499` logged in access log with zero-byte response | The browser or API client canceled the request before Nginx and PHP finished generating the response. | Usually caused by slow responses (>5s) causing users to click reload. Optimize N+1 Eloquent queries, add caching via Redis, and eliminate redundant API calls. |

### 2.4 WebSocket Disconnects & Dropped Terminal Sessions

| Symptom / Log Signature | Root Cause | Solution |
| :--- | :--- | :--- |
| Laravel Reverb (port 6001) or Node.js XTerm Terminal (port 6002) drops exactly every 60 seconds | Nginx defaults `proxy_read_timeout` to 60s for proxy_pass upstreams. | Configure `proxy_read_timeout 86400s;` and `proxy_send_timeout 86400s;` with `proxy_buffering off;` on WebSocket endpoints (`/app/` and `/terminal/ws`). |
| WebSocket handshake fails with `400 Bad Request` | Missing or static `Connection` header when clients negotiate protocol switch. | Use `proxy_set_header Upgrade $http_upgrade;` and `proxy_set_header Connection "Upgrade";` with `proxy_http_version 1.1;`. |

---

## 3. Tuned FastCGI & Proxy Directives

Below is the production-tested directive block applied in `docker/*/etc/nginx/site-opts.d/http.conf`:

```nginx
# WebSockets: Reverb (6001) and Terminal Daemon (6002)
location /app/ {
    proxy_pass http://127.0.0.1:6001;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
    proxy_connect_timeout 60s;
    proxy_read_timeout 86400s;
    proxy_send_timeout 86400s;
    proxy_buffering off;
}

location = /terminal/ws {
    proxy_pass http://127.0.0.1:6002;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
    proxy_connect_timeout 60s;
    proxy_read_timeout 86400s;
    proxy_send_timeout 86400s;
    proxy_buffering off;
}

# PHP-FPM FastCGI Handler
location ~ \.php$ {
    fastcgi_pass   127.0.0.1:9000;
    fastcgi_index  index.php;
    fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include        fastcgi_params;

    # Buffer tuning
    fastcgi_buffers 16 32k;
    fastcgi_buffer_size 64k;
    fastcgi_busy_buffers_size 64k;
    fastcgi_temp_file_write_size 64k;

    # Timeout chain
    fastcgi_connect_timeout 60s;
    fastcgi_send_timeout 300s;
    fastcgi_read_timeout 300s;

    # Shield error handling
    fastcgi_intercept_errors on;
}
```

---

## 4. Branded Gateway Error Shield

When upstream PHP-FPM or backend services restart or become momentarily unavailable, Nginx intercepts HTTP 502, 503, and 504 and presents `public/50x.html`:
- Styled using the STX Design System (`Familjen Grotesk`, `Instrument Sans`, `#0b2239` Ink palette).
- Includes one-click connection retry button and direct link to the public status page (`/status`).
- Automatically retries connection after 10 seconds.
