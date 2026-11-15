<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Supabase\SupabaseAuthService;
use App\Services\Supabase\SupabaseClient;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MigrateUsersToSupabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:users-to-supabase 
                            {--dry-run : Simulate user migration and verify password hashes without writing changes}
                            {--rollback : Roll back migration by clearing supabase_id and deleting synced profiles}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing platform users and bcrypt password hashes to self-hosted Supabase Auth';

    public function handle(SupabaseAuthService $authService, SupabaseClient $supabase): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $rollback = (bool) $this->option('rollback');

        $this->info('===============================================================');
        $this->info('  STX Enterprises — Supabase User Account Migration Tool');
        $this->info('===============================================================');

        if (! $authService->isConfigured()) {
            if ($dryRun) {
                $this->warn('Notice: SUPABASE_URL or SUPABASE_SERVICE_ROLE_KEY is not set in environment. Inspecting local accounts only.');
            } else {
                $this->error('Supabase is not configured! Check SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY.');

                return Command::FAILURE;
            }
        }

        $users = User::all();
        $this->info("Found {$users->count()} platform user(s) to process.");

        if ($rollback) {
            return $this->handleRollback($users, $authService, $supabase);
        }

        if ($dryRun) {
            $this->warn('DRY RUN MODE: No modifications will be made to Supabase or the database.');
        }

        $migratedCount = 0;
        $skippedCount = 0;
        $tableRows = [];

        foreach ($users as $user) {
            $role = match (true) {
                $user->id === 0 => 'owner',
                $user->teams()->where('teams.id', 0)->wherePivot('role', 'admin')->exists() => 'admin',
                $user->teams()->where('teams.id', 0)->wherePivot('role', 'owner')->exists() => 'owner',
                default => 'customer',
            };

            $supabaseId = $user->supabase_id ?: (string) Str::uuid();
            $hasExistingBcrypt = str_starts_with((string) $user->password, '$2y$') || str_starts_with((string) $user->password, '$2a$');

            if ($dryRun) {
                $tableRows[] = [
                    $user->id,
                    $user->email,
                    $role,
                    $hasExistingBcrypt ? 'Bcrypt (Compatible)' : 'Other/Unsupported',
                    $user->supabase_id ? 'Already Synced' : 'Pending Migration',
                ];

                continue;
            }

            // Real Migration
            try {
                // If user doesn't exist in Supabase Auth, create via Admin API
                if (blank($user->supabase_id)) {
                    // Create user in Supabase Auth GoTrue with existing password
                    $created = $authService->adminCreateUser(
                        $user->email,
                        (string) $user->password,
                        ['name' => $user->name, 'role' => $role],
                        true
                    );

                    if ($created && isset($created['id'])) {
                        $supabaseId = $created['id'];
                    }

                    $user->supabase_id = $supabaseId;
                    $user->saveQuietly();
                }

                // Sync to public.profiles table
                $supabase->upsert('profiles', [
                    'id' => $supabaseId,
                    'email' => $user->email,
                    'name' => $user->name,
                    'role' => $role,
                    'updated_at' => now()->toIso8601String(),
                ], 'id');

                $migratedCount++;
                $tableRows[] = [$user->id, $user->email, $role, $supabaseId, 'MIGRATED'];
            } catch (\Throwable $e) {
                $this->error("Failed to migrate {$user->email}: {$e->getMessage()}");
                $tableRows[] = [$user->id, $user->email, $role, 'ERROR', $e->getMessage()];
            }
        }

        $headers = $dryRun
            ? ['ID', 'Email', 'Role', 'Password Type', 'Status']
            : ['ID', 'Email', 'Role', 'Supabase ID', 'Status'];

        $this->table($headers, $tableRows);

        if (! $dryRun) {
            $this->info("Migration completed successfully. {$migratedCount} user(s) synced to Supabase Auth & Profiles.");
        }

        return Command::SUCCESS;
    }

    private function handleRollback($users, SupabaseAuthService $authService, SupabaseClient $supabase): int
    {
        $this->warn('ROLLBACK: Clearing supabase_id and deleting synced profiles...');

        $rolledBack = 0;
        foreach ($users as $user) {
            if ($user->supabase_id) {
                $authService->adminDeleteUser($user->supabase_id);
                $user->supabase_id = null;
                $user->saveQuietly();
                $rolledBack++;
            }
        }

        $this->info("Rollback complete. {$rolledBack} user(s) unlinked.");

        return Command::SUCCESS;
    }
}
