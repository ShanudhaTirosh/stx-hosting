<?php

namespace App\Services\Supabase;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TeamSyncService
{
    public function __construct(
        protected SupabaseClient $client
    ) {}

    public function syncTeam(Team $team): void
    {
        if (! $this->client->isConfigured() || ! $team->uuid) {
            return;
        }

        try {
            $slug = Str::slug($team->name).'-'.substr($team->uuid, 0, 8);

            $this->client->from('teams')->upsert([
                'id' => $team->uuid,
                'name' => $team->name,
                'slug' => $slug,
                'is_personal' => (bool) $team->personal_team,
                'updated_at' => now()->toIso8601String(),
            ], onConflict: 'id');
        } catch (\Throwable $e) {
            Log::warning('Failed to sync team to Supabase: '.$e->getMessage(), [
                'team_id' => $team->id,
                'team_uuid' => $team->uuid,
            ]);
        }
    }

    public function syncMember(Team $team, User $user, string $role): void
    {
        if (! $this->client->isConfigured() || ! $team->uuid || ! $user->supabase_id) {
            return;
        }

        try {
            $this->client->from('team_members')->upsert([
                'team_id' => $team->uuid,
                'user_id' => $user->supabase_id,
                'role' => $role,
                'updated_at' => now()->toIso8601String(),
            ], onConflict: 'team_id,user_id');
        } catch (\Throwable $e) {
            Log::warning('Failed to sync team member to Supabase: '.$e->getMessage(), [
                'team_id' => $team->id,
                'user_id' => $user->id,
                'role' => $role,
            ]);
        }
    }

    public function removeMember(Team $team, User $user): void
    {
        if (! $this->client->isConfigured() || ! $team->uuid || ! $user->supabase_id) {
            return;
        }

        try {
            $this->client->from('team_members')
                ->eq('team_id', $team->uuid)
                ->eq('user_id', $user->supabase_id)
                ->delete();
        } catch (\Throwable $e) {
            Log::warning('Failed to delete team member from Supabase: '.$e->getMessage(), [
                'team_id' => $team->id,
                'user_id' => $user->id,
            ]);
        }
    }

    public function deleteTeam(Team $team): void
    {
        if (! $this->client->isConfigured() || ! $team->uuid) {
            return;
        }

        try {
            $this->client->from('teams')
                ->eq('id', $team->uuid)
                ->delete();
        } catch (\Throwable $e) {
            Log::warning('Failed to delete team from Supabase: '.$e->getMessage(), [
                'team_id' => $team->id,
            ]);
        }
    }
}
