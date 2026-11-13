---
title: Deploy from a Git repository
summary: Connect a repository, choose one of our 3 fixed-price hosting plans, set environment variables and deploy with instant HTTPS.
order: 3
---
Every custom Git repository deployment runs inside a secure, resource-guarded container with fixed monthly pricing and automatic HTTPS.

## 3 Fixed-Price Git Hosting Plans

When deploying your Git repository, choose the plan that fits your resource needs:

1. **Starter Git App** (1 vCPU, 512 MB RAM, 5 GB SSD) — $5.00/month. Perfect for lightweight APIs, microservices, and static or small dynamic sites.
2. **Standard Git App** (1 vCPU, 1024 MB RAM, 15 GB SSD) — $10.00/month. Recommended for production web applications, Laravel, Next.js, and Node.js.
3. **Pro Git App** (2 vCPU, 2048 MB RAM, 30 GB SSD) — $20.00/month. High performance for heavier fullstack apps, background workers, and higher traffic.

## Steps

1. Open **Deploy Application** in the customer portal (`/portal/deploy`).
2. Switch to **Custom Git App (3 Plans)** and select your plan.
3. Enter your **Application Name** (e.g. `my-saas-api`). You will instantly see your free assigned HTTPS address (`https://my-saas-api.apps.<domain>`).
4. Enter your **Git Repository URL** and branch (defaults to `main`).
5. Choose your build pack (**Nixpacks** automatically detects languages; **Dockerfile** or language-specific presets are also supported).
6. Optionally add custom environment variables.
7. Click **Launch Git App**.

## What Happens on Deploy

- Your container is launched with strict CPU and RAM limits locked to your chosen plan.
- An instant wildcard HTTPS certificate secures your app with zero configuration.
- You can monitor live build and container logs from the app details page.
- You can stop, restart, redeploy, or attach a custom domain at any time.
