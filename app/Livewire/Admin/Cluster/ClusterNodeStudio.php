<?php

namespace App\Livewire\Admin\Cluster;

use App\Models\Commerce\ClusterNode;
use App\Models\Commerce\CommerceAuditLog;
use App\Models\Commerce\NodePlacementRule;
use App\Services\Commerce\ClusterPlacementService;
use App\Services\Commerce\NodeMigrationService;
use App\Services\RbacService;
use Livewire\Component;

class ClusterNodeStudio extends Component
{
    public bool $showEnrollModal = false;

    public string $newNodeName = '';

    public string $newNodeRegion = 'default';

    public string $enrollmentCommand = '';

    public ?int $selectedNodeId = null;

    public ?int $drainingNodeId = null;

    public array $drainResults = [];

    // Placement rules state
    public string $ruleType = 'pin_template';

    public string $ruleTarget = '';

    public ?int $ruleNodeId = null;

    public function boot(): void
    {
        if (! RbacService::isPlatformOwner(auth()->user())) {
            abort(403, 'Platform owner access required to manage cluster nodes.');
        }
    }

    public function mount(): void
    {
        ClusterPlacementService::ensureDefaultNode();
    }

    public function openEnrollModal(): void
    {
        $this->newNodeName = 'worker-node-'.(ClusterNode::count() + 1);
        $this->newNodeRegion = 'default';

        $node = ClusterNode::create([
            'name' => $this->newNodeName,
            'status' => 'provisioning',
            'public_ip' => '0.0.0.0',
            'region' => $this->newNodeRegion,
            'cpu_cores' => 2,
            'total_ram_mb' => 4096,
            'reserved_ram_mb' => 512,
            'total_disk_gb' => 80,
            'max_apps' => 50,
        ]);

        CommerceAuditLog::record('cluster.node_enrolled', ClusterNode::class, $node->id, [
            'name' => $node->name,
            'region' => $node->region,
        ]);

        $endpoint = url('/');
        $this->enrollmentCommand = "curl -fsSL {$endpoint}/install-node.sh | sudo bash -s -- --endpoint=\"{$endpoint}\" --token=\"{$node->enrollment_token}\" --name=\"{$node->name}\" --region=\"{$node->region}\"";

        $this->showEnrollModal = true;
    }

    public function closeEnrollModal(): void
    {
        $this->showEnrollModal = false;
        $this->enrollmentCommand = '';
    }

    public function cordonNode(int $nodeId): void
    {
        $node = ClusterNode::findOrFail($nodeId);
        $newStatus = $node->status === 'maintenance' ? 'ready' : 'maintenance';
        $node->update(['status' => $newStatus]);

        CommerceAuditLog::record('cluster.node_cordoned', ClusterNode::class, $node->id, [
            'name' => $node->name,
            'status' => $newStatus,
        ]);

        $this->dispatch('success', "Node '{$node->name}' status updated to {$newStatus}.");
    }

    public function drainNode(int $nodeId): void
    {
        $node = ClusterNode::findOrFail($nodeId);
        $this->drainingNodeId = $nodeId;

        try {
            $this->drainResults = NodeMigrationService::drainNode($node);
            CommerceAuditLog::record('cluster.node_drained', ClusterNode::class, $node->id, [
                'name' => $node->name,
                'results' => $this->drainResults,
            ]);
            $this->dispatch('success', "Node '{$node->name}' is now draining. Applications relocated.");
        } catch (\Throwable $e) {
            $this->dispatch('error', 'Drain failed: '.$e->getMessage());
        } finally {
            $this->drainingNodeId = null;
        }
    }

    public function deleteNode(int $nodeId): void
    {
        $node = ClusterNode::findOrFail($nodeId);

        if ($node->activeAppsCount() > 0) {
            $this->dispatch('error', "Cannot delete node '{$node->name}': It still hosts active applications. Drain the node first.");

            return;
        }

        CommerceAuditLog::record('cluster.node_deleted', ClusterNode::class, $node->id, [
            'name' => $node->name,
        ]);

        $node->delete();
        $this->dispatch('success', 'Node deleted successfully.');
    }

    public function addPlacementRule(): void
    {
        $this->validate([
            'ruleType' => 'required|in:pin_team,pin_template',
            'ruleTarget' => 'required|string',
            'ruleNodeId' => 'required|exists:cluster_nodes,id',
        ]);

        $rule = NodePlacementRule::create([
            'rule_type' => $this->ruleType,
            'target_identifier' => strtolower(trim($this->ruleTarget)),
            'cluster_node_id' => $this->ruleNodeId,
            'is_active' => true,
        ]);

        CommerceAuditLog::record('cluster.rule_created', NodePlacementRule::class, $rule->id, [
            'rule_type' => $this->ruleType,
            'target' => $this->ruleTarget,
        ]);

        $this->ruleTarget = '';
        $this->dispatch('success', 'Placement rule created successfully.');
    }

    public function deleteRule(int $ruleId): void
    {
        $rule = NodePlacementRule::findOrFail($ruleId);
        CommerceAuditLog::record('cluster.rule_deleted', NodePlacementRule::class, $rule->id, [
            'rule_type' => $rule->rule_type,
            'target' => $rule->target_identifier,
        ]);
        $rule->delete();
        $this->dispatch('success', 'Rule deleted.');
    }

    public function render()
    {
        $nodes = ClusterNode::withCount(['serviceInstances' => function ($q) {
            $q->where('status', 'active');
        }])->get();

        $rules = NodePlacementRule::with('clusterNode')->latest()->get();

        $totalCores = $nodes->sum('cpu_cores');
        $totalRamMb = $nodes->sum('total_ram_mb');
        $totalApps = $nodes->sum('service_instances_count');
        $readyNodes = $nodes->where('status', 'ready')->count();

        return view('livewire.admin.cluster.cluster-node-studio', [
            'nodes' => $nodes,
            'rules' => $rules,
            'totalCores' => $totalCores,
            'totalRamGb' => round($totalRamMb / 1024, 1),
            'totalApps' => $totalApps,
            'readyNodes' => $readyNodes,
        ])->layout('layouts.app');
    }
}
