---
title: Template Catalog & Deployments
summary: Staff guide for managing curated application templates, resource bounds, and 3-tier Git plans.
audience: admin
order: 6
---
Platform administrators and the owner curate which application templates appear in the customer deploy wizard and control their resource limits.

## Template Catalog Studio (`/admin/commerce/templates`)

The Template Catalog Studio allows staff to manage the deployable application catalog:
- **Curated 31-Template Production Corpus**: The active catalog in `templates/compose/` includes verified, high-impact stacks (Supabase, n8n-postgresql, WordPress, Ghost, Plausible, Nextcloud, Strapi, Uptime Kuma, etc.). 357 unhosted or legacy CMS templates are archived safely in `templates/not-used/`.
- **Active Logo Management**: 61 active icons/logos are maintained in `public/svgs/` with automatic fallback to `public/svgs/not-used/` or placeholder SVG via `getServiceLogo()` helper.
- **Enable / Disable Toggle**: Instantly enable or disable a template globally. When disabled, customers cannot deploy new instances of it.
- **Featured / Popular Toggle**: Flags high-demand templates (like WordPress or n8n) with a "Popular" badge in the customer deploy wizard.
- **Resource Limits**: Define minimum RAM (MB) and vCPU allocations for each template.
- **Internal Ports**: Specify the internal container listening port (e.g. 80 for WordPress, 2368 for Ghost, 5678 for n8n) for automated reverse proxy mapping.
- **Add New Template**: Staff can onboard any Docker image / Compose template by giving it a unique slug, category, and default specs.

## 3-Tier Git Hosting Plans

Custom Git deployments are structured into 3 fixed-price tiers:
1. **Starter Git App** (`git-starter`): 1 vCPU, 512 MB RAM, 5 GB SSD — $5.00/mo.
2. **Standard Git App** (`git-standard`): 1 vCPU, 1024 MB RAM, 15 GB SSD — $10.00/mo.
3. **Pro Git App** (`git-pro`): 2 vCPU, 2048 MB RAM, 30 GB SSD — $20.00/mo.

Pricing tiers can be adjusted by the Platform Owner under **Products & Plans** (`/admin/commerce/products`).

## Guardrails & Security

- Standard customers cannot execute raw Docker compose commands or deploy arbitrary unmetered containers.
- All deployed containers are bound to strict Docker cgroups quotas (`limits_cpus`, `limits_memory`, `limits_memory_swap`).
- Reverse proxy routing labels are applied automatically upon deployment.

