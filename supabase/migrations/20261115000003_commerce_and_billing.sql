-- 20261115000003_commerce_and_billing.sql
-- Orders, order items, invoices, payment proofs, transactions, entitlements, and commercial settings.

-- 1. Orders Table
CREATE TABLE IF NOT EXISTS public.orders (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    uuid TEXT UNIQUE NOT NULL DEFAULT gen_random_uuid()::text,
    team_id UUID NOT NULL REFERENCES public.teams (id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES public.profiles (id) ON DELETE CASCADE,
    status TEXT NOT NULL DEFAULT 'pending', -- pending, payment_submitted, paid, completed, cancelled, refunded
    subtotal_cents INTEGER NOT NULL DEFAULT 0,
    discount_cents INTEGER NOT NULL DEFAULT 0,
    tax_cents INTEGER NOT NULL DEFAULT 0,
    total_cents INTEGER NOT NULL DEFAULT 0,
    currency TEXT NOT NULL DEFAULT 'USD',
    customer_notes TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_orders_team_id ON public.orders (team_id);
CREATE INDEX IF NOT EXISTS idx_orders_user_id ON public.orders (user_id);
CREATE INDEX IF NOT EXISTS idx_orders_status ON public.orders (status);

-- 2. Order Items Table
CREATE TABLE IF NOT EXISTS public.order_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id UUID NOT NULL REFERENCES public.orders (id) ON DELETE CASCADE,
    product_variant_id UUID REFERENCES public.product_variants (id) ON DELETE SET NULL,
    pricing_tier_id UUID REFERENCES public.pricing_tiers (id) ON DELETE SET NULL,
    quantity INTEGER NOT NULL DEFAULT 1,
    unit_price_cents INTEGER NOT NULL DEFAULT 0,
    total_price_cents INTEGER NOT NULL DEFAULT 0,
    hostname TEXT,
    datacenter TEXT DEFAULT 'fsn1',
    config_options JSONB DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_order_items_order_id ON public.order_items (order_id);

-- 3. Invoices Table
CREATE TABLE IF NOT EXISTS public.invoices (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    uuid TEXT UNIQUE NOT NULL DEFAULT gen_random_uuid()::text,
    invoice_number TEXT UNIQUE NOT NULL,
    order_id UUID REFERENCES public.orders (id) ON DELETE SET NULL,
    team_id UUID NOT NULL REFERENCES public.teams (id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES public.profiles (id) ON DELETE CASCADE,
    status TEXT NOT NULL DEFAULT 'unpaid', -- unpaid, payment_pending, paid, cancelled, refunded
    subtotal_cents INTEGER NOT NULL DEFAULT 0,
    tax_cents INTEGER NOT NULL DEFAULT 0,
    total_cents INTEGER NOT NULL DEFAULT 0,
    currency TEXT NOT NULL DEFAULT 'USD',
    due_at TIMESTAMPTZ NOT NULL,
    paid_at TIMESTAMPTZ,
    metadata JSONB DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_invoices_team_id ON public.invoices (team_id);
CREATE INDEX IF NOT EXISTS idx_invoices_user_id ON public.invoices (user_id);
CREATE INDEX IF NOT EXISTS idx_invoices_status ON public.invoices (status);
CREATE INDEX IF NOT EXISTS idx_invoices_due_at ON public.invoices (due_at);

-- 4. Invoice Items Table
CREATE TABLE IF NOT EXISTS public.invoice_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    invoice_id UUID NOT NULL REFERENCES public.invoices (id) ON DELETE CASCADE,
    order_item_id UUID REFERENCES public.order_items (id) ON DELETE SET NULL,
    description TEXT NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 1,
    unit_price_cents INTEGER NOT NULL DEFAULT 0,
    total_price_cents INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_invoice_items_invoice_id ON public.invoice_items (invoice_id);

-- 5. Payment Proofs (Customer Uploaded Receipts for Manual Review)
CREATE TABLE IF NOT EXISTS public.payment_proofs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    invoice_id UUID NOT NULL REFERENCES public.invoices (id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES public.profiles (id) ON DELETE CASCADE,
    payment_method TEXT NOT NULL DEFAULT 'bank_transfer',
    transaction_reference TEXT,
    file_path TEXT NOT NULL,
    file_mime TEXT NOT NULL,
    file_size_bytes INTEGER NOT NULL,
    sha256_hash TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending_review', -- pending_review, approved, rejected
    attempt_no INTEGER NOT NULL DEFAULT 1,
    admin_notes TEXT,
    rejection_reason TEXT,
    reviewed_by_user_id UUID REFERENCES public.profiles (id) ON DELETE SET NULL,
    reviewed_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_payment_proofs_invoice_id ON public.payment_proofs (invoice_id);
CREATE INDEX IF NOT EXISTS idx_payment_proofs_status ON public.payment_proofs (status);
CREATE INDEX IF NOT EXISTS idx_payment_proofs_sha256 ON public.payment_proofs (sha256_hash);

-- 6. Payment Transactions (Automated Gateways & Approved Proofs)
CREATE TABLE IF NOT EXISTS public.payment_transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    invoice_id UUID NOT NULL REFERENCES public.invoices (id) ON DELETE CASCADE,
    payment_proof_id UUID REFERENCES public.payment_proofs (id) ON DELETE SET NULL,
    gateway TEXT NOT NULL, -- stripe, bank_transfer, crypto
    transaction_id TEXT NOT NULL,
    amount_cents INTEGER NOT NULL,
    currency TEXT NOT NULL DEFAULT 'USD',
    status TEXT NOT NULL DEFAULT 'successful', -- pending, successful, failed, refunded
    gateway_payload JSONB DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_payment_transactions_invoice_id ON public.payment_transactions (invoice_id);
CREATE INDEX IF NOT EXISTS idx_payment_transactions_txn_id ON public.payment_transactions (transaction_id);

-- 7. Hosting Entitlements (Permissions unlocked by purchase)
CREATE TABLE IF NOT EXISTS public.hosting_entitlements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    team_id UUID NOT NULL REFERENCES public.teams (id) ON DELETE CASCADE,
    product_type TEXT NOT NULL DEFAULT 'app_hosting', -- app_hosting, database, vps
    status TEXT NOT NULL DEFAULT 'active', -- active, expired, suspended
    granted_by UUID REFERENCES public.profiles (id) ON DELETE SET NULL,
    expires_at TIMESTAMPTZ,
    metadata JSONB DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_hosting_entitlements_team_id ON public.hosting_entitlements (team_id);
CREATE INDEX IF NOT EXISTS idx_hosting_entitlements_status ON public.hosting_entitlements (status);
CREATE INDEX IF NOT EXISTS idx_hosting_entitlements_expires_at ON public.hosting_entitlements (expires_at);

-- 8. Commercial Settings
CREATE TABLE IF NOT EXISTS public.commercial_settings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    key TEXT UNIQUE NOT NULL,
    value TEXT,
    type TEXT NOT NULL DEFAULT 'string', -- string, json, boolean, integer
    description TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

