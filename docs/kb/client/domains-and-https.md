---
title: Domains and HTTPS
summary: Your free address, and how to use your own domain with a certificate.
order: 5
---
## Your free address

Every app gets an address like `your-app-name.<our-apps-domain>` with HTTPS, with nothing to set up. Some names are reserved (for example `admin`, `api`, `mail`), and each address can belong to one app.

## Using your own domain

1. Open **Domains** and add your domain, for example `example.com`.
2. **Prove you own it.** At your DNS provider add the TXT record shown there, then press **Check now**. DNS changes can take a few minutes.
3. **Point it at us.** Add an A record for the domain to the IP address shown on the Domains page.
4. Set the domain on your app. We issue the HTTPS certificate automatically the first time someone visits.

## Rules

- Only a domain you have proven you own can be used. A domain already verified by another account cannot be claimed.
- A verified domain covers its subdomains, such as `www.example.com`.
- You cannot remove a domain while an app still uses it.
- If your DNS is behind a proxy such as Cloudflare's orange cloud, certificates may fail to issue. Set the record to **DNS only**.
