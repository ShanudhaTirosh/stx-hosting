<?php

return [
    /*
     | When true, clients can only create and deploy apps, databases and services if their
     | team has an active entitlement (a paid, approved plan) and is within its limits.
     | Platform staff and the root team are never restricted. Turn off only for local development.
     */
    'enforce_entitlements' => env('HOSTING_ENFORCE_ENTITLEMENTS', true),

    /*
     | Every client app gets <app-name>.<apps_domain>. Point a wildcard DNS record
     | (*.apps.example.com) at your ingress and set the same value as the server's wildcard domain.
     | Leave empty to keep Coolify's default generated hostnames.
     */
    'apps_domain' => env('HOSTING_APPS_DOMAIN'),

    // Public IP of the ingress, shown to clients as the A record for custom domains.
    'ingress_ip' => env('HOSTING_INGRESS_IP'),

    // Hostnames Coolify generates on its own (IP based); always allowed for client apps.
    'generated_host_suffixes' => ['sslip.io'],

    // Subdomain names clients can never take.
    'reserved_subdomains' => [
        'www', 'mail', 'smtp', 'imap', 'pop', 'ftp', 'sftp', 'ssh', 'api', 'app', 'apps', 'admin', 'administrator',
        'root', 'dashboard', 'panel', 'portal', 'billing', 'support', 'help', 'status', 'docs', 'blog', 'store',
        'shop', 'cdn', 'static', 'assets', 'auth', 'login', 'account', 'secure', 'ns1', 'ns2', 'git', 'traefik',
        'coolify', 'stx', 'shanutechx', 'localhost',
    ],
];
