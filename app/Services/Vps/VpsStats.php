<?php

namespace App\Services\Vps;

use App\Models\Commerce\ServiceInstance;
use App\Models\Commerce\VpsStatSample;

/**
 * Reads CPU, memory, disk, load, uptime and network counters from a VPS in one SSH round trip
 * and stores a sample. Bandwidth is the sum of the counter changes between samples.
 */
class VpsStats
{
    /** One command, key=value lines. Two /proc/stat readings one second apart give the CPU percentage. */
    public const COMMAND = <<<'SH'
a=$(head -1 /proc/stat); sleep 1; b=$(head -1 /proc/stat)
echo "cpu_a=$a"
echo "cpu_b=$b"
echo "mem=$(grep -E '^(MemTotal|MemAvailable):' /proc/meminfo | awk '{print $2}' | tr '\n' ' ')"
echo "disk=$(df -P -B1 / | tail -1 | awk '{print $2, $3}')"
echo "load=$(cut -d' ' -f1 /proc/loadavg)"
echo "uptime=$(cut -d. -f1 /proc/uptime)"
echo "net=$(awk 'NR>2 && $1 !~ /^lo:/ {gsub(":", " ", $0); rx+=$2; tx+=$10} END {print rx+0, tx+0}' /proc/net/dev)"
SH;

    /** @return array<string, int|float|null> */
    public static function parse(string $output): array
    {
        $values = [];
        foreach (preg_split('/\R/', trim($output)) as $line) {
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $values[trim($key)] = trim($value);
            }
        }

        $mem = array_map('intval', preg_split('/\s+/', $values['mem'] ?? '', -1, PREG_SPLIT_NO_EMPTY));
        $disk = array_map('floatval', preg_split('/\s+/', $values['disk'] ?? '', -1, PREG_SPLIT_NO_EMPTY));
        $net = array_map('intval', preg_split('/\s+/', $values['net'] ?? '', -1, PREG_SPLIT_NO_EMPTY));

        $memTotalKb = $mem[0] ?? 0;
        $memAvailableKb = $mem[1] ?? 0;

        return [
            'cpu_percent' => self::cpuPercent($values['cpu_a'] ?? '', $values['cpu_b'] ?? ''),
            'mem_total_mb' => (int) round($memTotalKb / 1024),
            'mem_used_mb' => (int) round(max(0, $memTotalKb - $memAvailableKb) / 1024),
            'disk_total_gb' => (int) round(($disk[0] ?? 0) / 1_073_741_824),
            'disk_used_gb' => round(($disk[1] ?? 0) / 1_073_741_824, 2),
            'load1' => isset($values['load']) ? (float) $values['load'] : null,
            'uptime_seconds' => (int) ($values['uptime'] ?? 0),
            'rx_bytes' => $net[0] ?? 0,
            'tx_bytes' => $net[1] ?? 0,
        ];
    }

    /** "cpu  user nice system idle iowait irq softirq steal ..." twice; percentage of non-idle time between them. */
    public static function cpuPercent(string $a, string $b): ?float
    {
        $one = self::cpuTimes($a);
        $two = self::cpuTimes($b);

        if ($one === null || $two === null) {
            return null;
        }

        $total = $two['total'] - $one['total'];
        $idle = $two['idle'] - $one['idle'];

        return $total > 0 ? round(max(0, min(100, (1 - $idle / $total) * 100)), 1) : null;
    }

    /** @return array{total: int, idle: int}|null */
    private static function cpuTimes(string $line): ?array
    {
        $parts = preg_split('/\s+/', trim($line));

        if (($parts[0] ?? '') !== 'cpu' || count($parts) < 5) {
            return null;
        }

        $numbers = array_map('intval', array_slice($parts, 1, 8));

        return ['total' => array_sum($numbers), 'idle' => ($numbers[3] ?? 0) + ($numbers[4] ?? 0)];
    }

    /** Collect one sample from a connected VPS and save it with the traffic since the previous sample. */
    public static function collect(ServiceInstance $service, RemoteShell $shell): VpsStatSample
    {
        $reading = self::parse($shell->exec(self::COMMAND, 20));

        $previous = VpsStatSample::where('service_instance_id', $service->id)->latest('collected_at')->first();

        // Counters go back to zero after a reboot: then there is no usable difference.
        $rxDelta = $txDelta = null;
        if ($previous && $reading['rx_bytes'] >= $previous->rx_bytes && $reading['tx_bytes'] >= $previous->tx_bytes) {
            $rxDelta = $reading['rx_bytes'] - $previous->rx_bytes;
            $txDelta = $reading['tx_bytes'] - $previous->tx_bytes;
        }

        return VpsStatSample::create($reading + [
            'service_instance_id' => $service->id,
            'rx_delta' => $rxDelta,
            'tx_delta' => $txDelta,
            'collected_at' => now(),
        ]);
    }

    /** Traffic in GB since the start of the current month. */
    public static function monthlyTrafficGb(ServiceInstance $service): float
    {
        $bytes = VpsStatSample::where('service_instance_id', $service->id)
            ->where('collected_at', '>=', now()->startOfMonth())
            ->selectRaw('coalesce(sum(rx_delta), 0) + coalesce(sum(tx_delta), 0) as total')
            ->value('total');

        return round(((float) $bytes) / 1_000_000_000, 2);
    }
}
