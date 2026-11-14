# scripts/bootstrap-selfhosted.ps1
# PowerShell Bootstrap Script for Self-Hosted Supabase, WildDuck Mail & Automated SSL

param(
    [string]$Domain = "ourdomain.com",
    [string]$AcmeEmail = "admin@ourdomain.com",
    [switch]$Start
)

$envFile = ".env.selfhosted"

Write-Host "=============================================================================="
Write-Host "STX Hosting: Bootstrapping Self-Hosted Supabase & WildDuck Stack (PowerShell)"
Write-Host "Target Domain: $Domain"
Write-Host "=============================================================================="

function Get-RandomHex([int]$bytes = 32) {
    $buffer = New-Object byte[] $bytes
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($buffer)
    return -join ($buffer | ForEach-Object { "{0:x2}" -f $_ })
}

function Get-RandomBase64([int]$bytes = 32) {
    $buffer = New-Object byte[] $bytes
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($buffer)
    return [Convert]::ToBase64String($buffer)
}

function Create-SignedJwt([string]$role, [string]$secret) {
    $iat = [DateTimeOffset]::UtcNow.ToUnixTimeSeconds()
    $exp = $iat + 315360000

    $headerJson = '{"alg":"HS256","typ":"JWT"}'
    $payloadJson = "{`"role`":`"$role`",`"iss`":`"supabase`",`"iat`":$iat,`"exp`":$exp}"

    $b64Header = [Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes($headerJson)).TrimEnd('=').Replace('+','-').Replace('/','_')
    $b64Payload = [Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes($payloadJson)).TrimEnd('=').Replace('+','-').Replace('/','_')
    $unsigned = "$b64Header.$b64Payload"

    $hmac = New-Object System.Security.Cryptography.HMACSHA256
    $hmac.Key = [System.Text.Encoding]::UTF8.GetBytes($secret)
    $signature = [Convert]::ToBase64String($hmac.ComputeHash([System.Text.Encoding]::UTF8.GetBytes($unsigned))).TrimEnd('=').Replace('+','-').Replace('/','_')

    return "$unsigned.$signature"
}

Write-Host "1. Generating cryptographic keys..."
$postgresPassword = Get-RandomHex 16
$jwtSecret = Get-RandomHex 32
$realtimeSecret = Get-RandomHex 32
$wildduckSecret = Get-RandomHex 16
$wildduckApiToken = Get-RandomHex 16
$webmailAppKey = Get-RandomBase64 32
$studioPassword = Get-RandomHex 8

$anonKey = Create-SignedJwt "anon" $jwtSecret
$serviceRoleKey = Create-SignedJwt "service_role" $jwtSecret

Write-Host "2. Writing configuration to $envFile..."
$content = @"
# Self-Hosted Stack Environment Configuration
DOMAIN=$Domain
ACME_EMAIL=$AcmeEmail
SITE_URL=https://app.$Domain
SUPABASE_PUBLIC_URL=https://api.$Domain
ADDITIONAL_REDIRECT_URLS=https://app.$Domain/auth/callback,https://localhost:8000/auth/callback

# Supabase Keys
POSTGRES_PASSWORD=$postgresPassword
SUPABASE_JWT_SECRET=$jwtSecret
SUPABASE_ANON_KEY=$anonKey
SUPABASE_SERVICE_ROLE_KEY=$serviceRoleKey
REALTIME_SECRET_KEY_BASE=$realtimeSecret

# Supabase Studio Credentials
STUDIO_USER=admin
STUDIO_PASSWORD=$studioPassword
STUDIO_BCRYPT_PASSWORD=`$2a`$14`$Z1kH4yL3k.7e5uF2q6F6j.4yO9U6E0wW8C2k2i3P4w6r8t0y2u4e

# WildDuck Mail Stack
MAIL_HOSTNAME=mail.$Domain
WILDDUCK_SECRET=$wildduckSecret
WILDDUCK_API_TOKEN=$wildduckApiToken
WEBMAIL_APP_KEY=$webmailAppKey
SMTP_HOST=mail.$Domain
SMTP_PORT=587
SMTP_USER=no-reply@$Domain
SMTP_PASS=$postgresPassword

# Outbound Relay Fallback
OUTBOUND_RELAY_ENABLED=false
OUTBOUND_RELAY_HOST=smtp.sendgrid.net
OUTBOUND_RELAY_PORT=587
OUTBOUND_RELAY_USER=
OUTBOUND_RELAY_PASS=
"@

Set-Content -Path $envFile -Value $content -Encoding UTF8

Write-Host "=============================================================================="
Write-Host "Bootstrap Complete! Credentials saved to $envFile"
Write-Host "Edge Endpoints:"
Write-Host "  - API Gateway:    https://api.$Domain"
Write-Host "  - Supabase Studio:https://studio.$Domain (User: admin, Pass: $studioPassword)"
Write-Host "  - Webmail UI:     https://webmail.$Domain"
Write-Host "  - Client Portal:  https://app.$Domain"
Write-Host "=============================================================================="
Write-Host "To launch all platform services:"
Write-Host "  docker compose -f docker-compose.selfhosted.yml --env-file $envFile up -d --build"
Write-Host "=============================================================================="

if ($Start) {
    Write-Host "Launching platform stack via Docker Compose..."
    docker compose -f docker-compose.selfhosted.yml --env-file $envFile up -d --build
}

