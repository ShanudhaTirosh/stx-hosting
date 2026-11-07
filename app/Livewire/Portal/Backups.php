<?php

namespace App\Livewire\Portal;

use App\Exceptions\PlatformRestrictionException;
use App\Models\BackupRestore;
use App\Models\ScheduledDatabaseBackupExecution;
use App\Services\Backups\BackupPolicyService;
use App\Services\Backups\BackupQuota;
use App\Services\Backups\DatabaseCatalog;
use App\Services\Backups\PlatformBackupStorage;
use Livewire\Component;

/** A customer's database backups: schedule, back up now, download, restore. Everything goes to our R2 storage. */
class Backups extends Component
{
    /** @var array<string, array{preset: string, keep: int}> per database uuid */
    public array $settings = [];

    /** @var array<string, string> what was typed to confirm a restore, per execution uuid */
    public array $confirm = [];

    public ?string $error = null;

    private function team()
    {
        $team = currentTeam();
        abort_unless($team, 403, 'No active team.');

        return $team;
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
        }
    }

    public function enable(string $databaseUuid, BackupPolicyService $service): void
    {
        $database = $this->database($databaseUuid);
        $chosen = $this->settings[$databaseUuid] ?? [];

        $this->guarded(fn () => $service->enable($database, (string) ($chosen['preset'] ?? 'daily'), (int) ($chosen['keep'] ?? 7), auth()->user()), 'Backups are on.');
    }

    public function disable(string $databaseUuid, BackupPolicyService $service): void
    {
        $database = $this->database($databaseUuid);

        $this->guarded(fn () => $service->disable($database, auth()->user()), 'Backups are off. Existing backups are kept.');
    }

    public function runNow(string $databaseUuid, BackupPolicyService $service): void
    {
        $database = $this->database($databaseUuid);

        $this->guarded(fn () => $service->runNow($database, auth()->user()), 'Backup started. It appears below when it finishes.');
    }

    public function restore(string $executionUuid, BackupPolicyService $service): void
    {
        if (strtoupper(trim($this->confirm[$executionUuid] ?? '')) !== 'RESTORE') {
            $this->addError("confirm.{$executionUuid}", 'Type RESTORE to confirm.');

            return;
        }

        $execution = $this->execution($executionUuid);

        $this->guarded(function () use ($service, $execution, $executionUuid) {
            $service->restore($execution, auth()->user());
            $this->confirm[$executionUuid] = '';
        }, 'Restore requested. We take a safety backup first, then restore.');
    }

    public function download(string $executionUuid, BackupPolicyService $service)
    {
        $execution = $this->execution($executionUuid);

        try {
            return redirect()->away($service->downloadUrl($execution, auth()->user()));
        } catch (PlatformRestrictionException $e) {
            $this->error = $e->getMessage();

            return null;
        }
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
                'executions' => $config ? $config->executions()->limit(10)->get() : collect(),
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

        return view('livewire.portal.backups', [
            'rows' => $rows,
            'available' => PlatformBackupStorage::storage() !== null,
            'usedGb' => BackupQuota::usedGb($team),
            'limitGb' => BackupQuota::limitGb($team),
            'presets' => config('backups.presets'),
            'keepOptions' => config('backups.keep_options'),
            'restores' => BackupRestore::where('team_id', $team->id)->latest()->limit(5)->get(),
        ]);
    }
}
