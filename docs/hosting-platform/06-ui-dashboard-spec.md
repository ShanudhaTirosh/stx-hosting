# Deliverable F: UI & Dashboard Specification (Customer Portal vs Admin Console)

**Document Identifier**: `DOC-STX-UI-SPEC-006`  
**Version**: `1.0.0`  
**Classification**: Frontend Architecture & UI/UX Systems Specification  
**Target Applications**: Customer Portal (`NEXT_PUBLIC_DASHBOARD_URL` / `localhost:3001`), Administrator Operations Console (`/admin` / STX Hosting Control Plane), Public Storefront (`web`)  
**Design System**: ShanuTechX Dark Glassmorphism, Tailwind CSS, Lucide Icons  
**Author**: Frontend Architecture & Operations UI Team  
**Status**: Approved for Implementation  

---

## 1. UI Architecture & Design System Foundations

The ShanuTechX hosting platform implements a unified design system across its public storefront, client control space, and internal administrative operations. This design system extends the visual language established in `@shanutechx/web` (Next.js 14 App Router, Tailwind CSS, Geist Sans, Lucide React).

### 1.1 Dark Glassmorphism Design Tokens

The visual theme uses ultra-dark layered surfaces, translucent glass panels, subtle border luminescence, and high-contrast status accents.

```css
/* Core Design Tokens */
:root {
  --bg-primary: #05070a;       /* Deep space canvas */
  --bg-secondary: #0b0f17;     /* Elevated panel surface */
  --bg-glass: rgba(15, 23, 42, 0.65); /* 65% Translucent slate */
  --border-glass: rgba(255, 255, 255, 0.08); /* 8% White luminescence */
  --border-glass-hover: rgba(255, 255, 255, 0.18);
  --accent-cyan: #06b6d4;      /* Brand interactive cyan */
  --accent-blue: #3b82f6;      /* Brand secondary blue */
  --status-active: #10b981;    /* Emerald green */
  --status-warning: #f59e0b;   /* Amber */
  --status-danger: #ef4444;    /* Crimson red */
  --status-pending: #8b5cf6;   /* Violet */
}
```

#### Tailwind Component Classes
- **Glass Card**: `backdrop-blur-xl bg-slate-900/60 border border-white/10 rounded-2xl shadow-2xl shadow-black/50`
- **Interactive Button (Primary)**: `bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-white font-medium px-4 py-2 rounded-xl transition-all shadow-lg shadow-cyan-500/20 active:scale-[0.98]`
- **Interactive Button (Ghost/Secondary)**: `bg-white/[0.04] hover:bg-white/[0.08] text-slate-300 hover:text-white border border-white/10 hover:border-white/20 font-medium px-4 py-2 rounded-xl transition-all`
- **Data Table**: Header: `bg-white/[0.02] text-xs font-semibold text-slate-400 uppercase tracking-wider border-b border-white/5 px-6 py-3.5`; Row: `border-b border-white/5 hover:bg-white/[0.02] transition-colors`

---

## 2. Customer Portal Specification (`NEXT_PUBLIC_DASHBOARD_URL`)

The Customer Portal (served at `http://localhost:3001` in local development and `https://app.shanutechx.com` in production) serves as the primary self-service interface for paying clients.

### 2.1 Information Architecture & Route Mapping

```
/portal (Root Layout with Sidebar & Global Topbar)
├── /dashboard                      # Overview: Metrics, Active Services summary, Quick actions
├── /services                       # Master Service Directory (VPS, App Hosting, Databases)
│   ├── /services/{uuid}            # Individual Service Management Console
│   │   ├── #overview               # Resource telemetry, connection info, power actions
│   │   ├── #console                # Web VNC / Serial Terminal modal
│   │   ├── #networking             # IP allocations, PTR records, firewall rules
│   │   ├── #storage                # Disks, mount points, backup snapshots
│   │   └── #settings               # Hostname update, re-install OS, cancellation
│   └── /services/deploy            # Multi-step checkout & service configuration wizard
├── /orders                         # Order History & status tracking
│   └── /orders/{uuid}              # Order details & real-time provisioning progress
├── /billing                        # Financial Hub
│   ├── /billing/invoices           # Historical & unpaid invoices
│   ├── /billing/invoices/{uuid}    # PDF invoice view & payment gateway checkout
│   └── /billing/proof-upload       # Manual payment proof submission & tracking
├── /security                       # Access Management
│   ├── /security/ssh-keys          # Customer public SSH keys manager
│   ├── /security/api-tokens        # Personal API tokens (Sanctum)
│   └── /security/2fa               # TOTP Multi-factor authentication
└── /support                        # Ticket support & documentation links
```

---

### 2.2 Customer Dashboard Overview Wireframe

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│  [STX LOGO]  ShanuTechX Cloud  │  Services  Billing  SSH Keys  Status  │  [Team] [User] │
├────────────────────────────────────────────────────────────────────────────────────────┤
│                                                                                        │
│  Welcome back, Alexander!                                        [ + Deploy New Server]│
│  ────────────────────────────────────────────────────────────────────────────────────  │
│  ┌──────────────────────┐ ┌──────────────────────┐ ┌─────────────────────────────────┐ │
│  │ ACTIVE SERVICES      │ │ MONTHLY RUN-RATE     │ │ UNPAID INVOICES                 │ │
│  │   3 Online           │ │   $48.00 / mo        │ │   1 Pending ($12.00)            │ │
│  │   0 Suspended        │ │   Renews: Nov 15     │ │   [ View & Pay Now -> ]         │ │
│  └──────────────────────┘ └──────────────────────┘ └─────────────────────────────────┘ │
│                                                                                        │
│  ACTIVE CLOUD INFRASTRUCTURE                                                           │
│  ┌──────────────────────────────────────────────────────────────────────────────────┐  │
│  │ NAME / HOSTNAME       │ TYPE      │ IP ADDRESS       │ STATUS      │ QUICK ACTIONS│  │
│  ├───────────────────────┼───────────┼──────────────────┼─────────────┼──────────────┤  │
│  │ srv1.fintech.io       │ Cloud VPS │ 188.245.82.14    │ [● Active]  │ [Reboot] [>] │  │
│  │ 2 vCPU • 4GB • 80GB   │ Ubuntu    │ 2a01:4f8:.../64  │ 99.98% Up   │ [Console]    │  │
│  ├───────────────────────┼───────────┼──────────────────┼─────────────┼──────────────┤  │
│  │ api-cluster-prod      │ Web App   │ api.client.com   │ [● Active]  │ [Restart] [>]│  │
│  │ 1 vCPU • 2GB • Node   │ STX Hosting   │ Traefik SSL OK   │ Healthy     │ [Logs]       │  │
│  └──────────────────────────────────────────────────────────────────────────────────┘  │
│                                                                                        │
│  RECENT FINANCIAL TRANSACTIONS                                                         │
│  ┌──────────────────────────────────────────────────────────────────────────────────┐  │
│  │ INVOICE #    │ SERVICE          │ AMOUNT   │ DATE       │ STATUS         │ RECEIPT│  │
│  ├──────────────┼──────────────────┼──────────┼────────────┼────────────────┼────────┤  │
│  │ INV-2026-084 │ Cloud VPS CX22   │ $12.00   │ 2026-10-29 │ [Under Review] │ [View] │  │
│  │ INV-2026-042 │ Web App Starter  │ $36.00   │ 2026-09-29 │ [● Paid]       │ [PDF]  │  │
│  └──────────────────────────────────────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

---

### 2.3 Individual VPS Management Console Wireframe

The VPS detail view (`/services/{uuid}`) is the core operational cockpit for compute instances:

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│  < Back to Services  │  srv1.fintech.io (Ubuntu 24.04 LTS)         [● RUNNING]          │
├────────────────────────────────────────────────────────────────────────────────────────┤
│                                                                                        │
│  POWER CONTROLS:   [ Reboot (ACPI) ]  [ Force Stop ]  [ Reset Root Password ] [ VNC ]   │
│  ────────────────────────────────────────────────────────────────────────────────────  │
│                                                                                        │
│  ┌────────────────────────────────────────┐ ┌────────────────────────────────────────┐ │
│  │ CONNECTION CREDENTIALS                 │ │ HARDWARE TELEMETRY (LIVE)              │ │
│  │ Public IPv4: 188.245.82.14  [Copy]     │ │ CPU Usage: [||||||||||..........] 38%  │ │
│  │ IPv6 Block:  2a01:4f8:c012:a4::/64     │ │ RAM Usage: [||||||||||||||||....] 64%  │ │
│  │ SSH Port:    22                        │ │ Disk I/O:  4.2 MB/s Read • 1.1 MB/s W  │ │
│  │ Username:    root / debian             │ │ Net Rx/Tx: 142 GB / 20,000 GB Traffic  │ │
│  │ SSH Command:                           │ │                                        │ │
│  │ $ ssh -i ~/.ssh/id_ed25519 root@188... │ │ Datacenter: Falkenstein (fsn1-de)      │ │
│  └────────────────────────────────────────┘ └────────────────────────────────────────┘ │
│                                                                                        │
│  AUTHORIZED SSH KEYS ON THIS INSTANCE                                                  │
│  ┌──────────────────────────────────────────────────────────────────────────────────┐  │
│  │ KEY LABEL        │ FINGERPRINT (SHA256)           │ INJECTED AT       │ ACTIONS  │  │
│  ├──────────────────┼────────────────────────────────┼───────────────────┼──────────┤  │
│  │ Alexander-MacBook│ SHA256:7uKj...qM0e            │ 2026-10-29 14:20  │ [Revoke] │  │
│  │ CI-CD-Deployer   │ SHA256:9xLp...aP3z            │ 2026-10-30 09:15  │ [Revoke] │  │
│  └──────────────────────────────────────────────────────────────────────────────────┘  │
│                                                                                        │
│  DANGER ZONE                                                                           │
│  [ Boot into Rescue System ]   [ Re-install Fresh OS ]   [ Request Cancellation ]      │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

#### Detailed VPS Action Specifications
1. **Reboot (ACPI Graceful)**:
   - Emits a confirmation modal: *"Gracefully restart srv1.fintech.io?"*
   - Sends `POST /api/v1/services/{uuid}/reboot`. Backend issues hypervisor ACPI shutdown with a 60-second fallback to hardware reset (`qm reboot $VMID` or Hetzner API `reboot`).
2. **Reset Root Password**:
   - Requires customer 2FA confirmation or password re-entry.
   - Generates a cryptographically random 24-character alphanumeric string (`Str::password(24)`).
   - Injects password via QEMU Guest Agent (`qm guest exec $VMID -- passwd root`).
   - Displays the generated secret in a **Single-Reveal Security Modal**. The password is shown once with a copy button and a 60-second self-destruct timer; it is never stored in plaintext.
3. **Web Serial / VNC Console**:
   - Opens an isolated browser-based NoVNC or Xterm.js WebSocket terminal directly into the hypervisor guest display.
   - Session authenticated via an ephemeral single-use token (valid for 60 seconds). Customer never connects to host hypervisor ports directly.

---

### 2.4 Invoice & Payment Proof Uploader Wireframe

Located at `/billing/invoices/{uuid}/pay`:

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│  PAYMENT CHECKOUT: Invoice #STX-INV-202610-0084                              Total: $12│
├────────────────────────────────────────────────────────────────────────────────────────┤
│                                                                                        │
│  Select Payment Method:                                                                │
│  ┌────────────────────────┐  ┌────────────────────────┐  ┌──────────────────────────┐  │
│  │ [●] Bank Wire / SEPA   │  │ [ ] Cryptocurrency     │  │ [ ] Credit Card (Stripe) │  │
│  └────────────────────────┘  └────────────────────────┘  └──────────────────────────┘  │
│                                                                                        │
│  STEP 1: TRANSFER FUNDS TO THE FOLLOWING ACCOUNT:                                      │
│  ┌──────────────────────────────────────────────────────────────────────────────────┐  │
│  │ Beneficiary:  ShanuTechX Cloud Solutions Ltd                                     │  │
│  │ IBAN:         DE89 3704 0044 0532 0130 00                                [Copy]  │  │
│  │ BIC / SWIFT:  COBADEFFXXX                                                [Copy]  │  │
│  │ Bank:         Commerzbank AG, Frankfurt am Main                                  │  │
│  │ Mandatory Ref:STX-INV-202610-0084 (Include exactly as shown)             [Copy]  │  │
│  └──────────────────────────────────────────────────────────────────────────────────┘  │
│                                                                                        │
│  STEP 2: UPLOAD TRANSFER RECEIPT / PAYMENT SLIP:                                       │
│  ┌──────────────────────────────────────────────────────────────────────────────────┐  │
│  │                     [ Drag & Drop Receipt Here or Browse ]                       │  │
│  │               Supports JPG, PNG, PDF up to 5MB (Quarantined Scan)                │  │
│  └──────────────────────────────────────────────────────────────────────────────────┘  │
│                                                                                        │
│  Sender Account Name:  [ Alexander Wright                          ]                   │
│  Bank Transaction Ref: [ TXN-9482104-DE                            ]                   │
│  Amount Transferred:   [ 12.00                 ] Currency: [ USD ▼ ]                   │
│                                                                                        │
│  [ Submit Proof for Administrative Verification ]                                      │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Administrator Operations Console Specification

The Administrator Operations Console operates inside the administrative plane, accessible only to authenticated users with `Role::ADMIN` or `Role::OWNER` within the root team (`team_id = 0`).

### 3.1 Information Architecture & Route Mapping

```
/admin (Root Administrative Shell)
├── /admin/dashboard                # Real-time KPIs: MRR, active nodes, pending reviews, incident status
├── /admin/billing                  # Financial Administration
│   ├── /admin/billing/proofs       # Verification Queue (dual-control proof approval)
│   ├── /admin/billing/invoices     # All platform invoices & status overrides
│   └── /admin/billing/transactions # Raw gateway logs & transaction ledger
├── /admin/inventory                # Hardware & Capacity Management
│   ├── /admin/inventory/vps        # Pool management (Bare Metal nodes, IPAM, wipe triggers)
│   ├── /admin/inventory/subnets    # IPv4/IPv6 allocation blocks & VLAN configurations
│   └── /admin/inventory/cloud-keys # Cloud provider API tokens (Hetzner, OVH, DO)
├── /admin/services                 # Master Global Service Roster (all tenants)
│   └── /admin/services/{uuid}      # Administrator override controls, hypervisor logs, force terminate
├── /admin/customers                # Customer Directory
│   └── /admin/customers/{id}       # Customer profile, team members, services, impersonation token
├── /admin/telemetry                # Health & Probing Dashboard (connected to Status Platform)
└── /admin/audit-logs               # Immutable Security & Compliance Event Stream
```

---

### 3.2 Operations Dashboard Wireframe

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│  [STX ROOT]  OPERATIONS CONSOLE  │  Proofs (3)  Inventory  Services  Logs  │  Admin (Root) │
├────────────────────────────────────────────────────────────────────────────────────────┤
│                                                                                        │
│  PLATFORM HEALTH & REVENUE OVERVIEW                                                    │
│  ┌────────────────────┐ ┌────────────────────┐ ┌────────────────────┐ ┌──────────────┐ │
│  │ MONTHLY RECURRING  │ │ PENDING PROOFS     │ │ VPS CAPACITY       │ │ STATUS MESH  │ │
│  │   $14,250.00       │ │   3 Unreviewed     │ │   78 / 100 Slices  │ │ [● 100% Up]  │ │
│  │   +18.4% this mo   │ │   [ Review Now -> ]│ │   14 Avail • 8 Wipe│ │ 0 Incidents  │ │
│  └────────────────────┘ └────────────────────┘ └────────────────────┘ └──────────────┘ │
│                                                                                        │
│  PENDING PAYMENT VERIFICATION QUEUE (HIGHEST PRIORITY)                                 │
│  ┌──────────────────────────────────────────────────────────────────────────────────┐  │
│  │ INVOICE # │ USER / COMPANY   │ CLAIMED REF    │ AMOUNT   │ PROOF DOC   │ ACTIONS │  │
│  ├───────────┼──────────────────┼────────────────┼──────────┼─────────────┼─────────┤  │
│  │ INV-0084  │ alex@corp.io     │ TXN-9482104    │ $12.00   │ [slip.pdf]  │ [Review]│  │
│  │ INV-0085  │ dev@cryptom.de   │ 0x7b3f9...     │ $48.00   │ [tx.png]    │ [Review]│  │
│  │ INV-0086  │ sampa@lanka.lk   │ SL-991204      │ $24.00   │ [slip.jpg]  │ [Review]│  │
│  └──────────────────────────────────────────────────────────────────────────────────┘  │
│                                                                                        │
│  HARDWARE NODE HEALTH (HYPERVISOR POOL)                                                │
│  • Node-FSN-01: 92% RAM • 42% CPU [Normal]   • Node-NBG-02: 44% RAM • 18% CPU [Normal]│
└────────────────────────────────────────────────────────────────────────────────────────┘
```

---

### 3.3 Side-by-Side Proof Verification Queue Wireframe

Clicking **[Review]** on an invoice opens the dual-pane reconciliation modal:

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│  VERIFY PAYMENT PROOF: Invoice #STX-INV-202610-0084                          [ ESC / X ]│
├────────────────────────────────────────────┬───────────────────────────────────────────┤
│  LEFT PANE: ATTACHED PROOF SLIP            │  RIGHT PANE: RECONCILIATION & ACTION      │
│                                            │                                           │
│  ┌──────────────────────────────────────┐  │  CUSTOMER & INVOICE DETAILS:              │
│  │ [RENDERED IMAGE / PDF VIEWER]        │  │  Customer:    Alexander Wright            │
│  │                                      │  │  Email:       alex@corp.io                │
│  │ Commerzbank Transfer Confirmation    │  │  Team:        FinTech Solutions (Team 42) │
│  │ Sender: Alexander Wright             │  │  Invoice Amt: $12.00 USD                  │
│  │ Beneficiary: ShanuTechX Solutions    │  │  Due Date:    2026-10-29 (Today)          │
│  │ Amount: $12.00 USD                   │  │                                           │
│  │ Date: 2026-10-29 14:18:22 UTC        │  │  SUBMITTED PROOF METADATA:                │
│  │ Ref: STX-INV-202610-0084             │  │  Sender:      Alexander Wright            │
│  │                                      │  │  Claimed Ref: TXN-9482104                 │
│  │ [Zoom In]  [Zoom Out]  [Rotate 90°]  │  │  Upload Time: 2026-10-29 14:22:10 UTC     │
│  └──────────────────────────────────────┘  │  Storage:     private/proofs/2026/10/...  │
│                                            │                                           │
│                                            │  ADMIN DECISION:                          │
│                                            │  [ ✓ APPROVE & PROVISION SERVICE ]        │
│                                            │                                           │
│                                            │  [ X REJECT PROOF ]                       │
│                                            │  Reason: [ Funds not received in account▼]│
│                                            │  Notes:  [ Transaction ID not found on bank│
│                                            │          statement as of 15:00 UTC.    ]  │
└────────────────────────────────────────────┴───────────────────────────────────────────┘
```

#### Dual-Control Approval Logic
- **"Approve & Provision Service"**:
  1. Issues `POST /admin/billing/proofs/{id}/approve`.
  2. Wraps state changes in a pessimistic database transaction (`lockForUpdate`).
  3. Transitions Invoice to `paid`, Order to `payment_verified`.
  4. Automatically dispatches `ProvisionServiceJob`.
  5. Broadcasts real-time completion toast and updates remaining queue count.
- **"Reject Proof"**:
  1. Prompts for mandatory rejection reason code:
     - `funds_not_received`: Funds not yet visible in official bank account.
     - `amount_mismatch`: Claimed amount does not match invoice total.
     - `unreadable_image`: Attached image is blurry, cropped, or illegible.
     - `duplicate_receipt`: Same transaction reference used on another invoice.
  2. Sets proof status to `rejected`, invoice reverts to `unpaid`.
  3. Sends polite notification email to customer with guidance on how to resubmit.

---

### 3.4 VPS Inventory Management Pool Wireframe

Located at `/admin/inventory/vps`:

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│  VPS INVENTORY POOL & HARDWARE ALLOCATION               [ + Add Pre-provisioned Node ] │
├────────────────────────────────────────────────────────────────────────────────────────┤
│  Filters: [ All Locations ▼ ]  [ Status: All ▼ ]  [ Search IP / Identifier...       ]  │
│                                                                                        │
│  ┌──────────────────────────────────────────────────────────────────────────────────┐  │
│  │ IDENTIFIER    │ DATACENTER │ IPV4 / IPV6 SUBNET │ STATUS      │ ASSIGNED TO│ ACTIONS │  │
│  ├───────────────┼────────────┼────────────────────┼─────────────┼────────────┼─────────┤  │
│  │ node-fsn-kvm01│ fsn1-de    │ 188.245.82.14/24   │ [● Allocated│ srv1 (T-42)│ [Manage]│  │
│  │ node-fsn-kvm02│ fsn1-de    │ 188.245.82.15/24   │ [● Available│ None       │ [Assign]│  │
│  │ node-fsn-kvm03│ fsn1-de    │ 188.245.82.16/24   │ [● Available│ None       │ [Assign]│  │
│  │ node-nbg-kvm04│ nbg1-de    │ 159.69.112.44/24   │ [● Wiping]  │ Sanitizing │ [Logs]  │  │
│  │ node-hel-kvm05│ hel1-fi    │ 65.21.94.102/24    │ [● Maint]   │ Hardware   │ [Edit]  │  │
│  └──────────────────────────────────────────────────────────────────────────────────┘  │
│                                                                                        │
│  POOL HEALTH SUMMARY:                                                                  │
│  • Total Hardware Slots: 100   • Allocated (In-Use): 78                                │
│  • Ready for Immediate Allocation: 14   • Undergoing Secure Wipe: 6   • Maintenance: 2 │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

#### Inventory Actions
- **Add Pre-provisioned Node**: Admin registers hardware node IP, subnet, gateway, hypervisor identifier (Proxmox VMID), and hardware specs.
- **Trigger Force Wipe**: Manually invokes `ExecuteCryptographicWipeAction` to sanitize storage and return node to `available`.
- **Set Maintenance**: Isolates node from the automatic provisioning scheduler for hardware maintenance or OS upgrade.

---

## 4. Strict Server-Side Role-Based Access Control (RBAC) & Authorization

Security boundaries are strictly enforced on the server. Client-side navigation guards and Blade/Livewire UI conditionals (e.g. `@can`) exist solely for user experience and are never relied upon for security enforcement.

### 4.1 Permission Matrix by Persona

| System Capability | Superadmin (`Role::OWNER`, Team 0) | Finance Admin (`Role::ADMIN`, Team 0) | Support Admin (`Role::MEMBER`, Team 0) | Customer Owner (`Role::OWNER`, Team > 0) | Customer Member (`Role::MEMBER`, Team > 0) |
|---|:---:|:---:|:---:|:---:|:---:|
| **Approve / Reject Payment Proofs** | **YES** | **YES** | No (Read Only) | No | No |
| **Manage Hardware Pool / Wipe Nodes** | **YES** | No | No | No | No |
| **Edit Pricing Tiers & Products** | **YES** | **YES** | No | No | No |
| **View Tenant Invoices (Global)** | **YES** | **YES** | No | No | No |
| **View Own Team Invoices & Pay** | **YES** | **YES** | **YES** | **YES** | No (Read Only) |
| **Submit Payment Proof** | N/A | N/A | N/A | **YES** | **YES** |
| **Reboot / Power Toggle Own VPS** | **YES** | No | **YES** (Audit Logged) | **YES** | **YES** |
| **Reset Root Password Own VPS** | **YES** | No | No | **YES** | No |
| **Delete / Cancel Service** | **YES** | No | No | **YES** | No |
| **Impersonate Tenant User** | **YES** | No | No (Requires 2FA) | No | No |
| **View Platform Audit Logs** | **YES** | **YES** (Finance Only) | No | No (Own Team Only) | No |

---

### 4.2 Server-Side Policy Implementation

Authorization logic is codified in dedicated Laravel Policy classes.

#### Example: `PaymentProofPolicy.php`
```php
namespace App\Policies;

use App\Models\User;
use App\Models\PaymentProof;
use App\Enums\Role;

class PaymentProofPolicy
{
    /**
     * Determine whether the user can view the payment proof image/document.
     */
    public function view(User $user, PaymentProof $proof): bool
    {
        // 1. Root team admins can inspect any proof
        if ($user->isAdminOfTeam(0)) {
            return true;
        }

        // 2. Customers can only view proofs uploaded by their own team
        return $user->teams()->where('teams.id', $proof->invoice->team_id)->exists();
    }

    /**
     * Determine whether the user can approve or reject the proof.
     */
    public function review(User $user, PaymentProof $proof): bool
    {
        // ONLY instance administrators belonging to Team 0 with ADMIN or OWNER role
        return $user->isAdminOfTeam(0);
    }
}
```

#### Example: `ServiceInstancePolicy.php`
```php
namespace App\Policies;

use App\Models\User;
use App\Models\ServiceInstance;
use App\Enums\Role;

class ServiceInstancePolicy
{
    /**
     * Determine whether the user can execute power actions (reboot, stop).
     */
    public function power(User $user, ServiceInstance $service): bool
    {
        // Platform Superadmin override
        if ($user->isAdminOfTeam(0)) {
            return true;
        }

        // Must belong to the service's owning team
        $teamUser = $user->teams()->where('teams.id', $service->team_id)->first();
        return $teamUser !== null;
    }

    /**
     * Determine whether the user can reset the root credential or destroy service.
     */
    public function updateCredentials(User $user, ServiceInstance $service): bool
    {
        if ($user->isAdminOfTeam(0)) {
            return true;
        }

        // ONLY team admins/owners can perform destructive or credential modifications
        return $user->isAdminOfTeam($service->team_id);
    }
}
```

---

### 4.3 Protection Against Cross-Tenant Attacks

1. **Scoped Query Filtering**:
   All database queries for customer-facing endpoints are explicitly constrained by the verified resource ownership:
   ```php
   // CORRECT: Scoped to the resource's authentic team ownership
   $service = ServiceInstance::where('team_id', $currentTeam->id)
       ->where('uuid', $requestedUuid)
       ->firstOrFail();
   ```
2. **Pessimistic Role Resolution**:
   Per the platform's architectural invariants, role checks never evaluate ephemeral session cookies or client headers. The backend always queries the canonical database relation:
   ```php
   $isAdmin = $user->isAdminOfTeam($service->team_id);
   ```
3. **Anti-IDOR (Insecure Direct Object Reference) Safeguards**:
   All user-facing URLs reference cryptographic UUIDv4 strings rather than autoincrementing integers (`/services/f81d4fae-7dec-11d0-a765-00a0c91e6bf6`).

---

## 5. Session Security, CSRF Defense & Rate Limiting

### 5.1 CSRF Defense
- **SameSite Strict Cookies**: All session cookies are configured with `SameSite=Strict`, `Secure=true`, and `HttpOnly=true`.
- **Automatic Blade & Livewire CSRF Injection**: Every mutating HTTP request (`POST`, `PUT`, `DELETE`) requires a verified `@csrf` token validated by Laravel's `VerifyCsrfToken` middleware.
- **WebSocket Handshake Authentication**: Livewire and Reverb WebSockets validate the session cookie during connection negotiation.

### 5.2 API & Endpoint Rate Limiting
To protect against brute-force attacks, card-testing scripts, and DoS attempts, custom rate limiters are configured in `app/Providers/RouteServiceProvider.php`:

```php
// Rate limiter for payment proof submissions
RateLimiter::for('proof-upload', function (Request $request) {
    return Limit::perHour(10)->by($request->user()?->id ?: $request->ip())
        ->response(function () {
            return response()->json([
                'error' => 'Too many upload attempts. Please wait one hour.'
            ], 429);
        });
});

// Rate limiter for VPS power actions (reboot, password reset)
RateLimiter::for('vps-power', function (Request $request) {
    return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
});

// Rate limiter for customer login & 2FA validation
RateLimiter::for('login', function (Request $request) {
    return Limit::perMinute(5)->by($request->input('email') . $request->ip());
});
```

### 5.3 Sensitive Data Masking & Credential Sanitization
To prevent credential leakage in audit logs, bug reports, and API responses:
- STX Hosting's `ApiSensitiveData` middleware intercepts all API serializations and replaces fields matching `password`, `private_key`, `secret`, `api_token`, and `encrypted_*` with redacted hashes (`********`).
- Transient passwords generated during VPS creation are held in Redis with a 300-second TTL and automatically purged after one view.

---

## 6. Verification Method & Acceptance Sign-Off

### 6.1 UI & Security Verification Test Rubric
This specification is independently verified through automated Pest browser tests and security feature tests:
1. `tests/Feature/CustomerPortalAuthorizationTest.php`:
   - Verifies customer cannot view or modify a service owned by another team (returns `403 Forbidden`).
   - Verifies customer cannot approve their own payment proof (returns `403 Forbidden`).
2. `tests/Feature/ProofUploadQuarantineTest.php`:
   - Tests file upload with Polyglot PHP/JPG file; verifies file is blocked by MIME inspection.
   - Verifies authenticated admin can download quarantined proof slip via authorized streaming controller.
3. `tests/v4/Browser/VpsPowerControlsTest.php`:
   - Simulates customer login, navigating to `/services/{uuid}`, clicking "Reboot", and asserting confirmation modal and ACPI event trigger.
4. `tests/v4/Browser/AdminProofReviewTest.php`:
   - Simulates Finance Admin logging into `/admin/billing/proofs`, inspecting proof image, and clicking "Approve", verifying invoice transitions to `paid`.

---
*End of Specification — Deliverable F (DOC-STX-UI-SPEC-006)*
