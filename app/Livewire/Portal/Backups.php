<?php

namespace App\Livewire\Portal;

use App\Exceptions\PlatformRestrictionException;
use App\Jobs\VolumeBackupJob;
use App\Jobs\VolumeBackupRecoveryJob;
use App\Models\Application;
use App\Models\BackupRestore;
use App\Models\LocalPersistentVolume;
use App\Models\ScheduledDatabaseBackupExecution;
use App\Models\ScheduledVolumeBackup;
use App\Models\ScheduledVolumeBackupExecution;
use App\Models\Service;
use App\Services\Backups\BackupPolicyService;
use App\Services\Backups\BackupQuota;
use App\Services\Backups\DatabaseCatalog;
use App\Services\Backups\PlatformBackupStorage;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

/** A customer's database & application volume backups: schedule, back up now, download, restore. Everything goes to our R2 storage. */
class Backups extends Component
{
    public string $tab = 'databases'; // 'databases' or 'volumes'

    /** @var array<string, array{preset: string, keep: int}> per database uuid */
    public array $settings = [];

    /** @var array<string, array{preset: string, keep: int}> per volume uuid */
    public array $volumeSettings = [];

    /** @var array<string, string> target database uuid for restore */
    public array $targetDatabases = [];

    /** @var array<string, string> what was typed to confirm a restore, per execution uuid */
    public array $confirm = [];

    /** @var array<string, string> what was typed to confirm a volume restore, per execution uuid */
    public array $confirmVolume = [];

    public ?string $error = null;

    private function team()
    {
        $team = currentTeam();
        abort_unless($team, 403, 'No active team.');

        return $team;
    }

    private function authorizeAction(int $teamId): void
    {
        abort_unless(auth()->user()?->canPerformInTeam($teamId, 'servers.access'), 403, 'You do not have permission to manage or download backups for this server.');
    }

    private function database(string $uuid)
    {
        return DatabaseCatalog::find($this->team(), $uuid) ?? abort(404);
    }

    /** Run something and show a friendly message instead of an error page. */
    private function guarded(callable $work, ?string $success = null): void
    {
        $this->error = null;

        try {
            $work();
            if ($success) {
                $this->dispatch('success', $success);
            }
        } catch (PlatformRestrictionException $e) {
            $this->error = $e->getMessage();
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function enable(string $databaseUuid, BackupPolicyService $service): void
    {
        $this->authorizeAction($this->team()->id);
        $database = $this->database($databaseUuid);
        $chosen = $this->settings[$databaseUuid] ?? [];

        $this->guarded(fn () => $service->enable($database, (string) ($chosen['preset'] ?? 'daily'), (int) ($chosen['keep'] ?? 7), auth()->user()), 'Backups are on.');
    }

    public function disable(string $databaseUuid, BackupPolicyService $service): void
    {
        $this->authorizeAction($this->team()->id);
        $database = $this->database($databaseUuid);

        $this->guarded(fn () => $service->disable($database, auth()->user()), 'Backups are off. Existing backups are kept.');
    }

    public function runNow(string $databaseUuid, BackupPolicyService $service): void
    {
        $this->authorizeAction($this->team()->id);
        $database = $this->database($databaseUuid);

        $this->guarded(fn () => $service->runNow($database, auth()->user()), 'Backup started. It appears below when it finishes.');
    }

    public function restore(string $executionUuid, BackupPolicyService $service): void
    {
        $this->authorizeAction($this->team()->id);
        if (strtoupper(trim($this->confirm[$executionUuid] ?? '')) !== 'RESTORE') {
            $this->addError("confirm.{$executionUuid}", 'Type RESTORE to confirm.');

            return;
        }

        $execution = $this->execution($executionUuid);
        $targetUuid = $this->targetDatabases[$executionUuid] ?? null;
        $targetDb = $targetUuid ? DatabaseCatalog::find($this->team(), $targetUuid) : null;

        $this->guarded(function () use ($service, $execution, $executionUuid, $targetDb) {
            $service->restore($execution, auth()->user(), $targetDb);
            $this->confirm[$executionUuid] = '';
        }, 'Restore requested. We take a safety backup first, then restore.');
    }

    public function download(string $executionUuid, BackupPolicyService $service)
    {
        $this->authorizeAction($this->team()->id);
        $execution = $this->execution($executionUuid);

        try {
            return redirect()->away($service->downloadUrl($execution, auth()->user()));
        } catch (PlatformRestrictionException $e) {
            $this->error = $e->getMessage();

            return null;
        }
    }

    /** Volume Backup Controls */
    public function enableVolume(string $volumeUuid): void
    {
        $this->authorizeAction($this->team()->id);
        $volume = $this->volume($volumeUuid);
        $storage = PlatformBackupStorage::storage();
        if (! $storage) {
            $this->error = 'Platform storage is not configured yet. Please contact support.';

            return;
        }

        $chosen = $this->volumeSettings[$volumeUuid] ?? [];
        $presets = config('backups.presets');
        $preset = (string) ($chosen['preset'] ?? 'daily');
        $cron = $presets[$preset]['cron'] ?? '0 2 * * *';
        $keep = max(1, min(30, (int) ($chosen['keep'] ?? 7)));

        $this->guarded(function () use ($volume, $storage, $cron, $keep) {
            ScheduledVolumeBackup::updateOrCreate([
                'backupable_type' => $volume->getMorphClass(),
                'backupable_id' => $volume->id,
            ], [
                'team_id' => $this->team()->id,
                's3_storage_id' => $storage->id,
                'frequency' => $cron,
                'enabled' => true,
                'save_s3' => true,
                'disable_local_backup' => true,
                'retention_amount_s3' => $keep,
                'retention_amount_locally' => 1,
            ]);
        }, 'Volume backup schedule enabled.');
    }

    public function disableVolume(string $volumeUuid): void
    {
        $this->authorizeAction($this->team()->id);
        $volume = $this->volume($volumeUuid);
        $backup = $volume->scheduledBackups()->first();

        $this->guarded(function () use ($backup) {
            $backup?->update(['enabled' => false]);
        }, 'Volume backups turned off. Existing archives are kept.');
    }

    public function runNowVolume(string $volumeUuid): void
    {
        $this->authorizeAction($this->team()->id);
        $volume = $this->volume($volumeUuid);
        $backup = $volume->scheduledBackups()->first();
        if (! $backup) {
            $this->error = 'Turn volume backups on first.';

            return;
        }

        if (! Cache::add("volume-backup-now:{$backup->id}", 1, 300)) {
            $this->error = 'A backup for this volume was recently initiated. Please wait a few minutes.';

            return;
        }

        $this->guarded(function () use ($backup) {
            VolumeBackupJob::dispatch($backup);
        }, 'Volume backup started. It will appear in history once complete.');
    }

    public function restoreVolume(string $executionUuid): void
    {
        $this->authorizeAction($this->team()->id);
        if (strtoupper(trim($this->confirmVolume[$executionUuid] ?? '')) !== 'RESTORE') {
            $this->addError("confirmVolume.{$executionUuid}", 'Type RESTORE to confirm.');

            return;
        }

        $execution = ScheduledVolumeBackupExecution::where('uuid', $executionUuid)->firstOrFail();
        abort_unless((int) $execution->scheduledVolumeBackup?->team_id === (int) $this->team()->id, 403);

        $this->guarded(function () use ($execution, $executionUuid) {
            VolumeBackupRecoveryJob::dispatch($execution);
            $this->confirmVolume[$executionUuid] = '';
        }, 'Volume recovery job queued.');
    }

    public function downloadVolume(string $executionUuid)
    {
        $this->authorizeAction($this->team()->id);
        $execution = ScheduledVolumeBackupExecution::where('uuid', $executionUuid)->firstOrFail();
        abort_unless((int) $execution->scheduledVolumeBackup?->team_id === (int) $this->team()->id, 403);

        $storage = PlatformBackupStorage::storage();
        if (! $storage || ! $execution->s3_uploaded || blank($execution->filename)) {
            $this->error = 'Volume archive is not available for download.';

            return null;
        }

        return redirect()->away(PlatformBackupStorage::presign($storage, (string) $execution->filename, basename((string) $execution->filename)));
    }

    private function volume(string $uuid): LocalPersistentVolume
    {
        $volume = LocalPersistentVolume::where('uuid', $uuid)->firstOrFail();
        $teamId = $volume->resource?->environment?->project?->team_id;
        abort_unless((int) $teamId === (int) $this->team()->id, 404);

        return $volume;
    }

    /** Only executions of this team's databases. */
    private function execution(string $uuid): ScheduledDatabaseBackupExecution
    {
        $execution = ScheduledDatabaseBackupExecution::where('uuid', $uuid)->with('scheduledDatabaseBackup')->first();
        abort_unless($execution && (int) $execution->scheduledDatabaseBackup?->team_id === (int) $this->team()->id, 404);

        return $execution;
    }

    public function render(BackupPolicyService $service)
    {
        $team = $this->team();
        $databases = DatabaseCatalog::forTeam($team);

        $rows = $databases->map(function ($db) use ($service) {
            $config = $service->configFor($db);

            return [
                'db' => $db,
                'config' => $config,
                'executions' => $config ? $config->executions()->latest()->limit(10)->get() : collect(),
            ];
        });

        foreach ($rows as $row) {
            $uuid = $row['db']->uuid;
            $cron = $row['config']?->frequency;
            $this->settings[$uuid] ??= [
                'preset' => collect(config('backups.presets'))->search(fn ($p) => $p['cron'] === $cron) ?: 'daily',
                'keep' => (int) ($row['config']?->database_backup_retention_amount_s3 ?: 7),
            ];
        }

        // Fetch team persistent volumes
        $volumes = LocalPersistentVolume::whereHasMorph('resource', [Application::class, Service::class], function ($query) use ($team) {
            $query->whereHas('environment.project', fn ($q) => $q->where('team_id', $team->id));
        })->with(['resource', 'scheduledBackups.executions'])->get();

        $volumeRows = $volumes->map(function ($vol) {
            $config = $vol->scheduledBackups()->first();

            return [
                'volume' => $vol,
                'config' => $config,
                'executions' => $config ? $config->executions()->latest()->limit(10)->get() : collect(),
            ];
        });

        foreach ($volumeRows as $vRow) {
            $uuid = $vRow['volume']->uuid;
            $cron = $vRow['config']?->frequency;
            $this->volumeSettings[$uuid] ??= [
                'preset' => collect(config('backups.presets'))->search(fn ($p) => $p['cron'] === $cron) ?: 'daily',
                'keep' => (int) ($vRow['config']?->retention_amount_s3 ?: 7),
            ];
        }

        return view('livewire.portal.backups', [
            'rows' => $rows,
            'databases' => $databases,
            'volumeRows' => $volumeRows,
            'available' => PlatformBackupStorage::storage() !== null,
            'usedGb' => BackupQuota::usedGb($team),
            'limitGb' => BackupQuota::limitGb($team),
            'presets' => config('backups.presets'),
            'keepOptions' => config('backups.keep_options'),
            'restores' => BackupRestore::where('team_id', $team->id)->latest()->limit(5)->get(),
        ]);
    }
}
