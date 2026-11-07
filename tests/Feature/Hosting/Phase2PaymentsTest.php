<?php

/*
|--------------------------------------------------------------------------
| Phase 2: billing and manual payments
|--------------------------------------------------------------------------
| order -> invoice -> payment instructions -> proof -> staff review -> unlock
*/

use App\Livewire\Admin\Billing\PaymentMethods;
use App\Livewire\Admin\Billing\ProofReviewQueue;
use App\Livewire\Portal\Checkout;
use App\Models\Commerce\Invoice;
use App\Models\Commerce\PaymentMethod;
use App\Models\Commerce\PaymentProof;
use App\Models\Commerce\PricingTier;
use App\Models\Commerce\ProductVariant;
use App\Models\HostingEntitlement;
use App\Models\InstanceSettings;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Commerce\OrderPlacedNotification;
use App\Notifications\Commerce\PaymentProofReceivedNotification;
use App\Notifications\Commerce\PaymentRejectedNotification;
use App\Notifications\Commerce\StaffProofSubmittedNotification;
use App\Services\Commerce\OrderService;
use App\Services\Commerce\PaymentReviewService;
use App\Services\Commerce\ProofStorageService;
use Database\Seeders\HostingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
    Server::forceCreate(['id' => 0, 'name' => 'localhost', 'ip' => '127.0.0.1', 'team_id' => 0]);
    $this->seed(HostingCatalogSeeder::class);
    Storage::fake('payment_proofs');
});

function p2User(string $role): User
{
    $user = User::factory()->create();
    if ($role === 'owner' || $role === 'admin') {
        $root = Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
        $root->members()->attach($user->id, ['role' => $role]);
    }
    Team::factory()->create()->members()->attach($user->id, ['role' => 'owner']);

    return $user->fresh();
}

function p2Team(User $user): Team
{
    return $user->teams()->where('teams.id', '!=', 0)->firstOrFail();
}

function p2Variant(): ProductVariant
{
    return ProductVariant::with(['product', 'pricingTiers'])
        ->whereHas('pricingTiers', fn ($q) => $q->where('is_active', true))
        ->firstOrFail();
}

/** Order + invoice through the real service, for the given client. */
function p2Place(User $client, string $currency = 'USD'): Invoice
{
    $variant = p2Variant();
    $tier = $variant->pricingTiers->firstWhere('is_active', true);

    [, $invoice] = app(OrderService::class)->place($client, p2Team($client), [
        ['variant' => $variant, 'billing_type' => $tier->billing_type, 'quantity' => 1],
    ], $currency);

    return $invoice->fresh(['order.items', 'items']);
}

/** A valid 1x1 PNG. $seed changes one byte of the pixel so two files differ. */
function p2Png(int $seed = 0): UploadedFile
{
    $img = imagecreatetruecolor(4, 4);
    imagefill($img, 0, 0, imagecolorallocate($img, $seed % 255, 120, 200));
    ob_start();
    imagepng($img);
    $bytes = ob_get_clean();
    imagedestroy($img);

    return UploadedFile::fake()->createWithContent('slip.png', $bytes);
}

/* ---------- orders: price comes from the catalog ---------- */

test('[P2-ORDER] the order total is the catalog price and starts unpaid', function () {
    Notification::fake();
    $client = p2User('client');
    $variant = p2Variant();
    $tier = $variant->pricingTiers->firstWhere('is_active', true);

    $invoice = p2Place($client);

    expect($invoice->total_cents)->toBe((int) $tier->price_cents);
    expect($invoice->status)->toBe('unpaid');
    expect($invoice->order->status)->toBe('pending');
    Notification::assertSentTo($client, OrderPlacedNotification::class);
});

test('[P2-ORDER] an LKR order for a plan without an LKR price is refused', function () {
    $client = p2User('client');
    $variant = p2Variant();
    $variant->pricingTiers()->update(['price_lkr_cents' => null]);

    expect(fn () => app(OrderService::class)->place($client, p2Team($client), [
        ['variant' => $variant->fresh('pricingTiers'), 'billing_type' => $variant->pricingTiers->first()->billing_type],
    ], 'LKR'))->toThrow(InvalidArgumentException::class);
});

/* ---------- checkout page ---------- */

test('[P2-CHECKOUT] the price and plan cannot be changed from the browser', function () {
    $client = p2User('client');
    session(['currentTeam' => p2Team($client)]);

    $component = Livewire::withQueryParams(['plan' => p2Variant()->uuid])
        ->actingAs($client)
        ->test(Checkout::class);

    expect(fn () => $component->set('totalAmountCents', 1))->toThrow(CannotUpdateLockedPropertyException::class);
    expect(fn () => $component->set('selectedVariant', null))->toThrow(CannotUpdateLockedPropertyException::class);
});

test('[P2-CHECKOUT] placing an order creates the invoice at the server price and moves to the payment step', function () {
    Notification::fake();
    $client = p2User('client');
    session(['currentTeam' => p2Team($client)]);
    $variant = p2Variant();
    $tier = $variant->pricingTiers->firstWhere('is_active', true);

    Livewire::withQueryParams(['plan' => $variant->uuid, 'billing' => $tier->billing_type])
        ->actingAs($client)
        ->test(Checkout::class)
        ->call('placeOrder')
        ->assertHasNoErrors()
        ->assertRedirect();

    $invoice = Invoice::where('user_id', $client->id)->firstOrFail();
    expect($invoice->total_cents)->toBe((int) $tier->price_cents);
    expect($invoice->status)->toBe('unpaid');
    expect($invoice->proofs()->count())->toBe(0); // no proof needed to place the order
});

test('[P2-CHECKOUT] without a plan the page sends the client to the store instead of guessing one', function () {
    $client = p2User('client');
    session(['currentTeam' => p2Team($client)]);

    Livewire::actingAs($client)->test(Checkout::class)->assertRedirect();

    expect(Invoice::count())->toBe(0);
});

test('[P2-CHECKOUT] another client cannot open or pay someone else\'s invoice', function () {
    Notification::fake();
    $victim = p2User('client');
    $invoice = p2Place($victim);

    $this->actingAs(p2User('client'))->get('/portal/checkout?invoice='.$invoice->uuid)->assertNotFound();
});

test('[P2-CHECKOUT] clients see the owner\'s active payment methods and no hard-coded bank details', function () {
    Notification::fake();
    PaymentMethod::create(['type' => 'bank_transfer', 'label' => 'Acme Bank Transfer', 'currency' => 'ANY', 'is_active' => true,
        'details' => [['label' => 'Account number', 'value' => '555-ACME']]]);
    PaymentMethod::create(['type' => 'crypto', 'label' => 'Hidden Wallet', 'currency' => 'ANY', 'is_active' => false]);
    PaymentMethod::create(['type' => 'wallet', 'label' => 'LKR Only Wallet', 'currency' => 'LKR', 'is_active' => true]);

    $client = p2User('client');
    session(['currentTeam' => p2Team($client)]);
    $invoice = p2Place($client, 'USD');

    $this->actingAs($client)->get('/portal/checkout?invoice='.$invoice->uuid)
        ->assertOk()
        ->assertSee('Acme Bank Transfer')
        ->assertSee('555-ACME')
        ->assertDontSee('Hidden Wallet')
        ->assertDontSee('LKR Only Wallet')
        ->assertDontSee('8012 3456 7890');
});

/* ---------- proof upload ---------- */

test('[P2-PROOF] a valid proof is stored privately, moves the order forward and notifies client and staff', function () {
    Notification::fake();
    $staff = p2User('admin');
    $client = p2User('client');
    $invoice = p2Place($client);

    $proof = app(ProofStorageService::class)->store(p2Png(1), $invoice, $client, 'bank_transfer', 'REF-1');

    expect($proof->status)->toBe('pending_review');
    expect($proof->attempt_no)->toBe(1);
    Storage::disk('payment_proofs')->assertExists($proof->file_path);
    expect($invoice->fresh()->status)->toBe('payment_pending');
    expect($invoice->order->fresh()->status)->toBe('payment_submitted');
    Notification::assertSentTo($client, PaymentProofReceivedNotification::class);
    Notification::assertSentTo($staff, StaffProofSubmittedNotification::class);
});

test('[P2-PROOF] a second proof is refused while one is waiting for review', function () {
    Notification::fake();
    $client = p2User('client');
    $invoice = p2Place($client);
    $svc = app(ProofStorageService::class);

    $svc->store(p2Png(1), $invoice, $client);

    expect(fn () => $svc->store(p2Png(2), $invoice->fresh(), $client))->toThrow(ValidationException::class);
});

test('[P2-PROOF] a file that only pretends to be an image is refused', function () {
    Notification::fake();
    $client = p2User('client');
    $invoice = p2Place($client);

    $fake = UploadedFile::fake()->createWithContent('slip.png', '<?php echo 1; ?>');

    expect(fn () => app(ProofStorageService::class)->store($fake, $invoice, $client))->toThrow(ValidationException::class);
    expect(PaymentProof::count())->toBe(0);
});

test('[P2-PROOF] the same receipt cannot be reused on a different invoice', function () {
    Notification::fake();
    $a = p2User('client');
    $b = p2User('client');
    $svc = app(ProofStorageService::class);

    $svc->store(p2Png(7), p2Place($a), $a);

    expect(fn () => $svc->store(p2Png(7), p2Place($b), $b))->toThrow(ValidationException::class);
});

/* ---------- staff review ---------- */

test('[P2-REVIEW] approval pays the invoice, marks the order paid, unlocks the service and cannot be repeated', function () {
    Notification::fake();
    $staff = p2User('admin');
    $client = p2User('client');
    $invoice = p2Place($client);
    $proof = app(ProofStorageService::class)->store(p2Png(3), $invoice, $client);

    $reviews = app(PaymentReviewService::class);
    $reviews->approve($proof, $staff);

    expect($invoice->fresh()->status)->toBe('paid');
    expect($invoice->order->fresh()->status)->toBe('paid');
    expect($proof->fresh()->status)->toBe('approved');
    expect($invoice->transactions()->count())->toBe(1);
    expect(HostingEntitlement::where('team_id', $invoice->team_id)->where('status', 'active')->count())->toBe(1);

    expect(fn () => $reviews->approve($proof->fresh(), $staff))->toThrow(ValidationException::class);
    expect(HostingEntitlement::where('team_id', $invoice->team_id)->count())->toBe(1);
});

test('[P2-REVIEW] rejection needs a reason, reopens the invoice and tells the client', function () {
    Notification::fake();
    $staff = p2User('owner');
    $client = p2User('client');
    $invoice = p2Place($client);
    $proof = app(ProofStorageService::class)->store(p2Png(4), $invoice, $client);
    $reviews = app(PaymentReviewService::class);

    expect(fn () => $reviews->reject($proof, $staff, 'no'))->toThrow(ValidationException::class);

    $reviews->reject($proof->fresh(), $staff, 'The amount does not match the invoice.');

    expect($proof->fresh()->status)->toBe('rejected');
    expect($proof->fresh()->rejection_reason)->toBe('The amount does not match the invoice.');
    expect($invoice->fresh()->status)->toBe('unpaid');
    expect($invoice->order->fresh()->status)->toBe('pending');
    Notification::assertSentTo($client, PaymentRejectedNotification::class);
});

test('[P2-REVIEW] after a rejection the client can upload a corrected proof (attempt 2)', function () {
    Notification::fake();
    $staff = p2User('admin');
    $client = p2User('client');
    $invoice = p2Place($client);
    $svc = app(ProofStorageService::class);

    $first = $svc->store(p2Png(5), $invoice, $client);
    app(PaymentReviewService::class)->reject($first, $staff, 'Receipt is cropped, please resend.');

    $second = $svc->store(p2Png(6), $invoice->fresh(), $client);

    expect($second->attempt_no)->toBe(2);
    expect($second->status)->toBe('pending_review');
});

test('[P2-REVIEW] a client cannot decide on a proof, even through the service', function () {
    Notification::fake();
    $client = p2User('client');
    $invoice = p2Place($client);
    $proof = app(ProofStorageService::class)->store(p2Png(8), $invoice, $client);

    expect(fn () => app(PaymentReviewService::class)->approve($proof, $client))->toThrow(HttpException::class);
    expect($invoice->fresh()->status)->toBe('payment_pending');
});

test('[P2-REVIEW] the staff queue approves through the same rules', function () {
    Notification::fake();
    $staff = p2User('admin');
    $client = p2User('client');
    $proof = app(ProofStorageService::class)->store(p2Png(9), p2Place($client), $client);

    Livewire::actingAs($staff)->test(ProofReviewQueue::class)
        ->call('approve', $proof->id)
        ->assertDispatched('success');

    expect($proof->fresh()->status)->toBe('approved');
});

/* ---------- owner-managed payment methods ---------- */

test('[P2-METHODS] only the owner can manage payment methods', function () {
    $this->actingAs(p2User('client'))->get('/admin/billing/methods')->assertForbidden();
    $this->actingAs(p2User('admin'))->get('/admin/billing/methods')->assertForbidden();
    $this->actingAs(p2User('owner'))->get('/admin/billing/methods')->assertOk();

    Livewire::actingAs(p2User('admin'))->test(PaymentMethods::class)->assertForbidden();
});

test('[P2-METHODS] the owner can create, hide and delete a method', function () {
    $owner = p2User('owner');

    $c = Livewire::actingAs($owner)->test(PaymentMethods::class)
        ->call('create')
        ->set('label', 'Sampath Bank')
        ->set('currency', 'LKR')
        ->set('details', [['label' => 'Account number', 'value' => '123456'], ['label' => '', 'value' => '']])
        ->call('save')
        ->assertHasNoErrors();

    $method = PaymentMethod::firstOrFail();
    expect($method->label)->toBe('Sampath Bank');
    expect($method->details)->toHaveCount(1); // the empty row is dropped
    expect(PaymentMethod::forCurrency('LKR')->count())->toBe(1);
    expect(PaymentMethod::forCurrency('USD')->count())->toBe(0);

    $c->call('toggle', $method->id);
    expect($method->fresh()->is_active)->toBeFalse();

    $c->call('delete', $method->id);
    expect(PaymentMethod::count())->toBe(0);
});

/* ---------- invoice PDF ---------- */

test('[P2-PDF] only the invoice owner or staff can fetch the PDF', function () {
    Notification::fake();
    $client = p2User('client');
    $invoice = p2Place($client);

    $this->actingAs(p2User('client'))->get("/invoices/{$invoice->uuid}/pdf")->assertForbidden();

    // 200 when dompdf is installed, 503 with a clear message when it is not.
    expect($this->actingAs($client)->get("/invoices/{$invoice->uuid}/pdf")->getStatusCode())->toBeIn([200, 503]);
    expect($this->actingAs(p2User('admin'))->get("/invoices/{$invoice->uuid}/pdf")->getStatusCode())->toBeIn([200, 503]);
});
