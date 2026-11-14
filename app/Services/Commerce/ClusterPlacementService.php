<?php

namespace App\Services\Commerce;

use App\Exceptions\PlatformRestrictionException;
use App\Models\Commerce\ClusterNode;
use App\Models\Commerce\NodePlacementRule;
use App\Models\Commerce\ProductVariant;
use App\Models\Server;
use App\Models\Team;
use Illuminate\Support\Collection;

class ClusterPlacementService
{
    /**
     * Automatically select the optimal cluster node using multi-factor load scoring and placement rules.
     */
    public static function selectOptimalNode(
        Team $team,
        string $appName,
        ?string $templateName = null,
        ?ProductVariant $variant = null,
        ?int $pinnedNodeId = null
    ): ClusterNode {
        // 1. Explicit owner override
        if ($pinnedNodeId) {
            $pinned = ClusterNode::find($pinnedNodeId);
            if ($pinned && ! $pinned->isOffline() && ! $pinned->isDraining()) {
                return $pinned;
            }
        }

        // 2. Owner placement rules: Team Pinning
        $teamRule = NodePlacementRule::where('rule_type', 'pin_team')
            ->where('target_identifier', (string) $team->id)
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->first();

        if ($teamRule && $teamRule->clusterNode && $teamRule->clusterNode->isReady()) {
            return $teamRule->clusterNode;
        }

        // 3. Owner placement rules: Template Pinning
        if ($templateName) {
            $tmplRule = NodePlacementRule::where('rule_type', 'pin_template')
                ->where('target_identifier', strtolower($templateName))
                ->where('is_active', true)
                ->orderByDesc('priority')
                ->first();

            if ($tmplRule && $tmplRule->clusterNode && $tmplRule->clusterNode->isReady()) {
                return $tmplRule->clusterNode;
            }
        }

        // 4. Ensure at least one cluster node exists
        self::ensureDefaultNode();

        // 5. Query candidate nodes
        $candidates = ClusterNode::where('status', 'ready')->get();

        if ($candidates->isEmpty()) {
            // Fallback: any node that is not draining or maintenance
            $candidates = ClusterNode::whereNotIn('status', ['draining', 'maintenance'])->get();
        }

        if ($candidates->isEmpty()) {
            throw new PlatformRestrictionException('No active cluster nodes are available to host your deployment. Please contact platform support.');
        }

        // Estimate requested RAM and CPU
        $reqRam = $variant?->ram_mb ?: 512;
        $reqVcpu = $variant?->vcpu ?: 1.0;

        // 6. Filter by hard constraints
        $eligible = $candidates->filter(function (ClusterNode $node) use ($templateName, $reqRam) {
            // Check template restrictions
            if ($templateName && ! $node->canHostTemplate($templateName)) {
                return false;
            }

            // Check if node is offline
            if ($node->isOffline()) {
                return false;
            }

            // Check if node has available RAM
            if ($node->availableRamMb() < $reqRam) {
                return false;
            }

            // Check if app capacity limit is reached
            if ($node->activeAppsCount() >= $node->max_apps) {
                return false;
            }

            return true;
        });

        // If hard constraints eliminated all nodes, fallback to node with most free RAM
        if ($eligible->isEmpty()) {
            $fallback = $candidates->sortByDesc(fn (ClusterNode $n) => $n->availableRamMb())->first();
            if ($fallback) {
                return $fallback;
            }

            throw new PlatformRestrictionException('All cluster nodes are currently running at maximum capacity. Please add another node or try again later.');
        }

        // 7. Multi-factor scoring phase
        $scored = $eligible->map(function (ClusterNode $node) use ($reqRam, $reqVcpu) {
            return [
                'node' => $node,
                'score' => $node->calculatePlacementScore($reqRam, $reqVcpu),
            ];
        })->sortByDesc('score');

        return $scored->first()['node'];
    }

    /**
     * Ensure at least one cluster node exists in the system (bootstraps from Server or defaults).
     */
    public static function ensureDefaultNode(): ?ClusterNode
    {
        if (ClusterNode::count() > 0) {
            return ClusterNode::first();
        }

        $server = null;
        try {
            $server = Server::first();
        } catch (\Throwable) {
            $server = null;
        }

        $ip = $server?->ip ?: config('hosting.ingress_ip') ?: '127.0.0.1';
        $name = $server?->name ?: 'primary-node-1';

        return ClusterNode::create([
            'name' => $name,
            'status' => 'ready',
            'public_ip' => $ip,
            'ssh_port' => $server?->port ?: 22,
            'server_id' => $server?->id,
            'region' => 'default',
            'cpu_cores' => 4,
            'total_ram_mb' => 8192,
            'reserved_ram_mb' => 1024,
            'total_disk_gb' => 160,
            'max_apps' => 50,
            'last_heartbeat_at' => now(),
        ]);
    }
}

