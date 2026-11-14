-- 20261115000007_rls_policies.sql
-- Enable Row Level Security (RLS) on all tables and define granular policies for Customer, Admin, Owner, and Public roles.

-- 1. Enable Row Level Security on ALL tables
ALTER TABLE public.profiles ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.teams ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.team_members ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.template_permissions ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.products ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.product_variants ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.pricing_tiers ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.hosting_plans ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.hosting_limits ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.orders ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.order_items ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.invoices ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.invoice_items ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.payment_proofs ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.payment_transactions ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.hosting_entitlements ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.commercial_settings ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.deployments ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.custom_domains ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.service_instances ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.service_credentials ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.monitors ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.monitor_checks ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.system_incidents ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.incident_updates ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.status_subscriptions ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.audit_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.commerce_audit_logs ENABLE ROW LEVEL SECURITY;

-- 2. Profiles Policies
CREATE POLICY "Users can read own profile" ON public.profiles
    FOR SELECT TO authenticated
    USING (id = auth.uid() OR auth.is_admin());

CREATE POLICY "Users can update own profile" ON public.profiles
    FOR UPDATE TO authenticated
    USING (id = auth.uid() OR auth.is_owner())
    WITH CHECK (id = auth.uid() OR auth.is_owner());

-- 3. Teams & Team Members Policies
CREATE POLICY "Team members can view their teams" ON public.teams
    FOR SELECT TO authenticated
    USING (auth.belongs_to_team(id) OR auth.is_admin());

CREATE POLICY "Team owners can update team" ON public.teams
    FOR UPDATE TO authenticated
    USING (
        EXISTS (
            SELECT 1 FROM public.team_members
            WHERE team_id = teams.id AND user_id = auth.uid() AND role IN ('owner', 'admin')
        ) OR auth.is_admin()
    );

CREATE POLICY "Team members can view team membership" ON public.team_members
    FOR SELECT TO authenticated
    USING (auth.belongs_to_team(team_id) OR auth.is_admin());

CREATE POLICY "Team owners can manage team membership" ON public.team_members
    FOR ALL TO authenticated
    USING (
        EXISTS (
            SELECT 1 FROM public.team_members tm
            WHERE tm.team_id = team_members.team_id AND tm.user_id = auth.uid() AND tm.role = 'owner'
        ) OR auth.is_admin()
    );

-- 4. Template Catalog Policies (Public and Customer Read, Owner Write)
CREATE POLICY "Anyone can view enabled templates" ON public.template_permissions
    FOR SELECT TO anon, authenticated
    USING (is_globally_enabled = true OR auth.is_admin());

CREATE POLICY "Only Owners and Admins can manage templates" ON public.template_permissions
    FOR ALL TO authenticated
    USING (auth.is_admin())
    WITH CHECK (auth.is_admin());

-- 5. Products, Variants & Pricing Tiers Policies (Storefront & Catalog)
CREATE POLICY "Public can view active products" ON public.products
    FOR SELECT TO anon, authenticated
    USING (is_active = true OR auth.is_admin());

CREATE POLICY "Admins can manage products" ON public.products
    FOR ALL TO authenticated
    USING (auth.is_admin())
    WITH CHECK (auth.is_admin());

CREATE POLICY "Public can view active product variants" ON public.product_variants
    FOR SELECT TO anon, authenticated
    USING (is_active = true OR auth.is_admin());

CREATE POLICY "Admins can manage product variants" ON public.product_variants
    FOR ALL TO authenticated
    USING (auth.is_admin())
    WITH CHECK (auth.is_admin());

CREATE POLICY "Public can view active pricing tiers" ON public.pricing_tiers
    FOR SELECT TO anon, authenticated
    USING (is_active = true OR auth.is_admin());

CREATE POLICY "Admins can manage pricing tiers" ON public.pricing_tiers
    FOR ALL TO authenticated
    USING (auth.is_admin())
    WITH CHECK (auth.is_admin());

CREATE POLICY "Public can view active hosting plans" ON public.hosting_plans
    FOR SELECT TO anon, authenticated
    USING (is_active = true OR auth.is_admin());

CREATE POLICY "Admins can manage hosting plans" ON public.hosting_plans
    FOR ALL TO authenticated
    USING (auth.is_admin())
    WITH CHECK (auth.is_admin());

-- 6. Resource Limits Policies
CREATE POLICY "Teams can view their resource limits" ON public.hosting_limits
    FOR SELECT TO authenticated
    USING (team_id IS NULL OR auth.belongs_to_team(team_id) OR auth.is_admin());

CREATE POLICY "Only Admins can modify resource limits" ON public.hosting_limits
    FOR ALL TO authenticated
    USING (auth.is_admin())
    WITH CHECK (auth.is_admin());

-- 7. Orders & Order Items Policies
CREATE POLICY "Customers can view their team orders" ON public.orders
    FOR SELECT TO authenticated
    USING (auth.belongs_to_team(team_id) OR auth.is_admin());

CREATE POLICY "Customers can create team orders" ON public.orders
    FOR INSERT TO authenticated
    WITH CHECK (auth.belongs_to_team(team_id) AND user_id = auth.uid());

CREATE POLICY "Admins can update orders" ON public.orders
    FOR UPDATE TO authenticated
    USING (auth.is_admin())
    WITH CHECK (auth.is_admin());

CREATE POLICY "Customers can view order items" ON public.order_items
    FOR SELECT TO authenticated
    USING (
        EXISTS (
            SELECT 1 FROM public.orders o
            WHERE o.id = order_items.order_id
            AND (auth.belongs_to_team(o.team_id) OR auth.is_admin())
        )
    );

CREATE POLICY "Customers can insert order items for their orders" ON public.order_items
    FOR INSERT TO authenticated
    WITH CHECK (
        EXISTS (
            SELECT 1 FROM public.orders o
            WHERE o.id = order_items.order_id
            AND o.user_id = auth.uid()
            AND auth.belongs_to_team(o.team_id)
        )
    );

-- 8. Invoices & Invoice Items Policies
CREATE POLICY "Customers can view team invoices" ON public.invoices
    FOR SELECT TO authenticated
    USING (auth.belongs_to_team(team_id) OR auth.is_admin());

CREATE POLICY "Admins can manage invoices" ON public.invoices
    FOR ALL TO authenticated
    USING (auth.is_admin())
    WITH CHECK (auth.is_admin());

CREATE POLICY "Customers can view invoice items" ON public.invoice_items
    FOR SELECT TO authenticated
    USING (
        EXISTS (
            SELECT 1 FROM public.invoices i
            WHERE i.id = invoice_items.invoice_id
            AND (auth.belongs_to_team(i.team_id) OR auth.is_admin())
        )
    );

-- 9. Payment Proofs & Transactions Policies
CREATE POLICY "Customers can view their submitted payment proofs" ON public.payment_proofs
    FOR SELECT TO authenticated
    USING (
        user_id = auth.uid() OR
        EXISTS (
            SELECT 1 FROM public.invoices i
            WHERE i.id = payment_proofs.invoice_id AND auth.belongs_to_team(i.team_id)
        ) OR auth.is_admin()
    );

CREATE POLICY "Customers can upload payment proofs for unpaid invoices" ON public.payment_proofs
    FOR INSERT TO authenticated
    WITH CHECK (
        user_id = auth.uid() AND
        EXISTS (
            SELECT 1 FROM public.invoices i
            WHERE i.id = payment_proofs.invoice_id
            AND auth.belongs_to_team(i.team_id)
            AND i.status IN ('unpaid', 'payment_pending')
        )
    );

CREATE POLICY "Staff can review and update payment proofs" ON public.payment_proofs
    FOR UPDATE TO authenticated
    USING (auth.is_admin())
    WITH CHECK (auth.is_admin());

CREATE POLICY "Customers can view their payment transactions" ON public.payment_transactions
    FOR SELECT TO authenticated
    USING (
        EXISTS (
            SELECT 1 FROM public.invoices i
            WHERE i.id = payment_transactions.invoice_id
            AND (auth.belongs_to_team(i.team_id) OR auth.is_admin())
        )
    );

-- 10. Hosting Entitlements Policies
CREATE POLICY "Customers can view their team entitlements" ON public.hosting_entitlements
    FOR SELECT TO authenticated
    USING (auth.belongs_to_team(team_id) OR auth.is_admin());

CREATE POLICY "Admins can manage hosting entitlements" ON public.hosting_entitlements
    FOR ALL TO authenticated
    USING (auth.is_admin())
    WITH CHECK (auth.is_admin());

-- 11. Commercial Settings Policies
CREATE POLICY "Anyone can read public commercial settings" ON public.commercial_settings
    FOR SELECT TO anon, authenticated
    USING (true);

CREATE POLICY "Only Owners can modify commercial settings" ON public.commercial_settings
    FOR ALL TO authenticated
    USING (auth.is_owner())
    WITH CHECK (auth.is_owner());

-- 12. Deployments Policies
CREATE POLICY "Customers can view team deployments" ON public.deployments
    FOR SELECT TO authenticated
    USING (auth.belongs_to_team(team_id) OR auth.is_admin());

CREATE POLICY "Customers can initiate team deployments" ON public.deployments
    FOR INSERT TO authenticated
    WITH CHECK (
        auth.belongs_to_team(team_id) AND
        user_id = auth.uid()
    );

CREATE POLICY "Admins and owners can update deployments" ON public.deployments
    FOR UPDATE TO authenticated
    USING (auth.is_admin());

-- 13. Custom Domains Policies
CREATE POLICY "Customers can view their team custom domains" ON public.custom_domains
    FOR SELECT TO authenticated
    USING (auth.belongs_to_team(team_id) OR auth.is_admin());

CREATE POLICY "Customers can register custom domains for team" ON public.custom_domains
    FOR INSERT TO authenticated
    WITH CHECK (auth.belongs_to_team(team_id));

CREATE POLICY "Customers can delete their team custom domains" ON public.custom_domains
    FOR DELETE TO authenticated
    USING (auth.belongs_to_team(team_id) OR auth.is_admin());

-- 14. Service Instances & Credentials Policies
CREATE POLICY "Customers can view their service instances" ON public.service_instances
    FOR SELECT TO authenticated
    USING (auth.belongs_to_team(team_id) OR auth.is_admin());

CREATE POLICY "Staff can manage service instances" ON public.service_instances
    FOR ALL TO authenticated
    USING (auth.is_admin())
    WITH CHECK (auth.is_admin());

CREATE POLICY "Customers can view credentials for their instances" ON public.service_credentials
    FOR SELECT TO authenticated
    USING (
        EXISTS (
            SELECT 1 FROM public.service_instances si
            WHERE si.id = service_credentials.service_instance_id
            AND (auth.belongs_to_team(si.team_id) OR auth.is_admin())
        )
    );

-- 15. Monitors, Checks, Incidents & Public Status Policies
CREATE POLICY "Public can view active public monitors" ON public.monitors
    FOR SELECT TO anon, authenticated
    USING (is_public = true AND is_active = true OR auth.is_admin());

CREATE POLICY "Admins can manage monitors" ON public.monitors
    FOR ALL TO authenticated
    USING (auth.is_admin())
    WITH CHECK (auth.is_admin());

CREATE POLICY "Public can view monitor checks" ON public.monitor_checks
    FOR SELECT TO anon, authenticated
    USING (
        EXISTS (
            SELECT 1 FROM public.monitors m
            WHERE m.id = monitor_checks.monitor_id
            AND (m.is_public = true OR auth.is_admin())
        )
    );

CREATE POLICY "Public can view system incidents" ON public.system_incidents
    FOR SELECT TO anon, authenticated
    USING (true);

CREATE POLICY "Admins can manage system incidents" ON public.system_incidents
    FOR ALL TO authenticated
    USING (auth.is_admin())
    WITH CHECK (auth.is_admin());

CREATE POLICY "Public can view incident updates" ON public.incident_updates
    FOR SELECT TO anon, authenticated
    USING (true);

CREATE POLICY "Admins can manage incident updates" ON public.incident_updates
    FOR ALL TO authenticated
    USING (auth.is_admin())
    WITH CHECK (auth.is_admin());

CREATE POLICY "Anyone can subscribe to status alerts" ON public.status_subscriptions
    FOR INSERT TO anon, authenticated
    WITH CHECK (true);

-- 16. Audit Logs Policies (Strict Staff Read-Only)
CREATE POLICY "Admins can read audit logs" ON public.audit_logs
    FOR SELECT TO authenticated
    USING (auth.is_admin());

CREATE POLICY "Admins can read commerce audit logs" ON public.commerce_audit_logs
    FOR SELECT TO authenticated
    USING (auth.is_admin());

