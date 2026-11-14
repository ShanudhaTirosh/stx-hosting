-- 20261115000006_audit_logging.sql
-- Audit logs and compliance event tracking.

-- 1. General Audit Logs Table
CREATE TABLE IF NOT EXISTS public.audit_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES public.profiles (id) ON DELETE SET NULL,
    team_id UUID REFERENCES public.teams (id) ON DELETE SET NULL,
    action TEXT NOT NULL,
    target_type TEXT,
    target_id TEXT,
    payload JSONB DEFAULT '{}'::jsonb,
    ip_address TEXT,
    user_agent TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_audit_logs_user_id ON public.audit_logs (user_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_team_id ON public.audit_logs (team_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_action ON public.audit_logs (action);
CREATE INDEX IF NOT EXISTS idx_audit_logs_created_at ON public.audit_logs (created_at DESC);

-- 2. Commerce Specific Audit Logs
CREATE TABLE IF NOT EXISTS public.commerce_audit_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES public.profiles (id) ON DELETE SET NULL,
    team_id UUID REFERENCES public.teams (id) ON DELETE SET NULL,
    action TEXT NOT NULL,
    target_type TEXT,
    target_id TEXT,
    payload JSONB DEFAULT '{}'::jsonb,
    ip_address TEXT,
    user_agent TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_commerce_audit_logs_team_id ON public.commerce_audit_logs (team_id);
CREATE INDEX IF NOT EXISTS idx_commerce_audit_logs_action ON public.commerce_audit_logs (action);

