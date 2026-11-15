<?php

declare(strict_types=1);

use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use App\Services\Supabase\SupabaseClient;
use App\Support\RootSetupWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('app.maintenance.store', 'array');
    config()->set('services.turnstile.enabled', true);
    config()->set('services.turnstile.site_key', 'test_site_key');
    config()->set('services.turnstile.secret_key', 'test_secret_key');
    config()->set('supabase.auth_enabled', true);
    config()->set('supabase.url', 'http://localhost:8000');
    config()->set('supabase.key', 'anon-test-key');
    config()->set('supabase.service_role_key', 'service-test-key');

    // Create Root Team 0
    $this->rootTeam = Team::forceCreate([
        'id' => 0,
        'name' => 'Root Team',
        'personal_team' => false,
        'show_boarding' => false,
    ]);

    // Create Instance Settings sentinel
    InstanceSettings::forceCreate([
        'id' => 0,
        'is_registration_enabled' => true,
    ]);

    Team::query()->update(['show_boarding' => false]);
    Cache::flush();
    RootSetupWindow::openIfNoUserExists();
});

test('turnstile blocks login attempt when token is missing or invalid', function () {
    $user = User::factory()->create([
        'email' => 'client@example.com',
        'password' => Hash::make('Secret123!'),
    ]);

    // 1. Missing token
    $response = $this->post('/login', [
        'email' => 'client@example.com',
        'password' => 'Secret123!',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();

    // 2. Mock fail token
    $responseFail = $this->post('/login', [
        'email' => 'client@example.com',
        'password' => 'Secret123!',
        'cf-turnstile-response' => 'mock_turnstile_fail_123',
    ]);

    $responseFail->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('turnstile allows login when token is valid and credentials match', function () {
    $user = User::factory()->create([
        'email' => 'client@example.com',
        'password' => Hash::make('Secret123!'),
    ]);

    $response = $this->post('/login', [
        'email' => 'client@example.com',
        'password' => 'Secret123!',
        'cf-turnstile-response' => 'mock_turnstile_success_123',
    ]);

    $response->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

test('supabase auth signs in and queues access token and cookie', function () {
    $user = User::factory()->create([
        'email' => 'supabase_user@example.com',
        'password' => Hash::make('Secret123!'),
        'supabase_id' => 'sb-test-uuid-456',
    ]);

    // Mock GoTrue sign in response
    Http::fake([
        'http://localhost:8000/auth/v1/token*' => Http::response([
            'access_token' => 'mock.jwt.access_token',
            'refresh_token' => 'mock_refresh_token',
            'user' => [
                'id' => 'sb-test-uuid-456',
                'email' => 'supabase_user@example.com',
                'user_metadata' => ['name' => 'Supabase User'],
            ],
        ], 200),
    ]);

    $response = $this->post('/login', [
        'email' => 'supabase_user@example.com',
        'password' => 'Secret123!',
        'cf-turnstile-response' => 'mock_turnstile_success',
    ]);

    $response->assertRedirect();
    $this->assertAuthenticatedAs($user);
    $response->assertCookie('sb_access_token');
    expect(session('supabase_access_token'))->toBe('mock.jwt.access_token');
});

test('turnstile blocks registration attempt when token is missing', function () {
    $response = $this->post('/register', [
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'password' => 'Secret123!Pass',
        'password_confirmation' => 'Secret123!Pass',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('turnstile allows registration with valid token and creates user', function () {
    Http::fake([
        'http://localhost:8000/auth/v1/signup' => Http::response([
            'user' => [
                'id' => 'sb-new-uuid-789',
                'email' => 'newuser@example.com',
            ],
        ], 200),
    ]);

    $response = $this->post('/register', [
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'password' => 'Secret123!Pass',
        'password_confirmation' => 'Secret123!Pass',
        'cf-turnstile-response' => 'mock_turnstile_success',
    ]);

    $response->assertRedirect();
    $this->assertAuthenticated();

    $newUser = User::where('email', 'newuser@example.com')->first();
    expect($newUser)->not->toBeNull();
    expect($newUser->supabase_id)->toBe('sb-new-uuid-789');
});

test('forgot password requires valid turnstile token', function () {
    // Missing turnstile
    $response = $this->post('/forgot-password', [
        'email' => 'client@example.com',
    ]);

    $response->assertSessionHasErrors('email');
});

test('logout revokes supabase session and clears cookie', function () {
    $user = User::factory()->create([
        'email' => 'logout_user@example.com',
    ]);

    $this->actingAs($user);
    session(['supabase_access_token' => 'active_jwt_token']);

    Http::fake([
        'http://localhost:8000/auth/v1/logout' => Http::response([], 200),
    ]);

    $response = $this->post('/logout');

    $response->assertRedirect();
    $this->assertGuest();
    expect(session('supabase_access_token'))->toBeNull();
});

test('legacy oauth routes are completely removed and inactive', function () {
    expect(Route::has('auth.redirect'))->toBeFalse();
    expect(Route::has('auth.callback'))->toBeFalse();
    expect(Route::has('settings.oauth'))->toBeFalse();

    // Since the platform uses a catch-all route redirecting unauthenticated requests to login,
    // requests to former OAuth endpoints do not execute any OAuth handler and redirect to login
    $responseRedirect = $this->get('/auth/google/redirect');
    $responseRedirect->assertRedirect('/login');

    $responseCallback = $this->get('/auth/google/callback');
    $responseCallback->assertRedirect('/login');

    $responseSettings = $this->get('/settings/oauth');
    $responseSettings->assertRedirect('/login');
});

test('supabase client forUser scopes requests with bearer token', function () {
    Http::fake([
        'http://localhost:8000/rest/v1/profiles*' => Http::response([['id' => '123']], 200),
    ]);

    $client = app(SupabaseClient::class);
    $userScopedClient = $client->forUser('custom_user_jwt_token_abc');

    $response = $userScopedClient->request('GET', 'profiles');

    expect($response)->not->toBeNull();
    expect($response->status())->toBe(200);

    Http::assertSent(function (Request $request) {
        return $request->hasHeader('Authorization', 'Bearer custom_user_jwt_token_abc');
    });
});
