<?php

namespace App\Services\Backups;

use App\Exceptions\PlatformRestrictionException;
use App\Jobs\DatabaseBackupJob;
use App\Jobs\RestoreDatabaseBackupJob;
use App\Models\BackupRestore;
use App\Models\Commerce\CommerceAuditLog;
use App\Models\ScheduledDatabaseBackup;
use App\Models\ScheduledDatabaseBackupExecution;
use App\Models\StandaloneClickhouse;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\User;
use App\Services\RbacService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * What a customer (or staff acting for them) can do with backups, with every rule in one place:
 *
 *  - backups always go to the platform's R2 storage; the customer never sees or supplies keys
 *  - schedule, retention and "back up now", within a space quota
 *  - restore in place from a finished backup, after a safety backup, with a cooldown
 *  - downloads are short-lived links, only for your own team's backups
 */
class BackupPolicyService
{
    /** @throws PlatformRestrictionException */
    public function enable(Model $database, string $preset, int $keep, User $by): ScheduledDatabaseBackup
    {
        $this->authorize($database, $by);

        $presets = config('backups.presets');
        if (! isset($presets[$preset])) {
            throw new PlatformRestrictionException('Choose daily or weekly.');
        }

        $storage = PlatformBackupStorage::storage();
        if (! $storage) {
            throw new PlatformRestrictionException('Backups are not available yet. Please contact support.');
        }

        $keep = max(1, min(30, $keep));
        $teamId = DatabaseCatalog::teamIdOf($database);

        $payload = [
            'team_id' => $teamId,
            'enabled' => true,
            'frequency' => $presets[$preset]['cron'],
            'save_s3' => true,
            's3_storage_id' => $storage->id,
            // The copy on the shared node is only a stepping stone: keep the latest, the rest lives in R2.
            'disable_local_backup' => true,
            'database_backup_retention_amount_locally' => 1,
            'database_backup_retention_days_locally' => 0,
            'database_backup_retention_max_storage_locally' => 0,
            'database_backup_retention_amount_s3' => $keep,
            'database_backup_retention_days_s3' => 0,
            'database_backup_retention_max_storage_s3' => 0,
            'dump_all' => false,
            'databases_to_backup' => $this->databaseName($database),
        ];

        $backup = ScheduledDatabaseBackup::updateOrCreate(
            ['database_id' => $database->id, 'database_type' => $database->getMorphClass()],
            $payload,
        );

        CommerceAuditLog::record('backup.enabled', ScheduledDatabaseBackup::class, $backup->id, ['preset' => $preset, 'keep' => $keep]);

        return $backup;
    }

    public function disable(Model $database, User $by): void
    {
        $this->authorize($database, $by);

        $backup = $this->configFor($database);
        $backup?->update(['enabled' => false]);

        if ($backup) {
            CommerceAuditLog::record('backup.disabled', ScheduledDatabaseBackup::class, $backup->id);
        }
    }

    public function configFor(Model $database): ?ScheduledDatabaseBackup
    {
        return ScheduledDatabaseBackup::where('database_id', $database->id)->where('database_type', $database->getMorphClass())->first();
    }

    /** @throws PlatformRestrictionException */
    public function runNow(Model $database, User $by): void
    {
        $this->authorize($database, $by);

        $backup = $this->configFor($database) ?? throw new PlatformRestrictionException('Turn backups on for this database first.');

        if (! DatabaseCatalog::isRunning($database)) {
            throw new PlatformRestrictionException('The database has to be running to back it up.');
        }

        $this->assertWithinQuota($database);

        if (! Cache::add("backup-now:{$backup->id}", 1, config('backups.run_now_cooldown_minutes', 5) * 60)) {
            throw new PlatformRestrictionException('A backup was just started. Give it a few minutes.');
        }

        DatabaseBackupJob::dispatch($backup);

        CommerceAuditLog::record('backup.run_now', ScheduledDatabaseBackup::class, $backup->id);
    }

    /** @throws PlatformRestrictionException */
    public function restore(ScheduledDatabaseBackupExecution $execution, User $by): BackupRestore
    {
        $backup = $execution->scheduledDatabaseBackup;
        $database = $backup?->database;

        abort_unless($backup && $database, 404);
        $this->authorize($database, $by);

        $storage = PlatformBackupStorage::storage();

        if ($execution->status !== 'success' || ! $execution->s3_uploaded || blank($execution->filename) || ! $storage || (int) $backup->s3_storage_id !== (int) $storage->id) {
            throw new PlatformRestrictionException('That backup cannot be restored. Pick a finished backup that was saved to our storage.');
        }

        if (! DatabaseCatalog::isRunning($database)) {
            throw new PlatformRestrictionException('Start the database first. A restore needs it running.');
        }

        if (! Cache::add("backup-restore:{$database->id}", 1, config('backups.restore_cooldown_minutes', 10) * 60)) {
            throw new PlatformRestrictionException('A restore for this database was just requested. Wait for it to finish.');
        }

        $restore = BackupRestore::create([
            'team_id' => DatabaseCatalog::teamIdOf($database),
            'user_id' => $by->id,
            'execution_id' => $execution->id,
            'status' => 'queued',
            'message' => 'Waiting to start.',
        ]);

        CommerceAuditLog::record('backup.restore_requested', BackupRestore::class, $restore->id, ['execution_uuid' => $execution->uuid]);

        RestoreDatabaseBackupJob::dispatch($restore);

        return $restore;
    }

    /** A link that works for ten minutes. */
    public function downloadUrl(ScheduledDatabaseBackupExecution $execution, User $by): string
    {
        $backup = $execution->scheduledDatabaseBackup;
        $database = $backup?->database;

        abort_unless($backup && $database, 404);
        $this->authorize($database, $by);

        $storage = PlatformBackupStorage::storage();
        if (! $storage || $execution->status !== 'success' || ! $execution->s3_uploaded || blank($execution->filename) || (int) $backup->s3_storage_id !== (int) $storage->id) {
            throw new PlatformRestrictionException('That backup is not available for download.');
        }

        CommerceAuditLog::record('backup.download_link', ScheduledDatabaseBackupExecution::class, $execution->id);

        return PlatformBackupStorage::presign($storage, (string) $execution->filename, basename((string) $execution->filename));
    }

    /** Your own team's databases, or anything for staff who manage backups. */
    private function authorize(Model $database, User $by): void
    {
        abort_unless(DatabaseCatalog::supports($database), 404);

        $teamId = DatabaseCatalog::teamIdOf($database);
        $allowed = RbacService::can($by, 'backups.manage') || ($teamId !== null && $by->teams->contains('id', $teamId));

        abort_unless($allowed, 404); // same answer for "does not exist" and "not yours"
    }

    private function assertWithinQuota(Model $database): void
    {
        $teamId = DatabaseCatalog::teamIdOf($database);
        $team = $teamId !== null ? \App\Models\Team::find($teamId) : null;

        if ($reason = BackupQuota::blockReason($team)) {
            throw new PlatformRestrictionException($reason);
        }
    }

    /** What the backup job calls the thing to dump, for engines that name it. */
    private function databaseName(Model $database): ?string
    {
        return match (true) {
            $database instanceof StandalonePostgresql => $database->postgres_db,
            $database instanceof StandaloneMysql => $database->mysql_database,
            $database instanceof StandaloneMariadb => $database->mariadb_database,
            $database instanceof StandaloneClickhouse => $database->clickhouse_db,
            $database instanceof StandaloneMongodb => null,
            default => null,
        };
    }
}
