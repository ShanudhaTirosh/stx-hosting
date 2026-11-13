<?php

namespace App\Jobs;

use App\Models\BackupRestore;
use App\Models\ScheduledDatabaseBackupExecution;
use App\Services\Backups\BackupRunner;
use App\Services\Backups\DatabaseRestorer;
use App\Services\Backups\PlatformBackupStorage;
use App\Services\Notifications\StaffEvents;
use App\Support\DatabaseImport\DatabaseImportException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Restore in two steps, so a bad restore can be undone:
 *   1. take a fresh backup of the database as it is now (the safety copy)
 *   2. only if that worked, start the restore from the chosen backup
 */
class RestoreDatabaseBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 5400;

    public function __construct(public BackupRestore $restore) {}

    public function handle(BackupRunner $runner, DatabaseRestorer $restorer): void
    {
        $execution = ScheduledDatabaseBackupExecution::with('scheduledDatabaseBackup.database')->find($this->restore->execution_id);
        $backup = $execution?->scheduledDatabaseBackup;
        $sourceDatabase = $backup?->database;
        $targetDatabase = $this->restore->targetDatabase ?? $sourceDatabase;
        $storage = PlatformBackupStorage::storage();

        if (! $execution || ! $backup || ! $sourceDatabase || ! $targetDatabase || ! $storage) {
            $this->fail2('The backup or the database no longer exists.');

            return;
        }

        $this->restore->update(['status' => 'snapshotting', 'message' => 'Taking a safety backup of the current data first.']);

        // Use target database's backup configuration if available, otherwise fallback to source backup config
        $targetBackupConfig = \App\Models\ScheduledDatabaseBackup::where('database_id', $targetDatabase->id)
            ->where('database_type', $targetDatabase->getMorphClass())
            ->first() ?? $backup;

        try {
            $safety = $runner->run($targetBackupConfig);
        } catch (\Throwable $e) {
            logger()->warning('Safety backup before restore failed: '.$e->getMessage());
            $safety = null;
        }

        if (! $safety || $safety->status !== 'success') {
            $this->fail2('We could not take a safety backup first, so the restore was cancelled. Nothing was changed.');

            return;
        }

        $this->restore->update(['status' => 'restoring', 'safety_execution_id' => $safety->id, 'message' => 'Restoring. The database may be unavailable for a few minutes.']);

        try {
            $restorer->start($targetDatabase, $execution, $storage, (int) $this->restore->team_id);
        } catch (DatabaseImportException $e) {
            $this->fail2($e->getMessage().' The safety backup is kept.');

            return;
        } catch (\Throwable $e) {
            logger()->error('Restore could not start: '.$e->getMessage());
            $this->fail2('The restore could not be started. The safety backup is kept.');

            return;
        }

        $this->restore->update(['status' => 'started', 'message' => 'Restore started. If something looks wrong, the safety backup taken just before can be restored.']);
    }

    private function fail2(string $message): void
    {
        $this->restore->update(['status' => 'failed', 'message' => $message]);

        StaffEvents::notify(StaffEvents::BACKUP_FAILED, 'Restore failed', $message, [
            'Team' => (string) $this->restore->team?->name,
        ], 'error', 'restore:'.$this->restore->id);
    }
}
