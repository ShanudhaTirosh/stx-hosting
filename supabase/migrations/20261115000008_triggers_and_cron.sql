-- 20261115000008_triggers_and_cron.sql
-- Database triggers for business rules, automated billing lifecycle, and pg_cron scheduled jobs.

-- 1. Enable Required Extensions
CREATE EXTENSION IF NOT EXISTS "pg_net" WITH SCHEMA extensions;
CREATE EXTENSION IF NOT EXISTS "pg_cron" WITH SCHEMA extensions;

-- 2. Business Rule Trigger: Enforce Template Gating on Deployment
CREATE OR REPLACE FUNCTION public.fn_enforce_template_deployment()
RETURNS trigger LANGUAGE plpgsql SECURITY DEFINER AS $$
BEGIN
    -- If template deployment, verify it is globally enabled
    IF NEW.template_name IS NOT NULL THEN
        IF NOT EXISTS (
            SELECT 1 FROM public.template_permissions
            WHERE template_name = NEW.template_name
            AND is_globally_enabled = true
        ) AND NOT auth.is_admin() THEN
            RAISE EXCEPTION 'Template "%" is not currently enabled for deployment by platform administrators.', NEW.template_name;
        END IF;
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_enforce_template_deployment ON public.deployments;
CREATE TRIGGER trg_enforce_template_deployment
    BEFORE INSERT ON public.deployments
    FOR EACH ROW EXECUTE FUNCTION public.fn_enforce_template_deployment();

-- 3. Business Rule Trigger: Enforce Quotas & Limits Before Deployment
CREATE OR REPLACE FUNCTION public.fn_enforce_resource_limits()
RETURNS trigger LANGUAGE plpgsql SECURITY DEFINER AS $$
DECLARE
    limit_row public.hosting_limits%ROWTYPE;
    active_deployments_count INTEGER;
BEGIN
    -- Owners and Admins bypass limits
    IF auth.is_admin() THEN
        RETURN NEW;
    END IF;

    -- Lookup team limits or fallback to global default
    SELECT * INTO limit_row
    FROM public.hosting_limits
    WHERE team_id = NEW.team_id;

    IF limit_row.id IS NULL THEN
        SELECT * INTO limit_row
        FROM public.hosting_limits
        WHERE team_id IS NULL
        LIMIT 1;
    END IF;

    IF limit_row.id IS NOT NULL THEN
        SELECT count(*) INTO active_deployments_count
        FROM public.deployments
        WHERE team_id = NEW.team_id
        AND status IN ('queued', 'in_progress', 'finished');

        IF active_deployments_count >= limit_row.max_deployments THEN
            RAISE EXCEPTION 'Team deployment limit (%) exceeded. Please upgrade your plan or contact support.', limit_row.max_deployments;
        END IF;
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_enforce_resource_limits ON public.deployments;
CREATE TRIGGER trg_enforce_resource_limits
    BEFORE INSERT ON public.deployments
    FOR EACH ROW EXECUTE FUNCTION public.fn_enforce_resource_limits();

-- 4. Automated Entitlement Granting on Invoice Payment
CREATE OR REPLACE FUNCTION public.fn_handle_invoice_paid()
RETURNS trigger LANGUAGE plpgsql SECURITY DEFINER AS $$
DECLARE
    item_record RECORD;
    term_days INTEGER := 30;
BEGIN
    -- Only trigger when invoice status transitions to 'paid'
    IF NEW.status = 'paid' AND (OLD.status IS DISTINCT FROM 'paid') THEN
        -- Mark associated order paid/completed
        UPDATE public.orders
        SET status = 'paid', updated_at = now()
        WHERE id = NEW.order_id;

        -- Iterate order items to grant hosting entitlements
        FOR item_record IN
            SELECT oi.id, oi.quantity, pv.slug, p.category, pt.billing_type
            FROM public.order_items oi
            LEFT JOIN public.product_variants pv ON pv.id = oi.product_variant_id
            LEFT JOIN public.products p ON p.id = pv.product_id
            LEFT JOIN public.pricing_tiers pt ON pt.id = oi.pricing_tier_id
            WHERE oi.order_id = NEW.order_id
        LOOP
            IF item_record.billing_type = 'annual' THEN
                term_days := 365;
            ELSIF item_record.billing_type = 'quarterly' THEN
                term_days := 90;
            ELSE
                term_days := 30;
            END IF;

            INSERT INTO public.hosting_entitlements (
                team_id,
                product_type,
                status,
                expires_at,
                metadata
            )
            VALUES (
                NEW.team_id,
                COALESCE(item_record.category, 'app_hosting'),
                'active',
                now() + (term_days * item_record.quantity || ' days')::interval,
                jsonb_build_object(
                    'invoice_id', NEW.id,
                    'order_item_id', item_record.id,
                    'variant_slug', item_record.slug
                )
            );
        END LOOP;

        -- Reactivate any suspended services for this team
        UPDATE public.service_instances
        SET status = 'active', suspended_at = NULL, updated_at = now()
        WHERE team_id = NEW.team_id AND status = 'suspended';
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_handle_invoice_paid ON public.invoices;
CREATE TRIGGER trg_handle_invoice_paid
    AFTER UPDATE OF status ON public.invoices
    FOR EACH ROW EXECUTE FUNCTION public.fn_handle_invoice_paid();

-- 5. Stored Function: Generate Renewal Invoices for Expiring Entitlements
CREATE OR REPLACE FUNCTION public.fn_generate_renewal_invoices()
RETURNS INTEGER LANGUAGE plpgsql SECURITY DEFINER AS $$
DECLARE
    ent_record RECORD;
    count_generated INTEGER := 0;
    new_invoice_id UUID;
    inv_num TEXT;
BEGIN
    FOR ent_record IN
        SELECT e.id, e.team_id, e.expires_at, tm.user_id
        FROM public.hosting_entitlements e
        JOIN public.team_members tm ON tm.team_id = e.team_id AND tm.role = 'owner'
        WHERE e.status = 'active'
        AND e.expires_at IS NOT NULL
        AND e.expires_at <= (now() + interval '7 days')
        AND NOT EXISTS (
            SELECT 1 FROM public.invoices i
            WHERE i.team_id = e.team_id
            AND i.status IN ('unpaid', 'payment_pending')
            AND (i.metadata->>'entitlement_id')::text = e.id::text
        )
    LOOP
        inv_num := 'INV-' || to_char(now(), 'YYYYMM') || '-' || substr(md5(random()::text), 1, 6);

        INSERT INTO public.invoices (
            invoice_number,
            team_id,
            user_id,
            status,
            subtotal_cents,
            total_cents,
            currency,
            due_at,
            metadata
        )
        VALUES (
            inv_num,
            ent_record.team_id,
            ent_record.user_id,
            'unpaid',
            1000, -- Default renewal amount
            1000,
            'USD',
            ent_record.expires_at,
            jsonb_build_object('entitlement_id', ent_record.id, 'auto_renewal', true)
        )
        RETURNING id INTO new_invoice_id;

        INSERT INTO public.invoice_items (
            invoice_id,
            description,
            quantity,
            unit_price_cents,
            total_price_cents
        )
        VALUES (
            new_invoice_id,
            'Hosting Subscription Renewal',
            1,
            1000,
            1000
        );

        count_generated := count_generated + 1;
    END LOOP;

    RETURN count_generated;
END;
$$;

-- 6. Stored Function: Suspend Overdue Services (Grace period of 4 days)
CREATE OR REPLACE FUNCTION public.fn_process_overdue_suspensions()
RETURNS INTEGER LANGUAGE plpgsql SECURITY DEFINER AS $$
DECLARE
    count_suspended INTEGER := 0;
BEGIN
    -- Mark expired entitlements as suspended
    UPDATE public.hosting_entitlements
    SET status = 'suspended', updated_at = now()
    WHERE status = 'active'
    AND expires_at IS NOT NULL
    AND expires_at < (now() - interval '4 days');

    -- Suspend active service instances owned by teams with suspended entitlements
    WITH suspended_teams AS (
        SELECT DISTINCT team_id
        FROM public.hosting_entitlements
        WHERE status = 'suspended'
        AND team_id NOT IN (
            SELECT team_id FROM public.hosting_entitlements WHERE status = 'active'
        )
    )
    UPDATE public.service_instances
    SET status = 'suspended', suspended_at = now(), updated_at = now()
    WHERE team_id IN (SELECT team_id FROM suspended_teams)
    AND status = 'active';

    GET DIAGNOSTICS count_suspended = ROW_COUNT;
    RETURN count_suspended;
END;
$$;

-- 7. Stored Function: Retry Unverified Domains
CREATE OR REPLACE FUNCTION public.fn_retry_unverified_domains()
RETURNS INTEGER LANGUAGE plpgsql SECURITY DEFINER AS $$
DECLARE
    count_retried INTEGER := 0;
BEGIN
    UPDATE public.custom_domains
    SET check_attempts = check_attempts + 1,
        last_checked_at = now(),
        updated_at = now()
    WHERE status = 'pending_dns'
    AND check_attempts < 15;

    GET DIAGNOSTICS count_retried = ROW_COUNT;
    RETURN count_retried;
END;
$$;

