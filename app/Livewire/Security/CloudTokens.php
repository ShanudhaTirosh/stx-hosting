<?php

namespace App\Livewire\Security;

use App\Models\CloudProviderToken;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class CloudTokens extends Component
{
    use AuthorizesRequests;

    public function mount(): void
    {
        $this->authorize('viewAny', CloudProviderToken::class);
    }

    public function render()
    {
        return view('livewire.security.cloud-tokens');
    }
}
