<?php

namespace App\Services;

use DateTimeInterface;

class StxUpgradeStatus
{
    public const FILE = '/data/stx/source/.upgrade-status';

    public const LEGACY_FILE = '/data/coolify/source/.upgrade-status';

    public const STALE_AFTER_MINUTES = 10;

    /**
     * An upgrade that has not written a status for this long is treated as stopped,
     * so a crashed upgrade does not block new ones forever.
     */
    public const RUNNING_LOCK_EXPIRES_AFTER_MINUTES = 15;

    /**
     * @return array{status: string, step?: int, message?: string, running_version: string, target_version: string}
     */
    public static function fromFile(
        string $content,
        string $runningVersion,
        string $targetVersion,
        ?DateTimeInterface $now = null,
        int $staleAfterMinutes = self::STALE_AFTER_MINUTES,
    ): array {
        $base = [
            'running_version' => $runningVersion,
            'target_version' => $targetVersion,
        ];

        $content = trim($content);
        if ($content === '') {
            return ['status' => 'none', ...$base];
        }

        $parts = explode('|', $content);
        if (count($parts) < 3) {
            return ['status' => 'none', ...$base];
        }

        [$step, $message, $timestamp] = $parts;

        try {
            $statusTime = new \DateTime($timestamp);
            $now = $now ?? new \DateTime;
            $diffMinutes = ($now->getTimestamp() - $statusTime->getTimestamp()) / 60;

            if ($diffMinutes > $staleAfterMinutes) {
                return ['status' => 'none', ...$base];
            }

            if ($step === 'error') {
                return [
                    'status' => 'error',
                    'message' => $message,
                    ...$base,
                ];
            }

            $stepInt = (int) $step;
            if ($stepInt >= 6) {
                return [
                    'status' => 'completed',
                    'step' => $stepInt,
                    'message' => $message,
                    ...$base,
                ];
            }

            return [
                'status' => 'in_progress',
                'step' => $stepInt,
                'message' => $message,
                ...$base,
            ];
        } catch (\Throwable) {
            return ['status' => 'none', ...$base];
        }
    }

    /**
     * True when the status file records an in-progress upgrade whose last update is
     * recent enough that we should not start another upgrade yet.
     */
    public static function isRunning(string $content, ?DateTimeInterface $now = null): bool
    {
        $content = trim($content);
        if ($content === '') {
            return false;
        }

        $parts = explode('|', $content);
        if (count($parts) < 3) {
            return false;
        }

        [$step, , $timestamp] = $parts;
        if ($step === 'error' || (int) $step >= 6) {
            return false;
        }

        try {
            $statusTime = new \DateTime($timestamp);
            $now = $now ?? new \DateTime;
            $diffMinutes = ($now->getTimestamp() - $statusTime->getTimestamp()) / 60;

            return $diffMinutes >= 0 && $diffMinutes < self::RUNNING_LOCK_EXPIRES_AFTER_MINUTES;
        } catch (\Throwable) {
            return false;
        }
    }
}
