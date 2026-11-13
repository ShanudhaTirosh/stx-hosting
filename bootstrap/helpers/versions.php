<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Get the versions of the channel this instance follows: its STX Hosting version and the helper, Sentinel and Traefik versions released with it.
 *
 * This function provides a centralized, cached access point for all
 * version data in the application. It reads the copy of versions.json of the CDN
 * that refresh_versions_file() keeps on this node, or the upstream.json of the image until then.
 * Data is cached in Redis for 1 hour and shared across all servers in the cluster.
 *
 * @return array{coolify: array{version: string}, stx?: array{version: string}, helper?: array{version: string}, sentinel?: array{version: string}, traefik?: array<string, string>}
 */
function get_versions_data(): array
{
    return Cache::remember('stx:versions', 3600, function () {
        $versionsPath = storage_path('app/versions.json');
        $currentAppVersion = config('constants.stx.version', config('constants.coolify.version'));
        if (! File::exists($versionsPath)) {
            $fallback = File::exists(base_path('upstream.json')) ? json_decode(File::get(base_path('upstream.json')), true) : [];

            return [
                'stx' => ['version' => $currentAppVersion],
                'coolify' => ['version' => $currentAppVersion],
            ] + $fallback;
        }
        $channels = json_decode(File::get($versionsPath), true);
        $channel = newest_update_channel($channels);
        $channelData = $channels[$channel] ?? [];
        $versions = $channelData + ['upgrade_script_url' => upgrade_script_url($channel)];

        $cdnVersion = data_get($versions, 'stx.version', data_get($versions, 'coolify.version', $currentAppVersion));
        if (version_compare($currentAppVersion, $cdnVersion, '>')) {
            data_set($versions, 'stx.version', $currentAppVersion);
            data_set($versions, 'coolify.version', $currentAppVersion);
        }

        return $versions;
    });
}

/**
 * Get Traefik versions from cached data.
 *
 * @return array|null Array of Traefik versions (e.g., ['v3.5' => '3.5.6'])
 */
function get_traefik_versions(): ?array
{
    $versions = get_versions_data();

    if (! $versions) {
        return null;
    }

    $traefikVersions = data_get($versions, 'traefik');

    return is_array($traefikVersions) ? $traefikVersions : null;
}

/**
 * Invalidate the versions cache.
 * Call this after updating versions.json to ensure fresh data is loaded.
 */
function invalidate_versions_cache(): void
{
    Cache::forget('stx:versions');
    Cache::forget('coolify:versions');
    Cache::forget('stx:versions:all');
    Cache::forget('coolify:versions:all');
}

/**
 * HTTP client for the CDN. It retries only transient failures (connection errors, 429, 5xx)
 * and does not throw for a failed response.
 */
function cdn_http(int $tries = 3): PendingRequest
{
    return Http::timeout(10)
        ->connectTimeout(10)
        ->retry(
            $tries,
            fn (int $attempt, Throwable $exception) => cdn_retry_delay_ms($attempt, $exception),
            fn (Throwable $exception) => $exception instanceof ConnectionException
                || ($exception instanceof RequestException
                    && ($exception->response->status() === 429 || $exception->response->serverError())),
            throw: false,
        );
}

/**
 * Wait as long as the Retry-After header says (Cloudflare sends 60 seconds with error 1200),
 * or 2^attempt seconds without it. A random part of up to the same length is added, so instances
 * that failed in the same burst do not retry in the same second.
 */
function cdn_retry_delay_ms(int $attempt, Throwable $exception): int
{
    $retryAfter = $exception instanceof RequestException ? (int) $exception->response->header('Retry-After') : 0;
    $seconds = $retryAfter > 0 ? min($retryAfter, 60) : 2 ** $attempt;

    return ($seconds + random_int(0, $seconds)) * 1000;
}

/**
 * Fetch versions.json from the CDN and take the block of the newest version this instance takes.
 * Only the stable, preview and nightly keys are read.
 *
 * @return array{channels: array, versions: array{coolify: array{version: string}}, upgrade_script_url: string}|null
 */
function fetch_latest_versions(int $tries = 3): ?array
{
    $versionsUrl = config('constants.stx.versions_url', config('constants.coolify.versions_url'));
    $response = cdn_http($tries)->get($versionsUrl);
    $channels = $response->json();
    if ($response->failed() || ! is_array($channels)) {
        return null;
    }
    $channel = newest_update_channel($channels);

    return [
        'channels' => $channels,
        'versions' => $channels[$channel] ?? [],
        'upgrade_script_url' => upgrade_script_url($channel),
    ];
}

/**
 * The upgrade script of an update channel on the CDN.
 */
function upgrade_script_url(string $channel): string
{
    $defaultUpgradeScript = config('constants.stx.upgrade_script_url', config('constants.coolify.upgrade_script_url'));
    $versionsUrl = config('constants.stx.versions_url', config('constants.coolify.versions_url'));

    return $channel === 'stable' ? $defaultUpgradeScript : dirname($versionsUrl)."/{$channel}/upgrade.sh";
}

/**
 * The channel of the newest version this instance takes: its update channel and the more stable ones.
 *
 * @param  array<string, mixed>  $versions  The versions.json of the CDN
 */
function newest_update_channel(array $versions): string
{
    $settings = instanceSettings();
    $channels = ['stable', 'preview', 'nightly'];
    $follows = (int) array_search($settings->update_channel ?? 'stable', $channels, true);
    $followsUntilRelease = $settings->previous_update_channel ? (int) array_search($settings->previous_update_channel, $channels, true) : -1;
    $currentAppVersion = config('constants.stx.version', config('constants.coolify.version'));
    $release = preg_replace('/^(\d+\.\d+\.\d+).*/', '$1', $currentAppVersion);
    $newest = 'stable';
    foreach ($channels as $rank => $channel) {
        $version = data_get($versions, "{$channel}.stx.version", data_get($versions, "{$channel}.coolify.version"));
        $newestVersion = (string) data_get($versions, "{$newest}.stx.version", data_get($versions, "{$newest}.coolify.version"));
        if (! is_string($version) || version_compare($version, $newestVersion, '<=')) {
            continue;
        }
        if ($rank <= $follows || ($rank <= $followsUntilRelease && version_compare($version, $release, '<'))) {
            $newest = $channel;
        }
    }

    return $newest;
}

/**
 * Fetch versions.json from the CDN and replace the copy of it on this node in one step.
 *
 * @return array|null The versions of the channel of this instance, or null if the CDN fetch failed
 */
function refresh_versions_file(int $tries = 3): ?array
{
    $versionsPath = storage_path('app/versions.json');
    $latest = fetch_latest_versions($tries);
    if ($latest === null) {
        Log::warning('Could not fetch versions.json from the CDN, using the local file');

        return null;
    }

    $latestVersion = data_get($latest, 'versions.stx.version', data_get($latest, 'versions.coolify.version'));
    $currentVersion = config('constants.stx.version', config('constants.coolify.version'));

    $existingAppVersion = null;
    if (File::exists($versionsPath)) {
        $existingChannels = json_decode(File::get($versionsPath), true);
        $activeChannel = newest_update_channel($existingChannels);
        $existingAppVersion = data_get($existingChannels, "{$activeChannel}.stx.version", data_get($existingChannels, "{$activeChannel}.coolify.version"));
    }

    if ($existingAppVersion && $latestVersion && version_compare($existingAppVersion, $latestVersion, '>')) {
        Log::warning('CDN served older version than cache', [
            'cdn_version' => $latestVersion,
            'cached_version' => $existingAppVersion,
            'current_version' => $currentVersion,
        ]);

        return get_versions_data();
    }

    if ($latestVersion && version_compare($latestVersion, $currentVersion, '<')) {
        Log::warning('Version downgrade prevented in CheckForUpdatesJob', [
            'cdn_version' => $latestVersion,
            'current_version' => $currentVersion,
        ]);
    }

    File::replace($versionsPath, json_encode($latest['channels'], JSON_PRETTY_PRINT));
    invalidate_versions_cache();
    $versions = get_versions_data();
    update_helper_version($versions);

    return $versions;
}

/**
 * Store the helper image version of the versions data when it is newer than the stored one.
 */
function update_helper_version(?array $versions): void
{
    $latestVersion = data_get($versions, 'stx.helper.version', data_get($versions, 'helper.version'));
    $settings = instanceSettings();
    if ($latestVersion && version_compare($latestVersion, (string) $settings->helper_version, '>')) {
        $settings->update(['helper_version' => $latestVersion]);
    }
}
