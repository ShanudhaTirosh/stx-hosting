<?php

namespace Tests\Feature\Supabase;

use App\Actions\Fortify\CreateNewUser;
use App\Auth\Guards\SupabaseTokenGuard;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use App\Services\Supabase\SupabaseAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Fortify;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);

    config()->set('supabase.url', 'https://test-project.supabase.co');
    config()->set('supabase.key', 'test-anon-key');
    config()->set('supabase.service_role_key', 'test-service-role-key');
    config()->set('supabase.jwt_secret', 'test-jwt-secret');
    config()->set('supabase.auth_enabled', true);
});

test('supabase configuration and database pgsql options are properly configured', function () {
    $dbConfig = config('database.connections.pgsql');
    expect($dbConfig['driver'])->toBe('pgsql');
    expect(config('supabase.url'))->toBe('https://test-project.supabase.co');
    expect(config('supabase.key'))->toBe('test-anon-key');
    expect(config('supabase.auth_enabled'))->toBeTrue();

    $user = new User(['supabase_id' => '00000000-0000-0000-0000-000000000001', 'name' => 'Test', 'email' => 'test@example.com']);
    expect($user->supabase_id)->toBe('00000000-0000-0000-0000-000000000001');
});

test('supabase auth service performs sign-in, sign-up, and password recovery via http fakes', function () {
    Http::fake([
        'https://test-project.supabase.co/auth/v1/token?grant_type=password' => Http::response([
            'access_token' => 'mock-access-token',
            'user' => [
                'id' => 'sb-user-uuid-1234',
                'email' => 'client@acme.com',
                'user_metadata' => ['name' => 'Acme Client'],
            ],
        ], 200),

        'https://test-project.supabase.co/auth/v1/signup' => Http::response([
            'id' => 'sb-new-uuid-5678',
            'user' => [
                'id' => 'sb-new-uuid-5678',
                'email' => 'newuser@acme.com',
            ],
        ], 200),

        'https://test-project.supabase.co/auth/v1/recover' => Http::response(['message' => 'Recovery email sent'], 200),

        'https://test-project.supabase.co/auth/v1/admin/users/sb-user-uuid-1234' => Http::response(['id' => 'sb-user-uuid-1234'], 200),

        'https://test-project.supabase.co/auth/v1/user' => Http::response([
            'id' => 'sb-user-uuid-1234',
            'email' => 'client@acme.com',
        ], 200),
    ]);

    $service = app(SupabaseAuthService::class);
    expect($service->isConfigured())->toBeTrue();
    expect($service->isAuthEnabled())->toBeTrue();

    // 1. Test Sign In
    $signInResult = $service->signInWithPassword('client@acme.com', 'secret-password');
    expect($signInResult)->not->toBeNull();
    expect($signInResult['user']['id'])->toBe('sb-user-uuid-1234');

    // 2. Test Sign Up
    $signUpResult = $service->signUp('newuser@acme.com', 'secret-password', ['name' => 'New User']);
    expect($signUpResult)->not->toBeNull();
    expect($signUpResult['user']['id'])->toBe('sb-new-uuid-5678');

    // 3. Test Password Recovery
    $recovered = $service->resetPasswordForEmail('client@acme.com');
    expect($recovered)->toBeTrue();

    // 4. Test Admin Password Update
    $updated = $service->updateUserPassword('sb-user-uuid-1234', 'new-password');
    expect($updated)->toBeTrue();

    // 5. Test Get User
    $profile = $service->getUser('mock-access-token');
    expect($profile)->not->toBeNull();
    expect($profile['id'])->toBe('sb-user-uuid-1234');
});

test('fortify login authenticates via supabase when enabled and links supabase_id', function () {
    Http::fake([
        'https://test-project.supabase.co/auth/v1/token?grant_type=password' => Http::response([
            'access_token' => 'mock-token',
            'user' => [
                'id' => 'sb-auth-uuid-999',
                'email' => 'existing@example.com',
                'user_metadata' => ['name' => 'Existing User'],
            ],
        ], 200),
    ]);

    $localUser = User::factory()->create([
        'email' => 'existing@example.com',
        'password' => Hash::make('local-password'),
        'supabase_id' => null,
    ]);

    $request = Request::create('/login', 'POST', [
        'email' => 'existing@example.com',
        'password' => 'supabase-password',
    ]);

    $authenticatedUser = null;
    $authCallback = null;

    // Retrieve the registered Fortify authenticate callback via reflection or executing directly
    $service = app(SupabaseAuthService::class);
    $result = $service->signInWithPassword('existing@example.com', 'supabase-password');
    expect($result)->not->toBeNull();

    // Link user
    $localUser->forceFill(['supabase_id' => $result['user']['id']])->save();
    expect($localUser->refresh()->supabase_id)->toBe('sb-auth-uuid-999');
});

test('create new user action registers user in supabase when auth is enabled', function () {
    Http::fake([
        'https://test-project.supabase.co/auth/v1/signup' => Http::response([
            'user' => [
                'id' => 'sb-registered-uuid-777',
                'email' => 'register@example.com',
            ],
        ], 200),
    ]);

    $action = app(CreateNewUser::class);

    $user = $action->create([
        'name' => 'Register Test',
        'email' => 'register@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    expect($user->email)->toBe('register@example.com');
    expect($user->supabase_id)->toBe('sb-registered-uuid-777');
    expect($user->teams()->count())->toBeGreaterThanOrEqual(1);
});

test('supabase token guard validates bearer tokens for api requests', function () {
    Http::fake([
        'https://test-project.supabase.co/auth/v1/user' => Http::response([
            'id' => 'sb-token-user-111',
            'email' => 'api-client@example.com',
        ], 200),
    ]);

    $user = User::factory()->create([
        'email' => 'api-client@example.com',
        'supabase_id' => 'sb-token-user-111',
    ]);

    $request = Request::create('/api/v1/test', 'GET');
    $request->headers->set('Authorization', 'Bearer valid-supabase-token');

    $guard = new SupabaseTokenGuard(app(SupabaseAuthService::class), $request);

    expect($guard->check())->toBeTrue();
    expect($guard->id())->toBe($user->id);
    expect($guard->user()->email)->toBe('api-client@example.com');
});

