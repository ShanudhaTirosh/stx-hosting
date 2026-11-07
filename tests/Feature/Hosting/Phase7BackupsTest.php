<?php

/*
|--------------------------------------------------------------------------
| Phase 7: backups on the platform's R2 storage
|--------------------------------------------------------------------------
| Coolify's own backup, upload and import code needs real servers, so the pieces that talk to a server are
| replaced by fakes here. Everything around them is tested: storage rules, schedule and retention, quota,
| authorization, restore order (safety backup first), download links and the staff view.
*/

use App\Actions\Database\StartDatabaseImport;
use App\Exceptions\PlatformRestrictionException;
use App\Jobs\DatabaseBackupJob;
use App\Jobs\RestoreDatabaseBackupJob;
use App\Jobs\SendMessageToDiscordJob;
use App\Livewire\Admin\Backups as AdminBackups;
use App\Livewire\Portal\Backups as PortalBackups;
use App\Models\BackupRestore;
use App\Models\Commerce\CommerceAuditLog;
use App\Models\Environment;
use App\Models\InstanceSettings;
use App\Models\NotificationRoute;
use App\Models\PlatformSetting;
use App\Models\Project;
use App\Models\S3Storage;
use App\Models\ScheduledDatabaseBackup;
use App\Models\ScheduledDatabaseBackupExecution;
use App\Models\Server;
use App\Models\StandaloneDocker;
use App\Models\StandalonePostgresql;
use App\Models\Team;
use App\Models\User;
use App\Services\Backups\BackupPolicyService;
use App\Services\Backups\BackupQuota;
use App\Services\Backups\BackupRunner;
use App\Services\Backups\DatabaseRestorer;
use App\Services\Backups\PlatformBackupStorage;
use App\Services\Notifications\StaffEvents;
use App\Support\DatabaseImport\DatabaseImportException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['hosting.enforce_entitlements' => false, 'backups.default_quota_gb' => 5, 'backups.storage_uuid' => null]);
    InstanceSettings::forceCreate(['id' => 0]);
    Team::find(0) ?: Team::forceCreate(['id' => 0, 'name' => 'Root Team', 'personal_team' => false]);
    Server::forceCreate(['id' => 0, 'name' => 'localhost', 'ip' => '127.0.0.1', 'team_id' => 0]);
    $this->destination = StandaloneDocker::where('server_id', 0)->first()
        ?? StandaloneDocker::create(['name' => 'default-docker', 'network' => 'net-'.Str::random(6), 'server_id' => 0]);
    PlatformBackupStorage::$presigner = null;
    PlatformBackupStorage::$connectionTester = null;
    Cache::flush();
});

function p7Staff(string $role = 'admin'): User
{
    $u = User::factory()->create();
    Team::find(0)->members()->attach($u->id, ['role' => $role]);

    return $u->fresh();
}

function p7Client(): array
{
    $u = User::factory()->create();
    $t = Team::factory()->create();
    $t->members()->attach($u->id, ['role' => 'owner']);

    return [$u->fresh(), $t];
}

function p7Storage(bool $usable = true, int $team = 0, string $name = 'Platform R2'): S3Storage
{
    $s = S3Storage::create([
        'name' => $name, 'region' => 'auto', 'key' => 'k', 'secret' => 's', 'bucket' => 'platform-bucket-secret-name',
        'endpoint' => 'https://acct.r2.cloudflarestorage.com', 'team_id' => $team,
    ]);
    $s->forceFill(['is_usable' => $usable])->save();

    return $s;
}

function p7Database(Team $team, string $status = 'running'): StandalonePostgresql
{
    $project = Project::create(['name' => 'p-'.Str::random(5), 'team_id' => $team->id]);
    $env = $project->environments()->first() ?? Environment::create(['name' => 'production', 'project_id' => $project->id]);

    return StandalonePostgresql::withoutEvents(fn () => StandalonePostgresql::factory()->create([
        'environment_id' => $env->id, 'destination_id' => test()->destination->id,
        'destination_type' => StandaloneDocker::class, 'status' => $status,
    ]));
}

function p7Execution(ScheduledDatabaseBackup $backup, string $status = 'success', bool $uploaded = true, int $size = 1000, ?string $file = null): ScheduledDatabaseBackupExecution
{
    return ScheduledDatabaseBackupExecution::create([
        'uuid' => (string) Str::uuid(), 'scheduled_database_backup_id' => $backup->id, 'status' => $status,
        'filename' => $file ?? '/data/coolify/backups/databases/team-1/db/pg-dump-'.Str::random(6).'.dmp',
        'size' => $size, 's3_uploaded' => $uploaded, 'database_name' => 'postgres',
    ]);
}

/** A backup configured the way the service configures it, for a team's database. */
function p7Enabled(Team $team, User $user, ?StandalonePostgresql $db = null): array
{
    $db ??= p7Database($team);
    $backup = app(BackupPolicyService::class)->enable($db, 'daily', 7, $user);

    return [$db, $backup];
}

/* ---------- the platform storage ---------- */

test('[P7-STORAGE] only a usable storage of the platform team can hold client backups', function () {
    [, $clientTeam] = p7Client();
    $theirs = p7Storage(true, $clientTeam->id, 'Client own');
    $unusable = p7Storage(false, 0, 'Broken');

    expect(PlatformBackupStorage::candidates()->pluck('name')->all())->toBe(['Broken'])->not->toContain('Client own');
    expect(fn () => PlatformBackupStorage::choose($theirs))->toThrow(InvalidArgumentException::class);

    PlatformBackupStorage::choose($unusable);
    expect(PlatformBackupStorage::storage())->toBeNull(); // chosen but not working: not used

    $good = p7Storage(true, 0, 'Platform R2');
    PlatformBackupStorage::choose($good);
    expect(PlatformBackupStorage::storage()->id)->toBe($good->id);
});

test('[P7-STORAGE] a pre-set storage from the environment works, and the owner\'s choice wins', function () {
    $a = p7Storage(true, 0, 'A');
    $b = p7Storage(true, 0, 'B');
    config(['backups.storage_uuid' => $a->uuid]);
    expect(PlatformBackupStorage::storage()->id)->toBe($a->id);

    PlatformBackupStorage::choose($b);
    expect(PlatformBackupStorage::storage()->id)->toBe($b->id);
});

/* ---------- turning backups on ---------- */

test('[P7-ENABLE] backups go to the platform storage with the chosen schedule and retention', function () {
    $storage = p7Storage();
    PlatformBackupStorage::choose($storage);
    [$user, $team] = p7Client();
    $db = p7Database($team);

    $backup = app(BackupPolicyService::class)->enable($db, 'weekly', 14, $user);

    expect($backup->team_id)->toBe($team->id);
    expect($backup->frequency)->toBe('0 3 * * 0');
    expect($backup->enabled)->toBeTrue();
    expect((bool) $backup->save_s3)->toBeTrue();
    expect((int) $backup->s3_storage_id)->toBe($storage->id);
    expect((bool) $backup->disable_local_backup)->toBeTrue();
    expect((int) $backup->database_backup_retention_amount_s3)->toBe(14);
    expect((int) $backup->database_backup_retention_amount_locally)->toBe(1);
    expect($backup->databases_to_backup)->toBe($db->postgres_db);
});

test('[P7-ENABLE] asking again updates the same schedule and the number to keep is bounded', function () {
    PlatformBackupStorage::choose(p7Storage());
    [$user, $team] = p7Client();
    $db = p7Database($team);
    $svc = app(BackupPolicyService::class);

    $first = $svc->enable($db, 'daily', 7, $user);
    $second = $svc->enable($db, 'weekly', 500, $user);

    expect($second->id)->toBe($first->id);
    expect(ScheduledDatabaseBackup::count())->toBe(1);
    expect((int) $second->database_backup_retention_amount_s3)->toBe(30);
    expect($svc->enable($db, 'daily', 0, $user)->database_backup_retention_amount_s3)->toBe(1);
});

test('[P7-ENABLE] without a platform storage, or with a made-up schedule, it is refused', function () {
    [$user, $team] = p7Client();
    $db = p7Database($team);
    $svc = app(BackupPolicyService::class);

    expect(fn () => $svc->enable($db, 'daily', 7, $user))->toThrow(PlatformRestrictionException::class, 'not available yet');

    PlatformBackupStorage::choose(p7Storage());
    expect(fn () => $svc->enable($db, '* * * * *', 7, $user))->toThrow(PlatformRestrictionException::class);
    expect(ScheduledDatabaseBackup::count())->toBe(0);
});

test('[P7-ENABLE] you cannot touch another team\'s database, but support staff can help any customer', function () {
    PlatformBackupStorage::choose(p7Storage());
    [$user, $team] = p7Client();
    [$stranger] = p7Client();
    $db = p7Database($team);
    $svc = app(BackupPolicyService::class);

    expect(fn () => $svc->enable($db, 'daily', 7, $stranger))->toThrow(HttpException::class);
    expect(ScheduledDatabaseBackup::count())->toBe(0);

    $svc->enable($db, 'daily', 7, p7Staff('admin'));
    expect(ScheduledDatabaseBackup::count())->toBe(1);
});

test('[P7-ENABLE] turning backups off keeps what was already saved', function () {
    PlatformBackupStorage::choose(p7Storage());
    [$user, $team] = p7Client();
    [$db, $backup] = p7Enabled($team, $user);
    $execution = p7Execution($backup);

    app(BackupPolicyService::class)->disable($db, $user);

    expect($backup->fresh()->enabled)->toBeFalse();
    expect(ScheduledDatabaseBackupExecution::find($execution->id))->not->toBeNull();
});

/* ---------- back up now, quota ---------- */

test('[P7-RUN] back up now queues the backup once per cooldown, only for a running database', function () {
    Bus::fake([DatabaseBackupJob::class]);
    PlatformBackupStorage::choose(p7Storage());
    [$user, $team] = p7Client();
    [$db] = p7Enabled($team, $user);
    $svc = app(BackupPolicyService::class);

    $svc->runNow($db, $user);
    Bus::assertDispatchedTimes(DatabaseBackupJob::class, 1);

    expect(fn () => $svc->runNow($db, $user))->toThrow(PlatformRestrictionException::class, 'just started');
    Bus::assertDispatchedTimes(DatabaseBackupJob::class, 1);

    $stopped = p7Database($team, 'exited');
    $svc->enable($stopped, 'daily', 7, $user);
    expect(fn () => $svc->runNow($stopped, $user))->toThrow(PlatformRestrictionException::class, 'running');
});

test('[P7-RUN] back up now needs backups to be turned on first', function () {
    PlatformBackupStorage::choose(p7Storage());
    [$user, $team] = p7Client();

    expect(fn () => app(BackupPolicyService::class)->runNow(p7Database($team), $user))->toThrow(PlatformRestrictionException::class, 'Turn backups on');
});

test('[P7-QUOTA] only a team\'s own saved backups count, and a full team cannot start another', function () {
    Bus::fake([DatabaseBackupJob::class]);
    PlatformBackupStorage::choose(p7Storage());
    [$user, $team] = p7Client();
    [$otherUser, $otherTeam] = p7Client();
    [$db, $backup] = p7Enabled($team, $user);
    [, $otherBackup] = p7Enabled($otherTeam, $otherUser);

    $gib = 1073741824;
    p7Execution($backup, 'success', true, 2 * $gib);
    p7Execution($backup, 'success', false, 100 * $gib);       // never reached the cloud: not counted
    p7Execution($backup, 'failed', false, 0);
    p7Execution($otherBackup, 'success', true, 50 * $gib);    // somebody else's
    $deleted = p7Execution($backup, 'success', true, 100 * $gib);
    $deleted->forceFill(['s3_storage_deleted' => true])->save(); // removed by retention: not counted

    expect(BackupQuota::usedGb($team))->toBe(2.0);
    expect(BackupQuota::blockReason($team))->toBeNull();

    p7Execution($backup, 'success', true, 4 * $gib);
    expect(BackupQuota::blockReason($team))->toContain('space is full');
    expect(fn () => app(BackupPolicyService::class)->runNow($db, $user))->toThrow(PlatformRestrictionException::class, 'space is full');
});

test('[P7-QUOTA] the default can be overridden per team, and the platform team is never limited', function () {
    [, $team] = p7Client();
    expect(BackupQuota::limitGb($team))->toBe(5.0);

    BackupQuota::setOverrideGb($team, 50);
    expect(BackupQuota::limitGb($team))->toBe(50.0);

    BackupQuota::setOverrideGb($team, null);
    expect(BackupQuota::limitGb($team))->toBe(5.0);
    expect(BackupQuota::blockReason(Team::find(0)))->toBeNull();
});

/* ---------- restore ---------- */

test('[P7-RESTORE] a finished cloud backup of your own running database queues a restore, once per cooldown', function () {
    Bus::fake([RestoreDatabaseBackupJob::class]);
    PlatformBackupStorage::choose(p7Storage());
    [$user, $team] = p7Client();
    [, $backup] = p7Enabled($team, $user);
    $execution = p7Execution($backup);
    $svc = app(BackupPolicyService::class);

    $restore = $svc->restore($execution, $user);

    expect($restore->status)->toBe('queued')->and($restore->team_id)->toBe($team->id)->and($restore->execution_id)->toBe($execution->id);
    Bus::assertDispatched(RestoreDatabaseBackupJob::class);
    expect(CommerceAuditLog::where('action', 'backup.restore_requested')->count())->toBe(1);

    expect(fn () => $svc->restore($execution, $user))->toThrow(PlatformRestrictionException::class, 'just requested');
});

test('[P7-RESTORE] backups that are failed, not in the cloud, in another storage, or for a stopped database cannot be restored', function () {
    Bus::fake([RestoreDatabaseBackupJob::class]);
    PlatformBackupStorage::choose(p7Storage());
    [$user, $team] = p7Client();
    [$db, $backup] = p7Enabled($team, $user);
    $svc = app(BackupPolicyService::class);

    expect(fn () => $svc->restore(p7Execution($backup, 'failed'), $user))->toThrow(PlatformRestrictionException::class);
    expect(fn () => $svc->restore(p7Execution($backup, 'success', false), $user))->toThrow(PlatformRestrictionException::class);
    expect(fn () => $svc->restore(p7Execution($backup, 'success', true, 10, ''), $user))->toThrow(PlatformRestrictionException::class);

    $other = p7Storage(true, 0, 'Some other bucket');
    $backup->forceFill(['s3_storage_id' => $other->id])->save();
    expect(fn () => $svc->restore(p7Execution($backup), $user))->toThrow(PlatformRestrictionException::class);
    $backup->forceFill(['s3_storage_id' => PlatformBackupStorage::storage()->id])->save();

    $db->update(['status' => 'exited']);
    expect(fn () => $svc->restore(p7Execution($backup), $user))->toThrow(PlatformRestrictionException::class, 'running');

    Bus::assertNotDispatched(RestoreDatabaseBackupJob::class);
    expect(BackupRestore::count())->toBe(0);
});

test('[P7-RESTORE] nobody can restore, or download, another team\'s backup', function () {
    Bus::fake([RestoreDatabaseBackupJob::class]);
    PlatformBackupStorage::choose(p7Storage());
    [$user, $team] = p7Client();
    [$stranger] = p7Client();
    [, $backup] = p7Enabled($team, $user);
    $execution = p7Execution($backup);
    $svc = app(BackupPolicyService::class);

    expect(fn () => $svc->restore($execution, $stranger))->toThrow(HttpException::class);
    expect(fn () => $svc->downloadUrl($execution, $stranger))->toThrow(HttpException::class);
    Bus::assertNotDispatched(RestoreDatabaseBackupJob::class);

    $svc->restore($execution, p7Staff('admin')); // support staff can restore for a customer
    Bus::assertDispatched(RestoreDatabaseBackupJob::class);
});

test('[P7-RESTORE] the safety backup is taken first, then Coolify\'s restore starts with the backup and the platform storage', function () {
    $storage = p7Storage();
    PlatformBackupStorage::choose($storage);
    [$user, $team] = p7Client();
    [$db, $backup] = p7Enabled($team, $user);
    $chosen = p7Execution($backup, 'success', true, 100, '/data/coolify/backups/x/chosen.dmp');
    $safety = p7Execution($backup, 'success', true, 100, '/data/coolify/backups/x/safety.dmp');
    $restore = BackupRestore::create(['team_id' => $team->id, 'user_id' => $user->id, 'execution_id' => $chosen->id, 'status' => 'queued']);

    $order = [];
    $runner = new class($safety, $order) extends BackupRunner
    {
        public function __construct(private $safety, public array &$order) {}

        public function run(ScheduledDatabaseBackup $backup): ?ScheduledDatabaseBackupExecution
        {
            $this->order[] = 'safety';

            return $this->safety;
        }
    };
    $restorer = new class($order) extends DatabaseRestorer
    {
        public array $args = [];

        public function __construct(public array &$order) {}

        public function start($database, ScheduledDatabaseBackupExecution $execution, S3Storage $storage, int $teamId): void
        {
            $this->order[] = 'restore';
            $this->args = [$database->id, $execution->id, $storage->id, $teamId];
        }
    };

    (new RestoreDatabaseBackupJob($restore))->handle($runner, $restorer);

    expect($order)->toBe(['safety', 'restore']);
    expect($restorer->args)->toBe([$db->id, $chosen->id, $storage->id, $team->id]);
    expect($restore->fresh()->status)->toBe('started')->and($restore->fresh()->safety_execution_id)->toBe($safety->id);
});

test('[P7-RESTORE] if the safety backup fails, nothing is restored and staff are told', function () {
    Bus::fake([SendMessageToDiscordJob::class]);
    NotificationRoute::create(['event' => StaffEvents::BACKUP_FAILED, 'webhook_url' => 'https://discord.com/api/webhooks/1/abc', 'is_enabled' => true]);
    PlatformBackupStorage::choose(p7Storage());
    [$user, $team] = p7Client();
    [, $backup] = p7Enabled($team, $user);
    $chosen = p7Execution($backup);
    $restore = BackupRestore::create(['team_id' => $team->id, 'user_id' => $user->id, 'execution_id' => $chosen->id, 'status' => 'queued']);

    $runner = new class extends BackupRunner
    {
        public function run(ScheduledDatabaseBackup $backup): ?ScheduledDatabaseBackupExecution
        {
            return null;
        }
    };
    $restorer = new class extends DatabaseRestorer
    {
        public bool $called = false;

        public function start($database, ScheduledDatabaseBackupExecution $execution, S3Storage $storage, int $teamId): void
        {
            $this->called = true;
        }
    };

    (new RestoreDatabaseBackupJob($restore))->handle($runner, $restorer);

    expect($restorer->called)->toBeFalse();
    expect($restore->fresh()->status)->toBe('failed')->and($restore->fresh()->message)->toContain('Nothing was changed');
    Bus::assertDispatched(SendMessageToDiscordJob::class);
});

test('[P7-RESTORE] if Coolify refuses to start the restore, the customer is told and the safety backup is kept', function () {
    PlatformBackupStorage::choose(p7Storage());
    [$user, $team] = p7Client();
    [, $backup] = p7Enabled($team, $user);
    $chosen = p7Execution($backup);
    $safety = p7Execution($backup);
    $restore = BackupRestore::create(['team_id' => $team->id, 'user_id' => $user->id, 'execution_id' => $chosen->id, 'status' => 'queued']);

    $runner = new class($safety) extends BackupRunner
    {
        public function __construct(private $safety) {}

        public function run(ScheduledDatabaseBackup $backup): ?ScheduledDatabaseBackupExecution
        {
            return $this->safety;
        }
    };
    $restorer = new class extends DatabaseRestorer
    {
        public function start($database, ScheduledDatabaseBackupExecution $execution, S3Storage $storage, int $teamId): void
        {
            throw new DatabaseImportException('The database is busy.');
        }
    };

    (new RestoreDatabaseBackupJob($restore))->handle($runner, $restorer);

    expect($restore->fresh()->status)->toBe('failed');
    expect($restore->fresh()->message)->toContain('The database is busy.')->toContain('safety backup is kept');
    expect(ScheduledDatabaseBackupExecution::find($safety->id))->not->toBeNull();
});

test('[P7-RESTORE] Coolify\'s import accepts a storage that belongs to another team (the platform)', function () {
    $params = collect((new ReflectionMethod(StartDatabaseImport::class, 'handle'))->getParameters())->map->getName()->all();

    expect($params)->toBe(['resource', 'source', 'teamId', 'storageTeamId']);
});

/* ---------- downloads ---------- */

test('[P7-DOWNLOAD] you get a short-lived link for your own finished backup, and it is logged', function () {
    $storage = p7Storage();
    PlatformBackupStorage::choose($storage);
    [$user, $team] = p7Client();
    [, $backup] = p7Enabled($team, $user);
    $execution = p7Execution($backup, 'success', true, 10, '/data/coolify/backups/databases/t/db/dump-1.dmp');

    $seen = [];
    PlatformBackupStorage::$presigner = function (S3Storage $s, string $key, int $minutes) use (&$seen) {
        $seen = [$s->id, $key, $minutes];

        return 'https://signed.example/'.$key.'?expires='.$minutes;
    };

    $url = app(BackupPolicyService::class)->downloadUrl($execution, $user);

    expect($url)->toContain('signed.example');
    expect($seen)->toBe([$storage->id, 'data/coolify/backups/databases/t/db/dump-1.dmp', 10]);
    expect(CommerceAuditLog::where('action', 'backup.download_link')->count())->toBe(1);

    $failed = p7Execution($backup, 'failed', false);
    expect(fn () => app(BackupPolicyService::class)->downloadUrl($failed, $user))->toThrow(PlatformRestrictionException::class);
});

/* ---------- the customer page ---------- */

test('[P7-PAGE] a customer sees their own databases and backups, never another team\'s, and never the storage details', function () {
    PlatformBackupStorage::choose(p7Storage());
    [$user, $team] = p7Client();
    [$other, $otherTeam] = p7Client();
    $mine = p7Database($team);
    $theirs = p7Database($otherTeam);
    $mine->update(['name' => 'my-shop-db']);
    $theirs->update(['name' => 'their-secret-db']);
    session(['currentTeam' => $team]);

    Livewire::actingAs($user)->test(PortalBackups::class)
        ->assertSee('my-shop-db')
        ->assertDontSee('their-secret-db')
        ->assertDontSee('platform-bucket-secret-name')
        ->assertDontSee('r2.cloudflarestorage.com');
});

test('[P7-PAGE] the page explains when backups are not switched on for the platform', function () {
    [$user, $team] = p7Client();
    p7Database($team);
    session(['currentTeam' => $team]);

    Livewire::actingAs($user)->test(PortalBackups::class)
        ->assertSee('not switched on for the platform')
        ->call('enable', p7Database($team)->uuid)
        ->assertSet('error', 'Backups are not available yet. Please contact support.');
});

test('[P7-PAGE] a customer turns backups on from the page', function () {
    PlatformBackupStorage::choose(p7Storage());
    [$user, $team] = p7Client();
    $db = p7Database($team);
    session(['currentTeam' => $team]);

    Livewire::actingAs($user)->test(PortalBackups::class)
        ->set("settings.{$db->uuid}.preset", 'weekly')
        ->set("settings.{$db->uuid}.keep", 14)
        ->call('enable', $db->uuid)
        ->assertSet('error', null);

    $config = ScheduledDatabaseBackup::firstOrFail();
    expect($config->frequency)->toBe('0 3 * * 0')->and((int) $config->database_backup_retention_amount_s3)->toBe(14);
});

test('[P7-PAGE] restoring needs the word RESTORE; a backup of another team is a 404', function () {
    Bus::fake([RestoreDatabaseBackupJob::class]);
    PlatformBackupStorage::choose(p7Storage());
    [$user, $team] = p7Client();
    [$other, $otherTeam] = p7Client();
    [, $mine] = p7Enabled($team, $user);
    [, $theirs] = p7Enabled($otherTeam, $other);
    $myExec = p7Execution($mine);
    $theirExec = p7Execution($theirs);
    session(['currentTeam' => $team]);

    $page = Livewire::actingAs($user)->test(PortalBackups::class);

    $page->set("confirm.{$myExec->uuid}", 'yes')->call('restore', $myExec->uuid)->assertHasErrors("confirm.{$myExec->uuid}");
    Bus::assertNotDispatched(RestoreDatabaseBackupJob::class);

    $page->set("confirm.{$myExec->uuid}", 'restore')->call('restore', $myExec->uuid);
    Bus::assertDispatched(RestoreDatabaseBackupJob::class);

    $page->set("confirm.{$theirExec->uuid}", 'RESTORE')->call('restore', $theirExec->uuid)->assertNotFound();
});

test('[P7-PAGE] download sends the customer to a short-lived link', function () {
    PlatformBackupStorage::choose(p7Storage());
    PlatformBackupStorage::$presigner = fn ($s, $key, $minutes) => 'https://signed.example/'.$key;
    [$user, $team] = p7Client();
    [, $backup] = p7Enabled($team, $user);
    $execution = p7Execution($backup, 'success', true, 10, '/f/dump.dmp');
    session(['currentTeam' => $team]);

    Livewire::actingAs($user)->test(PortalBackups::class)
        ->call('download', $execution->uuid)
        ->assertRedirect('https://signed.example/f/dump.dmp');
});

/* ---------- the staff page ---------- */

test('[P7-ADMIN] customers cannot open the staff page; admins can look but not change storage or quotas', function () {
    [$client] = p7Client();
    $this->actingAs($client)->get('/admin/backups')->assertForbidden();

    $admin = p7Staff('admin');
    $this->actingAs($admin)->get('/admin/backups')->assertOk();

    $storage = p7Storage();
    Livewire::actingAs($admin)->test(AdminBackups::class)
        ->set('storageUuid', $storage->uuid)->call('chooseStorage')->assertForbidden();

    [, $team] = p7Client();
    Livewire::actingAs($admin)->test(AdminBackups::class)
        ->set("quota.{$team->id}", '20')->call('setQuota', $team->id)->assertForbidden();
});

test('[P7-ADMIN] the owner picks the storage (it must work) and sets a team\'s quota', function () {
    $owner = p7Staff('owner');
    $storage = p7Storage(false);
    [, $team] = p7Client();

    // The bucket does not work yet: chosen, but the owner is told and customers still cannot use it.
    PlatformBackupStorage::$connectionTester = function (S3Storage $s) {
        throw new RuntimeException('Access denied');
    };
    Livewire::actingAs($owner)->test(AdminBackups::class)
        ->set('storageUuid', $storage->uuid)->call('chooseStorage')
        ->assertSet('error', fn ($e) => str_contains($e, 'Access denied'));
    expect(PlatformBackupStorage::storage())->toBeNull();

    PlatformBackupStorage::$connectionTester = function (S3Storage $s) {
        $s->forceFill(['is_usable' => true])->save();
    };
    Livewire::actingAs($owner)->test(AdminBackups::class)
        ->set('storageUuid', $storage->uuid)->call('chooseStorage')->assertSet('error', null)
        ->set("quota.{$team->id}", '25')->call('setQuota', $team->id);

    expect(PlatformBackupStorage::storage()?->id)->toBe($storage->id);
    expect(BackupQuota::limitGb($team))->toBe(25.0);
    expect(PlatformSetting::get('backups.storage_uuid'))->toBe($storage->uuid);
});

test('[P7-ADMIN] only platform storages can be chosen, never a customer\'s own', function () {
    $owner = p7Staff('owner');
    [, $team] = p7Client();
    $theirs = p7Storage(true, $team->id, 'Customer bucket');

    Livewire::actingAs($owner)->test(AdminBackups::class)
        ->set('storageUuid', $theirs->uuid)->call('chooseStorage')
        ->assertSet('error', fn ($e) => str_contains((string) $e, 'platform storages'));

    expect(PlatformSetting::get('backups.storage_uuid'))->toBeNull();
});

test('[P7-ADMIN] support staff can restore a backup for a customer from the staff page', function () {
    Bus::fake([RestoreDatabaseBackupJob::class]);
    PlatformBackupStorage::choose(p7Storage());
    [$user, $team] = p7Client();
    [, $backup] = p7Enabled($team, $user);
    $execution = p7Execution($backup);

    Livewire::actingAs(p7Staff('admin'))->test(AdminBackups::class)->call('restore', $execution->uuid);

    Bus::assertDispatched(RestoreDatabaseBackupJob::class);
});
