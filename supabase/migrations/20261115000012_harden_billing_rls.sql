-- 20261115000012_harden_billing_rls.sql
-- Harden Row Level Security (RLS) on billing, invoices, orders, and payment proofs.
-- Restricts financial data visibility strictly to team owners, admins, and designated billing roles,
-- preventing read-only viewers, developers, or regular members from querying company invoices and payment slips.

-- 1. Helper function for team role checking
CREATE OR REPLACE FUNCTION auth.has_team_role(check_team_id UUID, allowed_roles TEXT[]) RETURNS boolean AS $$
    SELECT EXISTS (
        SELECT 1 FROM public.team_members
        WHERE team_id = check_team_id
        AND user_id = auth.uid()
        AND role = ANY(allowed_roles)
    );
$$ LANGUAGE sql STABLE SECURITY DEFINER;

-- 2. Drop existing overly-broad customer billing policies
DROP POLICY IF EXISTS "Customers can view their team orders" ON public.orders;
DROP POLICY IF EXISTS "Customers can create team orders" ON public.orders;
DROP POLICY IF EXISTS "Customers can view order items" ON public.order_items;
DROP POLICY IF EXISTS "Customers can insert order items for their orders" ON public.order_items;

DROP POLICY IF EXISTS "Customers can view team invoices" ON public.invoices;
DROP POLICY IF EXISTS "Customers can view invoice items" ON public.invoice_items;

DROP POLICY IF EXISTS "Customers can view their submitted payment proofs" ON public.payment_proofs;
DROP POLICY IF EXISTS "Customers can upload payment proofs for unpaid invoices" ON public.payment_proofs;
DROP POLICY IF EXISTS "Customers can view team payment transactions" ON public.payment_transactions;

-- 3. Hardened Orders Policies (Owner, Admin, and Billing roles only)
CREATE POLICY "Authorized team members can view team orders" ON public.orders
    FOR SELECT TO authenticated
    USING (
        auth.has_team_role(team_id, ARRAY['owner', 'admin', 'billing']) OR
        auth.is_admin()
    );

CREATE POLICY "Authorized team members can create team orders" ON public.orders
    FOR INSERT TO authenticated
    WITH CHECK (
        (auth.has_team_role(team_id, ARRAY['owner', 'admin', 'billing']) AND user_id = auth.uid()) OR
        auth.is_admin()
    );

CREATE POLICY "Authorized team members can view order items" ON public.order_items
    FOR SELECT TO authenticated
    USING (
        EXISTS (
            SELECT 1 FROM public.orders o
            WHERE o.id = order_items.order_id
            AND (
                auth.has_team_role(o.team_id, ARRAY['owner', 'admin', 'billing']) OR
                auth.is_admin()
            )
        )
    );

CREATE POLICY "Authorized team members can insert order items" ON public.order_items
    FOR INSERT TO authenticated
    WITH CHECK (
        EXISTS (
            SELECT 1 FROM public.orders o
            WHERE o.id = order_items.order_id
            AND o.user_id = auth.uid()
            AND (
                auth.has_team_role(o.team_id, ARRAY['owner', 'admin', 'billing']) OR
                auth.is_admin()
            )
        )
    );

-- 4. Hardened Invoices Policies (Owner, Admin, and Billing roles only)
CREATE POLICY "Authorized team members can view team invoices" ON public.invoices
    FOR SELECT TO authenticated
    USING (
        auth.has_team_role(team_id, ARRAY['owner', 'admin', 'billing']) OR
        auth.is_admin()
    );

CREATE POLICY "Authorized team members can view invoice items" ON public.invoice_items
    FOR SELECT TO authenticated
    USING (
        EXISTS (
            SELECT 1 FROM public.invoices i
            WHERE i.id = invoice_items.invoice_id
            AND (
                auth.has_team_role(i.team_id, ARRAY['owner', 'admin', 'billing']) OR
                auth.is_admin()
            )
        )
    );

-- 5. Hardened Payment Proofs Policies (Uploader, Team Owner/Admin/Billing, or Staff)
CREATE POLICY "Authorized team members can view payment proofs" ON public.payment_proofs
    FOR SELECT TO authenticated
    USING (
        user_id = auth.uid() OR
        EXISTS (
            SELECT 1 FROM public.invoices i
            WHERE i.id = payment_proofs.invoice_id
            AND (
                auth.has_team_role(i.team_id, ARRAY['owner', 'admin', 'billing']) OR
                auth.is_admin()
            )
        ) OR
        auth.is_admin()
    );

CREATE POLICY "Authorized team members can upload payment proofs" ON public.payment_proofs
    FOR INSERT TO authenticated
    WITH CHECK (
        user_id = auth.uid() AND
        EXISTS (
            SELECT 1 FROM public.invoices i
            WHERE i.id = payment_proofs.invoice_id
            AND i.status IN ('unpaid', 'payment_pending')
            AND (
                auth.has_team_role(i.team_id, ARRAY['owner', 'admin', 'billing']) OR
                auth.is_admin()
            )
        )
    );

-- 6. Hardened Payment Transactions Policies
CREATE POLICY "Authorized team members can view payment transactions" ON public.payment_transactions
    FOR SELECT TO authenticated
    USING (
        EXISTS (
            SELECT 1 FROM public.invoices i
            WHERE i.id = payment_transactions.invoice_id
            AND (
                auth.has_team_role(i.team_id, ARRAY['owner', 'admin', 'billing']) OR
                auth.is_admin()
            )
        ) OR
        auth.is_admin()
    );
