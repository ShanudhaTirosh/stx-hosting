<?php

/*
|--------------------------------------------------------------------------
| Phase 6: editable emails, staff alerts on Discord, support chat
|--------------------------------------------------------------------------
*/

use App\Events\ChatMessageSent;
use App\Jobs\SendMessageToDiscordJob;
use App\Livewire\Admin\Notifications\DiscordRoutes;
use App\Livewire\Admin\Notifications\EmailTemplatesPage;
use App\Livewire\Support\ClientIndex;
use App\Livewire\Support\StaffInbox;
use App\Livewire\Support\ThreadView;
use App\Models\ChatAttachment;
use App\Models\ChatThread;
use App\Models\Commerce\Order;
use App\Models\Commerce\PaymentMethod;
use App\Models\Commerce\ProductVariant;
use App\Models\Commerce\ServiceInstance;
use App\Models\EmailTemplate;
use App\Models\InstanceSettings;
use App\Models\NotificationRoute;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Commerce\ChatReplyNotification;
use App\Notifications\Commerce\EmailTemplatePreviewNotification;
use App\Notifications\Commerce\OrderPlacedNotification;
use App\Notifications\Commerce\PaymentApprovedNotification;
use App\Notifications\Commerce\ServiceDeliveredNotification;
use App\Services\Chat\ChatService;
use App\Services\Commerce\OrderService;
use App\Services\Commerce\PaymentReviewService;
use App\Services\Commerce\ProofStorageService;
use App\Services\Notifications\EmailTemplates;
use App\Services\Notifications\StaffEvents;
use Database\Seeders\HostingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

const P6_HOOK = 'https://discord.com/api/webhooks/123456789/abcDEF_ghi-JKL';

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
    Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    Server::forceCreate(['id' => 0, 'name' => 'localhost', 'ip' => '127.0.0.1', 'team_id' => 0]);
    $this->seed(HostingCatalogSeeder::class);
    Storage::fake('local');
    Storage::fake('payment_proofs');
    Cache::flush();
    RateLimiter::clear('chat-post');
});

function p6Staff(string $role = 'admin'): User
{
    $u = User::factory()->create();
    Team::find(0)->members()->attach($u->id, ['role' => $role]);

    return $u->fresh();
}

function p6Client(): array
{
    $u = User::factory()->create();
    $t = Team::factory()->create();
    $t->members()->attach($u->id, ['role' => 'owner']);

    return [$u->fresh(), $t];
}

function p6Png(): UploadedFile
{
    static $n = 0;
    $n++;
    $img = imagecreatetruecolor(3 + $n, 3);
    ob_start();
    imagepng($img);

    return UploadedFile::fake()->createWithContent('shot.png', ob_get_clean());
}

function p6Route(string $event, bool $enabled = true): NotificationRoute
{
    return NotificationRoute::create(['event' => $event, 'webhook_url' => P6_HOOK, 'is_enabled' => $enabled]);
}

function p6Paid(User $client, Team $team, string $category = 'vps'): Order
{
    $variant = ProductVariant::with('pricingTiers')->whereHas('product', fn ($q) => $q->where('category', $category))->whereHas('pricingTiers', fn ($q) => $q->where('is_active', true))->firstOrFail();
    $tier = $variant->pricingTiers->firstWhere('is_active', true);
    [, $invoice] = app(OrderService::class)->place($client, $team, [['variant' => $variant, 'billing_type' => $tier->billing_type]], 'USD');

    return $invoice->order->fresh('invoice');
}

/* ---------- emails ---------- */

test('[P6-EMAIL] customer text cannot inject markup into an email', function () {
    $r = EmailTemplates::render('payment_rejected', [
        'name' => '<b>Bob</b> *bold*', 'invoice_number' => 'INV-1', 'reason' => '<script>alert(1)</script> [click](javascript:alert(1))',
    ]);

    expect($r['html'])->not->toContain('<script')->not->toContain('href=');
    expect($r['subject'])->toContain('INV-1');
    expect($r['html'])->toContain('Bob');
});

test('[P6-EMAIL] only the listed variables are replaced; anything else disappears', function () {
    $r = EmailTemplates::renderDraft('proof_received', ['subject' => 'S {invoice_number} {secret}', 'heading' => 'H', 'body' => 'Hi {name} {app_key}', 'cta_text' => null, 'cta_path' => null],
        ['name' => 'Ann', 'invoice_number' => 'INV-9', 'secret' => 'LEAK', 'app_key' => 'LEAK']);

    expect($r['subject'])->toBe('S INV-9')->and($r['html'])->not->toContain('LEAK');
});

test('[P6-EMAIL] the button can only point inside this site', function () {
    $mk = fn ($path) => EmailTemplates::renderDraft('proof_received', ['subject' => 's', 'heading' => 'h', 'body' => 'b', 'cta_text' => 'Go', 'cta_path' => $path], [])['cta_url'];

    expect($mk('/portal/billing'))->toBe(url('/portal/billing'));
    expect($mk('//evil.example'))->toBeNull();
    expect($mk('https://evil.example'))->toBeNull();
});

test('[P6-EMAIL] an owner edit replaces the built-in wording and reset brings it back', function () {
    $builtIn = EmailTemplates::get('proof_received')['subject'];
    EmailTemplate::create(['key' => 'proof_received', 'subject' => 'Thanks {name}', 'heading' => 'Custom', 'body' => 'Custom body']);

    expect(EmailTemplates::render('proof_received', ['name' => 'Zed'])['subject'])->toBe('Thanks Zed');

    EmailTemplate::where('key', 'proof_received')->delete();
    expect(EmailTemplates::get('proof_received')['subject'])->toBe($builtIn);
});

test('[P6-EMAIL] the order email shows the owner\'s payment methods and never made-up bank details', function () {
    Notification::fake();
    [$client, $team] = p6Client();
    $order = p6Paid($client, $team);

    $without = (string) (new OrderPlacedNotification($order, $order->invoice))->toMail($client)->render();
    expect($without)->toContain('not published yet')->not->toContain('8012 3456 7890');

    PaymentMethod::create(['type' => 'bank_transfer', 'label' => 'Acme Bank', 'currency' => 'ANY', 'is_active' => true, 'details' => [['label' => 'Account number', 'value' => 'ACME-555']]]);
    $with = (string) (new OrderPlacedNotification($order, $order->invoice))->toMail($client)->render();
    expect($with)->toContain('Acme Bank')->toContain('ACME-555')->not->toContain('8012 3456 7890');
});

test('[P6-EMAIL] the approval email says what really happens next', function () {
    Notification::fake();
    [$client, $team] = p6Client();

    $vps = p6Paid($client, $team, 'vps');
    $apps = p6Paid($client, $team, 'app_hosting');

    $a = (string) (new PaymentApprovedNotification($vps, $vps->invoice))->toMail($client)->render();
    $b = (string) (new PaymentApprovedNotification($apps, $apps->invoice))->toMail($client)->render();

    expect($a)->toContain('24 to 72 hours');
    expect($b)->toContain('plan is active')->not->toContain('24 to 72 hours');
});

test('[P6-EMAIL] the delivery email carries the server details', function () {
    [$client, $team] = p6Client();
    $order = Order::create(['team_id' => $team->id, 'user_id' => $client->id, 'status' => 'completed', 'subtotal_cents' => 1, 'total_cents' => 1, 'currency' => 'USD']);
    $service = ServiceInstance::create([
        'team_id' => $team->id, 'user_id' => $client->id, 'order_id' => $order->id, 'product_variant_id' => ProductVariant::firstOrFail()->id,
        'service_type' => 'vps', 'status' => 'active', 'name' => 'x', 'hostname' => 'vps-77', 'ip_address' => '203.0.113.77', 'os_image' => 'debian-12',
        'activated_at' => now(), 'renews_at' => now()->addDays(30),
    ]);

    $html = (string) (new ServiceDeliveredNotification($service))->toMail($client)->render();

    expect($html)->toContain('vps-77')->toContain('203.0.113.77')->toContain('ssh root@203.0.113.77');
});

test('[P6-EMAIL] only the owner can edit emails; saving validates variables and links', function () {
    $this->actingAs(p6Staff('admin'))->get('/admin/emails')->assertForbidden();
    [$client] = p6Client();
    $this->actingAs($client)->get('/admin/emails')->assertForbidden();

    $owner = p6Staff('owner');
    $page = Livewire::actingAs($owner)->test(EmailTemplatesPage::class)->assertOk();

    $page->set('body', 'Hello {name} {nope}')->call('save')->assertHasErrors('body');
    $page->set('body', 'Hello {name}')->set('ctaPath', 'https://evil.example')->call('save')->assertHasErrors('ctaPath');
    expect(EmailTemplate::count())->toBe(0);

    $page->set('ctaPath', '/portal/billing')->set('subject', 'Order {invoice_number}')->call('save')->assertHasNoErrors();
    expect(EmailTemplate::where('key', 'order_placed')->value('subject'))->toBe('Order {invoice_number}');

    $page->call('resetToDefault');
    expect(EmailTemplate::count())->toBe(0);
});

test('[P6-EMAIL] the owner can send themselves a test of any email', function () {
    Notification::fake();
    $owner = p6Staff('owner');

    Livewire::actingAs($owner)->test(EmailTemplatesPage::class)->call('sendTest');

    Notification::assertSentTo($owner, EmailTemplatePreviewNotification::class);
});

/* ---------- Discord staff alerts ---------- */

test('[P6-DISCORD] only the owner manages channels, and only real Discord webhooks are accepted', function () {
    $this->actingAs(p6Staff('admin'))->get('/admin/notifications')->assertForbidden();

    $owner = p6Staff('owner');
    $page = Livewire::actingAs($owner)->test(DiscordRoutes::class);

    foreach (['http://discord.com/api/webhooks/1/abc', 'https://example.com/hook', 'https://169.254.169.254/latest', 'https://discord.com.evil.test/api/webhooks/1/a', 'nope'] as $bad) {
        $page->set('webhookUrl', $bad)->call('add')->assertHasErrors('webhookUrl');
    }
    expect(NotificationRoute::count())->toBe(0);

    $page->set('event', StaffEvents::ORDER_CREATED)->set('webhookUrl', P6_HOOK)->call('add')->assertHasNoErrors();
    expect(NotificationRoute::count())->toBe(1);
});

test('[P6-DISCORD] the webhook is stored encrypted and shown masked', function () {
    $route = p6Route(StaffEvents::ORDER_CREATED);

    expect(DB::table('notification_routes')->value('webhook_url'))->not->toContain('discord.com');
    expect($route->fresh()->webhook_url)->toBe(P6_HOOK);
    expect($route->maskedUrl())->not->toContain('discord.com')->toContain('JKL');
});

test('[P6-DISCORD] only the channels set for an event hear about it, and off means off', function () {
    Bus::fake([SendMessageToDiscordJob::class]);
    p6Route(StaffEvents::ORDER_CREATED);
    p6Route(StaffEvents::ORDER_CREATED, false);
    p6Route(StaffEvents::NODE_ISSUE);

    StaffEvents::notify(StaffEvents::ORDER_CREATED, 'New order', 'x');

    Bus::assertDispatchedTimes(SendMessageToDiscordJob::class, 1);
});

test('[P6-DISCORD] the same thing is announced once, and a burst is capped', function () {
    Bus::fake([SendMessageToDiscordJob::class]);
    p6Route(StaffEvents::CHAT_MESSAGE);

    StaffEvents::notify(StaffEvents::CHAT_MESSAGE, 't', 'd', [], 'info', 'same');
    StaffEvents::notify(StaffEvents::CHAT_MESSAGE, 't', 'd', [], 'info', 'same');
    Bus::assertDispatchedTimes(SendMessageToDiscordJob::class, 1);

    for ($i = 0; $i < 50; $i++) {
        StaffEvents::notify(StaffEvents::CHAT_MESSAGE, 't', 'd', [], 'info', "k{$i}");
    }
    expect(count(Bus::dispatched(SendMessageToDiscordJob::class)))->toBeLessThanOrEqual(30);
});

test('[P6-DISCORD] customer text cannot ping @everyone in a staff channel', function () {
    expect(StaffEvents::clean('hi @everyone and @here <@123> <b>x</b>', 200))->not->toContain('@everyone')->not->toContain('<@123>')->not->toContain('<b>');
    expect(mb_strlen(StaffEvents::clean(str_repeat('a', 5000), 100)))->toBe(100);
});

test('[P6-DISCORD] an alert that fails never breaks the order', function () {
    // A channel whose stored URL cannot be decrypted: sending the alert throws.
    DB::table('notification_routes')->insert([
        'uuid' => (string) Illuminate\Support\Str::uuid(), 'event' => StaffEvents::ORDER_CREATED,
        'webhook_url' => 'not-encrypted', 'is_enabled' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    Notification::fake();
    [$client, $team] = p6Client();

    $order = p6Paid($client, $team);

    expect($order->exists)->toBeTrue();
    expect($order->invoice->status)->toBe('unpaid');
});

test('[P6-DISCORD] orders, proofs, reviews and late deliveries reach their channels', function () {
    Bus::fake([SendMessageToDiscordJob::class]);
    Notification::fake();
    foreach ([StaffEvents::ORDER_CREATED, StaffEvents::PROOF_UPLOADED, StaffEvents::PROOF_REVIEWED, StaffEvents::DELIVERY_DUE] as $e) {
        p6Route($e);
    }
    $staff = p6Staff('owner');
    [$client, $team] = p6Client();

    $order = p6Paid($client, $team);
    Bus::assertDispatchedTimes(SendMessageToDiscordJob::class, 1); // order created

    $img = imagecreatetruecolor(9, 9);
    ob_start();
    imagepng($img);
    $proof = app(ProofStorageService::class)->store(UploadedFile::fake()->createWithContent('s.png', ob_get_clean()), $order->invoice, $client);
    Bus::assertDispatchedTimes(SendMessageToDiscordJob::class, 2); // proof uploaded

    app(PaymentReviewService::class)->approve($proof, $staff);
    Bus::assertDispatchedTimes(SendMessageToDiscordJob::class, 3); // proof reviewed

    $order->invoice->update(['paid_at' => now()->subHours(30)]);
    $this->artisan('vps:check-delivery-sla');
    Bus::assertDispatchedTimes(SendMessageToDiscordJob::class, 4); // delivery reminder
});

/* ---------- chat ---------- */

test('[P6-CHAT] a customer starts a conversation; staff are alerted', function () {
    Bus::fake([SendMessageToDiscordJob::class]);
    p6Route(StaffEvents::TICKET_CREATED);
    [$client, $team] = p6Client();
    session(['currentTeam' => $team]);

    Livewire::actingAs($client)->test(ClientIndex::class)
        ->set('subject', 'My VPS is slow')
        ->set('message', 'It was fast yesterday.')
        ->call('start')
        ->assertHasNoErrors()
        ->assertRedirect();

    $thread = ChatThread::firstOrFail();
    expect($thread->team_id)->toBe($team->id)->and($thread->messages)->toHaveCount(1);
    expect($thread->messages->first()->is_staff)->toBeFalse();
    Bus::assertDispatched(SendMessageToDiscordJob::class);
});

test('[P6-CHAT] only the customer\'s own team and support staff can open a conversation', function () {
    [$client, $team] = p6Client();
    [$other] = p6Client();
    $thread = app(ChatService::class)->open($client, $team, 'Help', 'hello');

    Livewire::actingAs($client)->test(ThreadView::class, ['uuid' => $thread->uuid])->assertOk();
    Livewire::actingAs(p6Staff('admin'))->test(ThreadView::class, ['uuid' => $thread->uuid])->assertOk();
    Livewire::actingAs($other)->test(ThreadView::class, ['uuid' => $thread->uuid])->assertNotFound();
});

test('[P6-CHAT] unread counts follow who wrote last and who has looked', function () {
    Notification::fake();
    $staff = p6Staff('admin');
    [$client, $team] = p6Client();
    $chat = app(ChatService::class);

    $thread = $chat->open($client, $team, 'Q', 'hi');
    expect($chat->unreadForStaff())->toBe(1)->and($chat->unreadForCustomer($client))->toBe(0);

    $this->travel(2)->seconds();
    $chat->post($thread, $staff, 'on it');
    expect($chat->unreadForStaff())->toBe(0)->and($chat->unreadForCustomer($client))->toBe(1);
    expect($thread->fresh()->assigned_to)->toBe($staff->id); // first reply takes the conversation

    $this->travel(2)->seconds();
    Livewire::actingAs($client)->test(ThreadView::class, ['uuid' => $thread->uuid]);
    expect($chat->unreadForCustomer($client))->toBe(0);
});

test('[P6-CHAT] if the customer is away they get an email once, not for every reply', function () {
    Notification::fake();
    $staff = p6Staff('admin');
    [$client, $team] = p6Client();
    $chat = app(ChatService::class);
    $thread = $chat->open($client, $team, 'Q', 'hi');

    $chat->post($thread, $staff, 'first');
    $chat->post($thread->fresh(), $staff, 'second');
    Notification::assertSentToTimes($client, ChatReplyNotification::class, 1);

    // Reading resets it: the next reply while away emails again.
    $chat->markRead($thread->fresh(), $client);
    $chat->post($thread->fresh(), $staff, 'third');
    Notification::assertSentToTimes($client, ChatReplyNotification::class, 2);
});

test('[P6-CHAT] no email when the customer is looking at the conversation', function () {
    Notification::fake();
    $staff = p6Staff('admin');
    [$client, $team] = p6Client();
    $chat = app(ChatService::class);
    $thread = $chat->open($client, $team, 'Q', 'hi');

    $chat->touchPresence($thread, $client);
    $chat->post($thread->fresh(), $staff, 'hello there');

    Notification::assertNothingSentTo($client);
});

test('[P6-CHAT] closed conversations: staff cannot post, a customer reply reopens', function () {
    Notification::fake();
    $staff = p6Staff('owner');
    [$client, $team] = p6Client();
    $chat = app(ChatService::class);
    $thread = $chat->open($client, $team, 'Q', 'hi');

    $chat->close($thread, $staff);
    expect(fn () => $chat->post($thread->fresh(), $staff, 'late'))->toThrow(ValidationException::class);

    $chat->post($thread->fresh(), $client, 'still broken');
    expect($thread->fresh()->status)->toBe('open');
});

test('[P6-CHAT] customers cannot assign or close conversations', function () {
    [$client, $team] = p6Client();
    $thread = app(ChatService::class)->open($client, $team, 'Q', 'hi');

    Livewire::actingAs($client)->test(ThreadView::class, ['uuid' => $thread->uuid])->call('close')->assertForbidden();
    expect($thread->fresh()->status)->toBe('open');
});

test('[P6-CHAT] message rules: not empty, bounded, limited number of files, rate limited', function () {
    [$client, $team] = p6Client();
    $chat = app(ChatService::class);
    $thread = $chat->open($client, $team, 'Q', 'hi');

    expect(fn () => $chat->post($thread, $client, '   '))->toThrow(ValidationException::class);
    expect(fn () => $chat->post($thread, $client, str_repeat('a', ChatService::MAX_BODY + 1)))->toThrow(ValidationException::class);
    expect(fn () => $chat->post($thread, $client, 'x', [p6Png(), p6Png(), p6Png(), p6Png()]))->toThrow(ValidationException::class);

    RateLimiter::clear("chat-post:{$client->id}");
    for ($i = 0; $i < 20; $i++) {
        $chat->post($thread, $client, "m{$i}");
    }
    expect(fn () => $chat->post($thread, $client, 'one too many'))->toThrow(ValidationException::class);
});

test('[P6-CHAT] attachments are checked by content, stored privately under random names', function () {
    [$client, $team] = p6Client();
    $chat = app(ChatService::class);
    $thread = $chat->open($client, $team, 'Q', 'hi');

    // A Windows program renamed to .png: the content gives it away.
    $disguised = UploadedFile::fake()->createWithContent('photo.png', "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xff\xff\x00\x00");
    expect(fn () => $chat->post($thread, $client, 'see', [$disguised]))->toThrow(ValidationException::class);

    $big = UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf');
    expect(fn () => $chat->post($thread, $client, 'see', [$big]))->toThrow(ValidationException::class);

    $message = $chat->post($thread, $client, 'screenshot', [p6Png(), UploadedFile::fake()->createWithContent('notes.txt', 'plain notes')]);

    $names = $message->attachments->pluck('path');
    expect($names)->toHaveCount(2);
    foreach ($names as $path) {
        expect($path)->toStartWith('chat/'.$thread->uuid.'/')->not->toContain('shot')->not->toContain('notes');
        Storage::disk('local')->assertExists($path);
    }
    expect($message->attachments->pluck('mime')->all())->toContain('image/png', 'text/plain');
});

test('[P6-CHAT] attachment downloads check the conversation, are never inline for non-images, and are not sniffable', function () {
    [$client, $team] = p6Client();
    [$other] = p6Client();
    $chat = app(ChatService::class);
    $thread = $chat->open($client, $team, 'Q', 'hi');
    $chat->post($thread, $client, 'files', [p6Png(), UploadedFile::fake()->createWithContent('notes.txt', 'plain notes')]);

    $png = ChatAttachment::where('mime', 'image/png')->firstOrFail();
    $txt = ChatAttachment::where('mime', 'text/plain')->firstOrFail();

    $ok = $this->actingAs($client)->get(route('support.attachment', $png->uuid))->assertOk();
    expect($ok->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    expect($ok->headers->get('Content-Disposition'))->toContain('inline');

    $this->actingAs($client)->get(route('support.attachment', $txt->uuid))->assertOk();
    expect($this->actingAs($client)->get(route('support.attachment', $txt->uuid))->headers->get('Content-Disposition'))->toContain('attachment');

    $this->actingAs($other)->get(route('support.attachment', $png->uuid))->assertNotFound();
    $this->actingAs(p6Staff('admin'))->get(route('support.attachment', $png->uuid))->assertOk();
    auth()->logout();
    $this->get(route('support.attachment', $png->uuid))->assertRedirect();
});

test('[P6-CHAT] a new message broadcasts a refresh signal that carries no message text', function () {
    Event::fake([ChatMessageSent::class]);
    [$client, $team] = p6Client();
    $thread = app(ChatService::class)->open($client, $team, 'Q', 'secret words');

    Event::assertDispatched(ChatMessageSent::class, function ($e) use ($thread) {
        return $e->threadUuid === $thread->uuid
            && $e->broadcastOn()[0]->name === 'private-chat.thread.'.$thread->uuid
            && ! str_contains(json_encode($e->broadcastWith()), 'secret words');
    });
});

test('[P6-CHAT] the staff inbox is for support staff only and filters', function () {
    Notification::fake();
    [$client, $team] = p6Client();
    $chat = app(ChatService::class);
    $a = $chat->open($client, $team, 'Open one', 'hi');
    $b = $chat->open($client, $team, 'Closed one', 'hi');
    $chat->close($b, p6Staff('owner'));

    $this->actingAs($client)->get('/admin/support')->assertForbidden();

    $staff = p6Staff('admin');
    Livewire::actingAs($staff)->test(StaffInbox::class)->assertSee('Open one')->assertDontSee('Closed one')
        ->set('filter', 'closed')->assertSee('Closed one')->assertDontSee('Open one');
    $this->actingAs($staff)->get('/admin/support')->assertOk();
});

test('[P6-CHAT] customers see only their own team\'s conversations in their list', function () {
    [$a, $teamA] = p6Client();
    [$b, $teamB] = p6Client();
    app(ChatService::class)->open($a, $teamA, 'Alpha problem', 'x');
    app(ChatService::class)->open($b, $teamB, 'Beta problem', 'x');
    session(['currentTeam' => $teamA]);

    Livewire::actingAs($a)->test(ClientIndex::class)->assertSee('Alpha problem')->assertDontSee('Beta problem');
});
