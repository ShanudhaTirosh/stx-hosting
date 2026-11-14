---
title: Deploy Next.js 15 Applications on Cloud PaaS with Auto-SSL and Custom Domains
summary: Step-by-step tutorial on deploying containerized Next.js full-stack applications with automatic Let's Encrypt SSL, zero downtime redeployments, and Git webhook triggers.
author: STX Cloud Engineering
date: 2026-11-13
read_time: 7 min read
keywords: deploy nextjs app, nextjs docker hosting, self-hosted vercel alternative, sri lanka app hosting
category: Application Deployment
---

# Deploy Next.js 15 Applications on Cloud PaaS with Auto-SSL and Custom Domains

Next.js has become the default React framework for building modern web applications. However, deploying Next.js to proprietary hyperscalers can lead to unpredictable billing spikes, serverless function cold starts, and steep vendor lock-in.

STX Cloud PaaS provides a **cost-predictable, high-performance container runtime** where you can deploy Next.js 15 with Server-Side Rendering (SSR), API routes, and static generation directly from your GitHub repository.

---

## Direct Answer: How to Deploy Next.js on STX Hosting

1. **Connect GitHub**: Authenticate your GitHub account or link your repository in the STX Portal.
2. **Choose Deployment Template**: Select the official **Next.js Standalone** template or point to your repository containing a standard `Dockerfile`.
3. **Configure Environment Variables**: Enter your database connection strings, auth secrets, and public environment keys.
4. **Trigger Build**: Click **Deploy**. STX Hosting builds the container, allocates dedicated CPU and memory bounds via Linux cgroups, and publishes the app.
5. **Attach Custom Domain**: Map your custom domain (`app.yourdomain.com`). Traefik automatically issues a trusted Let's Encrypt TLS certificate.

---

## Architecture: How STX PaaS Runs Next.js

```
┌─────────────────┐       ┌─────────────────┐       ┌────────────────────────┐
│  Git Push to    │ ----> │ STX Build Engine│ ----> │ Traefik Reverse Proxy  │
│  main branch    │       │ Dockerfile Build│       │ Automated Let's Encrypt│
└─────────────────┘       └─────────────────┘       └───────────┬────────────┘
                                                                │
                                                                ▼
                                                    ┌────────────────────────┐
                                                    │ Next.js Node.js Server │
                                                    │ Isolated cgroups Quota │
                                                    └────────────────────────┘
```

---

## Key Benefits of Self-Hosted Container PaaS

- **Zero Cold Starts**: Your Node.js server stays warm in persistent container memory, delivering sub-50ms Time to First Byte (TTFB).
- **Persistent WebSockets & Background Tasks**: Run long-lived connections, background cron jobs, and queue listeners without arbitrary serverless timeouts.
- **Predictable Fixed Pricing**: Pay a flat monthly fee in LKR or USD with zero surprises from bandwidth or execution-second overage bills.

---

## Frequently Asked Questions

### Does STX Hosting support Next.js Standalone output?
Yes. When using `output: 'standalone'` in your `next.config.js`, the build footprint is reduced to minimal production assets, resulting in 5-second image builds and rapid container rollouts.

### Can I deploy private GitHub repositories?
Yes. The integrated STX GitHub App securely installs into your organization or personal account, requesting fine-grained read-only repository permissions.
