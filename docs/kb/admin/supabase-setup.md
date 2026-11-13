---
title: Supabase Database & Auth Setup
summary: Complete guide to using Supabase as the primary platform database and authentication provider.
audience: admin
order: 7
---
STX Hosting supports **Supabase** as both its primary database engine (PostgreSQL) and its authentication provider (Supabase Auth).

## 1. Using Supabase as the Primary Platform Database

All platform state — payments, invoices, subscription plans, user accounts, containers, domains, and backups — can reside directly within Supabase.

### Step 1: Obtain Database Credentials from Supabase
1. Open your project in the [Supabase Dashboard](https://supabase.com/dashboard).
2. Go to **Project Settings** $\to$ **Database**.
3. Under **Connection parameters**, locate your credentials:
   - **Host**: `db.<your-project-ref>.supabase.co`
   - **Database name**: `postgres`
   - **Port**: `5432`
   - **User**: `postgres`
   - **Password**: Your database password chosen at project creation.

### Step 2: Configure STX Hosting `.env`
Update your `.env` file on the server:

```dotenv
# Primary Database Connection: Supabase Postgres
DB_CONNECTION=pgsql
DB_HOST=db.<your-project-ref>.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=<your-database-password>
DB_SSLMODE=require
DB_SEARCH_PATH=public
```

> [!TIP]
> **Direct Connection vs Connection Pooler**:
> Always use the **Direct Connection (port 5432)** or Session Mode for backend database migrations. Do not use the transaction pooler on port 6543 for running `php artisan migrate`, as transaction poolers disallow DDL schema locks.

### Step 3: Run Database Migrations & Seeds
Run database migrations to initialize all tables in Supabase:

```bash
php artisan migrate --force
php artisan db:seed --class=HostingCatalogSeeder --force
```

Open the **Table Editor** in your Supabase dashboard to verify your tables (`users`, `teams`, `products`, `pricing_tiers`, `invoices`, `custom_domains`, `service_instances`).

---

## 2. Using Supabase for Authentication

When Supabase Auth is enabled, user registration, passwords, and logins are delegated to Supabase GoTrue API.

### Step 1: Obtain Supabase API Keys
1. In the Supabase Dashboard, go to **Project Settings** $\to$ **API**.
2. Copy:
   - **Project URL**: `https://<your-project-ref>.supabase.co`
   - **Project API Anon Key**: `eyJ...` (public key)
   - **Project API Service-Role Secret**: `eyJ...` (backend secret key)
   - **JWT Secret**: Found under **JWT Settings**.

### Step 2: Enable Supabase Auth in `.env`
Add the following to your `.env`:

```dotenv
SUPABASE_URL=https://<your-project-ref>.supabase.co
SUPABASE_KEY=<your-anon-key>
SUPABASE_SERVICE_ROLE_KEY=<your-service-role-key>
SUPABASE_JWT_SECRET=<your-jwt-secret>
SUPABASE_AUTH_ENABLED=true
```

### How the Auth Bridge Works
- **Registration**: When a user registers at `/register`, STX Hosting creates the user in Supabase Auth via API, then mirrors the user in `public.users` with their `supabase_id` UUID and creates their personal team.
- **Login**: When a user logs in at `/login`, credentials are verified against Supabase Auth. Upon successful verification, the user session and team are restored.
- **Password Updates**: Password changes made in the platform automatically update the user's password in Supabase Auth.
- **Fallback**: If `SUPABASE_AUTH_ENABLED=false`, the platform safely falls back to native Laravel authentication.

### Social OAuth (Google, GitHub, Discord)
To enable social logins via Supabase:
1. In the Supabase Dashboard, go to **Authentication** $\to$ **Providers**.
2. Enable your desired providers (e.g. Google, GitHub) and enter the Client ID and Secret.
3. Users authenticating via Supabase OAuth are automatically mapped to their STX Hosting team.

