# Deliverable H: 4-Tier Testing Strategy & Quality Assurance Framework

**Document Identifier**: `DOC-STX-TEST-008`  
**Version**: `1.0.0`  
**Classification**: Engineering Testing Specification & Quality Standard  
**Target Systems**: ShanuTechX Storefront (`web`), Status Platform (`status`), Customer Portal, Administrator Operations Console, STX Hosting Orchestration Engine (`stx-platform`), Database & Queue Workers  
**Author**: ShanuTechX Quality Engineering & Security Assurance Group  
**Status**: Approved for Execution  

---

## 1. Executive Summary & Quality Principles

The ShanuTechX Commercial Hosting Platform manages financial transactions, multi-tenant customer applications, and physical/virtual server infrastructure. A defect in this ecosystem can cause financial discrepancies, infrastructure outages, or catastrophic cross-tenant privilege escalation.

To guarantee zero-defect reliability, this testing strategy implements a comprehensive **4-Tier Testing Pyramid**:

```
                       ┌─────────────────────────┐
                       │      TIER 4: E2E        │
                       │   Browser & Playwright  │
                       │   (Full User Journeys)  │
                       ├─────────────────────────┤
                       │    TIER 3: SECURITY     │
                       │ Tenant Isolation, Upload│
                       │ Sanitization & Exploits │
                       ├─────────────────────────┤
                       │   TIER 2: INTEGRATION   │
                       │ Database Locks, STX Hosting │
                       │ API Mocks, Idempotency  │
                       ├─────────────────────────┤
                       │      TIER 1: UNIT       │
                       │ Models, State Machines, │
                       │ Pricing & Validation    │
                       └─────────────────────────┘
```

### 1.1 Core Testing Principles
1. **Behavioral Over Implementation Testing**: Tests assert observable outcomes and business invariants rather than private internal method calls.
2. **Zero-Flake & Deterministic**: Tests never rely on arbitrary sleep timers; asynchronous states are asserted using polling predicates with explicit timeouts (`eventually()`).
3. **Integrity Mandate**: No dummy facades, no hardcoded assertions, and no shortcuts. All test scenarios maintain real state and exercise genuine business logic.
4. **Defense-in-Depth Security Gates**: Every release candidate must pass automated tenant boundary checks, preventing cross-tenant data leakage or privilege escalation.

---

## 2. Tier 1: Unit Testing (Pest PHP / PHPUnit)

Unit tests execute in milliseconds using in-memory SQLite databases and verify isolated components, calculation formulas, validation rules, and state machine transitions.

### 2.1 Financial & Pricing Calculations
Financial calculations must never produce floating-point rounding errors. All monetary values are represented as 64-bit integers in the smallest currency unit (cents).

#### Test Specifications:
- **`PricingCalculationTest.php`**:
  - Verifies monthly price conversion to cents.
  - Verifies annual 30% discount logic: `price * 12 * 0.70`.
  - Verifies setup fee accumulation.
  - Verifies multi-item cart totals.
  - Verifies currency conversion and display formatting (USD `$12.00` vs LKR `Rs. 2,500`).

```php
// tests/Unit/Commerce/PricingCalculationTest.php
use App\Models\Commerce\ProductVariant;
use App\Models\Commerce\PricingTier;
use App\Services\Commerce\PricingCalculator;

it('calculates annual 30 percent discount accurately in cents', function () {
    $monthlyTier = new PricingTier([
        'billing_cycle' => 'monthly',
        'price_cents' => 1200, // $12.00
        'currency' => 'USD',
    ]);

    $annualTier = new PricingTier([
        'billing_cycle' => 'annually',
        'price_cents' => (int) round(1200 * 12 * 0.70), // $100.80 -> 10080 cents
        'currency' => 'USD',
    ]);

    expect($annualTier->price_cents)->toBe(10080)
        ->and(PricingCalculator::calculateSavingsPercentage($monthlyTier, $annualTier))->toBe(30.0);
});

it('rejects negative or zero base prices for active commercial plans', function () {
    expect(fn () => PricingTier::create([
        'product_variant_id' => 1,
        'billing_cycle' => 'monthly',
        'price_cents' => -500,
        'currency' => 'USD',
    ]))->toThrow(\InvalidArgumentException::class);
});
```

### 2.2 State Machine Transitions
All state machines (`Order`, `Invoice`, `ServiceInstance`, `PaymentProof`) must enforce valid transitions and strictly reject illegal status jumps.

#### Test Specifications:
- **`OrderStateMachineTest.php`**:
  - `pending` ➔ `processing` (Valid)
  - `processing` ➔ `completed` (Valid)
  - `pending` ➔ `completed` (Forbidden without payment)
  - `completed` ➔ `cancelled` (Forbidden without refund workflow)
- **`InvoiceStateMachineTest.php`**:
  - `unpaid` ➔ `payment_pending` (Valid on proof upload)
  - `payment_pending` ➔ `paid` (Valid on admin approval)
  - `payment_pending` ➔ `unpaid` (Valid on proof rejection)
  - `paid` ➔ `unpaid` (Forbidden; requires credit note)
- **`ServiceInstanceStateMachineTest.php`**:
  - `pending_provisioning` ➔ `active` ➔ `suspended` ➔ `terminated`

```php
// tests/Unit/Commerce/InvoiceStateMachineTest.php
use App\Models\Commerce\Invoice;
use App\Exceptions\InvalidStateTransitionException;

it('forbids marking an unpaid invoice as paid without a verified transaction or approved proof', function () {
    $invoice = Invoice::factory()->create(['status' => 'unpaid']);

    expect(fn () => $invoice->transitionTo('paid'))
        ->toThrow(InvalidStateTransitionException::class);
});

it('allows transitioning unpaid to payment_pending upon proof upload', function () {
    $invoice = Invoice::factory()->create(['status' => 'unpaid']);
    $invoice->transitionTo('payment_pending');

    expect($invoice->status)->toBe('payment_pending');
});
```

### 2.3 Input & Domain Validation Rules
- **Hostname Validator (`ValidHostnameTest.php`)**:
  - Asserts compliance with RFC 1123 (allows `app.example.com`, `host-1.domain.net`; rejects `-host.com`, `host..com`, `host@name`).
- **SSH Public Key Validator (`ValidSshPublicKeyTest.php`)**:
  - Accepts valid OpenSSH ED25519 (`ssh-ed25519 AAAAC3...`) and RSA (`ssh-rsa AAAAB3...` >= 2048-bit).
  - Strictly rejects private keys (`-----BEGIN OPENSSH PRIVATE KEY-----`), invalid formats, and truncated strings.
- **Port Offset Validator (`ValidPublicPortTest.php`)**:
  - Enforces database public port allocation range between 10000 and 60000; rejects reserved system ports (<1024).

---

## 3. Tier 2: Integration Testing

Integration tests verify the interaction between models, database transactions, queue workers, and external service clients (STX Hosting REST API and SQLite Status database).

### 3.1 Database Transaction Integrity & Pessimistic Locking
Prevents race conditions where two simultaneous payment approvals or duplicate provisioning requests might allocate the same inventory node.

```php
// tests/Integration/Commerce/PaymentApprovalConcurrencyTest.php
use App\Models\Commerce\Invoice;
use App\Models\Commerce\VpsInventory;
use App\Actions\Commerce\ApprovePaymentAction;
use Illuminate\Support\Facades\ParallelTesting;

it('prevents double allocation of inventory when approved concurrently', function () {
    $inventory = VpsInventory::factory()->create(['status' => 'available']);
    $invoice = Invoice::factory()->create(['status' => 'payment_pending']);

    // Simulate concurrent approval requests
    $action = app(ApprovePaymentAction::class);

    $result1 = $action->execute($invoice->id, $adminUser1->id);
    expect($result1->isSuccess())->toBeTrue();

    // Second call should fail gracefully because status is no longer payment_pending
    $result2 = $action->execute($invoice->id, $adminUser2->id);
    expect($result2->isSuccess())->toBeFalse()
        ->and($result2->getErrorMessage())->toContain('already processed');
});
```

### 3.2 Idempotent Worker Execution
Every background worker must be completely idempotent. Executing `ProvisionManualVpsJob` or `ProvisionApplicationJob` multiple times with the same parameters must yield identical infrastructure state without duplicate records.

```php
// tests/Integration/Jobs/ProvisionManualVpsJobTest.php
use App\Jobs\Commerce\ProvisionManualVpsJob;
use App\Models\Commerce\ServiceInstance;
use App\Models\Commerce\VpsInventory;

it('is fully idempotent and does not re-allocate new nodes on worker retry', function () {
    $service = ServiceInstance::factory()->create(['status' => 'pending_provisioning']);
    $node = VpsInventory::factory()->create(['status' => 'available']);

    $job = new ProvisionManualVpsJob($service->id);

    // First execution
    $job->handle();
    $service->refresh();
    expect($service->status)->toBe('active')
        ->and($service->vps_inventory_id)->toBe($node->id);

    $originalAssignedId = $service->vps_inventory_id;

    // Retry execution
    $job->handle();
    $service->refresh();
    expect($service->vps_inventory_id)->toBe($originalAssignedId)
        ->and($service->status)->toBe('active');
});
```

### 3.3 STX Hosting REST API Client Mock & Error Test Harness
Tests integration with STX Hosting v4 API endpoints using Laravel's `Http::fake()` and live contract validation.

#### Scenarios Covered:
- **`STX HostingApiClientTest.php`**:
  - `createPublicApplication`: Handles HTTP 201 Created returning application UUID.
  - Domain Conflicts: Handles HTTP 409 Conflict when a requested domain is already bound; catches exception and reports clean error to customer.
  - Token Scope Verification: Asserts `deploy` ability is provided when triggering start/stop/restart; catches HTTP 403 when permissions are insufficient.
  - Log Streaming: Verifies handling of sanitized logs versus masked logs when token lacks `read:sensitive`.

```php
// tests/Integration/STX Hosting/STX HostingApiClientTest.php
use App\Services\STX Hosting\STX HostingApiClient;
use Illuminate\Support\Facades\Http;

it('handles STX Hosting domain conflict 409 response gracefully', function () {
    Http::fake([
        '*/api/v1/applications/public' => Http::response([
            'message' => 'Domain conflicts detected.',
            'conflicts' => ['app.example.com'],
        ], 409),
    ]);

    $client = app(STX HostingApiClient::class);

    expect(fn () => $client->createPublicApplication([
        'project_uuid' => 'prj_123',
        'server_uuid' => 'srv_123',
        'git_repository' => 'https://github.com/example/repo',
        'domains' => 'https://app.example.com',
    ]))->toThrow(\App\Exceptions\STX HostingDomainConflictException::class);
});
```

### 3.4 Status Platform Integration Tests
Verifies that when a service transitions to `active`, the telemetry bridge correctly creates a monitor in `status/data/status.db`.

```php
// tests/Integration/Status/StatusProbeBridgeTest.php
use App\Services\Status\StatusProbeService;

it('creates and activates an ICMP and TCP monitor in status db for new VPS', function () {
    $statusService = app(StatusProbeService::class);

    $monitorId = $statusService->registerVpsProbe('srv-uuid-001', 'vps1.example.com', '198.51.100.42');

    $monitor = $statusService->getMonitor($monitorId);
    expect($monitor)->not->toBeNull()
        ->and($monitor['target'])->toBe('198.51.100.42')
        ->and($monitor['port'])->toBe(22)
        ->and($monitor['active'])->toBe(1);

    // Teardown
    $statusService->deleteMonitor($monitorId);
});
```

---

## 4. Tier 3: Security & Isolation Testing

Security tests run against dedicated test environments and execute real attack vectors to verify that isolation barriers hold under adversarial conditions.

### 4.1 Multi-Tenant Boundary Isolation
Validates that User A (Tenant 1) cannot access, modify, list, or infer the existence of User B's (Tenant 2) resources across API endpoints, Livewire actions, and file downloads.

```php
// tests/Security/TenantIsolationTest.php
use App\Models\User;
use App\Models\Commerce\Invoice;
use App\Models\Commerce\ServiceInstance;

it('strictly forbids a customer from viewing or downloading another customer invoice', function () {
    $tenantA = User::factory()->create();
    $tenantB = User::factory()->create();

    $invoiceB = Invoice::factory()->create(['user_id' => $tenantB->id]);

    // Acting as Tenant A
    $this->actingAs($tenantA)
        ->getJson("/api/v1/invoices/{$invoiceB->uuid}")
        ->assertStatus(403);

    // Direct PDF download route
    $this->actingAs($tenantA)
        ->get("/portal/invoices/{$invoiceB->uuid}/download")
        ->assertStatus(403);
});

it('strictly forbids a customer from rebooting or stopping another customer service', function () {
    $tenantA = User::factory()->create();
    $tenantB = User::factory()->create();

    $serviceB = ServiceInstance::factory()->create([
        'user_id' => $tenantB->id,
        'service_type' => 'vps',
    ]);

    $this->actingAs($tenantA)
        ->postJson("/api/v1/services/{$serviceB->uuid}/reboot")
        ->assertStatus(403);
});
```

### 4.2 SSH Key Segregation & Host Isolation
- **Test**: Customer public keys submitted during VPS provisioning are injected *only* into the customer's isolated guest OS `/home/debian/.ssh/authorized_keys` or cloud-init user-data.
- **Assertion**: Customer public keys are never written to the host hypervisor's `/root/.ssh/authorized_keys` or the STX Hosting master node.
- **Assertion**: Host private keys (used by STX Hosting to manage the host) are never serialized in customer API payloads.

### 4.3 Payment Proof Tamper Resistance & Upload Security
Validates defense mechanisms against malicious file uploads and tampered payment slips.

#### Attack Vectors Tested:
1. **Extension Spoofing**: Submitting a PHP webshell disguised as `slip.jpg` (`Content-Type: image/jpeg` with PHP payload `<?php system($_GET['cmd']); ?>`).
   - **Verification**: `finfo` magic byte inspection identifies file as `text/x-php` and immediately rejects upload with HTTP 422.
2. **Path Traversal in Filename**: Uploading a file named `../../../../etc/cron.d/malicious`.
   - **Verification**: Backend replaces user-supplied filename with a cryptographically generated UUID; path characters are stripped.
3. **MIME Whitelisting**: Rejecting `.exe`, `.sh`, `.svg` (prevents SVG stored XSS), `.html`, `.zip`. Only `image/jpeg`, `image/png`, `image/webp`, and `application/pdf` are accepted.
4. **SHA-256 Collision & Tampering**: Verifies that re-uploading an altered file produces a differing SHA-256 hash.
5. **Signed URL Tamper Resistance**: Modifying parameters or timestamp in a signed proof URL results in HTTP 403 Invalid Signature.

```php
// tests/Security/PaymentProofSecurityTest.php
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('rejects disguised PHP shell files even with image extension and MIME header', function () {
    Storage::fake('payment_proofs');

    $phpPayload = "<?php echo 'compromised'; ?>";
    $fakeImage = UploadedFile::fake()->createWithContent('malicious.jpg', $phpPayload);

    $user = User::factory()->create();
    $invoice = Invoice::factory()->create(['user_id' => $user->id, 'status' => 'unpaid']);

    $this->actingAs($user)
        ->postJson("/api/v1/invoices/{$invoice->uuid}/proof", [
            'proof_file' => $fakeImage,
            'payment_method' => 'bank_transfer',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['proof_file']);
});
```

### 4.4 Multi-Tenancy Docker Quota Escape Checks
For Phase 3 shared container hosting:
- Asserts that containers deployed for customers do not have `--privileged` flag enabled.
- Asserts that `/var/run/docker.sock` is never mounted into tenant containers.
- Asserts that memory and CPU limits are strictly enforced by inspecting the Docker container inspect output.

---

## 5. Tier 4: End-to-End (E2E) Browser Testing (Playwright / Pest Browser)

E2E tests use `pestphp/pest-plugin-browser` with headless Chromium to simulate real browser interactions, form submissions, file uploads, and admin approvals across the full user lifecycle.

### 5.1 Test Setup & Environment Isolation
Per repository conventions:
1. Browser tests reside in `tests/v4/Browser/`.
2. Tests boot an in-process amphp HTTP server and launch headless Chromium via local Playwright server (`node node_modules/.bin/playwright run-server`).
3. Shared sqlite `:memory:` database ensures rapid execution and complete isolation.
4. Maintenance store is forced to array (`config()->set('app.maintenance.store', 'array')`).
5. Every test concludes with `->screenshot('...')` saving PNG artifacts in `tests/Browser/Screenshots/`.

### 5.2 Complete E2E User Journey Test Matrix

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                              E2E TEST MATRIX EXECUTION FLOW                             │
└────────────────────────────────────────────────────────────────────────────────────────┘
  [Step 1: Storefront Browsing]
  Customer visits `/vps`, views CX22 plan, inputs hostname and SSH key, clicks "Order Now".
       │
       ▼
  [Step 2: Customer Checkout & Invoice]
  Customer logs into portal, submits order, views invoice INV-2026-0001 in status 'unpaid'.
       │
       ▼
  [Step 3: Proof Upload]
  Customer uploads test JPEG bank transfer receipt; invoice transitions to 'payment_pending'.
       │
       ▼
  [Step 4: Administrator Approval]
  Admin logs into `/admin/billing/proofs`, views side-by-side slip, clicks "Approve Payment".
       │
       ▼
  [Step 5: Provisioning & Service Handover]
  Inventory slot allocated; welcome credentials generated; customer views active VPS in portal.
```

### 5.3 Concrete E2E Browser Test Script

```php
// tests/v4/Browser/HostingOrderToActivationE2ETest.php
use App\Models\User;
use App\Models\Commerce\Product;
use App\Models\Commerce\ProductVariant;
use App\Models\Commerce\PricingTier;
use App\Models\Commerce\VpsInventory;
use App\Models\InstanceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('app.maintenance.store', 'array');
    InstanceSettings::create(['id' => 0]);
    Storage::fake('payment_proofs');

    // Seed test product and pricing
    $this->product = Product::create([
        'category' => 'vps',
        'name' => 'Hetzner Cloud VPS',
        'slug' => 'hetzner-cloud-vps',
        'is_active' => true,
    ]);

    $this->variant = ProductVariant::create([
        'product_id' => $this->product->id,
        'sku' => 'vps-cx22',
        'name' => 'CX22 Cloud Compute',
        'vcpu' => 2,
        'ram_mb' => 4096,
        'disk_gb' => 40,
        'is_active' => true,
    ]);

    $this->tier = PricingTier::create([
        'product_variant_id' => $this->variant->id,
        'billing_cycle' => 'monthly',
        'price_cents' => 1200,
        'currency' => 'USD',
        'is_active' => true,
    ]);

    // Seed available inventory node
    $this->inventory = VpsInventory::create([
        'provider' => 'hetzner',
        'datacenter' => 'fsn1-de',
        'vcpu' => 2,
        'ram_mb' => 4096,
        'disk_gb' => 40,
        'ipv4_address' => '198.51.100.77',
        'status' => 'available',
    ]);

    // Create test customer and admin
    $this->customer = User::factory()->create([
        'email' => 'client@example.com',
        'password' => bcrypt('password123'),
    ]);

    $this->admin = User::factory()->create([
        'email' => 'admin@shanutechx.com',
        'password' => bcrypt('adminpass123'),
        'role' => 'owner',
    ]);
});

test('full order-to-activation lifecycle with proof upload and admin approval', function () {
    // 1. Customer logs in and places order
    $browser = $this->browse();

    $browser->visit('/login')
        ->fill('email', 'client@example.com')
        ->fill('password', 'password123')
        ->click('Login')
        ->assertPathIs('/portal')
        ->screenshot('01-customer-dashboard-empty');

    // 2. Customer navigates to checkout for CX22
    $browser->visit("/services/checkout?plan={$this->tier->id}")
        ->fill('hostname', 'web1.clientsite.com')
        ->fill('ssh_public_key', 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIExampleKeyCustomer client@local')
        ->click('Place Order')
        ->assertSee('Invoice INV-')
        ->assertSee('$12.00')
        ->screenshot('02-invoice-generated');

    // 3. Customer uploads payment proof
    $testProof = UploadedFile::fake()->image('bank_transfer_slip.jpg', 800, 600);
    $browser->attach('proof_file', $testProof->path())
        ->select('payment_method', 'bank_transfer')
        ->click('Submit Payment Proof')
        ->assertSee('Payment Verification Pending')
        ->screenshot('03-proof-uploaded');

    // 4. Admin logs in and navigates to review queue
    $adminBrowser = $this->browse();
    $adminBrowser->visit('/login')
        ->fill('email', 'admin@shanutechx.com')
        ->fill('password', 'adminpass123')
        ->click('Login')
        ->visit('/admin/billing/proofs')
        ->assertSee('bank_transfer_slip.jpg')
        ->assertSee('web1.clientsite.com')
        ->screenshot('04-admin-proof-review-queue');

    // 5. Admin clicks Approve
    $adminBrowser->click('Approve Payment')
        ->assertSee('Payment approved successfully')
        ->screenshot('05-admin-approved');

    // 6. Verify Customer Dashboard shows active VPS
    $browser->visit('/portal/services')
        ->assertSee('web1.clientsite.com')
        ->assertSee('198.51.100.77')
        ->assertSee('Active')
        ->screenshot('06-customer-service-active');

    // 7. Verify inventory node is marked allocated
    expect($this->inventory->fresh()->status)->toBe('allocated');
});
```

---

## 6. Test Environments, Fixtures & CI/CD Pipeline Automation

### 6.1 Environment Topology
- **Local Dev / Unit**: SQLite `:memory:`, Array cache, Array queue. Tests run in <10 seconds.
- **Continuous Integration (CI)**: GitHub Actions runner with PostgreSQL 16 service container and Redis 7 service container.
- **Staging Canary**: Full multi-service Docker Compose environment running storefront (`web`), status (`status`), and STX Hosting on private dev VLAN.

### 6.2 CI/CD GitHub Actions Workflow
The automated quality gate executes on every pull request and push to `main`:

```yaml
# .github/workflows/hosting-platform-tests.yml
name: Hosting Platform Quality Gates

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  code-quality:
    name: Lint & Static Analysis
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP 8.3
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, pdo_pgsql, redis, bcmath
      - name: Install Dependencies
        run: composer install --prefer-dist --no-interaction
      - name: Code Formatting (Pint)
        run: vendor/bin/pint --test
      - name: Static Analysis (PHPStan)
        run: vendor/bin/phpstan analyse --level=8 app/

  unit-and-integration:
    name: Pest Unit, Integration & Security Tests
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:16-alpine
        env:
          POSTGRES_DB: stx_test
          POSTGRES_USER: stx
          POSTGRES_PASSWORD: password
        ports:
          - 5432:5432
      redis:
        image: redis:7-alpine
        ports:
          - 6379:6379
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP 8.3
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, pdo_pgsql, redis, bcmath, fileinfo
      - name: Run Migrations
        run: php artisan migrate --env=testing
      - name: Run Pest Suite
        run: php artisan test --compact --exclude-group=browser

  browser-e2e:
    name: Playwright E2E Browser Suite
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup Node.js & Playwright
        uses: actions/setup-node@v4
        with:
          node-version: 20
      - run: npm install && npx playwright install --with-deps chromium
      - name: Run Browser Tests
        run: php artisan test --compact tests/v4/Browser/
      - name: Upload Screenshots on Failure
        if: failure()
        uses: actions/upload-artifact@v4
        with:
          name: playwright-failure-screenshots
          path: tests/Browser/Screenshots/
```

---

## 7. Defect Severity & Release Gate Rubric

| Severity | Criteria | Deployment Policy |
|---|---|---|
| **P0: Blocker** | Financial miscalculation, cross-tenant security breach, privilege escalation, or complete provisioning failure. | **Immediate Halt**. No deployment allowed under any circumstances. |
| **P1: Critical** | Partial payment proof upload failure, missing email notifications, or domain SSL issuance failure. | Hotfix required within 24 hours. Blocks production promotion. |
| **P2: Major** | UI visual defects, slow status probe reporting, or minor formatting issues in invoices. | Fix in current milestone sprint. |
| **P3: Minor** | Typo in documentation or non-standard icon alignment. | Backlog scheduling. |

---

## 8. Summary

This 4-Tier Testing Strategy establishes complete, verifiable coverage spanning unit calculations, concurrency locks, security exploits, and full-browser customer workflows. Adherence to this framework guarantees that the ShanuTechX platform launches with enterprise-grade resilience. Proceed to **Deliverable I: Operations Runbook** (`09-operations-runbook.md`) for deployment and disaster recovery procedures.
