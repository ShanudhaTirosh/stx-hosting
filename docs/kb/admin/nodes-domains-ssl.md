---
title: Nodes, domains and HTTPS
summary: Adding servers, wildcard DNS, certificates and the GitHub App.
order: 4
---
## Nodes

Owner and admins can add nodes. Only the owner can remove one, and only after it has no resources. Customers can never add or import a server. The node terminal, restart and file pages are owner-only; admins see read-only stats. Move a workload between nodes with the resource's destination switch.

## Wildcard subdomains

1. Create a DNS record `*.apps.example.com` pointing at your ingress.
2. In `.env` set `HOSTING_APPS_DOMAIN=apps.example.com` and `HOSTING_INGRESS_IP=<ip>`.
3. In the server settings set the **Wildcard Domain** to `https://apps.example.com`.

New client apps get `<app-name>.apps.example.com`, unique and never a reserved word.

## Certificates

Wildcard certificates need the **DNS challenge**, not the HTTP one. Configure the proxy's Let's Encrypt DNS-01 resolver with a Cloudflare API token limited to your zone. Keep customer-facing records **DNS only** in Cloudflare; a proxied record breaks normal certificate issuance for custom domains.

## Custom domains

Customers prove ownership with a TXT record at `_stx-verify.<domain>`, then point an A record at the ingress IP. A verified domain belongs to one account. Apps can only use our wildcard names, generated hostnames and verified domains.

## GitHub App

Create the GitHub App under **Sources** and tick **system wide** so customers can use it for their repositories.
