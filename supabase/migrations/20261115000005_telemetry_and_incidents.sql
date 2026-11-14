-- 20261115000005_telemetry_and_incidents.sql
-- Monitors, uptime checks, incidents, incident updates, and status alert subscriptions.

-- 1. Monitors Table (Infrastructure and service health probes)
CREATE TABLE IF NOT EXISTS public.monitors (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    uuid TEXT UNIQUE NOT NULL DEFAULT gen_random_uuid()::text,
    name TEXT NOT NULL,
    public_label TEXT NOT NULL,
    group_name TEXT NOT NULL DEFAULT 'Core Infrastructure',
    type TEXT NOT NULL DEFAULT 'http', -- http, tcp, ping
    target_host TEXT NOT NULL, -- Never exposed publicly
    target_url TEXT,
    port INTEGER NOT NULL DEFAULT 80,
    expected_status_code INTEGER NOT NULL DEFAULT 200,
    interval_seconds INTEGER NOT NULL DEFAULT 60,
    timeout_ms INTEGER NOT NULL DEFAULT 3000,
    is_public BOOLEAN NOT NULL DEFAULT true,
    is_active BOOLEAN NOT NULL DEFAULT true,
    current_status TEXT NOT NULL DEFAULT 'operational', -- operational, degraded, down
    uptime_percentage NUMERIC(5, 2) NOT NULL DEFAULT 100.00,
    last_latency_ms INTEGER NOT NULL DEFAULT 15,
    last_checked_at TIMESTAMPTZ,
    display_order INTEGER NOT NULL DEFAULT 0,
    custom_headers JSONB DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_monitors_public_active ON public.monitors (is_public, is_active);
CREATE INDEX IF NOT EXISTS idx_monitors_group ON public.monitors (group_name);
CREATE INDEX IF NOT EXISTS idx_monitors_display_order ON public.monitors (display_order);

-- 2. Monitor Checks (History for uptime bars and latency tracking)
CREATE TABLE IF NOT EXISTS public.monitor_checks (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    monitor_id UUID NOT NULL REFERENCES public.monitors (id) ON DELETE CASCADE,
    status TEXT NOT NULL, -- up, down, degraded
    response_time_ms INTEGER NOT NULL DEFAULT 0,
    error_message TEXT,
    checked_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_monitor_checks_monitor_checked ON public.monitor_checks (monitor_id, checked_at DESC);

-- 3. System Incidents & Maintenance Notices
CREATE TABLE IF NOT EXISTS public.system_incidents (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    uuid TEXT UNIQUE NOT NULL DEFAULT gen_random_uuid()::text,
    title TEXT NOT NULL,
    incident_type TEXT NOT NULL DEFAULT 'incident', -- incident, maintenance
    impact_level TEXT NOT NULL DEFAULT 'minor', -- none, minor, major, critical
    status TEXT NOT NULL DEFAULT 'investigating', -- investigating, identified, monitoring, resolved
    severity TEXT NOT NULL DEFAULT 'operational',
    message TEXT NOT NULL,
    affected_monitors JSONB DEFAULT '[]'::jsonb,
    scheduled_start_at TIMESTAMPTZ,
    scheduled_end_at TIMESTAMPTZ,
    resolved_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_system_incidents_status_created ON public.system_incidents (status, created_at DESC);

-- 4. Incident Updates
CREATE TABLE IF NOT EXISTS public.incident_updates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    system_incident_id UUID NOT NULL REFERENCES public.system_incidents (id) ON DELETE CASCADE,
    status TEXT NOT NULL, -- investigating, identified, monitoring, resolved, update
    message TEXT NOT NULL,
    created_by UUID REFERENCES public.profiles (id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_incident_updates_incident_id ON public.incident_updates (system_incident_id);

-- 5. Status Subscriptions
CREATE TABLE IF NOT EXISTS public.status_subscriptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email TEXT UNIQUE NOT NULL,
    is_verified BOOLEAN NOT NULL DEFAULT false,
    token TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_status_subscriptions_email ON public.status_subscriptions (email);

