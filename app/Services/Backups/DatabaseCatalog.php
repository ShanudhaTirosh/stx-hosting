<?php

namespace App\Services\Backups;

use App\Models\StandaloneClickhouse;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** The databases of a team that can be dumped and restored (the engines the backup job supports). */
class DatabaseCatalog
{
    public const SUPPORTED = [
        StandalonePostgresql::class,
        StandaloneMysql::class,
        StandaloneMariadb::class,
        StandaloneMongodb::class,
        StandaloneClickhouse::class,
    ];

    /** @return Collection<int, Model> */
    public static function forTeam(Team $team): Collection
    {
        $all = collect();

        foreach (self::SUPPORTED as $class) {
            $all = $all->merge($class::whereHas('environment.project', fn ($q) => $q->where('team_id', $team->id))->orderBy('name')->get());
        }

        return $all->values();
    }

    public static function find(Team $team, string $uuid): ?Model
    {
        return self::forTeam($team)->first(fn ($db) => $db->uuid === $uuid);
    }

    public static function supports(Model $database): bool
    {
        return in_array($database::class, self::SUPPORTED, true);
    }

    public static function teamIdOf(Model $database): ?int
    {
        $team = $database->environment?->project?->team_id;

        return $team === null ? null : (int) $team;
    }

    public static function isRunning(Model $database): bool
    {
        return str_starts_with((string) $database->status, 'running');
    }
}
