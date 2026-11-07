<?php

namespace App\Livewire\Admin;

use App\Exceptions\PlatformRestrictionException;
use App\Models\BackupRestore;
use App\Models\PlatformSetting;
use App\Models\ScheduledDatabaseBackup;
use App\Models\ScheduledDatabaseBackupExecution;
use App\Models\Team;
use App\Services\Backups\BackupPolicyService;
use App\Services\Backups\BackupQuota;
use App\Services\Backups\PlatformBackupStorage;
use App\Services\RbacService;
use Livewire\Component;

/** Staff view of every customer's backups. The owner picks the R2 storage and sets quotas. */
class Backups extends Component
{
    public string $storageUuid = '';

    /** @var array<int, string> quota override in GB, per team id */
    public array $quota = [];

    public ?string $error = null;

    public function boot(): void
    {
        abort_unless(RbacService::can(auth()->user(), 'backups.manage'), 403, 'Backup staff only.');
    }

    public function mount(): void
    {
        $this->storageUuid = (string) (PlatformBackupStorage::storage()?->uuid ?? '');
    }

    public function chooseStorage(): void
    {
        abort_unless(RbacService::can(auth()->user(), 'backups.storage'), 403, 'Only the owner can choose the backup storage.');

        $storage = PlatformBackupStorage::candidates()->firstWhere('uuid', $this->storageUuid);

        if (! $storage) {
            $this->error = 'Choose one of the platform storages. Add the R2 bucket under Storages first.';

            return;
        }

        PlatformBackupStorage::choose($storage);

        if ($problem = PlatformBackupStorage::verify($storage)) {
            $this->error = 'Chosen, but the connection failed: '.$problem.' Customers cannot use backups until it works.';

            return;
        }

        $this->error = null;
        $this->dispatch('success', 'Backups will use '.$storage->name.'.');
    }

    public function testStorage(): void
    {
        abort_unless(RbacService::can(auth()->user(), 'backups.storage'), 403);

        $storage = PlatformBackupStorage::candidates()->firstWhere('uuid', PlatformSetting::get('backups.storage_uuid') ?: $this->storageUuid);

        if (! $storage) {
            $this->error = 'Choose a storage first.';

            return;
        }

        $problem = PlatformBackupStorage::verify($storage);
        $problem ? ($this->error = 'Test failed: '.$problem) : $this->dispatch('success', 'Connection works.');
    }

    public function setQuota(int $teamId): void
    {
        abort_unless(RbacService::can(auth()->user(), 'backups.storage'), 403, 'Only the owner can set quotas.');

        $team = Team::findOrFail($teamId);
        $value = trim((string) ($this->quota[$teamId] ?? ''));

        if ($value === '') {
            BackupQuota::setOverrideGb($team, null); // back to the default
        } elseif (is_numeric($value) && (float) $value >= 0 && (float) $value <= 10000) {
            BackupQuota::setOverrideGb($team, (float) $value);
        } else {
            $this->error = 'Enter a number of GB, or leave empty for the default.';

            return;
        }

        $this->error = null;
        $this->dispatch('success', 'Quota saved.');
    }

    public function restore(string $executionUuid, BackupPolicyService $service): void
    {
        $execution = ScheduledDatabaseBackupExecution::where('uuid', $executionUuid)->firstOrFail();

        try {
            $service->restore($execution, auth()->user());
            $this->error = null;
            $this->dispatch('success', 'Restore requested.');
        } catch (PlatformRestrictionException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        $teamIds = ScheduledDatabaseBackup::distinct()->pluck('team_id');

        return view('livewire.admin.backups', [
            'candidates' => PlatformBackupStorage::candidates(),
            'current' => PlatformBackupStorage::storage(),
            'teams' => Team::whereIn('id', $teamIds)->orderBy('name')->get()->map(fn ($t) => [
                'team' => $t, 'used' => BackupQuota::usedGb($t), 'limit' => BackupQuota::limitGb($t),
            ]),
            'failures' => ScheduledDatabaseBackupExecution::with('scheduledDatabaseBackup.team')->where('status', 'failed')->latest('id')->limit(15)->get(),
            'recent' => ScheduledDatabaseBackupExecution::with('scheduledDatabaseBackup.team')->where('status', 'success')->where('s3_uploaded', true)->latest('id')->limit(15)->get(),
            'restores' => BackupRestore::with('team')->latest('id')->limit(15)->get(),
            'isOwner' => RbacService::can(auth()->user(), 'backups.storage'),
        ]);
    }
}
