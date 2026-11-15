<?php

namespace App\Livewire\Portal;

use App\Exceptions\VpsAccessException;
use App\Models\Commerce\CommerceAuditLog;
use App\Models\Commerce\ServiceInstance;
use App\Models\Commerce\VpsStatSample;
use App\Services\Vps\RemoteShell;
use App\Services\Vps\VpsConnectionFactory;
use App\Services\Vps\VpsPaths;
use App\Services\Vps\VpsStats;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Client panel for one VPS: restart (no power off), usage, files over SFTP, and the in-browser terminal.
 * The panel signs in with its own key (installed once), so the customer can change the root password freely.
 */
class VpsPanel extends Component
{
    use WithFileUploads;

    public const MAX_DOWNLOAD_BYTES = 25 * 1024 * 1024;

    public const MAX_UPLOAD_KB = 10240;

    public const MAX_EDIT_BYTES = 512 * 1024;

    public string $uuid = '';

    public string $path = '/root';

    /** @var array<int, array{name: string, type: string, size: int, modified: int}> */
    public array $entries = [];

    public bool $filesLoaded = false;

    public string $rootPassword = '';

    public string $restartConfirm = '';

    public $upload;

    // File Manager Modals
    public bool $showEditModal = false;

    public string $editingFileName = '';

    public string $fileContent = '';

    public bool $showRenameModal = false;

    public string $renameOldName = '';

    public string $renameNewName = '';

    public bool $showNewModal = false;

    public string $newEntryName = '';

    public string $newEntryType = 'file'; // 'file' or 'dir'

    public ?string $error = null;

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;
        $this->service(); // 404 for anything that is not this team's active VPS
    }

    private function service(): ServiceInstance
    {
        $service = ServiceInstance::where('uuid', $this->uuid)
            ->where('service_type', 'vps')
            ->where('status', 'active')
            ->first();

        abort_unless($service, 404);
        abort_unless(auth()->user()?->canPerformInTeam($service->team_id, 'servers.access'), 403);

        return $service;
    }

    private function connect(): RemoteShell
    {
        return app(VpsConnectionFactory::class)->connect($this->service());
    }

    /** Run something against the VPS and show a friendly message instead of an error page. */
    private function guarded(callable $work): mixed
    {
        $this->error = null;

        try {
            return $work();
        } catch (VpsAccessException $e) {
            $this->error = $e->getMessage();
        } catch (\Throwable $e) {
            logger()->warning('VPS panel action failed: '.$e->getMessage());
            $this->error = $e instanceof \RuntimeException ? $e->getMessage() : 'Something went wrong talking to your server. Try again.';
        }

        return null;
    }

    public function enablePanelAccess(): void
    {
        $this->validate(['rootPassword' => 'nullable|string|max:256']);

        $this->guarded(function () {
            app(VpsConnectionFactory::class)->bootstrap($this->service(), $this->rootPassword ?: null);
            $this->rootPassword = '';
            $this->dispatch('success', 'Panel access is on. Stats, files and the terminal are ready.');
        });
    }

    public function restart(): void
    {
        $this->restartConfirm = strtoupper(trim($this->restartConfirm));

        if ($this->restartConfirm !== 'RESTART') {
            $this->addError('restartConfirm', 'Type RESTART to confirm.');

            return;
        }

        $service = $this->service();

        // One restart request per five minutes per server: protects against reboot loops and double clicks.
        if (! Cache::add("vps-restart:{$service->id}", true, 300)) {
            $this->error = 'This server was restarted a moment ago. Give it a few minutes.';

            return;
        }

        $done = $this->guarded(function () use ($service) {
            $shell = $this->connect();
            try {
                // Reboot after two seconds so this command returns before the connection drops. There is no power off.
                $shell->exec("nohup sh -c 'sleep 2; /sbin/reboot' >/dev/null 2>&1 &", 10);
            } finally {
                $shell->disconnect();
            }

            CommerceAuditLog::record('vps.restart_requested', ServiceInstance::class, $service->id, ['ip' => $service->ip_address]);

            return true;
        });

        if ($done) {
            $this->restartConfirm = '';
            $this->dispatch('success', 'Restart requested. The server is back in about a minute.');
        } else {
            Cache::forget("vps-restart:{$service->id}");
        }
    }

    public function loadFiles(?string $path = null): void
    {
        $this->guarded(function () use ($path) {
            $target = VpsPaths::normalize($path ?? $this->path);
            $shell = $this->connect();
            try {
                $this->entries = $shell->listDirectory($target);
            } finally {
                $shell->disconnect();
            }
            $this->path = $target;
            $this->filesLoaded = true;
        });
    }

    public function open(string $name): void
    {
        $this->guarded(fn () => $this->loadFiles(VpsPaths::join($this->path, $name)));
    }

    public function up(): void
    {
        $this->loadFiles(VpsPaths::parent($this->path));
    }

    public function download(string $name)
    {
        $path = null;

        $data = $this->guarded(function () use ($name, &$path) {
            $path = VpsPaths::join($this->path, $name);
            $shell = $this->connect();
            try {
                return $shell->download($path, self::MAX_DOWNLOAD_BYTES);
            } finally {
                $shell->disconnect();
            }
        });

        if ($data === null || $path === null) {
            return null;
        }

        CommerceAuditLog::record('vps.file_downloaded', ServiceInstance::class, $this->service()->id, ['path' => $path]);

        return response()->streamDownload(function () use ($data) {
            echo $data;
        }, basename($path));
    }

    public function uploadFile(): void
    {
        $this->validate(['upload' => 'required|file|max:'.self::MAX_UPLOAD_KB]);

        $this->guarded(function () {
            $path = VpsPaths::join($this->path, VpsPaths::safeName($this->upload->getClientOriginalName()));
            $shell = $this->connect();
            try {
                $shell->upload($path, (string) file_get_contents($this->upload->getRealPath()));
            } finally {
                $shell->disconnect();
            }

            CommerceAuditLog::record('vps.file_uploaded', ServiceInstance::class, $this->service()->id, ['path' => $path]);
            $this->upload = null;
            $this->dispatch('success', 'File uploaded.');
        });

        $this->loadFiles();
    }

    public function deleteEntry(string $name): void
    {
        $this->guarded(function () use ($name) {
            $path = VpsPaths::join($this->path, $name);
            $shell = $this->connect();
            try {
                $shell->delete($path);
            } finally {
                $shell->disconnect();
            }

            CommerceAuditLog::record('vps.file_deleted', ServiceInstance::class, $this->service()->id, ['path' => $path]);
        });

        $this->loadFiles();
    }

    public function editFile(string $name): void
    {
        $this->guarded(function () use ($name) {
            $path = VpsPaths::join($this->path, $name);
            $shell = $this->connect();
            try {
                $this->fileContent = $shell->download($path, self::MAX_EDIT_BYTES);
                $this->editingFileName = $name;
                $this->showEditModal = true;
            } finally {
                $shell->disconnect();
            }
        });
    }

    public function saveFileContent(): void
    {
        $this->guarded(function () {
            $path = VpsPaths::join($this->path, $this->editingFileName);
            $shell = $this->connect();
            try {
                $shell->upload($path, $this->fileContent);
            } finally {
                $shell->disconnect();
            }

            CommerceAuditLog::record('vps.file_edited', ServiceInstance::class, $this->service()->id, ['path' => $path]);
            $this->showEditModal = false;
            $this->editingFileName = '';
            $this->fileContent = '';
            $this->dispatch('success', 'File saved successfully.');
        });

        $this->loadFiles();
    }

    public function openRename(string $name): void
    {
        $this->renameOldName = $name;
        $this->renameNewName = $name;
        $this->showRenameModal = true;
    }

    public function renameEntry(): void
    {
        $this->validate([
            'renameNewName' => 'required|string|max:255',
        ]);

        $this->guarded(function () {
            $oldPath = VpsPaths::join($this->path, $this->renameOldName);
            $newPath = VpsPaths::join($this->path, VpsPaths::safeName($this->renameNewName));

            $shell = $this->connect();
            try {
                $shell->rename($oldPath, $newPath);
            } finally {
                $shell->disconnect();
            }

            CommerceAuditLog::record('vps.file_renamed', ServiceInstance::class, $this->service()->id, [
                'old_path' => $oldPath,
                'new_path' => $newPath,
            ]);

            $this->showRenameModal = false;
            $this->renameOldName = '';
            $this->renameNewName = '';
            $this->dispatch('success', 'Renamed successfully.');
        });

        $this->loadFiles();
    }

    public function openNew(string $type = 'file'): void
    {
        $this->newEntryType = $type === 'dir' ? 'dir' : 'file';
        $this->newEntryName = '';
        $this->showNewModal = true;
    }

    public function createEntry(): void
    {
        $this->validate([
            'newEntryName' => 'required|string|max:255',
        ]);

        $this->guarded(function () {
            $name = VpsPaths::safeName($this->newEntryName);
            $path = VpsPaths::join($this->path, $name);
            $shell = $this->connect();
            try {
                if ($this->newEntryType === 'dir') {
                    $shell->exec('mkdir -p ' . escapeshellarg($path));
                } else {
                    $shell->upload($path, '');
                }
            } finally {
                $shell->disconnect();
            }

            CommerceAuditLog::record('vps.file_created', ServiceInstance::class, $this->service()->id, [
                'path' => $path,
                'type' => $this->newEntryType,
            ]);

            $this->showNewModal = false;
            $this->newEntryName = '';
            $this->dispatch('success', ucfirst($this->newEntryType) . ' created.');
        });

        $this->loadFiles();
    }

    /** Points for a small inline chart: values 0..100 spread over 240 x 40. */
    public static function sparkline(array $values): string
    {
        $count = count($values);
        if ($count < 2) {
            return '';
        }

        return collect($values)->values()->map(fn ($v, $i) => round($i / ($count - 1) * 240, 1).','.round(40 - (max(0, min(100, (float) $v)) / 100) * 38 - 1, 1))->implode(' ');
    }

    public function render()
    {
        $service = $this->service();
        $samples = VpsStatSample::where('service_instance_id', $service->id)->latest('collected_at')->limit(60)->get()->reverse()->values();

        return view('livewire.portal.vps-panel', [
            'service' => $service,
            'hasAccess' => app(VpsConnectionFactory::class)->hasPanelAccess($service),
            'latest' => $samples->last(),
            'cpuLine' => self::sparkline($samples->pluck('cpu_percent')->map(fn ($v) => (float) $v)->all()),
            'memLine' => self::sparkline($samples->map(fn ($s) => $s->mem_total_mb > 0 ? $s->mem_used_mb / $s->mem_total_mb * 100 : 0)->all()),
            'trafficGb' => VpsStats::monthlyTrafficGb($service),
            'trafficLimitGb' => (int) ($service->productVariant?->bandwidth_gb ?? 0),
        ]);
    }
}
