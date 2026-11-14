-- 20261115000002_catalog_and_templates.sql
-- Template catalog studio, hosting products, variants, pricing tiers, and resource limits.

-- 1. Template Permissions Catalog
CREATE TABLE IF NOT EXISTS public.template_permissions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    template_name TEXT NOT NULL UNIQUE,
    display_name TEXT NOT NULL,
    category TEXT NOT NULL DEFAULT 'cms',
    icon_url TEXT,
    short_description TEXT,
    default_port INTEGER NOT NULL DEFAULT 80,
    min_ram_mb INTEGER NOT NULL DEFAULT 512,
    min_vcpu NUMERIC(3, 1) NOT NULL DEFAULT 1.0,
    env_schema JSONB DEFAULT '[]'::jsonb,
    is_globally_enabled BOOLEAN NOT NULL DEFAULT true,
    is_featured BOOLEAN NOT NULL DEFAULT false,
    metadata JSONB DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_template_permissions_category ON public.template_permissions (category);
CREATE INDEX IF NOT EXISTS idx_template_permissions_enabled ON public.template_permissions (is_globally_enabled);
CREATE INDEX IF NOT EXISTS idx_template_permissions_featured ON public.template_permissions (is_featured);

-- 2. Products Catalog
CREATE TABLE IF NOT EXISTS public.products (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    slug TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    category TEXT NOT NULL, -- app_hosting, database, vps, web_hosting
    description TEXT,
    icon_url TEXT,
    badge TEXT,
    is_popular BOOLEAN NOT NULL DEFAULT false,
    is_active BOOLEAN NOT NULL DEFAULT true,
    features JSONB DEFAULT '[]'::jsonb,
    metadata JSONB DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_products_category ON public.products (category);
CREATE INDEX IF NOT EXISTS idx_products_active ON public.products (is_active);

-- 3. Product Variants (Specific resource allocations)
CREATE TABLE IF NOT EXISTS public.product_variants (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL REFERENCES public.products (id) ON DELETE CASCADE,
    slug TEXT NOT NULL,
    name TEXT NOT NULL,
    vcpu NUMERIC(4, 2) NOT NULL DEFAULT 1.0,
    ram_mb INTEGER NOT NULL DEFAULT 1024,
    disk_gb INTEGER NOT NULL DEFAULT 20,
    disk_type TEXT NOT NULL DEFAULT 'nvme',
    bandwidth_gb INTEGER NOT NULL DEFAULT 1000,
    specs_json JSONB DEFAULT '{}'::jsonb,
    is_active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (product_id, slug)
);

CREATE INDEX IF NOT EXISTS idx_product_variants_product_id ON public.product_variants (product_id);

-- 4. Pricing Tiers
CREATE TABLE IF NOT EXISTS public.pricing_tiers (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_variant_id UUID NOT NULL REFERENCES public.product_variants (id) ON DELETE CASCADE,
    billing_type TEXT NOT NULL DEFAULT 'monthly', -- monthly, quarterly, annual
    price_cents INTEGER NOT NULL DEFAULT 0,
    price_lkr_cents INTEGER,
    setup_fee_cents INTEGER NOT NULL DEFAULT 0,
    currency TEXT NOT NULL DEFAULT 'USD',
    is_active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (product_variant_id, billing_type)
);

CREATE INDEX IF NOT EXISTS idx_pricing_tiers_variant_id ON public.pricing_tiers (product_variant_id);

-- 5. Hosting Plans (Logical groupings)
CREATE TABLE IF NOT EXISTS public.hosting_plans (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID REFERENCES public.products (id) ON DELETE SET NULL,
    name TEXT NOT NULL,
    slug TEXT UNIQUE,
    price_cents INTEGER NOT NULL DEFAULT 0,
    billing_interval TEXT NOT NULL DEFAULT 'monthly',
    term_days INTEGER DEFAULT 30,
    is_active BOOLEAN NOT NULL DEFAULT true,
    metadata JSONB DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- 6. Centralized Resource Limits (Global defaults and per-team overrides)
CREATE TABLE IF NOT EXISTS public.hosting_limits (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    team_id UUID UNIQUE REFERENCES public.teams (id) ON DELETE CASCADE, -- NULL indicates global platform defaults
    hosting_plan_id UUID REFERENCES public.hosting_plans (id) ON DELETE SET NULL,
    max_projects INTEGER NOT NULL DEFAULT 5,
    max_deployments INTEGER NOT NULL DEFAULT 50,
    max_applications INTEGER NOT NULL DEFAULT 5,
    max_databases INTEGER NOT NULL DEFAULT 3,
    max_servers INTEGER NOT NULL DEFAULT 2,
    max_running_containers INTEGER NOT NULL DEFAULT 10,
    max_cpu NUMERIC(8, 2) NOT NULL DEFAULT 2.00,
    max_ram_mb INTEGER NOT NULL DEFAULT 4096,
    max_storage_gb INTEGER NOT NULL DEFAULT 50,
    max_bandwidth_gb INTEGER NOT NULL DEFAULT 200,
    max_smtp_accounts INTEGER NOT NULL DEFAULT 0,
    max_emails_per_day INTEGER NOT NULL DEFAULT 0,
    allowed_technologies JSONB DEFAULT '[]'::jsonb,
    allowed_template_categories JSONB DEFAULT '["all"]'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_hosting_limits_team_id ON public.hosting_limits (team_id);

