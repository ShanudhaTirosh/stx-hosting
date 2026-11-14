---
title: Complete Guide to Business Email Hosting with Custom Domains and High Deliverability
summary: Learn how to set up corporate email addresses on your custom domain with modern webmail, IMAP/SMTP client sync, and full SPF, DKIM, and DMARC deliverability.
author: STX Cloud Engineering
date: 2026-11-12
read_time: 5 min read
keywords: business email hosting, custom domain email sri lanka, wildduck webmail, spf dkim dmarc setup
category: Email & Productivity
---

# Complete Guide to Business Email Hosting with Custom Domains and High Deliverability

Using generic `@gmail.com` or `@yahoo.com` email addresses for commercial business damages customer trust and hurts brand credibility. Professional organizations require email addresses on their own custom domains (e.g., `contact@yourcompany.lk` or `sales@yourcompany.com`).

STX Hosting delivers private, enterprise-grade email hosting powered by the self-hosted **WildDuck Mail Server**, Haraka high-throughput SMTP, Dovecot IMAP, and containerized STX Webmail.

---

## Direct Answer: 4 Steps to Professional Domain Email

1. **Choose an Email Plan**: Pick a pooled storage plan on [STX Email Hosting](https://shanutechx.com/email-hosting) (Starter 500MB, Basic 1GB, Pro 3GB, or Business 5GB).
2. **Add Your Domain**: Enter your domain in the portal to receive your unique MX, SPF, and 2048-bit DKIM DNS keys.
3. **Configure DNS Records**: Add the provided records to your domain registrar (Cloudflare, GoDaddy, or Namecheap).
4. **Create Team Mailboxes**: Provision mailboxes for your team and sign in immediately via STX Webmail or configure your mobile/desktop mail clients.

---

## Deliverability Checklist: SPF, DKIM, and DMARC

To guarantee your messages hit the primary inbox rather than spam folders, STX automatically generates authenticated records:

| Record Type | Host | Target / Value | Purpose |
|---|---|---|---|
| **MX** | `@` | `mail.shanutechx.com` (Priority 10) | Directs inbound mail to STX Haraka filters |
| **TXT (SPF)** | `@` | `v=spf1 include:_spf.shanutechx.com ~all` | Authorizes STX outbound mail servers |
| **TXT (DKIM)**| `wildduck._domainkey` | `v=DKIM1; k=rsa; p=MIIBIjAN...` | Cryptographically signs outgoing emails |
| **TXT (DMARC)**| `_dmarc` | `v=DMARC1; p=quarantine; pct=100;` | Prevents email spoofing and phishing |

---

## Pooled Storage vs Per-User Licensing

Traditional enterprise email providers charge \$6 to \$18 per user every month. If you have 10 employees who each receive a few dozen emails a week, you end up paying \$100+ monthly for storage you never use.

**STX Hosting uses pooled team storage**:
- One flat monthly subscription covers all your team mailboxes.
- You can allocate custom per-mailbox storage caps (e.g., 2GB for the CEO, 250MB for general inquiries).
- Add-on storage packs can be attached at any time.
