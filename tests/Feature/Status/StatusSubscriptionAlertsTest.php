<?php

namespace Tests\Feature\Status;

use App\Livewire\Status\PublicStatus;
use App\Models\Commerce\IncidentUpdate;
use App\Models\Commerce\StatusSubscription;
use App\Models\Commerce\SystemIncident;
use App\Models\InstanceSettings;
use App\Notifications\Status\StatusIncidentNotification;
use App\Notifications\Status\VerifyStatusSubscriptionNotification;
use App\Services\Status\StatusAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
});

test('visitor can request status subscription via email', function () {
    Notification::fake();

    Livewire::test(PublicStatus::class)
        ->set('subscribeEmail', 'client@example.com')
        ->call('subscribe')
        ->assertSet('subscribeMessage', 'Confirmation email sent! Please check your inbox and click the link to activate alerts.');

    $subscription = StatusSubscription::where('email', 'client@example.com')->first();
    expect($subscription)->not->toBeNull();
    expect($subscription->is_active)->toBeTrue();
    expect($subscription->isVerified())->toBeFalse();

    Notification::assertSentTo($subscription, VerifyStatusSubscriptionNotification::class);
});

test('visitor can verify status subscription with token', function () {
    $subscription = StatusSubscription::create([
        'email' => 'client2@example.com',
        'verification_token' => 'test-token-12345',
        'unsubscribe_token' => 'test-unsub-12345',
        'is_active' => true,
    ]);

    $response = $this->get('/status/verify/test-token-12345');
    $response->assertRedirect('/status');
    $response->assertSessionHas('success');

    expect($subscription->fresh()->isVerified())->toBeTrue();
});

test('status alert service dispatches notifications to verified active subscribers', function () {
    Notification::fake();

    $activeSub = StatusSubscription::create([
        'email' => 'subscriber@example.com',
        'verified_at' => now(),
        'unsubscribe_token' => 'unsub-token-abc',
        'is_active' => true,
    ]);

    $unverifiedSub = StatusSubscription::create([
        'email' => 'unverified@example.com',
        'verification_token' => 'pending-token',
        'unsubscribe_token' => 'unsub-token-xyz',
        'is_active' => true,
    ]);

    $incident = SystemIncident::create([
        'uuid' => 'incident-uuid-001',
        'title' => 'Core Network Latency',
        'status' => 'investigating',
        'severity' => 'major',
        'message' => 'Investigating elevated packet loss on Falkenstein uplink.',
    ]);

    $alertService = app(StatusAlertService::class);
    $alertService->dispatchIncidentOpened($incident);

    Notification::assertSentTo($activeSub, StatusIncidentNotification::class);
    Notification::assertNotSentTo($unverifiedSub, StatusIncidentNotification::class);
});

test('subscriber can unsubscribe via token', function () {
    $subscription = StatusSubscription::create([
        'email' => 'subscriber@example.com',
        'verified_at' => now(),
        'unsubscribe_token' => 'unsub-token-def',
        'is_active' => true,
    ]);

    $response = $this->get('/status/unsubscribe/unsub-token-def');
    $response->assertRedirect('/status');
    $response->assertSessionHas('success');

    expect($subscription->fresh()->is_active)->toBeFalse();
});

