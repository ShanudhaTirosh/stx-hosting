<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\RequiresPlatformStaff;
use App\Models\Commerce\CommerceAuditLog;
use Livewire\Component;
use Livewire\WithPagination;

/** Read-only view of the platform's audit log (payments, deliveries, prices, backups, emails, restores...). */
class AuditLog extends Component
{
    use RequiresPlatformStaff;
    use WithPagination;

    public string $area = '';

    public string $search = '';

    public function updating(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = CommerceAuditLog::with(['user', 'team'])->orderByDesc('id');

        if ($this->area !== '') {
            $query->where('action', 'like', str_replace(['%', '_'], ['\\%', '\\_'], $this->area).'.%');
        }

        if (trim($this->search) !== '') {
            $term = trim($this->search);
            $query->where(fn ($q) => $q->where('action', 'like', "%{$term}%")
                ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")));
        }

        $areas = CommerceAuditLog::query()->pluck('action')->map(fn ($a) => strtok($a, '.'))->unique()->sort()->values();

        return view('livewire.admin.audit-log', ['entries' => $query->paginate(50), 'areas' => $areas]);
    }
}
