-- supabase/seed.sql
-- Seed initial templates, products, hosting plans, global limits, and infrastructure monitors.

-- 1. Seed Global Default Resource Limits
INSERT INTO public.hosting_limits (
    team_id,
    hosting_plan_id,
    max_projects,
    max_deployments,
    max_applications,
    max_databases,
    max_servers,
    max_running_containers,
    max_cpu,
    max_ram_mb,
    max_storage_gb,
    max_bandwidth_gb,
    max_smtp_accounts,
    max_emails_per_day,
    allowed_technologies,
    allowed_template_categories
)
VALUES (
    NULL,
    NULL,
    5,
    50,
    5,
    3,
    2,
    10,
    2.00,
    4096,
    50,
    200,
    0,
    0,
    '["docker", "nodejs", "python", "php", "go", "rust"]'::jsonb,
    '["all"]'::jsonb
)
ON CONFLICT (team_id) DO NOTHING;

-- 2. Seed Git App Hosting Product
INSERT INTO public.products (id, slug, name, category, description, icon_url, badge, is_popular, is_active)
VALUES (
    'a0000000-0000-0000-0000-000000000001'::uuid,
    'git-app-hosting',
    'Git App Hosting',
    'app_hosting',
    'Deploy custom web applications directly from your GitHub, GitLab, or Git repository with automatic HTTPS and fixed monthly pricing.',
    '/images/logos/git.svg',
    'Developer',
    true,
    true
)
ON CONFLICT (slug) DO NOTHING;

-- 3. Seed Git App Product Variants (Starter, Standard, Pro)
INSERT INTO public.product_variants (id, product_id, slug, name, vcpu, ram_mb, disk_gb, disk_type, bandwidth_gb, specs_json, is_active)
VALUES
    (
        'b0000000-0000-0000-0000-000000000001'::uuid,
        'a0000000-0000-0000-0000-000000000001'::uuid,
        'git-starter',
        'Starter Git App',
        1.0,
        512,
        5,
        'nvme',
        500,
        '{"label": "Eco / Starter", "tagline": "Ideal for APIs, microservices, and static or lightweight dynamic sites.", "recommended_for": "APIs, Bots, Lightweight Apps"}'::jsonb,
        true
    ),
    (
        'b0000000-0000-0000-0000-000000000002'::uuid,
        'a0000000-0000-0000-0000-000000000001'::uuid,
        'git-standard',
        'Standard Git App',
        1.0,
        1024,
        15,
        'nvme',
        1000,
        '{"label": "Standard / Growth", "tagline": "Perfect for production web applications, Laravel, Next.js, and Node.js.", "recommended_for": "Fullstack Web Apps, Laravel, Next.js"}'::jsonb,
        true
    ),
    (
        'b0000000-0000-0000-0000-000000000003'::uuid,
        'a0000000-0000-0000-0000-000000000001'::uuid,
        'git-pro',
        'Pro Git App',
        2.0,
        2048,
        30,
        'nvme',
        2000,
        '{"label": "Pro / Scale", "tagline": "High performance for intensive apps, background workers, and higher concurrency.", "recommended_for": "Heavy Apps, Background Workers, Scale"}'::jsonb,
        true
    )
ON CONFLICT (product_id, slug) DO NOTHING;

-- 4. Seed Pricing Tiers for Git App Variants
INSERT INTO public.pricing_tiers (product_variant_id, billing_type, price_cents, price_lkr_cents, setup_fee_cents, currency, is_active)
VALUES
    ('b0000000-0000-0000-0000-000000000001'::uuid, 'monthly', 500, 160000, 0, 'USD', true),
    ('b0000000-0000-0000-0000-000000000002'::uuid, 'monthly', 1000, 320000, 0, 'USD', true),
    ('b0000000-0000-0000-0000-000000000003'::uuid, 'monthly', 2000, 640000, 0, 'USD', true)
ON CONFLICT (product_variant_id, billing_type) DO NOTHING;

-- 5. Seed Curated Templates Catalog
INSERT INTO public.template_permissions (template_name, display_name, category, icon_url, short_description, default_port, min_ram_mb, min_vcpu, is_featured, is_globally_enabled)
VALUES
    (
        'wordpress',
        'WordPress',
        'cms',
        '/images/logos/wordpress.svg',
        'The world''s most popular website builder and blogging platform.',
        80,
        512,
        1.0,
        true,
        true
    ),
    (
        'ghost',
        'Ghost Publishing',
        'blog',
        '/images/logos/ghost.svg',
        'Modern, elegant platform for newsletters, publications, and blogs.',
        2368,
        512,
        0.5,
        true,
        true
    ),
    (
        'n8n',
        'n8n Workflow Automation',
        'automation',
        '/images/logos/n8n.svg',
        'Fair-code workflow automation tool to connect any app and automate tasks.',
        5678,
        1024,
        1.0,
        true,
        true
    ),
    (
        'nextcloud',
        'Nextcloud Hub',
        'cloud',
        '/images/logos/nextcloud.svg',
        'Self-hosted productivity platform keeping you in full control of your files.',
        80,
        1024,
        1.0,
        true,
        true
    ),
    (
        'strapi',
        'Strapi CMS',
        'cms',
        '/images/logos/strapi.svg',
        'Leading open-source headless CMS that is 100% JavaScript and customizable.',
        1337,
        1024,
        1.0,
        false,
        true
    ),
    (
        'umami',
        'Umami Analytics',
        'analytics',
        '/images/logos/umami.svg',
        'Simple, fast, privacy-focused alternative to Google Analytics.',
        3000,
        512,
        0.5,
        false,
        true
    ),
    (
        'plausible',
        'Plausible Analytics',
        'analytics',
        '/images/logos/plausible.svg',
        'Lightweight and open-source web analytics. No cookies and fully compliant with GDPR.',
        8000,
        1024,
        1.0,
        false,
        true
    ),
    (
        'minio',
        'MinIO S3 Storage',
        'storage',
        '/images/logos/minio.svg',
        'High-Performance Object Storage compatible with Amazon S3 APIs.',
        9000,
        512,
        0.5,
        false,
        true
    )
ON CONFLICT (template_name) DO UPDATE SET
    display_name = EXCLUDED.display_name,
    category = EXCLUDED.category,
    icon_url = EXCLUDED.icon_url,
    short_description = EXCLUDED.short_description,
    is_globally_enabled = EXCLUDED.is_globally_enabled;

-- 6. Seed Status Page Core Infrastructure Monitors
INSERT INTO public.monitors (uuid, name, public_label, group_name, type, target_host, target_url, port, expected_status_code, interval_seconds, is_public, is_active, current_status, uptime_percentage, last_latency_ms, display_order)
VALUES
    (
        'c0000000-0000-0000-0000-000000000001'::uuid,
        'API Gateway',
        'Core REST API Gateway',
        'Core Infrastructure',
        'http',
        '127.0.0.1',
        'http://127.0.0.1:8080/api/health',
        8080,
        200,
        60,
        true,
        true,
        'operational',
        100.00,
        12,
        1
    ),
    (
        'c0000000-0000-0000-0000-000000000002'::uuid,
        'Realtime Engine',
        'WebSockets & Log Streaming',
        'Core Infrastructure',
        'http',
        '127.0.0.1',
        'http://127.0.0.1:54321/realtime/v1',
        54321,
        200,
        60,
        true,
        true,
        'operational',
        99.98,
        15,
        2
    ),
    (
        'c0000000-0000-0000-0000-000000000003'::uuid,
        'Database Cluster',
        'Primary Database Engine',
        'Data & Storage',
        'tcp',
        '127.0.0.1',
        NULL,
        5432,
        0,
        60,
        true,
        true,
        'operational',
        100.00,
        4,
        3
    ),
    (
        'c0000000-0000-0000-0000-000000000004'::uuid,
        'Ingress Edge',
        'Edge Proxy & TLS Handshake',
        'Networking & Ingress',
        'http',
        '127.0.0.1',
        'http://127.0.0.1:80',
        80,
        200,
        60,
        true,
        true,
        'operational',
        100.00,
        8,
        4
    )
ON CONFLICT (uuid) DO NOTHING;



-- 7. Seed Email Hosting Plans (Customer Brings Domain)
INSERT INTO public.mail_plans (id, slug, name, storage_mb, max_mailboxes, price_cents, price_lkr_cents, billing_interval, is_active, features)
VALUES
    (
        'd0000000-0000-0000-0000-000000000001'::uuid,
        'mail-starter',
        'Starter Email',
        500,
        3,
        250, -- $2.50 / mo
        80000, -- LKR 800 / mo
        'monthly',
        true,
        '["500 MB Shared Storage", "Up to 3 Mailboxes", "Bring 1 Custom Domain", "Webmail + IMAP/SMTP", "Spam & Virus Filtering"]'::jsonb
    ),
    (
        'd0000000-0000-0000-0000-000000000002'::uuid,
        'mail-basic',
        'Basic Business Email',
        1024,
        5,
        499, -- $4.99 / mo
        160000, -- LKR 1,600 / mo
        'monthly',
        true,
        '["1 GB Shared Storage", "Up to 5 Mailboxes", "Bring 1 Custom Domain", "Custom Per-Mailbox Quotas", "Webmail + IMAP/SMTP", "Spam & Virus Filtering"]'::jsonb
    ),
    (
        'd0000000-0000-0000-0000-000000000003'::uuid,
        'mail-pro',
        'Pro Team Email',
        3072,
        15,
        1299, -- $12.99 / mo
        410000, -- LKR 4,100 / mo
        'monthly',
        true,
        '["3 GB Shared Storage", "Up to 15 Mailboxes", "Bring 2 Custom Domains", "Custom Per-Mailbox Quotas", "Email Aliases Included", "Webmail + IMAP/SMTP"]'::jsonb
    ),
    (
        'd0000000-0000-0000-0000-000000000004'::uuid,
        'mail-business',
        'Business Enterprise Email',
        5120,
        30,
        2499, -- $24.99 / mo
        790000, -- LKR 7,900 / mo
        'monthly',
        true,
        '["5 GB Shared Storage", "Up to 30 Mailboxes", "Bring 3 Custom Domains", "Unlimited Aliases", "Custom Quotas & Forwarding", "Webmail + IMAP/SMTP", "Priority Support"]'::jsonb
    )
ON CONFLICT (slug) DO UPDATE SET
    name = EXCLUDED.name,
    storage_mb = EXCLUDED.storage_mb,
    max_mailboxes = EXCLUDED.max_mailboxes,
    price_cents = EXCLUDED.price_cents,
    is_active = EXCLUDED.is_active;

-- 8. Seed Email Hosting Add-Ons
INSERT INTO public.mail_addons (slug, name, type, specs_json, price_cents, billing_interval, is_active)
VALUES
    ('addon-storage-1gb', '+1 GB Pooled Storage', 'storage', '{"extra_storage_mb": 1024}'::jsonb, 150, 'monthly', true),
    ('addon-storage-5gb', '+5 GB Pooled Storage', 'storage', '{"extra_storage_mb": 5120}'::jsonb, 600, 'monthly', true),
    ('addon-mailboxes-5', '+5 Extra Mailboxes Pack', 'mailbox', '{"extra_mailboxes": 5}'::jsonb, 300, 'monthly', true),
    ('addon-domain-1', '+1 Extra Custom Domain', 'domain', '{"extra_domains": 1}'::jsonb, 200, 'monthly', true),
    ('addon-unlimited-aliases', 'Unlimited Email Aliases', 'feature', '{"unlimited_aliases": true}'::jsonb, 100, 'monthly', true),
    ('addon-catch-all', 'Catch-All Mail Routing', 'feature', '{"catch_all": true}'::jsonb, 150, 'monthly', true),
    ('addon-compliance-archive', '1-Year Compliance Mail Archive', 'feature', '{"archive_retention_days": 365}'::jsonb, 400, 'monthly', true),
    ('addon-priority-deliverability', 'Priority Deliverability & IP Monitoring', 'feature', '{"priority_queue": true}'::jsonb, 500, 'monthly', true)
ON CONFLICT (slug) DO UPDATE SET
    name = EXCLUDED.name,
    specs_json = EXCLUDED.specs_json,
    price_cents = EXCLUDED.price_cents,
    is_active = EXCLUDED.is_active;

-- 9. Seed Status Page Mail Telemetry Monitors
INSERT INTO public.monitors (uuid, name, public_label, group_name, type, target_host, target_url, port, expected_status_code, interval_seconds, is_public, is_active, current_status, uptime_percentage, last_latency_ms, display_order)
VALUES
    (
        'c0000000-0000-0000-0000-000000000005'::uuid,
        'SMTP Submission (STARTTLS)',
        'Mail Ingress & Submission (Port 587)',
        'Mail Infrastructure',
        'tcp',
        '127.0.0.1',
        NULL,
        587,
        0,
        60,
        true,
        true,
        'operational',
        100.00,
        18,
        5
    ),
    (
        'c0000000-0000-0000-0000-000000000006'::uuid,
        'IMAP Secure (IMAPS)',
        'Mailbox Sync & IMAP (Port 993)',
        'Mail Infrastructure',
        'tcp',
        '127.0.0.1',
        NULL,
        993,
        0,
        60,
        true,
        true,
        'operational',
        100.00,
        14,
        6
    ),
    (
        'c0000000-0000-0000-0000-000000000007'::uuid,
        'Webmail UI Application',
        'Customer Webmail Portal',
        'Mail Infrastructure',
        'http',
        '127.0.0.1',
        'http://127.0.0.1:80/login',
        80,
        200,
        60,
        true,
        true,
        'operational',
        99.99,
        22,
        7
    )
ON CONFLICT (uuid) DO NOTHING;
