<?php

namespace App\Services\Backups;

use App\Models\PlatformSetting;
use App\Models\S3Storage;
use Illuminate\Support\Collection;

/**
 * The one R2 (S3 compatible) bucket the platform keeps client backups in. It belongs to the root team, so
 * clients never see its keys; each team's files sit under their own folder (Coolify already names it
 * databases/<team>-<id>/...).
 */
class PlatformBackupStorage
{
    /** Replace in tests: fn (S3Storage $storage): void, throws when the connection does not work. */
    public static ?\Closure $connectionTester = null;

    /** Replace in tests: fn (S3Storage $storage, string $key, int $minutes): string */
    public static ?\Closure $presigner = null;

    public static function storage(): ?S3Storage
    {
        $uuid = PlatformSetting::get('backups.storage_uuid') ?: config('backups.storage_uuid');

        if (blank($uuid)) {
            return null;
        }

        return S3Storage::where('uuid', $uuid)->where('team_id', 0)->where('is_usable', true)->first();
    }

    /** @return Collection<int, S3Storage> storages the owner can choose from */
    public static function candidates(): Collection
    {
        return S3Storage::where('team_id', 0)->orderBy('name')->get();
    }

    public static function choose(S3Storage $storage): void
    {
        if ((int) $storage->team_id !== 0) {
            throw new \InvalidArgumentException('Only a storage owned by the platform team can hold client backups.');
        }

        PlatformSetting::put('backups.storage_uuid', $storage->uuid);
    }

    /**
     * Check the bucket really works and remember the result (only a usable storage is used for backups).
     *
     * @return string|null null when it works, otherwise a message for the owner
     */
    public static function verify(S3Storage $storage): ?string
    {
        try {
            self::$connectionTester ? (self::$connectionTester)($storage) : $storage->testConnection(true);

            return null;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    /** Link that works for a few minutes, for one file. */
    public static function presign(S3Storage $storage, string $key, string $downloadName, int $minutes = 10): string
    {
        $key = ltrim($key, '/');

        if (self::$presigner) {
            return (self::$presigner)($storage, $key, $minutes);
        }

        return $storage->filesystem()->temporaryUrl($key, now()->addMinutes($minutes), [
            'ResponseContentDisposition' => 'attachment; filename="'.str_replace(['"', '\\', "\r", "\n"], '', $downloadName).'"',
        ]);
    }
}
