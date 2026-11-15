# STX Enterprises Cloud Platform - Solidification Changelog

All modifications and hardening updates executed as part of the Platform Solidification Roadmap (Phases 1 through 5) are recorded chronologically in this document.

---

## [Phase 1: Fix Bugs First] - November 2026

### 1. Security & Authorization Fixes
- **Supabase RLS Billing & Financial Data Hardening** (`ISSUE-CRIT-01`):
  - *Root Cause*: Supabase RLS policy `"Customers can view team invoices"` previously checked `auth.belongs_to_team(team_id)`, permitting any user attached to a team (regardless of role) to read financial invoices, items, payment transactions, and payment slips.
  - *Fix*: Created migration `supabase/migrations/20261115000012_harden_billing_rls.sql` with new `auth.has_team_role(check_team_id, allowed_roles)` helper function. Dropped broad policies and enforced `role = ANY(ARRAY['owner', 'admin', 'billing'])` on `invoices`, `invoice_items`, `orders`, `order_items`, `payment_proofs`, and `payment_transactions`.
- **Server-Side Authorization Gates on Portal Livewire Mutations** (`ISSUE-CRIT-02`):
  - *Root Cause*: Livewire action methods in `Domains.php`, `AppDetail.php`, `ServiceDetail.php`, and `MailDashboard.php` checked session team matching without validating whether the active user held management permissions (`isAdminOfTeam()`) on that team, exposing mutating actions (domain removal, container restarts, redeployments, mailbox deletions, and credential reveals) to unauthorized members.
  - *Fix*:
    - `app/Livewire/Portal/Domains.php`: Added `isAdminOfTeam()` enforcement to `add()`, `verify()`, and `remove()`.
    - `app/Livewire/Portal/Apps/AppDetail.php`: Implemented `getAuthorizedService(bool $requireAdmin = false)` ensuring team membership for viewing and admin authorization for `addDomain()`, `verifyDomain()`, `removeDomain()`, `setPrimaryDomain()`, `addEnvVar()`, `restart()`, `redeploy()`, and `duplicateApp()`.
    - `app/Livewire/Portal/ServiceDetail.php`: Implemented `getService(bool $requireAdmin = false)` guarding `revealRootPassword()`, `updateCustomerManagedPassword()`, `markCustomerManaged()`, `addAuthorizedKey()`, `deleteAuthorizedKey()`, and `requestPtr()`.
    - `app/Livewire/Portal/Mail/MailDashboard.php`: Updated `team(bool $requireAdmin = false)` guarding `addDomain()`, `deleteDomain()`, `createMailbox()`, `deleteMailbox()`, `executePasswordReset()`, `saveMailboxQuota()`, `toggleMailboxStatus()`, `toggleCatchAll()`, `addAlias()`, `deleteAlias()`, and `applyAddon()`.

### 2. Functional & Workflow Fixes
- **Context-Aware Team Switcher Routing** (`ISSUE-NORM-01`):
  - *Root Cause*: `SwitchTeam::switch_to()` statically redirected to `route('dashboard')`, pulling customers out of the portal and redirecting them to the internal infrastructure dashboard.
  - *Fix*: Updated `app/Livewire/SwitchTeam.php` to detect `/portal` referers and preserve customer context, redirecting non-staff to `route('portal.index')` and staff to `route('dashboard')`.
- **Team Invitation Link Expiration Guard** (`ISSUE-NORM-02`):
  - *Root Cause*: `TeamInvitation::hasExpired()` assumed `config('constants.invitation.link.expiration_days')` was always non-null.
  - *Fix*: Added fallback default of 3 days in `app/Models/TeamInvitation.php`.

### 3. Micro Polish & UI/UX Improvements
- **Primary Button Loading States & Double-Click Protection** (`ISSUE-MICR-01`):
  - Added `wire:target="submit"` to `resources/views/livewire/team/create.blade.php`.
  - Added loading indicator and disable state to `resources/views/livewire/portal/domains.blade.php`.
- **Portal Services Empty State Launchpad** (`ISSUE-MICR-02`):
  - Upgraded empty state in `resources/views/livewire/portal/services-list.blade.php` to a modern launchpad with illustrated cards and direct links to Deploy Web App (`/portal/deploy`), Managed Database (`/store?category=databases`), and Cloud VPS (`/store?category=vps`).
- **Dark/Light Mode Contrast on Status Badges** (`ISSUE-MICR-03`):
  - Standardized theme tokens with dual light/dark mode contrast in `resources/views/livewire/portal/domains.blade.php` and `resources/views/livewire/portal/mail/mail-dashboard.blade.php`.
- **Copy & Grammar Polish** (`ISSUE-MICR-04`):
  - Refined domain deletion alert in `app/Livewire/Portal/Mail/MailDashboard.php` from "Delete them first" to professional copy.

### 4. Automated Regression Tests
- Created `tests/Feature/Portal/PortalResourceAuthorizationTest.php` covering:
  - Custom domain removal authorization rejection for non-admin members.
  - Custom domain removal success for team owners.
  - App mutation (restart, redeploy, remove domain) authorization rejection for members.
  - App mutation success for team admins.
  - Mail domain deletion authorization rejection for members.
  - Context-preserving team switcher redirection.

---

## [Phase 2: Harden Existing Features] - November 2026

### 1. Payment & Verification Resilience
- **PayVerify Outage Graceful Degradation**:
  - *Hardening*: In `app/Jobs/Commerce/VerifyPaymentProofJob.php`, detected service unreachability and API error verdicts. Instead of falsely hard-rejecting the customer's payment slip due to third-party or network failure, proofs gracefully escalate to `STATUS_PENDING_REVIEW` with staff event notifications, ensuring continuous business continuity even during backend outages.

### 2. Team Role Scoping & Ownership Integrity
- **Client Team Owner Role Elevation**:
  - *Hardening*: In `app/Livewire/Team/Member.php`, differentiated platform root team (`id = 0`) ownership transfers (restricted to Platform Owners) from customer team ownership transfers (accessible to client team owners for their respective organizations).

---

## [Phase 3: UI and UX Improvements] - November 2026

### 1. Role-Tailored Navigation & Sidebar Separation
- **Separation of Fleet Infrastructure from Customer Portal**:
  - *Improvement*: In `resources/views/components/navbar.blade.php`, gated internal cluster/fleet infrastructure navigation (`Fleet Dashboard`, `Projects`, `Terminal`, `Monitoring`, `Servers`, `Integrations`, `Shared Variables`) behind explicit RBAC capability checks (`nodes.view`, `nodes.terminal`, `nodes.stats`, `integrations.manage`).
  - *UX Enhancement*: Filtered out empty section groups from the sidebar navigation. Regular clients now enjoy a clean, distraction-free sidebar focused solely on **Customer Portal** and **Manage**, while platform staff retain access to **Administration** and **Infrastructure**.

---

## [Phase 4: Client-Facing Teams] - November 2026

### 1. Schema & Multi-Tenant Data Layer
- **Team UUID & Seat Limit Migration** (`database/migrations/2026_11_21_000001_add_uuid_and_enhance_teams_and_invitations.php`):
  - Added unique `uuid` and configurable `seat_limit` (default: 5 members) to `teams`.
  - Backfilled all existing teams with UUIDs, strictly respecting the sentinel invariant for Root Team `id = 0` (`00000000-0000-0000-0000-000000000000`).
  - Enhanced `team_invitations` table with nullable `email`, `max_uses` (integer default 1), `uses_count` (integer default 0), and `expires_at` (timestamp) for open shareable links with auto-revocation upon fulfillment.

### 2. 5-Tier Role Matrix & Granular Capabilities
- **Role Enum Expansion** (`app/Enums/Role.php`):
  - Expanded roles to 5 distinct tiers: `OWNER` (rank 5), `ADMIN` (rank 4), `DEVELOPER` (rank 3), `BILLING` (rank 2), and `VIEWER`/`MEMBER` (rank 1).
  - Implemented capability methods:
    - `canDeploy()`: Owner, Admin, Developer.
    - `canManageDomains()`: Owner, Admin, Developer.
    - `canAccessServers()`: Owner, Admin, Developer.
    - `canViewBilling()` & `canManageBilling()`: Owner, Admin, Billing.
    - `canManageMembers()`: Owner, Admin.
    - `canDeleteTeam()`: Owner only.
- **Team Policy Authorization Gates** (`app/Policies/TeamPolicy.php`):
  - Bound team action policies to the 5-role capability matrix: `viewBilling`, `manageBilling`, `deploy`, `manageDomains`, `accessServers`, `manageMembers`, `delete`, and `transferOwnership`.
- **User Permission Helper** (`app/Models/User.php`):
  - Added `canPerformInTeam($teamId, $permission)` enabling concise server-side permission queries across controllers, policies, and Livewire components.

### 3. Team Workspace Creation with Logo & Supabase Sync
- **Team Creation Flow** (`app/Livewire/Team/Create.php` & `resources/views/livewire/team/create.blade.php`):
  - Added team logo upload with Livewire `WithFileUploads` and instant client-side preview.
  - Automatically compressed and persisted logo files via `TeamAvatarStorageService`.
  - Added `TeamSyncService` to seamlessly sync created teams and team members to Supabase `public.teams` and `public.team_members` for cross-system RLS enforcement.

### 4. Shareable & Expiring Invitations
- **Configurable Expiry & Usage Limits** (`app/Livewire/Team/InviteLink.php` & `resources/views/livewire/team/invite-link.blade.php`):
  - Supported open shareable links without demanding upfront recipient email.
  - Added expiration periods (1, 3, 7, 30 days) and usage limits (single-use, 5 uses, 10 uses, unlimited).
  - Added seat limit check (`hasReachedSeatLimit()`) preventing invitations when a team is full.
  - Enhanced `app/Livewire/Team/Invitations.php` with invitation resend functionality and shareable link indicator.
- **Safe Invitation Controller Acceptance** (`app/Http/Controllers/Controller.php`):
  - Cleanly redirected unauthenticated invitees to login/register preserving invitation state in session.
  - Enforced email verification matching when an email was specified.
  - Incremented `uses_count` via `recordUse()` and automatically purged fulfilled invitations.

### 5. Ownership Transfer & Leave Team Lifecycle
- **Team Ownership Succession** (`app/Livewire/Team/DangerZone.php` & `resources/views/livewire/team/danger-zone.blade.php`):
  - Implemented atomic ownership transfer promoting the selected successor to `owner` and demoting the previous owner to `admin`.
  - Implemented `leaveTeam()` allowing members to voluntarily depart non-personal teams with automatic token revocation and active team switching.
- **Member Management UI** (`app/Livewire/Team/Member.php` & `resources/views/livewire/team/member.blade.php`):
  - Added management options to promote/demote members across all 5 roles (`Make developer`, `Make billing`, `Make viewer`, `Make admin`, `Make member`, `Make owner`).
  - Added color-coded role badges (Gold for Owner, Indigo for Admin, Emerald for Developer, Cyan for Billing, Slate for Viewer/Member).

### 6. Automated Verification Tests
- Created `tests/Feature/Team/ClientTeamsTest.php` covering:
  - Team creation with auto-assigned UUID and default seat limit.
  - 5-tier role capability assertions.
  - Open shareable link generation and multi-user acceptance with usage tracking.
  - Team seat limit boundary enforcement.
  - Ownership transfer in danger zone.
  - Team departure lifecycle.
