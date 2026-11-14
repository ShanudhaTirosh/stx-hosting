-- 20261115000004_deployments_and_domains.sql
-- Deployments, custom domains with DNS/SSL verification, service instances, and service credentials.

-- 1. Deployments Table (Application deployment queue and execution tracking)
CREATE TABLE IF NOT EXISTS public.deployments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    uuid TEXT UNIQUE NOT NULL DEFAULT gen_random_uuid()::text,
    team_id UUID NOT NULL REFERENCES public.teams (id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES public.profiles (id) ON DELETE CASCADE,
    application_name TEXT NOT NULL,
    template_name TEXT REFERENCES public.template_permissions (template_name) ON DELETE SET NULL,
    git_repo TEXT,
    git_branch TEXT DEFAULT 'main',
    build_pack TEXT DEFAULT 'nixpacks', -- nixpacks, dockerfile, compose
    commit_hash TEXT,
    commit_message TEXT,
    status TEXT NOT NULL DEFAULT 'queued', -- queued, in_progress, finished, error, cancelled
    environment_variables JSONB DEFAULT '{}'::jsonb,
    logs TEXT, -- Complete or aggregated build log
    deployment_url TEXT,
    current_step TEXT,
    error_message TEXT,
    started_at TIMESTAMPTZ,
    finished_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_deployments_team_id ON public.deployments (team_id);
CREATE INDEX IF NOT EXISTS idx_deployments_user_id ON public.deployments (user_id);
CREATE INDEX IF NOT EXISTS idx_deployments_status ON public.deployments (status);
CREATE INDEX IF NOT EXISTS idx_deployments_created_at ON public.deployments (created_at DESC);

-- 2. Custom Domains Table (Live DNS verification and SSL certificate tracking)
CREATE TABLE IF NOT EXISTS public.custom_domains (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    uuid TEXT UNIQUE NOT NULL DEFAULT gen_random_uuid()::text,
    team_id UUID NOT NULL REFERENCES public.teams (id) ON DELETE CASCADE,
    domain TEXT NOT NULL,
    record_type TEXT NOT NULL DEFAULT 'CNAME', -- CNAME or A
    expected_target TEXT NOT NULL, -- Platform ingress hostname or IP
    token TEXT NOT NULL, -- TXT verification token for ownership verification
    status TEXT NOT NULL DEFAULT 'pending_dns', -- pending_dns, verified, failed
    ssl_status TEXT NOT NULL DEFAULT 'pending', -- pending, active, expired, failed
    ssl_issued_at TIMESTAMPTZ,
    ssl_expires_at TIMESTAMPTZ,
    last_error_message TEXT,
    check_attempts INTEGER NOT NULL DEFAULT 0,
    verified_at TIMESTAMPTZ,
    last_checked_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (team_id, domain)
);

CREATE INDEX IF NOT EXISTS idx_custom_domains_team_id ON public.custom_domains (team_id);
CREATE INDEX IF NOT EXISTS idx_custom_domains_domain ON public.custom_domains (domain);
CREATE INDEX IF NOT EXISTS idx_custom_domains_status ON public.custom_domains (status);

-- 3. Service Instances (Provisioned containers and applications)
CREATE TABLE IF NOT EXISTS public.service_instances (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    uuid TEXT UNIQUE NOT NULL DEFAULT gen_random_uuid()::text,
    team_id UUID NOT NULL REFERENCES public.teams (id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES public.profiles (id) ON DELETE CASCADE,
    order_id UUID REFERENCES public.orders (id) ON DELETE SET NULL,
    service_type TEXT NOT NULL, -- web_app, database, vps, template
    template_name TEXT,
    name TEXT NOT NULL,
    hostname TEXT UNIQUE,
    ip_address TEXT,
    status TEXT NOT NULL DEFAULT 'provisioning', -- provisioning, active, suspended, terminated
    health_status TEXT NOT NULL DEFAULT 'healthy', -- healthy, degraded, unhealthy
    last_health_check_at TIMESTAMPTZ,
    activated_at TIMESTAMPTZ,
    renews_at TIMESTAMPTZ,
    suspended_at TIMESTAMPTZ,
    network_config JSONB DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_service_instances_team_id ON public.service_instances (team_id);
CREATE INDEX IF NOT EXISTS idx_service_instances_status ON public.service_instances (status);
CREATE INDEX IF NOT EXISTS idx_service_instances_health ON public.service_instances (health_status);

-- 4. Service Credentials (Encrypted credentials for services and VPS)
CREATE TABLE IF NOT EXISTS public.service_credentials (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    uuid TEXT UNIQUE NOT NULL DEFAULT gen_random_uuid()::text,
    service_instance_id UUID NOT NULL REFERENCES public.service_instances (id) ON DELETE CASCADE,
    credential_type TEXT NOT NULL, -- root_password, ssh_key, api_token, db_password
    username TEXT NOT NULL DEFAULT 'root',
    encrypted_secret TEXT NOT NULL,
    ssh_port INTEGER NOT NULL DEFAULT 22,
    last_retrieved_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_service_credentials_instance_id ON public.service_credentials (service_instance_id);

