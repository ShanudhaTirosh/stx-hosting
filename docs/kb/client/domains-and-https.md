---
title: Domains and HTTPS
summary: Your free instant address, and how to attach your own custom domain with automated SSL.
order: 5
---
Every application deployed on STX Hosting is exposed securely over HTTPS without requiring complex manual proxy configuration.

## Free Instant Address

Every app automatically receives an instant address like `https://your-app-name.apps.<our-domain>` upon deployment:
- **Zero DNS Setup**: Uses wildcard DNS and wildcard SSL certificates.
- **Immediate Live Access**: Ready the exact second your container starts.
- **Collision Protection**: If a name is already taken, a short unique suffix is assigned automatically.

## Using Your Own Custom Domain

You can brand your application with any domain you own (e.g. `mybrand.com` or `blog.mybrand.com`):

1. Open **Domains** in the portal (`/portal/domains`).
2. Enter your domain name and click **Add Domain**.
3. The platform displays an exact, copyable DNS instruction card:
   - **For Subdomains** (`blog.mybrand.com`): Create a `CNAME` record pointing to our Ingress host.
   - **For Root / Apex Domains** (`mybrand.com`): Create an `A` record pointing to our Ingress IP.
4. **Automated Verification**: Our background system automatically polls your DNS records every 30 seconds.
5. As soon as your records point to STX Hosting, the status changes to **HTTPS Secured** and an automated SSL certificate is issued and auto-renewed.
6. Open your application in the portal and add your verified domain under **Custom Domains**.

## Security & Ownership Protection

- **Takeover Prevention**: Once a domain is verified by your account, no other team or user can claim it without cryptographic DNS proof (`stx-verify` TXT record).
- **Auto-Renewal**: Certificates automatically renew 30 days before expiration.
- **Cloudflare / Proxies**: If your domain uses Cloudflare, set your DNS record to **DNS only** (grey cloud) during initial SSL issuance.
