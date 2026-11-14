<?php

namespace Tests\Feature\Supabase;

use App\Models\InstanceSettings;
use App\Services\Supabase\SupabaseAuthService;
use App\Services\Supabase\SupabaseClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
});

test('supabase client initializes with configuration and detects configuration status', function () {
    config()->set('supabase.url', 'https://example-test.supabase.co');
    config()->set('supabase.key', 'anon-test-key');
    config()->set('supabase.service_role_key', 'service-test-key');

    $client = new SupabaseClient;

    expect($client->isConfigured())->toBeTrue();
});

test('supabase client builds fluent query parameters correctly', function () {
    config()->set('supabase.url', 'https://example-test.supabase.co');
    config()->set('supabase.key', 'anon-test-key');
    config()->set('supabase.service_role_key', 'service-test-key');

    Http::fake([
        'https://example-test.supabase.co/rest/v1/template_permissions*' => Http::response([
            ['id' => '1', 'template_name' => 'wordpress', 'is_globally_enabled' => true],
        ], 200),
    ]);

    $client = new SupabaseClient;
    $results = $client->from('template_permissions')
        ->select('id,template_name,is_globally_enabled')
        ->eq('is_globally_enabled', true)
        ->order('template_name', 'asc')
        ->get();

    expect($results)->toBeArray()
        ->and($results)->toHaveCount(1)
        ->and($results[0]['template_name'])->toBe('wordpress');
});

test('supabase client invokes edge functions with proper authorization headers', function () {
    config()->set('supabase.url', 'https://example-test.supabase.co');
    config()->set('supabase.service_role_key', 'service-test-key');

    Http::fake([
        'https://example-test.supabase.co/functions/v1/deploy-trigger' => Http::response([
            'success' => true,
            'deployment_uuid' => 'test-deploy-uuid',
            'status' => 'queued',
        ], 201),
    ]);

    $client = new SupabaseClient;
    $response = $client->invokeFunction('deploy-trigger', [
        'application_name' => 'my-nextjs-app',
        'template_name' => 'wordpress',
    ]);

    expect($response)->toBeArray()
        ->and($response['success'])->toBeTrue()
        ->and($response['deployment_uuid'])->toBe('test-deploy-uuid');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://example-test.supabase.co/functions/v1/deploy-trigger' &&
            $request->hasHeader('Authorization', 'Bearer service-test-key');
    });
});

test('supabase storage uploads file with correct mime type and headers', function () {
    config()->set('supabase.url', 'https://example-test.supabase.co');
    config()->set('supabase.service_role_key', 'service-test-key');

    Http::fake([
        'https://example-test.supabase.co/storage/v1/object/user-uploads/proofs/test.pdf' => Http::response([], 200),
    ]);

    $client = new SupabaseClient;
    $uploaded = $client->uploadFile('user-uploads', 'proofs/test.pdf', '%PDF-1.4 test content', 'application/pdf');

    expect($uploaded)->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://example-test.supabase.co/storage/v1/object/user-uploads/proofs/test.pdf' &&
            $request->hasHeader('x-upsert', 'true') &&
            $request->hasHeader('Content-Type', 'application/pdf');
    });
});

test('supabase realtime broadcasts messages to expected endpoints', function () {
    config()->set('supabase.url', 'https://example-test.supabase.co');
    config()->set('supabase.service_role_key', 'service-test-key');

    Http::fake([
        'https://example-test.supabase.co/realtime/v1/api/broadcast' => Http::response([], 200),
    ]);

    $client = new SupabaseClient;
    $broadcasted = $client->broadcastRealtime('public:status', 'monitor_status_changed', [
        'name' => 'API Gateway',
        'status' => 'operational',
    ]);

    expect($broadcasted)->toBeTrue();
});

test('supabase migration files exist and cover all schema domains and RLS policies', function () {
    $migrationsPath = base_path('supabase/migrations');
    expect(is_dir($migrationsPath))->toBeTrue();

    $files = scandir($migrationsPath);
    $sqlFiles = array_filter($files, fn ($f) => str_ends_with($f, '.sql'));

    expect(count($sqlFiles))->toBeGreaterThanOrEqual(9);

    $allContent = '';
    foreach ($sqlFiles as $file) {
        $allContent .= file_get_contents($migrationsPath.'/'.$file)."\n";
    }

    // Verify key tables created
    expect($allContent)->toContain('CREATE TABLE IF NOT EXISTS public.profiles')
        ->and($allContent)->toContain('CREATE TABLE IF NOT EXISTS public.teams')
        ->and($allContent)->toContain('CREATE TABLE IF NOT EXISTS public.template_permissions')
        ->and($allContent)->toContain('CREATE TABLE IF NOT EXISTS public.orders')
        ->and($allContent)->toContain('CREATE TABLE IF NOT EXISTS public.invoices')
        ->and($allContent)->toContain('CREATE TABLE IF NOT EXISTS public.deployments')
        ->and($allContent)->toContain('CREATE TABLE IF NOT EXISTS public.custom_domains')
        ->and($allContent)->toContain('CREATE TABLE IF NOT EXISTS public.monitors')
        ->and($allContent)->toContain('CREATE TABLE IF NOT EXISTS public.system_incidents')
        ->and($allContent)->toContain('CREATE TABLE IF NOT EXISTS public.audit_logs');

    // Verify Row Level Security enabled on all tables
    expect($allContent)->toContain('ALTER TABLE public.profiles ENABLE ROW LEVEL SECURITY;')
        ->and($allContent)->toContain('ALTER TABLE public.deployments ENABLE ROW LEVEL SECURITY;')
        ->and($allContent)->toContain('ALTER TABLE public.custom_domains ENABLE ROW LEVEL SECURITY;')
        ->and($allContent)->toContain('ALTER TABLE public.orders ENABLE ROW LEVEL SECURITY;')
        ->and($allContent)->toContain('ALTER TABLE public.invoices ENABLE ROW LEVEL SECURITY;')
        ->and($allContent)->toContain('ALTER TABLE public.monitors ENABLE ROW LEVEL SECURITY;');

    // Verify storage buckets configuration
    expect($allContent)->toContain("INSERT INTO storage.buckets (id, name, public, file_size_limit, allowed_mime_types)")
        ->and($allContent)->toContain("'logos'")
        ->and($allContent)->toContain("'template-assets'")
        ->and($allContent)->toContain("'invoices'")
        ->and($allContent)->toContain("'user-uploads'");
});

test('supabase edge functions are present with required entrypoints', function () {
    $functionsPath = base_path('supabase/functions');
    expect(is_dir($functionsPath))->toBeTrue();

    $expectedFunctions = [
        'deploy-trigger',
        'verify-domain-dns',
        'check-ssl',
        'stripe-webhook',
        'send-email',
        'health-probe',
    ];

    foreach ($expectedFunctions as $fn) {
        $entrypoint = $functionsPath.'/'.$fn.'/index.ts';
        expect(file_exists($entrypoint))->toBeTrue("Expected entrypoint for {$fn} at {$entrypoint}");

        $content = file_get_contents($entrypoint);
        expect(strlen($content))->toBeGreaterThan(100);
    }
});
