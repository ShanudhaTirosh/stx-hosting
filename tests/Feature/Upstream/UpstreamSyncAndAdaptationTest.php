<?php

namespace Tests\Feature\Upstream;

use App\Enums\AccessLevel;
use App\Enums\CancelPreviousDeployments;
use App\Http\Middleware\ApiAbility;
use App\Models\Application;
use App\Models\ApplicationSetting;
use App\Models\ImageScan;
use App\Models\ImageScanPolicy;
use App\Models\InstanceSettings;
use App\Models\PersonalAccessToken;
use App\Models\S3Storage;
use App\Models\Server;
use App\Models\ServerSetting;
use App\Models\StandaloneRedis;
use App\Models\Star;
use App\Models\Team;
use App\Models\TemplatePermission;
use App\Models\User;
use App\Services\Commerce\HostingCatalogSeederService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class UpstreamSyncAndAdaptationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        InstanceSettings::create(['id' => 0]);
    }

    public function test_instance_sentinel_id_zero_is_preserved(): void
    {
        $settings = InstanceSettings::find(0);
        $this->assertNotNull($settings);
        $this->assertEquals(0, $settings->id);

        $team = Team::create(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
        $this->assertEquals(0, $team->id);

        $server = Server::create([
            'id' => 0,
            'name' => 'localhost',
            'ip' => 'host.docker.internal',
            'team_id' => 0,
        ]);
        $this->assertEquals(0, $server->id);
    }

    public function test_image_scanning_models_and_settings_are_available(): void
    {
        $server = Server::create([
            'name' => 'Test Server',
            'ip' => '1.2.3.4',
            'team_id' => 0,
        ]);

        $settings = $server->settings;
        $this->assertFalse($settings->is_image_scan_enabled);
        $this->assertEquals(ServerSetting::DEFAULT_IMAGE_SCAN_MEMORY_MB, $settings->image_scan_memory_mb);
        $this->assertEquals(ServerSetting::DEFAULT_IMAGE_SCAN_TIMEOUT_SECONDS, $settings->image_scan_syft_timeout);
        $this->assertEquals(ServerSetting::DEFAULT_IMAGE_SCAN_CPUS, $settings->image_scan_cpus);

        $policy = ImageScanPolicy::create([
            'uuid' => 'test-policy-uuid',
            'gate_mode' => 'off',
            'on_scan_error' => 'deploy',
            'count_medium' => false,
        ]);
        $this->assertDatabaseHas('image_scan_policies', ['uuid' => 'test-policy-uuid']);
    }

    public function test_granular_rbac_access_level_and_delete_permissions(): void
    {
        $this->assertEquals('delete', AccessLevel::Delete->value);
        $this->assertEquals('write', AccessLevel::Write->value);

        // Delete includes Read
        $deleteIncludes = array_map(fn ($l) => $l->value, AccessLevel::Delete->includes());
        $this->assertContains('delete', $deleteIncludes);
        $this->assertContains('read', $deleteIncludes);

        // Write includes Delete, ReadSensitive, Deploy, and Read
        $writeIncludes = array_map(fn ($l) => $l->value, AccessLevel::Write->includes());
        $this->assertContains('write', $writeIncludes);
        $this->assertContains('delete', $writeIncludes);
        $this->assertContains('read:sensitive', $writeIncludes);

        // ApiAbility scoped routes
        $this->assertContains('applications', ApiAbility::SCOPED_ACCESS_ROUTES);
        $this->assertContains('services', ApiAbility::SCOPED_ACCESS_ROUTES);
        $this->assertContains('databases', ApiAbility::SCOPED_ACCESS_ROUTES);
        $this->assertContains('vulnerabilities', ApiAbility::SCOPED_ACCESS_ROUTES);
    }

    public function test_token_supports_scopes_and_pausing(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token', ['read', 'delete']);

        /** @var PersonalAccessToken $accessToken */
        $accessToken = $token->accessToken;
        $this->assertNull($accessToken->paused_at);
        $this->assertFalse($accessToken->isPaused());

        $accessToken->pause();
        $this->assertTrue($accessToken->isPaused());
        $this->assertNotNull($accessToken->paused_at);

        $accessToken->resume();
        $this->assertFalse($accessToken->isPaused());
    }

    public function test_deployments_support_auto_cancel_and_git_https_credentials(): void
    {
        $app = new Application();
        $app->git_https_username = 'gituser';
        $app->git_https_password = 'supersecrettoken';
        
        $this->assertEquals('gituser', $app->git_https_username);
        // git_https_password is encrypted and hidden from serialization
        $this->assertContains('git_https_password', $app->getHidden());

        $settings = new ApplicationSetting();
        $this->assertTrue($settings->is_auto_cancel_queued_deployments_enabled);
        $this->assertFalse($settings->is_auto_cancel_building_deployments_enabled);

        $this->assertEquals('queued', CancelPreviousDeployments::Queued->value);
        $this->assertEquals('building', CancelPreviousDeployments::Building->value);
    }

    public function test_multi_protocol_storage_supports_sftp_and_tls_skip(): void
    {
        $s3 = new S3Storage();
        $s3->type = 'sftp';
        $s3->sftp_host_key = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAA...';
        $s3->skip_tls_verification = true;

        $this->assertEquals('sftp', $s3->type);
        $this->assertTrue($s3->skip_tls_verification);
        $this->assertNotNull($s3->sftp_host_key);
    }

    public function test_valkey_and_database_engine_defaults(): void
    {
        $redis = new StandaloneRedis();
        // StandaloneRedis default is valkey/valkey:9-alpine
        $this->assertEquals('valkey/valkey:9-alpine', $redis->image);
    }

    public function test_curated_templates_include_orbit_and_hi_events_as_admin_curated(): void
    {
        $this->assertTrue(File::exists(base_path('templates/compose/orbit.yaml')));
        $this->assertTrue(File::exists(base_path('templates/compose/hi-events.yaml')));

        HostingCatalogSeederService::seedCuratedTemplates();

        $orbit = TemplatePermission::where('template_name', 'orbit')->first();
        $this->assertNotNull($orbit);
        $this->assertEquals('Orbit Team Workspace', $orbit->display_name);
        $this->assertFalse((bool) $orbit->is_globally_enabled); // Admin curated

        $hiEvents = TemplatePermission::where('template_name', 'hi-events')->first();
        $this->assertNotNull($hiEvents);
        $this->assertEquals('Hi.Events Platform', $hiEvents->display_name);
        $this->assertFalse((bool) $hiEvents->is_globally_enabled); // Admin curated
    }

    public function test_release_channels_and_star_favorites(): void
    {
        $settings = InstanceSettings::find(0);
        $this->assertEquals('stable', $settings->update_channel);

        $settings->switchUpdateChannel('preview');
        $this->assertEquals('preview', $settings->update_channel);

        // Stars table exists and is usable
        $user = User::factory()->create();
        $star = Star::create([
            'user_id' => $user->id,
            'resource_type' => 'App\Models\Server',
            'resource_id' => 0,
        ]);
        $this->assertDatabaseHas('stars', ['user_id' => $user->id, 'resource_id' => 0]);
    }
}

