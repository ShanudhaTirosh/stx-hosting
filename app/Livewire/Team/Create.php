<?php

namespace App\Livewire\Team;

use App\Models\Team;
use App\Services\Supabase\TeamSyncService;
use App\Services\TeamAvatarStorageService;
use App\Support\ValidationPatterns;
use Livewire\Component;
use Livewire\WithFileUploads;

class Create extends Component
{
    use WithFileUploads;

    public string $name = '';

    public ?string $description = null;

    public $logo;

    protected function rules(): array
    {
        return [
            'name' => ValidationPatterns::nameRules(),
            'description' => ValidationPatterns::descriptionRules(),
            'logo' => 'nullable|image|max:2048',
        ];
    }

    protected function messages(): array
    {
        return array_merge(ValidationPatterns::combinedMessages(), [
            'logo.image' => 'The team logo must be a valid image.',
            'logo.max' => 'The team logo must not exceed 2MB in size.',
        ]);
    }

    public function submit()
    {
        try {
            $this->validate();

            $team = Team::create([
                'name' => $this->name,
                'description' => $this->description,
                'personal_team' => false,
                'seat_limit' => 5,
                'is_mcp_server_enabled' => true,
            ]);

            if ($this->logo) {
                app(TeamAvatarStorageService::class)->storeTeam($team, $this->logo);
            }

            auth()->user()->teams()->attach($team, ['role' => 'owner']);

            // Sync with Supabase for multi-tenant RLS
            app(TeamSyncService::class)->syncTeam($team);
            app(TeamSyncService::class)->syncMember($team, auth()->user(), 'owner');

            auditLog('ui.team.created', [
                'team_id' => $team->id,
                'team_name' => $team->name,
                'team_uuid' => $team->uuid,
            ]);

            refreshSession($team);

            return redirectRoute($this, 'team.index');
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }
}
