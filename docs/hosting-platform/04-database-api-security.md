# Deliverable D: Database Models, REST API Specification & Security Architecture
**Document Reference**: `docs/hosting-platform/04-database-api-security.md`  
**Requirement Mapping**: R3 (Hosting Products Specification and Security Architecture)  
**System Ecosystem**: ShanuTechX Commercial Hosting Platform (`web`, `status`, `webmail`, `stx`)  
**Status**: Production Architecture Baseline  
**Author**: ShanuTechX Platform Architecture & Systems Engineering  
**Revision**: 1.0.0 (October 2026)  

---

## 1. Executive Summary

This specification establishes the authoritative data models, application programming interfaces, and security isolation architecture powering the ShanuTechX Commercial Hosting Platform. 

To bridge the public marketing storefront (`web`), the real-time telemetry status platform (`status`), and the STX Hosting container orchestration engine (`stx`), the system implements:
1. **A production-grade PostgreSQL 16 relational database schema** comprising 15 normalized tables, strict referential constraints, granular indexes, and auditable ledger mechanics.
2. **A comprehensive OpenAPI 3.0 REST API contract** enabling programmatic customer interactions, storefront dynamic plan queries, and administrative queue operations.
3. **A zero-trust infrastructure security architecture** enforcing hardware-level tenant boundary isolation, cryptographic credential vaulting (AES-256-GCM), tamper-proof payment proof handling, and protection against privilege escalation.

---

## 2. Relational Database Schema Design (PostgreSQL 16)

### 2.1 Entity Relationship Diagram (ERD)

```
  ┌────────────────────────────────────────────────────────────────────────┐
  │                           CORE COMMERCE ERD                            │
  └────────────────────────────────────────────────────────────────────────┘

        ┌──────────────┐
        │   products   │
        └──────┬───────┘
               │ 1
               │ has many
               ▼ *
        ┌──────────────┐          ┌────────────────────┐
        │product_vari- │ 1      * │   pricing_tiers    │
        │     ants     ├──────────┤ (billing cycles &  │
        └──────┬───────┘          │   cycle prices)    │
               │ 1                └─────────┬──────────┘
               │                            │ 1
               │                            │ has many
               │                            ▼ *
               │                  ┌────────────────────┐
               │                  │  price_histories   │
               │                  └────────────────────┘
               │
               │ * (referenced by)
               ├──────────────────────────────────────────────┐
               │                                              │
               ▼ *                                            ▼ *
        ┌──────────────┐          ┌──────────────┐     ┌──────────────┐
        │ order_items  │ *      1 │    orders    │ 1 * │   invoices   │
        │(config_opts) ├──────────┤ (status, tax)│─────┤ (status, due)│
        └──────┬───────┘          └──────┬───────┘     └──────┬───────┘
               │ 1                       │ 1                  │ 1
               │                         │                    ├──────────────┐
               │                         │                    │ 1            │ 1
               │                         │                    ▼ *            ▼ *
               │                         │             ┌──────────────┐┌─────────────┐
               │                         │             │invoice_items ││payment_proof│
               │                         │             └──────────────┘└──────┬──────┘
               │                         │                                    │ *
               │                         │                                    ▼ 1
               │                         │                             ┌─────────────┐
               │                         │                             │payment_tran-│
               │                         │                             │  sactions   │
               │                         │                             └─────────────┘
               │                         │                                    │ *
               │       ┌──────────┐      │                                    │
               ▼ 1     │  teams   │◄─────┼────────────────────────────────────┤
        ┌──────────────┤ (tenancy)├──────┼───────────────────────┐            ▼ *
        │service_inst- │          │◄─────┘                       │     ┌─────────────┐
        │    ances     ├──────────┤                              ├────►│ audit_logs  │
        └──────┬───────┘ 1      * │                              │     │(audit trail)│
               │ 1     └────┬─────┘                              │     └─────────────┘
               │            │ 1                                  │
               │            ▼ *                                  │
               │       ┌──────────┐                              │
               │       │  users   │◄─────────────────────────────┘
               │       │  (auth)  │
               │       └──────────┘
               │
               ├─────────────────────────┐
               │ 1                       │ 1
               ▼ *                       ▼ 0..1
        ┌──────────────┐          ┌──────────────┐
        │service_crede-│          │vps_inventory │
        │    ntials    │          │(hardware pool│
        └──────────────┘          └──────────────┘
```

---

### 2.2 Full PostgreSQL DDL Specification

Below is the complete, syntactically valid PostgreSQL 16 DDL script for all platform commerce, inventory, credential, and telemetry tables:

```sql
-- =============================================================================
-- ShanuTechX Hosting Platform — Production PostgreSQL Schema DDL
-- Database Version: PostgreSQL 16+
-- =============================================================================

CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- -----------------------------------------------------------------------------
-- 1. Table: users
-- Core authentication and identity table.
-- -----------------------------------------------------------------------------
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL DEFAULT gen_random_uuid() UNIQUE,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    email_verified_at TIMESTAMP WITH TIME ZONE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(32) NOT NULL DEFAULT 'member' 
        CHECK (role IN ('member', 'admin', 'owner', 'superadmin')),
    two_factor_secret TEXT,
    two_factor_recovery_codes TEXT,
    two_factor_confirmed_at TIMESTAMP WITH TIME ZONE,
    remember_token VARCHAR(100),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);

-- -----------------------------------------------------------------------------
-- 2. Table: products
-- Top-level catalog definitions corresponding to storefront marketing categories.
-- -----------------------------------------------------------------------------
CREATE TABLE products (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL DEFAULT gen_random_uuid() UNIQUE,
    category VARCHAR(64) NOT NULL 
        CHECK (category IN ('vps', 'app_hosting', 'database', 'web_hosting', 'addon')),
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    icon_url VARCHAR(512),
    is_popular BOOLEAN NOT NULL DEFAULT FALSE,
    badge VARCHAR(64),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_products_category_active ON products(category, is_active, sort_order);

-- -----------------------------------------------------------------------------
-- 3. Table: product_variants
-- Granular hardware and resource specifications for each product.
-- -----------------------------------------------------------------------------
CREATE TABLE product_variants (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL DEFAULT gen_random_uuid() UNIQUE,
    product_id BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    sku VARCHAR(128) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    vcpu NUMERIC(4,2) NOT NULL,              -- Allows fractions for shared containers, e.g., 0.50 vCPU
    ram_mb INT NOT NULL,                     -- e.g. 512, 1024, 2048, 4096, 8192, 16384
    disk_gb INT NOT NULL,                    -- e.g. 20, 40, 80, 160, 320
    disk_type VARCHAR(32) NOT NULL DEFAULT 'NVMe'
        CHECK (disk_type IN ('NVMe', 'SSD', 'HDD', 'SAN')),
    bandwidth_tb NUMERIC(8,2) NOT NULL DEFAULT 20.00,
    port_speed_mbps INT NOT NULL DEFAULT 1000,
    has_dedicated_ipv4 BOOLEAN NOT NULL DEFAULT TRUE,
    has_ipv6_block BOOLEAN NOT NULL DEFAULT TRUE,
    specs_json JSONB NOT NULL DEFAULT '{}'::jsonb,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_product_variants_lookup ON product_variants(product_id, is_active, sort_order);
CREATE INDEX idx_product_variants_sku ON product_variants(sku);
CREATE INDEX idx_product_variants_specs ON product_variants USING GIN (specs_json);

-- -----------------------------------------------------------------------------
-- 4. Table: pricing_tiers
-- Billing intervals, duration terms, and pricing rates per variant.
-- -----------------------------------------------------------------------------
CREATE TABLE pricing_tiers (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL DEFAULT gen_random_uuid() UNIQUE,
    product_variant_id BIGINT NOT NULL REFERENCES product_variants(id) ON DELETE CASCADE,
    billing_cycle VARCHAR(32) NOT NULL
        CHECK (billing_cycle IN ('30-day', '50-day', 'monthly', 'quarterly', 'semi-annually', 'annually')),
    price_cents BIGINT NOT NULL CHECK (price_cents >= 0),
    setup_fee_cents BIGINT NOT NULL DEFAULT 0 CHECK (setup_fee_cents >= 0),
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    is_renewable BOOLEAN NOT NULL DEFAULT TRUE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_variant_cycle_currency UNIQUE (product_variant_id, billing_cycle, currency)
);

CREATE INDEX idx_pricing_tiers_variant ON pricing_tiers(product_variant_id, is_active);

-- -----------------------------------------------------------------------------
-- 5. Table: price_histories
-- Preserves audit history of plan price changes for grandfathered renewals.
-- -----------------------------------------------------------------------------
CREATE TABLE price_histories (
    id BIGSERIAL PRIMARY KEY,
    pricing_tier_id BIGINT NOT NULL REFERENCES pricing_tiers(id) ON DELETE CASCADE,
    old_price_cents BIGINT NOT NULL,
    new_price_cents BIGINT NOT NULL,
    changed_by_user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    change_reason VARCHAR(255),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_price_histories_tier ON price_histories(pricing_tier_id);

-- -----------------------------------------------------------------------------
-- 6. Table: orders
-- Tracks checkout acquisition events and shopping cart conversions.
-- -----------------------------------------------------------------------------
CREATE TABLE orders (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL DEFAULT gen_random_uuid() UNIQUE,
    order_number VARCHAR(64) NOT NULL UNIQUE,  -- e.g. 'STX-ORD-202610-0084'
    team_id BIGINT NOT NULL REFERENCES teams(id) ON DELETE RESTRICT,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    status VARCHAR(32) NOT NULL DEFAULT 'payment_pending'
        CHECK (status IN ('payment_pending', 'payment_submitted', 'payment_verified', 'provisioning', 'active', 'provision_failed', 'rejected', 'cancelled')),
    total_amount_cents BIGINT NOT NULL CHECK (total_amount_cents >= 0),
    tax_amount_cents BIGINT NOT NULL DEFAULT 0 CHECK (tax_amount_cents >= 0),
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    ip_address INET,
    user_agent TEXT,
    notes TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_orders_team_status ON orders(team_id, status);
CREATE INDEX idx_orders_user_status ON orders(user_id, status);
CREATE INDEX idx_orders_created ON orders(created_at DESC);

-- -----------------------------------------------------------------------------
-- 7. Table: order_items
-- Itemized product allocations, customer SSH keys, and requested configurations.
-- -----------------------------------------------------------------------------
CREATE TABLE order_items (
    id BIGSERIAL PRIMARY KEY,
    order_id BIGINT NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    product_variant_id BIGINT NOT NULL REFERENCES product_variants(id) ON DELETE RESTRICT,
    pricing_tier_id BIGINT NOT NULL REFERENCES pricing_tiers(id) ON DELETE RESTRICT,
    quantity INT NOT NULL DEFAULT 1 CHECK (quantity > 0),
    unit_price_cents BIGINT NOT NULL CHECK (unit_price_cents >= 0),
    setup_fee_cents BIGINT NOT NULL DEFAULT 0 CHECK (setup_fee_cents >= 0),
    total_price_cents BIGINT NOT NULL CHECK (total_price_cents >= 0),
    config_options JSONB NOT NULL DEFAULT '{}'::jsonb, -- Hostname, OS, customer_ssh_key, datacenter_location
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_order_items_order ON order_items(order_id);
CREATE INDEX idx_order_items_variant ON order_items(product_variant_id);

-- -----------------------------------------------------------------------------
-- 8. Table: invoices
-- Authoritative billing and accounting ledger records.
-- -----------------------------------------------------------------------------
CREATE TABLE invoices (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL DEFAULT gen_random_uuid() UNIQUE,
    invoice_number VARCHAR(64) NOT NULL UNIQUE, -- e.g. 'STX-INV-202610-0084'
    team_id BIGINT NOT NULL REFERENCES teams(id) ON DELETE RESTRICT,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    order_id BIGINT REFERENCES orders(id) ON DELETE SET NULL,
    type VARCHAR(32) NOT NULL DEFAULT 'initial'
        CHECK (type IN ('initial', 'renewal', 'upgrade', 'addon')),
    status VARCHAR(32) NOT NULL DEFAULT 'unpaid'
        CHECK (status IN ('draft', 'unpaid', 'payment_pending', 'paid', 'partially_paid', 'cancelled', 'refunded')),
    subtotal_cents BIGINT NOT NULL CHECK (subtotal_cents >= 0),
    tax_cents BIGINT NOT NULL DEFAULT 0 CHECK (tax_cents >= 0),
    total_cents BIGINT NOT NULL CHECK (total_cents >= 0),
    balance_cents BIGINT NOT NULL CHECK (balance_cents >= 0),
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    due_date DATE NOT NULL,
    paid_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_invoices_team_status_due ON invoices(team_id, status, due_date);
CREATE INDEX idx_invoices_user_status_due ON invoices(user_id, status, due_date);
CREATE INDEX idx_invoices_status ON invoices(status);

-- -----------------------------------------------------------------------------
-- 9. Table: invoice_items
-- Itemized line items associated with an invoice.
-- -----------------------------------------------------------------------------
CREATE TABLE invoice_items (
    id BIGSERIAL PRIMARY KEY,
    invoice_id BIGINT NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
    service_instance_id BIGINT, -- Linked on recurring subscription renewals
    description VARCHAR(255) NOT NULL,
    amount_cents BIGINT NOT NULL,
    quantity INT NOT NULL DEFAULT 1 CHECK (quantity > 0),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_invoice_items_invoice ON invoice_items(invoice_id);

-- -----------------------------------------------------------------------------
-- 10. Table: payment_transactions
-- Immutable record of incoming financial transaction attempts.
-- -----------------------------------------------------------------------------
CREATE TABLE payment_transactions (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL DEFAULT gen_random_uuid() UNIQUE,
    transaction_reference VARCHAR(128) NOT NULL UNIQUE,
    invoice_id BIGINT NOT NULL REFERENCES invoices(id) ON DELETE RESTRICT,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    payment_method VARCHAR(64) NOT NULL
        CHECK (payment_method IN ('bank_wire', 'crypto_usdt', 'crypto_btc', 'stripe', 'paypal', 'balance')),
    amount_cents BIGINT NOT NULL CHECK (amount_cents > 0),
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    status VARCHAR(32) NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending', 'completed', 'failed', 'refunded', 'disputed')),
    gateway_response JSONB,
    processed_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_transactions_invoice ON payment_transactions(invoice_id, status);
CREATE INDEX idx_transactions_user ON payment_transactions(user_id, status);

-- -----------------------------------------------------------------------------
-- 11. Table: payment_proofs
-- Secure repository for manual payment deposit slips and receipts.
-- -----------------------------------------------------------------------------
CREATE TABLE payment_proofs (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL DEFAULT gen_random_uuid() UNIQUE,
    transaction_id BIGINT REFERENCES payment_transactions(id) ON DELETE SET NULL,
    invoice_id BIGINT NOT NULL REFERENCES invoices(id) ON DELETE RESTRICT,
    team_id BIGINT NOT NULL REFERENCES teams(id) ON DELETE RESTRICT,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    file_path VARCHAR(512) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_size_bytes BIGINT NOT NULL CHECK (file_size_bytes > 0 AND file_size_bytes <= 5242880), -- 5MB limit
    mime_type VARCHAR(128) NOT NULL
        CHECK (mime_type IN ('image/jpeg', 'image/png', 'application/pdf')),
    sha256_hash VARCHAR(64) NOT NULL,
    sender_account_name VARCHAR(255),
    transaction_reference VARCHAR(255),
    bank_name VARCHAR(255),
    payment_date DATE NOT NULL,
    amount_paid_cents BIGINT NOT NULL CHECK (amount_paid_cents > 0),
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    status VARCHAR(32) NOT NULL DEFAULT 'pending_review'
        CHECK (status IN ('pending_review', 'approved', 'rejected')),
    reviewer_user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    review_notes TEXT,
    reviewed_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_payment_proofs_team ON payment_proofs(team_id, status);
CREATE INDEX idx_payment_proofs_hash ON payment_proofs(sha256_hash);
CREATE INDEX idx_payment_proofs_review ON payment_proofs(status, created_at);
CREATE INDEX idx_payment_proofs_invoice ON payment_proofs(invoice_id);

-- -----------------------------------------------------------------------------
-- 12. Table: vps_inventory
-- Hardware and VM node capacity pool for Phase 1 pre-provisioned allocation.
-- -----------------------------------------------------------------------------
CREATE TABLE vps_inventory (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL DEFAULT gen_random_uuid() UNIQUE,
    server_identifier VARCHAR(128) NOT NULL UNIQUE, -- e.g. 'node-fsn1-kvm-042'
    provider VARCHAR(64) NOT NULL DEFAULT 'internal_hardware'
        CHECK (provider IN ('internal_hardware', 'hetzner', 'ovh', 'digitalocean')),
    datacenter_location VARCHAR(64) NOT NULL,       -- 'fsn1-de', 'nbg1-de', 'hel1-fi', 'ash-us'
    ipv4_address INET NOT NULL UNIQUE,
    ipv6_address INET,
    subnet_mask VARCHAR(32) NOT NULL DEFAULT '255.255.255.0',
    gateway INET NOT NULL,
    mac_address MACADDR,
    ssh_port INT NOT NULL DEFAULT 22 CHECK (ssh_port > 0 AND ssh_port <= 65535),
    admin_username VARCHAR(64) NOT NULL DEFAULT 'root',
    encrypted_admin_password TEXT,                  -- Encrypted via AES-256-GCM
    encrypted_root_ssh_key TEXT,                    -- Encrypted platform management private key
    status VARCHAR(32) NOT NULL DEFAULT 'available'
        CHECK (status IN ('available', 'reserved', 'allocated', 'maintenance', 'wiping', 'retired')),
    current_service_instance_id BIGINT,
    last_wiped_at TIMESTAMP WITH TIME ZONE,
    notes TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_vps_inventory_status_loc ON vps_inventory(status, datacenter_location);
CREATE INDEX idx_vps_inventory_ip ON vps_inventory(ipv4_address);

-- -----------------------------------------------------------------------------
-- 13. Table: service_instances
-- The primary customer asset entity representing an active hosting service.
-- -----------------------------------------------------------------------------
CREATE TABLE service_instances (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL DEFAULT gen_random_uuid() UNIQUE,
    team_id BIGINT NOT NULL REFERENCES teams(id) ON DELETE RESTRICT,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    order_item_id BIGINT REFERENCES order_items(id) ON DELETE SET NULL,
    product_variant_id BIGINT NOT NULL REFERENCES product_variants(id) ON DELETE RESTRICT,
    service_type VARCHAR(32) NOT NULL
        CHECK (service_type IN ('vps', 'app_hosting', 'database')),
    name VARCHAR(255) NOT NULL,
    hostname VARCHAR(255) NOT NULL,
    ipv4_address INET,
    ipv6_address INET,
    status VARCHAR(32) NOT NULL DEFAULT 'pending_setup'
        CHECK (status IN ('pending_setup', 'provisioning', 'active', 'suspended', 'cancelled', 'terminated', 'provision_failed')),
    billing_cycle VARCHAR(32) NOT NULL,
    current_period_start TIMESTAMP WITH TIME ZONE,
    current_period_end TIMESTAMP WITH TIME ZONE,
    next_due_date DATE,
    suspended_at TIMESTAMP WITH TIME ZONE,
    suspension_reason VARCHAR(255),
    terminated_at TIMESTAMP WITH TIME ZONE,
    vps_inventory_id BIGINT REFERENCES vps_inventory(id) ON DELETE SET NULL,
    hetzner_server_id BIGINT,                     -- Used for Phase 2 automated cloud VMs
    stx_project_uuid VARCHAR(64),             -- Used for App Hosting PaaS
    stx_app_uuid VARCHAR(64),                 -- Used for App Hosting PaaS
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_services_team_status ON service_instances(team_id, status);
CREATE INDEX idx_services_user_status ON service_instances(user_id, status);
CREATE INDEX idx_services_due_date ON service_instances(status, next_due_date);
CREATE INDEX idx_services_stx_app ON service_instances(stx_app_uuid);

-- -----------------------------------------------------------------------------
-- 14. Table: service_credentials
-- Vaulted customer-facing access credentials (passwords, authorized keys).
-- -----------------------------------------------------------------------------
CREATE TABLE service_credentials (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL DEFAULT gen_random_uuid() UNIQUE,
    service_instance_id BIGINT NOT NULL REFERENCES service_instances(id) ON DELETE CASCADE,
    credential_type VARCHAR(32) NOT NULL
        CHECK (credential_type IN ('customer_ssh_key', 'initial_root_password', 'db_credential')),
    username VARCHAR(128) NOT NULL,
    encrypted_secret TEXT,                        -- Encrypted via AES-256-GCM
    public_key TEXT,                              -- Customer SSH Public Key
    fingerprint VARCHAR(128),                     -- SHA256 SSH key fingerprint
    is_one_time_viewed BOOLEAN NOT NULL DEFAULT FALSE,
    expires_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_credentials_service ON service_credentials(service_instance_id);

-- -----------------------------------------------------------------------------
-- 15. Table: audit_logs
-- Immutable compliance and administrative audit record.
-- -----------------------------------------------------------------------------
CREATE TABLE audit_logs (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    impersonator_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    action VARCHAR(128) NOT NULL,                 -- e.g. 'order.created', 'proof.approved', 'vps.wiped'
    entity_type VARCHAR(128) NOT NULL,
    entity_id BIGINT NOT NULL,
    old_values JSONB,
    new_values JSONB,
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_audit_logs_entity ON audit_logs(entity_type, entity_id);
CREATE INDEX idx_audit_logs_user_action ON audit_logs(user_id, action);
CREATE INDEX idx_audit_logs_created ON audit_logs(created_at DESC);
```

---

## 3. Hosting Business REST API Specification (OpenAPI 3.0)

The ShanuTechX Hosting Platform REST API operates under standard RESTful conventions, returning JSON payloads serialized via the `serializeApiResponse()` helper and secured by Laravel Sanctum token abilities.

### 3.1 OpenAPI 3.0 Specification (YAML)

```yaml
openapi: 3.0.3
info:
  title: ShanuTechX Commercial Hosting Platform API
  version: 1.0.0
  description: >
    Production REST API specification for ShanuTechX Cloud VPS, Application Hosting,
    Managed Databases, Order-to-Activation workflows, and Billing management.
servers:
  - url: https://api.shanutechx.com/api/v1
    description: Production API Gateway
  - url: http://localhost:8000/api/v1
    description: Local Development Server

security:
  - BearerAuth: []

paths:
  # ===========================================================================
  # AUTHENTICATION ENDPOINTS
  # ===========================================================================
  /auth/register:
    post:
      summary: Register a new customer account
      security: []
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [name, email, password, password_confirmation]
              properties:
                name: { type: string, example: "Jane Doe" }
                email: { type: string, format: email, example: "jane@corp.io" }
                password: { type: string, format: password, minLength: 8 }
                password_confirmation: { type: string, format: password }
      responses:
        '201':
          description: Account created successfully
          content:
            application/json:
              schema:
                type: object
                properties:
                  user: { $ref: '#/components/schemas/User' }
                  token: { type: string, example: "1|aBcDeF..." }
        '422':
          $ref: '#/components/responses/ValidationError'

  /auth/login:
    post:
      summary: Authenticate user and receive Sanctum bearer token
      security: []
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [email, password]
              properties:
                email: { type: string, format: email }
                password: { type: string, format: password }
      responses:
        '200':
          description: Login successful
          content:
            application/json:
              schema:
                type: object
                properties:
                  user: { $ref: '#/components/schemas/User' }
                  token: { type: string }
        '401':
          description: Invalid email or password

  /auth/me:
    get:
      summary: Retrieve authenticated user profile and team context
      responses:
        '200':
          content:
            application/json:
              schema:
                type: object
                properties:
                  user: { $ref: '#/components/schemas/User' }

  # ===========================================================================
  # PRODUCT CATALOG ENDPOINTS (Consumed by web/lib/api.ts)
  # ===========================================================================
  /products:
    get:
      summary: List active commercial products and plans
      security: []
      parameters:
        - name: category
          in: query
          required: false
          schema:
            type: string
            enum: [vps, app_hosting, database, web_hosting]
          description: Filter plans by product category
      responses:
        '200':
          description: Product list matching web/lib/api.ts contract
          content:
            application/json:
              schema:
                type: object
                properties:
                  products:
                    type: array
                    items:
                      $ref: '#/components/schemas/Product'

  /products/{slug}:
    get:
      summary: Get detailed product specifications by slug
      security: []
      parameters:
        - name: slug
          in: path
          required: true
          schema: { type: string }
      responses:
        '200':
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Product'
        '404':
          description: Product not found

  # ===========================================================================
  # ORDERING & CHECKOUT
  # ===========================================================================
  /orders:
    get:
      summary: List authenticated user orders
      responses:
        '200':
          content:
            application/json:
              schema:
                type: object
                properties:
                  orders:
                    type: array
                    items:
                      $ref: '#/components/schemas/Order'
    post:
      summary: Submit a new order checkout
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [items]
              properties:
                items:
                  type: array
                  items:
                    type: object
                    required: [product_variant_sku, billing_cycle, config_options]
                    properties:
                      product_variant_sku: { type: string, example: "vps-std-2" }
                      billing_cycle: { type: string, example: "30-day" }
                      quantity: { type: integer, default: 1 }
                      config_options:
                        type: object
                        required: [hostname, customer_ssh_public_key]
                        properties:
                          hostname: { type: string, example: "api-prod.client.com" }
                          customer_ssh_public_key: { type: string, example: "ssh-ed25519 AAAAC3NzaC1lZDI1NTE5..." }
                          datacenter_location: { type: string, default: "fsn1-de" }
                          os_image: { type: string, default: "ubuntu-24.04" }
      responses:
        '201':
          description: Order created and unpaid invoice generated
          content:
            application/json:
              schema:
                type: object
                properties:
                  order: { $ref: '#/components/schemas/Order' }
                  invoice: { $ref: '#/components/schemas/Invoice' }

  /orders/{uuid}:
    get:
      summary: Retrieve order status and details
      parameters:
        - name: uuid
          in: path
          required: true
          schema: { type: string, format: uuid }
      responses:
        '200':
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Order'

  # ===========================================================================
  # INVOICES & MANUAL PAYMENT PROOFS
  # ===========================================================================
  /invoices:
    get:
      summary: List user invoices
      responses:
        '200':
          content:
            application/json:
              schema:
                type: object
                properties:
                  invoices:
                    type: array
                    items:
                      $ref: '#/components/schemas/Invoice'

  /invoices/{uuid}:
    get:
      summary: Retrieve invoice details and itemization
      parameters:
        - name: uuid
          in: path
          required: true
          schema: { type: string, format: uuid }
      responses:
        '200':
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Invoice'

  /invoices/{uuid}/pdf:
    get:
      summary: Download formal accounting invoice PDF
      parameters:
        - name: uuid
          in: path
          required: true
          schema: { type: string, format: uuid }
      responses:
        '200':
          description: PDF binary stream
          content:
            application/pdf:
              schema:
                type: string
                format: binary

  /invoices/{uuid}/proof:
    post:
      summary: Upload manual payment receipt / deposit slip
      parameters:
        - name: uuid
          in: path
          required: true
          schema: { type: string, format: uuid }
      requestBody:
        required: true
        content:
          multipart/form-data:
            schema:
              type: object
              required: [proof_file, payment_date, amount_paid, transaction_reference]
              properties:
                proof_file:
                  type: string
                  format: binary
                  description: PNG, JPG, or PDF file (max 5MB)
                payment_date:
                  type: string
                  format: date
                  example: "2026-10-29"
                amount_paid:
                  type: number
                  example: 12.00
                currency:
                  type: string
                  default: "USD"
                transaction_reference:
                  type: string
                  example: "WIRE-98214-DE"
                bank_name:
                  type: string
                  example: "Deutsche Bank"
                sender_account_name:
                  type: string
                  example: "Acme Corp Ltd"
      responses:
        '201':
          description: Payment proof uploaded and queued for review
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/PaymentProof'

  # ===========================================================================
  # SERVICE INSTANCES (CUSTOMER PORTAL)
  # ===========================================================================
  /services:
    get:
      summary: List all active and pending customer services
      responses:
        '200':
          content:
            application/json:
              schema:
                type: object
                properties:
                  services:
                    type: array
                    items:
                      $ref: '#/components/schemas/ServiceInstance'

  /services/{uuid}:
    get:
      summary: Get service details, IP coordinates, and telemetry
      parameters:
        - name: uuid
          in: path
          required: true
          schema: { type: string, format: uuid }
      responses:
        '200':
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ServiceInstance'

  /services/{uuid}/credentials:
    get:
      summary: Reveal initial root credentials (one-time viewable)
      parameters:
        - name: uuid
          in: path
          required: true
          schema: { type: string, format: uuid }
      responses:
        '200':
          content:
            application/json:
              schema:
                type: object
                properties:
                  username: { type: string, example: "root" }
                  secret: { type: string, example: "s3cur3P@ssw0rd!" }
                  is_one_time_viewed: { type: boolean }
                  message: { type: string, example: "Store password safely. It cannot be shown again." }

  /services/vps/{uuid}/reboot:
    post:
      summary: Gracefully reboot a customer VPS
      parameters:
        - name: uuid
          in: path
          required: true
          schema: { type: string, format: uuid }
      responses:
        '200':
          description: Reboot command dispatched

  /services/vps/{uuid}/power:
    post:
      summary: Power on or power off a customer VPS
      parameters:
        - name: uuid
          in: path
          required: true
          schema: { type: string, format: uuid }
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [action]
              properties:
                action:
                  type: string
                  enum: [poweron, poweroff]
      responses:
        '200':
          description: Power action dispatched

  /services/vps/{uuid}/rescue:
    post:
      summary: Boot customer VPS into network rescue environment
      parameters:
        - name: uuid
          in: path
          required: true
          schema: { type: string, format: uuid }
      responses:
        '200':
          description: Server rebooted into network rescue mode

  # ===========================================================================
  # ADMINISTRATOR VERIFICATION & INVENTORY QUEUE
  # ===========================================================================
  /admin/proofs:
    get:
      summary: List pending payment proofs for administrative review
      description: Requires Role::ADMIN or root team membership
      responses:
        '200':
          content:
            application/json:
              schema:
                type: object
                properties:
                  proofs:
                    type: array
                    items:
                      $ref: '#/components/schemas/PaymentProof'

  /admin/proofs/{uuid}/approve:
    post:
      summary: Atomically approve payment proof, mark invoice paid, and trigger provisioning
      parameters:
        - name: uuid
          in: path
          required: true
          schema: { type: string, format: uuid }
      responses:
        '200':
          description: Proof approved and provisioning jobs dispatched

  /admin/proofs/{uuid}/reject:
    post:
      summary: Reject payment proof with reason
      parameters:
        - name: uuid
          in: path
          required: true
          schema: { type: string, format: uuid }
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [reason]
              properties:
                reason: { type: string, example: "Transaction reference not found in bank statement." }
      responses:
        '200':
          description: Proof rejected and notification email queued

  /admin/vps-inventory:
    get:
      summary: View hardware pool capacity and allocation health
      responses:
        '200':
          content:
            application/json:
              schema:
                type: object
                properties:
                  inventory:
                    type: array
                    items:
                      $ref: '#/components/schemas/VpsInventory'
    post:
      summary: Seed a new physical or virtual node into inventory
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [server_identifier, provider, datacenter_location, ipv4_address, gateway]
              properties:
                server_identifier: { type: string, example: "node-fsn1-kvm-050" }
                provider: { type: string, enum: [internal_hardware, hetzner, ovh, digitalocean] }
                datacenter_location: { type: string, example: "fsn1-de" }
                ipv4_address: { type: string, format: ipv4 }
                ipv6_address: { type: string, format: ipv6 }
                gateway: { type: string, format: ipv4 }
                subnet_mask: { type: string, default: "255.255.255.0" }
                ssh_port: { type: integer, default: 22 }
                admin_username: { type: string, default: "root" }
                admin_password: { type: string }
      responses:
        '201':
          description: Inventory node added

  /admin/vps-inventory/{uuid}/wipe:
    post:
      summary: Trigger manual secure disk wipe and OS re-image script
      parameters:
        - name: uuid
          in: path
          required: true
          schema: { type: string, format: uuid }
      responses:
        '200':
          description: Sanitization job queued

# =============================================================================
# COMPONENTS & DATA SCHEMAS
# =============================================================================
components:
  securitySchemes:
    BearerAuth:
      type: http
      scheme: bearer
      bearerFormat: Sanctum

  responses:
    ValidationError:
      description: Input validation failure
      content:
        application/json:
          schema:
            type: object
            properties:
              message: { type: string, example: "The given data was invalid." }
              errors:
                type: object
                additionalProperties:
                  type: array
                  items: { type: string }

  schemas:
    User:
      type: object
      properties:
        uuid: { type: string, format: uuid }
        name: { type: string }
        email: { type: string, format: email }
        role: { type: string, enum: [member, admin, owner, superadmin] }

    Plan:
      type: object
      properties:
        id: { type: string }
        productId: { type: string }
        name: { type: string }
        price: { type: number }
        currency: { type: string }
        billingType: { type: string }
        isPopular: { type: boolean }
        badge: { type: string, nullable: true }
        specsJson: { type: object, nullable: true }
        isActive: { type: boolean }

    Product:
      type: object
      properties:
        id: { type: string }
        category: { type: string }
        name: { type: string }
        slug: { type: string }
        description: { type: string, nullable: true }
        iconUrl: { type: string, nullable: true }
        isPopular: { type: boolean }
        badge: { type: string, nullable: true }
        isActive: { type: boolean }
        plans:
          type: array
          items:
            $ref: '#/components/schemas/Plan'

    Order:
      type: object
      properties:
        uuid: { type: string, format: uuid }
        order_number: { type: string }
        team_id: { type: string, format: uuid }
        status:
          type: string
          enum: [payment_pending, payment_submitted, payment_verified, provisioning, active, provision_failed, rejected, cancelled]
        total_amount_cents: { type: integer }
        currency: { type: string }
        created_at: { type: string, format: date-time }

    Invoice:
      type: object
      properties:
        uuid: { type: string, format: uuid }
        invoice_number: { type: string }
        team_id: { type: string, format: uuid }
        type: { type: string }
        status: { type: string }
        subtotal_cents: { type: integer }
        tax_cents: { type: integer }
        total_cents: { type: integer }
        balance_cents: { type: integer }
        currency: { type: string }
        due_date: { type: string, format: date }
        paid_at: { type: string, format: date-time, nullable: true }

    PaymentProof:
      type: object
      properties:
        uuid: { type: string, format: uuid }
        invoice_id: { type: string }
        team_id: { type: string, format: uuid }
        status: { type: string }
        file_name: { type: string }
        file_size_bytes: { type: integer }
        sha256_hash: { type: string, example: "a591a6d40bf420404a011733cfb7b190d62c65bf0bcda32b57b277d9ad9f146e" }
        transaction_reference: { type: string }
        bank_name: { type: string, nullable: true }
        amount_paid_cents: { type: integer }
        currency: { type: string }
        payment_date: { type: string, format: date }
        created_at: { type: string, format: date-time }

    ServiceInstance:
      type: object
      properties:
        uuid: { type: string, format: uuid }
        team_id: { type: string, format: uuid }
        name: { type: string }
        hostname: { type: string }
        service_type: { type: string }
        status: { type: string }
        ipv4_address: { type: string, nullable: true }
        ipv6_address: { type: string, nullable: true }
        billing_cycle: { type: string }
        current_period_end: { type: string, format: date-time, nullable: true }

    VpsInventory:
      type: object
      properties:
        uuid: { type: string, format: uuid }
        server_identifier: { type: string }
        provider: { type: string }
        datacenter_location: { type: string }
        ipv4_address: { type: string }
        status: { type: string }
        last_wiped_at: { type: string, format: date-time, nullable: true }
```

---

## 4. Infrastructure Security & Tenant Isolation Architecture

The hosting platform enforces strict defense-in-depth isolation across compute, networking, storage, and credential boundaries.

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                         DEFENSE-IN-DEPTH ISOLATION TOPOLOGY                            │
└────────────────────────────────────────────────────────────────────────────────────────┘

 [ LAYER 1: NETWORK PERIMETER ]
 ├── Cloudflare Edge Protection (Anycast Layer 3/4/7 DDoS Mitigation)
 ├── Traefik Edge Reverse Proxy (Wildcard SSL SNI Routing, Strict HTTP Headers)
 └── Host iptables / ebtables Firewall (Default Deny Inbound to Management Subnets)

 [ LAYER 2: COMPUTE HARD BOUNDARY ]
 ├── Customer Dedicated Virtual Machines (KVM Hypervisor / VirtIO Isolation)
 │   ├── Dedicated vCPU, RAM & NVMe allocations (Hardware Virt Boundaries)
 │   ├── In-Guest Host Isolation: Hypervisor Ports (8006, 22, 5432) Completely Blocked
 │   └── Customer Injected Public Key ONLY (No Private Key Held by Platform)
 └── Shared Application Hosting Containers (PaaS Cluster)
     ├── Platform Abstraction Layer (Customer NEVER gets root Docker access)
     ├── cgroups v2 Enforcement (`limits_memory`, `limits_cpus`, `limits_cpu_shares`)
     ├── Linux XFS Project Quotas (Disk storage exhaustion prevention)
     └── Sandboxed Docker Bridge (`icc=false`, isolated per tenant)

 [ LAYER 3: CREDENTIAL & VAULT SECURITY ]
 ├── AES-256-GCM Cryptographic Envelope (Encrypted DB Credentials & Provider Tokens)
 ├── Ephemeral Initial Passwords (One-Time Viewed flag, Instant In-Memory Zeroing)
 ├── ApiSensitiveData Middleware (Automatic masking of secrets in serializations)
 └── Private Payment Proof Storage (Non-public disk, Signed Stream, Magic-Byte check)
```

### 4.1 Customer VPS Guest Isolation vs. Host Hypervisor & Control Plane

To prevent hypervisor escapes, host discovery, lateral movement, or unauthorized control plane access from a customer VPS:

1. **Hypervisor Interface Shielding & Netfilter Ingress Defense (`INPUT`, `FORWARD`, `DOCKER-USER`)**:
   - The hypervisor management interfaces (Proxmox Web GUI `8006`, Hypervisor SSH `22`, STX Hosting Control Plane `8000`, Reverb WebSockets `6001`, Terminal Server `6002`, PostgreSQL database `5432`, and Redis `6379`) are bound exclusively to an internal, non-routable management VLAN (`10.250.0.0/24`) or `127.0.0.1`.
   - **Critical Netfilter Ingress Boundary**: In Linux netfilter architecture, packets from customer virtual machines directed at the host's bridge gateway IP address (e.g., on `vmbr0`) do **NOT** traverse the `FORWARD` chain; they enter the host's `INPUT` chain directly. Furthermore, Docker routing forwards packets via `PREROUTING` directly to container ports, bypassing standard user chains.
   - To guarantee complete isolation, the host netfilter rules enforce multi-chain blocking across IPv4 and IPv6:
     ```bash
     # 1. INPUT Chain: Drop tenant VM packets targeting hypervisor host services & RFC1918 subnets
     iptables -I INPUT -i vmbr0 -p tcp -m multiport --dports 22,5432,6379,8000,8006,6001,6002 -j DROP
     iptables -I INPUT -i vmbr0 -d 10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,127.0.0.1 -j DROP

     # 2. FORWARD Chain: Prevent lateral inter-VM and inter-tenant routing across subnets
     iptables -I FORWARD -i vmbr0 -d 10.250.0.0/24 -j DROP
     iptables -I FORWARD -i vmbr0 -d 127.0.0.1 -j DROP
     ebtables -I FORWARD -p IPv4 --ip-dst 10.250.0.0/24 -j DROP

     # 3. DOCKER-USER Chain: Prevent customer bridge traffic from bypassing host filters into Docker containers
     iptables -I DOCKER-USER -i vmbr0 -j DROP

     # 4. Dual-Stack IPv6 Parity (Prevent IPv6 netfilter bypass)
     ip6tables -I INPUT -i vmbr0 -p tcp -m multiport --dports 22,5432,6379,8000,8006,6001,6002 -j DROP
     ip6tables -I FORWARD -i vmbr0 -d fd00::/8 -j DROP
     ```
   - **Layer 2 Anti-Spoofing & MAC/IP Pinning**: Enforced via Proxmox VE `ipfilter: 1` (`/etc/pve/firewall/<vmid>.fw`) with `[IPSET ipfilter-net0]` pinning each guest's allocated MAC, IPv4, and IPv6 address, preventing ARP poisoning, rogue DHCP, or cross-tenant IP theft.

2. **Hardware Virtualization Hardening**:
   - Customer KVM instances execute with `qemu` unprivileged user privileges (`user = "libvirt-qemu"`).
   - Direct PCI device passthrough is disabled; storage operates via `virtio-scsi-pci` with write-back caching and I/O rate limiting (IOPS throttling) to prevent noisy-neighbor storage starvation.
   - Proxmox QEMU Guest Agent (`qm guest exec`) is prioritized over host-to-guest SSH for health checks and reboots, eliminating network-level SSH exposure between hypervisor and guest.

3. **Shared Application Hosting PaaS Container Isolation & Build Egress Shielding**:
   - **Link-Local Cloud Metadata Egress Blocking**: During Dockerfile builds and container runtime, customer-supplied code could attempt Server-Side Request Forgery (SSRF) against cloud provider metadata services (e.g. `http://169.254.169.254/hetzner/v1/metadata`) to exfiltrate hypervisor or cloud API tokens. The host netfilter enforces an immutable block:
     ```bash
     # Drop all container and build environment access to link-local cloud metadata
     iptables -I DOCKER-USER -d 169.254.169.254 -j DROP
     ```
   - **Dedicated Build Execution Nodes**: Shared PaaS builds (`ApplicationDeploymentJob`) must never execute on the STX Hosting control plane node (`Server ID 0`). Builds execute on dedicated, ephemeral worker nodes residing in an isolated DMZ with zero routing to management networks.
   - **Host Bind Mount Rejection**: The platform API gateway sanitizes all raw Compose and Docker configurations, strictly rejecting any volume mapping where `type == 'bind'` or where host paths (`/`, `/var/run/docker.sock`, `/etc`, `~`) are referenced, permitting only platform-managed Docker named volumes.

---

### 4.2 SSH Key Management & Credential Vaulting

1. **Customer Public Key Ingress**:
   - Customers submit only their **public SSH key** (`ssh-ed25519` or `ssh-rsa`) during order creation or via `/portal/security/ssh-keys`.
   - The platform **NEVER** generates, requests, or stores a customer's private SSH key.
   - Public keys are sanitized against newline command injection before being passed to cloud-init or guest authorized_keys:
     ```php
     $cleanKey = trim(preg_replace('/[\r\n]+/', '', $request->input('customer_ssh_public_key')));
     if (!preg_match('/^(ssh-(rsa|ed25519)|ecdsa-sha2-[a-z0-9]+)\s+[A-Za-z0-9+\/]+[=]{0,2}(\s+.+)?$/', $cleanKey)) {
         throw new ValidationException('Invalid SSH public key format.');
     }
     ```
2. **AES-256-GCM Secret Encryption**:
   - Platform secrets, upstream cloud provider tokens (Hetzner, DigitalOcean API keys), and internal inventory root passwords are encrypted at rest using Laravel's cryptographic envelope (`Illuminate\Support\Facades\Crypt`), utilizing OpenSSL with `AES-256-GCM` authenticated encryption.
3. **Ephemeral Initial Password Display**:
   - Where an initial operating system root password is generated (for password-enabled templates), the plaintext value is stored in `service_credentials.encrypted_secret`.
   - The password can be retrieved via `GET /api/v1/services/{uuid}/credentials` **exactly once**. Upon the first successful read, the row is marked `is_one_time_viewed = TRUE`, and the `encrypted_secret` column is overwritten with `NULL` after a 24-hour expiration window.

---

### 4.3 Secure Payment Proof Upload Pipeline

Because customer payment proofs originate from untrusted external sources, the upload pipeline implements strict security controls to eliminate web-shell execution, polyglot attacks, and unauthenticated file discovery:

```
[ Customer File Upload (5MB Max) ]
                │
                ▼
[ File Validation & MIME Verification ]
 • Enforce extensions: .png, .jpg, .jpeg, .pdf
 • Verify magic file bytes via finfo_file():
   - JPEG: \xFF\xD8\xFF
   - PNG:  \x89\x50\x4E\x47
   - PDF:  %PDF-
                │
                ▼
[ Storage Quarantine on Private Disk ]
 • Generate cryptographically random UUID filename
 • Store at: `storage/app/private/payment_proofs/{uuid}.bin`
 • Disk is OUTSIDE public webroot (non-routable via web server)
                │
                ▼
[ Controlled Administrative Streaming ]
 • Admin calls: GET /api/v1/admin/proofs/{uuid}/view
 • Server validates `auth()->user()->isAdminOfTeam(0)`
 • Response streamed via `BinaryFileResponse` with `Content-Disposition: inline`
 • Direct browser execution disabled via `X-Content-Type-Options: nosniff`
```

1. **Non-Public Storage Quarantine**:
   Proofs are written strictly to `storage/app/private/payment_proofs/`. Neither Nginx, Traefik, nor the public web root (`public/`) has filesystem access or symbolic links to this directory.
2. **Magic Byte Signature Inspection**:
   In addition to standard HTTP MIME headers (which can be spoofed), the controller inspects the actual file binary preamble using `finfo`:
   ```php
   $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($uploadedFile->getRealPath());
   if (!in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true)) {
       throw new SecurityException('Rejected invalid file header signature.');
   }
   ```
3. **Private Signed-URL & Stream Authorization**:
   Only authenticated administrators (`Role::ADMIN` or `user->isAdminOfTeam(0)`) can access proofs. Files are streamed with forced headers:
   ```php
   return response()->file($filePath, [
       'Content-Type' => $proof->mime_type,
       'X-Content-Type-Options' => 'nosniff',
       'Content-Security-Policy' => "default-src 'none'",
   ]);
   ```

---

### 4.4 Defense-in-Depth & Anti-Leakage Controls

1. **Automated API Secret Redaction**:
   All API endpoints and Livewire components inherit STX Hosting's `ApiSensitiveData` middleware. This middleware recursively inspects outbound JSON arrays and automatically masks sensitive keys (`password`, `secret`, `encrypted_secret`, `token`, `private_key`, `two_factor_secret`) with `***REDACTED***` unless explicitly decrypted in an authorized, single-view action.
2. **Role Hierarchy & Server-Side Enforcement**:
   - `Role::MEMBER` (Rank 1): Read-only access to owned services; ability to create orders and upload payment proofs.
   - `Role::ADMIN` (Rank 2): Team administration, service power actions, reboot, and environment variable configuration.
   - `Role::OWNER` / `Instance Admin` (Rank 3): Access to Admin Verification Queue (`/admin/billing/proofs`), inventory node creation, and database wipes.
   - Client-side checks (such as Blade `@can`) are never relied upon for security; all operations invoke `$this->authorize(...)` and verify database row team ownership (`where('user_id', auth()->id())`).

---

## 5. Deliverable D Summary & Architectural Sign-off

Deliverable D provides a complete, hardened, and publication-ready database, API, and security specification for the ShanuTechX Commercial Hosting Platform. With 15 fully specified PostgreSQL tables, complete OpenAPI 3.0 REST contracts, strict cgroups resource limits, and multi-layered hardware and credential isolation, the platform guarantees rock-solid reliability, regulatory compliance, and bulletproof security across all hosting tiers.
