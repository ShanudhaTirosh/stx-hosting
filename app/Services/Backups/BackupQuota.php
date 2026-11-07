<?php

namespace App\Services\Backups;

use App\Models\PlatformSetting;
use App\Models\ScheduledDatabaseBackupExecution;
use App\Models\Team;

/** Backup space per client team. The platform team and staff are never limited. */
class BackupQuota
{
    private const GIB = 1073741824;

    public static function limitGb(Team $team): float
    {
        $override = PlatformSetting::get("backups.quota_gb.{$team->id}");

        return $override !== null ? (float) $override : (float) config('backups.default_quota_gb', 5);
    }

    public static function setOverrideGb(Team $team, ?float $gb): void
    {
        PlatformSetting::put("backups.quota_gb.{$team->id}", $gb === null ? null : (string) max(0, $gb));
    }

    public static function usedBytes(Team $team): int
    {
        return (int) ScheduledDatabaseBackupExecution::whereHas('scheduledDatabaseBackup', fn ($q) => $q->where('team_id', $team->id))
            ->where('s3_uploaded', true)
            ->where(fn ($q) => $q->whereNull('s3_storage_deleted')->orWhere('s3_storage_deleted', false))
            ->sum('size');
    }

    public static function usedGb(Team $team): float
    {
        return round(self::usedBytes($team) / self::GIB, 2);
    }

    /** Why a new backup must not run, or null. */
    public static function blockReason(?Team $team): ?string
    {
        if ($team === null || (int) $team->id === 0) {
            return null;
        }

        if (self::usedBytes($team) >= self::limitGb($team) * self::GIB) {
            return 'Backup space is full ('.self::limitGb($team).' GB). Delete old backups or contact support for more space.';
        }

        return null;
    }
}
