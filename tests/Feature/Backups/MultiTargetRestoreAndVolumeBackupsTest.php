<?php

namespace Tests\Feature\Backups;

use App\Exceptions\PlatformRestrictionException;
use App\Models\InstanceSettings;
use App\Models\LocalPersistentVolume;
use App\Models\S3Storage;
use App\Models\ScheduledDatabaseBackup;
use App\Models\ScheduledDatabaseBackupExecution;
use App\Models\ScheduledVolumeBackup;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneMysql;
use App\Models\Team;
use App\Models\User;
use App\Services\Backups\BackupPolicyService;
use App\Services\Backups\PlatformBackupStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::forceCreate(['id' => 0]);
});

test('restore accepts target database of same engine', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $team->members()->attach($user->id, ['role' => 'owner']);

    $storage = S3Storage::factory()->create(['team_id' => 0]);
    PlatformBackupStorage::setStorage($storage);

    $dbSource = StandalonePostgresql::factory()->create([
        'status' => 'running',
    ]);
    $dbSource->environment->project->update(['team_id' => $team->id]);

    $dbTarget = StandalonePostgresql::factory()->create([
        'status' => 'running',
    ]);
    $dbTarget->environment->project->update(['team_id' => $team->id]);

    $backup = ScheduledDatabaseBackup::create([
        'database_id' => $dbSource->id,
        'database_type' => $dbSource->getMorphClass(),
        'team_id' => $team->id,
        's3_storage_id' => $storage->id,
        'frequency' => '0 0 * * *',
        'enabled' => true,
    ]);

    $execution = ScheduledDatabaseBackupExecution::create([
        'scheduled_database_backup_id' => $backup->id,
        'status' => 'success',
        's3_uploaded' => true,
        'filename' => 'backup-pg-01.dump',
    ]);

    $service = app(BackupPolicyService::class);
    $restore = $service->restore($execution, $user, $dbTarget);

    expect($restore)->not->toBeNull();
    expect($restore->target_database_id)->toBe($dbTarget->id);
    expect($restore->target_database_type)->toBe($dbTarget->getMorphClass());
});

test('restore rejects target database of different engine', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $team->members()->attach($user->id, ['role' => 'owner']);

    $storage = S3Storage::factory()->create(['team_id' => 0]);
    PlatformBackupStorage::setStorage($storage);

    $dbSource = StandalonePostgresql::factory()->create([
        'status' => 'running',
    ]);
    $dbSource->environment->project->update(['team_id' => $team->id]);

    $dbMysql = StandaloneMysql::factory()->create([
        'status' => 'running',
    ]);
    $dbMysql->environment->project->update(['team_id' => $team->id]);

    $backup = ScheduledDatabaseBackup::create([
        'database_id' => $dbSource->id,
        'database_type' => $dbSource->getMorphClass(),
        'team_id' => $team->id,
        's3_storage_id' => $storage->id,
        'frequency' => '0 0 * * *',
        'enabled' => true,
    ]);

    $execution = ScheduledDatabaseBackupExecution::create([
        'scheduled_database_backup_id' => $backup->id,
        'status' => 'success',
        's3_uploaded' => true,
        'filename' => 'backup-pg-01.dump',
    ]);

    $service = app(BackupPolicyService::class);

    expect(fn () => $service->restore($execution, $user, $dbMysql))
        ->toThrow(PlatformRestrictionException::class);
});

