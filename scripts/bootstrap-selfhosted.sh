#!/usr/bin/env bash
set -euo pipefail

# ==============================================================================
# scripts/bootstrap-selfhosted.sh
# Bootstrap Script for Self-Hosted Supabase, WildDuck Mail & Automated SSL
# ==============================================================================

DOMAIN="${1:-ourdomain.com}"
ACME_EMAIL="${2:-admin@${DOMAIN}}"
ENV_FILE=".env.selfhosted"

echo "=============================================================================="
echo "STX Hosting: Bootstrapping Self-Hosted Supabase & WildDuck Stack"
echo "Target Domain: ${DOMAIN}"
echo "=============================================================================="

# Helper: Random string
generate_secret() {
    openssl rand -hex "${1:-32}"
}

# Helper: Random base64
generate_base64() {
    openssl rand -base64 "${1:-32}"
}

# Helper: Sign JWT using OpenSSL HMAC-SHA256
generate_jwt() {
    local role="$1"
    local secret="$2"
    local iat exp header payload unsigned_token signature

    iat=$(date +%s)
    exp=$((iat + 315360000)) # 10 years

    header=$(echo -n '{"alg":"HS256","typ":"JWT"}' | openssl base64 -e | tr -d '=' | tr '/+' '_-' | tr -d '\n')
    payload=$(echo -n "{\"role\":\"${role}\",\"iss\":\"supabase\",\"iat\":${iat},\"exp\":${exp}}" | openssl base64 -e | tr -d '=' | tr '/+' '_-' | tr -d '\n')
    unsigned_token="${header}.${payload}"

    signature=$(echo -n "${unsigned_token}" | openssl dgst -sha256 -hmac "${secret}" -binary | openssl base64 -e | tr -d '=' | tr '/+' '_-' | tr -d '\n')
    echo "${unsigned_token}.${signature}"
}

echo "1. Generating cryptographic keys and passwords..."
POSTGRES_PASSWORD=$(generate_secret 16)
JWT_SECRET=$(generate_secret 32)
REALTIME_SECRET_KEY_BASE=$(generate_secret 32)
WILDDUCK_SECRET=$(generate_secret 16)
WILDDUCK_API_TOKEN=$(generate_secret 16)
WEBMAIL_APP_KEY=$(generate_base64 32)
STUDIO_PASSWORD=$(generate_secret 8)
STUDIO_BCRYPT=$(openssl passwd -6 "${STUDIO_PASSWORD}")

ANON_KEY=$(generate_jwt "anon" "${JWT_SECRET}")
SERVICE_ROLE_KEY=$(generate_jwt "service_role" "${JWT_SECRET}")

echo "2. Writing configuration to ${ENV_FILE}..."
cat <<EOF > "${ENV_FILE}"
# Self-Hosted Stack Environment Configuration
DOMAIN=${DOMAIN}
ACME_EMAIL=${ACME_EMAIL}
SITE_URL=https://app.${DOMAIN}
SUPABASE_PUBLIC_URL=https://api.${DOMAIN}
ADDITIONAL_REDIRECT_URLS=https://app.${DOMAIN}/auth/callback,https://localhost:8000/auth/callback

# Supabase Keys
POSTGRES_PASSWORD=${POSTGRES_PASSWORD}
SUPABASE_JWT_SECRET=${JWT_SECRET}
SUPABASE_ANON_KEY=${ANON_KEY}
SUPABASE_SERVICE_ROLE_KEY=${SERVICE_ROLE_KEY}
REALTIME_SECRET_KEY_BASE=${REALTIME_SECRET_KEY_BASE}

# Supabase Studio Credentials
STUDIO_USER=admin
STUDIO_PASSWORD=${STUDIO_PASSWORD}
STUDIO_BCRYPT_PASSWORD=${STUDIO_BCRYPT}

# WildDuck Mail Stack
MAIL_HOSTNAME=mail.${DOMAIN}
WILDDUCK_SECRET=${WILDDUCK_SECRET}
WILDDUCK_API_TOKEN=${WILDDUCK_API_TOKEN}
WEBMAIL_APP_KEY=${WEBMAIL_APP_KEY}
SMTP_HOST=mail.${DOMAIN}
SMTP_PORT=587
SMTP_USER=no-reply@${DOMAIN}
SMTP_PASS=${POSTGRES_PASSWORD}

# Outbound Relay Fallback (Enable if host port 25 is blocked)
OUTBOUND_RELAY_ENABLED=false
OUTBOUND_RELAY_HOST=smtp.sendgrid.net
OUTBOUND_RELAY_PORT=587
OUTBOUND_RELAY_USER=
OUTBOUND_RELAY_PASS=
EOF

echo "3. Creating Docker bridge networks..."
docker network create supabase_net 2>/dev/null || true
docker network create mail_net 2>/dev/null || true
docker network create caddy_net 2>/dev/null || true
docker network create stx 2>/dev/null || true

echo "=============================================================================="
echo "Bootstrap Complete! Credentials saved to ${ENV_FILE}"
echo ""
echo "DNS Records Required:"
echo "------------------------------------------------------------------------------"
echo "A       api.${DOMAIN}             -> <SERVER_IP>"
echo "A       studio.${DOMAIN}          -> <SERVER_IP>"
echo "A       webmail.${DOMAIN}         -> <SERVER_IP>"
echo "A       mail.${DOMAIN}            -> <SERVER_IP>"
echo "A       app.${DOMAIN}             -> <SERVER_IP>"
echo "A       status.${DOMAIN}          -> <SERVER_IP>"
echo "MX      ${DOMAIN}                 -> mail.${DOMAIN} (Priority 10)"
echo "TXT     ${DOMAIN}                 -> v=spf1 mx a:mail.${DOMAIN} ~all"
echo "TXT     _dmarc.${DOMAIN}          -> v=DMARC1; p=quarantine; pct=100"
echo "------------------------------------------------------------------------------"
echo "Studio Access: https://studio.${DOMAIN}"
echo "Username:      admin"
echo "Password:      ${STUDIO_PASSWORD}"
echo "=============================================================================="
echo "To launch all platform services:"
echo "  docker compose -f docker-compose.selfhosted.yml --env-file ${ENV_FILE} up -d --build"
echo "=============================================================================="

