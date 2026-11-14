-- 20261115000010_mail_hosting_schema.sql
-- Schema for Sellable Email Hosting: Plans, Add-ons, Subscriptions, Domains, Mailboxes, and Aliases with strict RLS.

-- 1. Mail Hosting Plans Table
CREATE TABLE IF NOT EXISTS public.mail_plans (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    slug TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    storage_mb INTEGER NOT NULL, -- 500, 1024, 3072, 5120
    max_mailboxes INTEGER NOT NULL, -- 3, 5, 15, 30
    price_cents INTEGER NOT NULL DEFAULT 0,
    price_lkr_cents INTEGER,
    billing_interval TEXT NOT NULL DEFAULT 'monthly',
    is_active BOOLEAN NOT NULL DEFAULT true,
    features JSONB DEFAULT '[]'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_mail_plans_slug ON public.mail_plans (slug);
CREATE INDEX IF NOT EXISTS idx_mail_plans_active ON public.mail_plans (is_active);

-- 2. Mail Add-Ons Catalog Table
CREATE TABLE IF NOT EXISTS public.mail_addons (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    slug TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    type TEXT NOT NULL, -- storage, mailbox, domain, feature
    specs_json JSONB NOT NULL DEFAULT '{}'::jsonb,
    price_cents INTEGER NOT NULL DEFAULT 0,
    billing_interval TEXT NOT NULL DEFAULT 'monthly',
    is_active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_mail_addons_type ON public.mail_addons (type);
CREATE INDEX IF NOT EXISTS idx_mail_addons_active ON public.mail_addons (is_active);

-- 3. Customer Mail Subscriptions Table
CREATE TABLE IF NOT EXISTS public.mail_subscriptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    team_id UUID NOT NULL REFERENCES public.teams (id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES public.profiles (id) ON DELETE CASCADE,
    plan_id UUID NOT NULL REFERENCES public.mail_plans (id),
    status TEXT NOT NULL DEFAULT 'active', -- active, past_due, suspended, cancelled
    total_storage_mb INTEGER NOT NULL, -- Base storage + add-ons
    max_mailboxes INTEGER NOT NULL,    -- Base mailboxes + add-ons
    max_domains INTEGER NOT NULL DEFAULT 1,
    active_addons JSONB DEFAULT '[]'::jsonb,
    renews_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_mail_subscriptions_team ON public.mail_subscriptions (team_id);
CREATE INDEX IF NOT EXISTS idx_mail_subscriptions_status ON public.mail_subscriptions (status);

-- 4. Customer Mail Domains Table
CREATE TABLE IF NOT EXISTS public.mail_domains (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    subscription_id UUID NOT NULL REFERENCES public.mail_subscriptions (id) ON DELETE CASCADE,
    team_id UUID NOT NULL REFERENCES public.teams (id) ON DELETE CASCADE,
    domain TEXT UNIQUE NOT NULL,
    wildduck_domain_id TEXT,
    dkim_selector TEXT NOT NULL DEFAULT 'wildduck',
    dkim_public_key TEXT,
    verification_token TEXT NOT NULL,
    is_verified BOOLEAN NOT NULL DEFAULT false,
    dns_status JSONB DEFAULT '{"mx": false, "spf": false, "dkim": false, "dmarc": false}'::jsonb,
    last_checked_at TIMESTAMPTZ,
    verified_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_mail_domains_team ON public.mail_domains (team_id);
CREATE INDEX IF NOT EXISTS idx_mail_domains_domain ON public.mail_domains (domain);
CREATE INDEX IF NOT EXISTS idx_mail_domains_verified ON public.mail_domains (is_verified);

-- 5. Customer Mailboxes Table
CREATE TABLE IF NOT EXISTS public.mail_mailboxes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    domain_id UUID NOT NULL REFERENCES public.mail_domains (id) ON DELETE CASCADE,
    team_id UUID NOT NULL REFERENCES public.teams (id) ON DELETE CASCADE,
    wildduck_user_id TEXT UNIQUE NOT NULL,
    address TEXT UNIQUE NOT NULL, -- e.g. alex@acme.com
    name TEXT NOT NULL,
    quota_mb INTEGER, -- Optional custom limit; NULL = shares total_storage_mb
    used_storage_bytes BIGINT NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT true,
    is_catch_all BOOLEAN NOT NULL DEFAULT false,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_mail_mailboxes_domain ON public.mail_mailboxes (domain_id);
CREATE INDEX IF NOT EXISTS idx_mail_mailboxes_team ON public.mail_mailboxes (team_id);
CREATE INDEX IF NOT EXISTS idx_mail_mailboxes_address ON public.mail_mailboxes (address);

-- 6. Mail Aliases Table
CREATE TABLE IF NOT EXISTS public.mail_aliases (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    mailbox_id UUID NOT NULL REFERENCES public.mail_mailboxes (id) ON DELETE CASCADE,
    alias_address TEXT UNIQUE NOT NULL, -- e.g. billing@acme.com -> alex@acme.com
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_mail_aliases_mailbox ON public.mail_aliases (mailbox_id);

-- 7. Enable Row Level Security (RLS) on all mail tables
ALTER TABLE public.mail_plans ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.mail_addons ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.mail_subscriptions ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.mail_domains ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.mail_mailboxes ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.mail_aliases ENABLE ROW LEVEL SECURITY;

-- 8. Policies for Mail Plans & Add-ons (Public Read, Owner Manage)
CREATE POLICY "Public can view active mail plans" ON public.mail_plans
    FOR SELECT TO anon, authenticated
    USING (is_active = true OR auth.is_admin());

CREATE POLICY "Admins can manage mail plans" ON public.mail_plans
    FOR ALL TO authenticated
    USING (auth.is_admin())
    WITH CHECK (auth.is_admin());

CREATE POLICY "Public can view active mail addons" ON public.mail_addons
    FOR SELECT TO anon, authenticated
    USING (is_active = true OR auth.is_admin());

CREATE POLICY "Admins can manage mail addons" ON public.mail_addons
    FOR ALL TO authenticated
    USING (auth.is_admin())
    WITH CHECK (auth.is_admin());

-- 9. Policies for Subscriptions
CREATE POLICY "Customers can view their team mail subscriptions" ON public.mail_subscriptions
    FOR SELECT TO authenticated
    USING (auth.belongs_to_team(team_id) OR auth.is_admin());

CREATE POLICY "Staff can manage mail subscriptions" ON public.mail_subscriptions
    FOR ALL TO authenticated
    USING (auth.is_admin())
    WITH CHECK (auth.is_admin());

-- 10. Policies for Domains
CREATE POLICY "Customers can view their team mail domains" ON public.mail_domains
    FOR SELECT TO authenticated
    USING (auth.belongs_to_team(team_id) OR auth.is_admin());

CREATE POLICY "Customers can register mail domains for team" ON public.mail_domains
    FOR INSERT TO authenticated
    WITH CHECK (auth.belongs_to_team(team_id));

CREATE POLICY "Customers can delete their team mail domains" ON public.mail_domains
    FOR DELETE TO authenticated
    USING (auth.belongs_to_team(team_id) OR auth.is_admin());

-- 11. Policies for Mailboxes
CREATE POLICY "Customers can view their team mailboxes" ON public.mail_mailboxes
    FOR SELECT TO authenticated
    USING (auth.belongs_to_team(team_id) OR auth.is_admin());

CREATE POLICY "Customers can create mailboxes within subscription limits" ON public.mail_mailboxes
    FOR INSERT TO authenticated
    WITH CHECK (auth.belongs_to_team(team_id));

CREATE POLICY "Customers can update their team mailboxes" ON public.mail_mailboxes
    FOR UPDATE TO authenticated
    USING (auth.belongs_to_team(team_id) OR auth.is_admin());

CREATE POLICY "Customers can delete their team mailboxes" ON public.mail_mailboxes
    FOR DELETE TO authenticated
    USING (auth.belongs_to_team(team_id) OR auth.is_admin());

-- 12. Policies for Aliases
CREATE POLICY "Customers can view their mailbox aliases" ON public.mail_aliases
    FOR SELECT TO authenticated
    USING (
        EXISTS (
            SELECT 1 FROM public.mail_mailboxes m
            WHERE m.id = mail_aliases.mailbox_id
            AND (auth.belongs_to_team(m.team_id) OR auth.is_admin())
        )
    );

CREATE POLICY "Customers can manage aliases for their mailboxes" ON public.mail_aliases
    FOR ALL TO authenticated
    USING (
        EXISTS (
            SELECT 1 FROM public.mail_mailboxes m
            WHERE m.id = mail_aliases.mailbox_id
            AND (auth.belongs_to_team(m.team_id) OR auth.is_admin())
        )
    );

-- 13. Mailbox Quota Guard Trigger
CREATE OR REPLACE FUNCTION public.fn_enforce_mailbox_limits()
RETURNS trigger LANGUAGE plpgsql SECURITY DEFINER AS $$
DECLARE
    sub_record public.mail_subscriptions%ROWTYPE;
    current_count INTEGER;
BEGIN
    IF auth.is_admin() THEN
        RETURN NEW;
    END IF;

    SELECT * INTO sub_record
    FROM public.mail_subscriptions
    WHERE team_id = NEW.team_id
    AND status = 'active'
    LIMIT 1;

    IF sub_record.id IS NULL THEN
        RAISE EXCEPTION 'No active email hosting subscription found for this team.';
    END IF;

    SELECT count(*) INTO current_count
    FROM public.mail_mailboxes
    WHERE team_id = NEW.team_id;

    IF current_count >= sub_record.max_mailboxes THEN
        RAISE EXCEPTION 'Mailbox limit (%) reached for this subscription. Please purchase an extra mailbox add-on.', sub_record.max_mailboxes;
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_enforce_mailbox_limits ON public.mail_mailboxes;
CREATE TRIGGER trg_enforce_mailbox_limits
    BEFORE INSERT ON public.mail_mailboxes
    FOR EACH ROW EXECUTE FUNCTION public.fn_enforce_mailbox_limits();

