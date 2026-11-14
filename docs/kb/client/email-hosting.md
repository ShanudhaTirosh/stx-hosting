---
title: Email Hosting & Webmail Guide
summary: How to set up custom email domains, create mailboxes, configure DNS, and use STX Webmail.
audience: client
order: 5
---

STX Email Hosting provides secure, business-grade email on your own domain with pooled storage, spam filtering, custom aliases, and webmail access.

---

## 1. Quickstart: 4 Steps to Your Custom Email

1. **Order an Email Hosting Plan**: Go to **Email Hosting** on the storefront or portal, select a plan (Starter, Basic, Pro, or Business), and complete payment.
2. **Add Your Domain**: In your customer portal, navigate to **Mail** (`/portal/mail`) and click **Add Domain** (e.g. `yourcompany.com`).
3. **Configure DNS Records**: Add the MX, SPF, DKIM, and DMARC records shown on your screen at your domain registrar or DNS provider (Cloudflare, Namecheap, GoDaddy).
4. **Create Your First Mailbox**: Click **New Mailbox**, enter your desired address (e.g. `hello@yourcompany.com`), set a secure password, and allocate mailbox storage.

---

## 2. Managing Mailboxes and Aliases

### Mailbox Storage Quotas
Each email plan includes a **pooled storage bucket** shared across all your mailboxes:
- **Starter**: 5 GB Pooled Storage, up to 2 Mailboxes.
- **Basic**: 15 GB Pooled Storage, up to 5 Mailboxes.
- **Pro**: 50 GB Pooled Storage, up to 15 Mailboxes.
- **Business**: 100 GB Pooled Storage, up to 50 Mailboxes.

You can allocate storage per mailbox (for example, give 10 GB to `ceo@yourcompany.com` and 2 GB to `sales@yourcompany.com`). You can change allocations anytime as long as the total fits within your plan's pooled storage.

### Email Aliases (Forwarders)
An alias lets you receive email sent to an alternate address (like `billing@yourcompany.com`) directly into your primary mailbox:
1. In the **Mail Dashboard**, click **Aliases** $\to$ **New Alias**.
2. Select your domain, enter the alias prefix, and choose the destination mailbox.
3. Emails sent to the alias arrive in your inbox with standard delivery tags.

---

## 3. Accessing Your Email

### Webmail Client
STX Hosting includes **STX Webmail**, a clean, fast webmail interface:
- Click **Open Webmail** next to any mailbox in the portal, or visit your server's webmail URL directly.
- Sign in with your full email address (`you@yourcompany.com`) and mailbox password.
- Features include thread conversations, folder management, contact suggestions, draft autosaving, and desktop notifications.

### Connecting Mail Apps (IMAP / SMTP)
You can connect your desktop or mobile email apps (Apple Mail, Microsoft Outlook, Mozilla Thunderbird, iOS Mail, Android):

| Setting | Value |
|---|---|
| **Incoming Server (IMAP)** | `mail.<your-hosting-domain>.com` |
| **IMAP Port / Security** | Port `993` with SSL/TLS |
| **Outgoing Server (SMTP)** | `mail.<your-hosting-domain>.com` |
| **SMTP Port / Security** | Port `465` (SSL/TLS) or Port `587` (STARTTLS) |
| **Username** | Your full email address (e.g. `you@yourcompany.com`) |
| **Password** | Your mailbox password |

---

## 4. Required DNS Records

To guarantee your emails are delivered and not marked as spam, add these 4 records to your DNS:

1. **MX Record**:
   - Host: `@`
   - Value: `mail.<your-hosting-domain>.com`
   - Priority: `10`
2. **SPF Record (TXT)**:
   - Host: `@`
   - Value: `v=spf1 mx include:<your-hosting-domain>.com ~all`
3. **DKIM Record (TXT)**:
   - Host: `s2026._domainkey`
   - Value: (Copy the exact cryptographic key shown in your Mail Dashboard)
4. **DMARC Record (TXT)**:
   - Host: `_dmarc`
   - Value: `v=DMARC1; p=quarantine; pct=100; rua=mailto:postmaster@yourcompany.com`

Once added, click **Verify DNS** in your portal. Status badges will turn green once DNS propagation is confirmed.

