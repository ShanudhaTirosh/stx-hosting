-- Migration: 20261115000013_harden_credentials_and_settings_rls.sql
-- Description: Harden Supabase RLS policies for commercial settings, service credentials, and mail resources (SEC-07, SEC-08, SEC-09)

-- 1. Helper function to check specific roles within a team
CREATE OR REPLACE FUNCTION auth.has_team_role(check_team_id UUID, allowed_roles TEXT[]) 
RETURNS boolean AS $$
    SELECT EXISTS (
        SELECT 1 FROM public.team_members
        WHERE team_id = check_team_id
        AND user_id = auth.uid()
        AND role = ANY(allowed_roles)
    );
$$ LANGUAGE sql STABLE SECURITY DEFINER;

-- 2. Harden commercial_settings: prevent anonymous leaks of sensitive internal configurations (SEC-07)
DROP POLICY IF EXISTS "Anyone can read public commercial settings" ON public.commercial_settings;
DROP POLICY IF EXISTS "Public can read whitelist commercial settings" ON public.commercial_settings;

CREATE POLICY "Public can read whitelist commercial settings" ON public.commercial_settings
    FOR SELECT TO anon, authenticated
    USING (
        key IN (
            'currency', 'storefront_name', 'support_email', 'brand_name',
            'company_address', 'company_phone', 'vat_percentage',
            'bank_name', 'bank_account_name', 'bank_account_number', 'bank_branch'
        )
        OR auth.is_admin()
    );

-- 3. Harden service_credentials: restrict to Owner, Admin, Developer (block Viewers & Billing) (SEC-08)
DROP POLICY IF EXISTS "Customers can view credentials for their instances" ON public.service_credentials;
DROP POLICY IF EXISTS "Authorized team roles can view service credentials" ON public.service_credentials;

CREATE POLICY "Authorized team roles can view service credentials" ON public.service_credentials
    FOR SELECT TO authenticated
    USING (
        EXISTS (
            SELECT 1 FROM public.service_instances si
            WHERE si.id = service_credentials.service_instance_id
            AND (auth.has_team_role(si.team_id, ARRAY['owner', 'admin', 'developer']) OR auth.is_admin())
        )
    );

-- 4. Harden mail_domains & mail_mailboxes: restrict DELETE to Owner and Admin (block Viewers & Billing) (SEC-09)
DROP POLICY IF EXISTS "Customers can delete their team mail domains" ON public.mail_domains;
DROP POLICY IF EXISTS "Owners and Admins can delete their team mail domains" ON public.mail_domains;

CREATE POLICY "Owners and Admins can delete their team mail domains" ON public.mail_domains
    FOR DELETE TO authenticated
    USING (auth.has_team_role(team_id, ARRAY['owner', 'admin']) OR auth.is_admin());

DROP POLICY IF EXISTS "Customers can delete their team mailboxes" ON public.mail_mailboxes;
DROP POLICY IF EXISTS "Owners and Admins can delete their team mailboxes" ON public.mail_mailboxes;

CREATE POLICY "Owners and Admins can delete their team mailboxes" ON public.mail_mailboxes
    FOR DELETE TO authenticated
    USING (auth.has_team_role(team_id, ARRAY['owner', 'admin']) OR auth.is_admin());
