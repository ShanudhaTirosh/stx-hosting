<?php

namespace App\Livewire\Server;

use App\Models\PrivateKey;
use App\Models\Server;
use App\Models\Team;
use Livewire\Component;

class Create extends Component
{
    public $private_keys = [];

    public ?string $selectedType = 'manual';

    public ?string $selectedTokenUuid = null;

    public bool $limit_reached = false;

    public bool $has_hetzner_tokens = false;

    public function mount(?string $selectedType = null, ?string $selectedTokenUuid = null): void
    {
        $this->authorize('create', Server::class);

        $this->selectedType = 'manual';
        $this->selectedTokenUuid = null;

        $this->private_keys = PrivateKey::ownedByCurrentTeamCached();
        if (! isCloud()) {
            $this->limit_reached = false;

            return;
        }
        $this->limit_reached = Team::serverLimitReached();
    }

    public function render()
    {
        return view('livewire.server.create');
    }
}
