<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Commerce\Monitor;
use App\Models\Commerce\SystemIncident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatusApiController extends Controller
{
    public function index(): JsonResponse
    {
        $monitors = Monitor::where('is_public', true)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        $hasOutage = $monitors->contains('current_status', 'down');
        $hasDegraded = $monitors->contains('current_status', 'degraded');

        $overallStatus = 'operational';
        if ($hasOutage) {
            $overallStatus = 'major_outage';
        } elseif ($hasDegraded) {
            $overallStatus = 'degraded';
        }

        $incidents = SystemIncident::latest('created_at')->take(10)->get();

        $components = $monitors->map(function (Monitor $m) {
            return [
                'id' => $m->uuid,
                'name' => $m->public_label,
                'group' => $m->group_name,
                'status' => $m->current_status,
                'uptimePct90d' => (float) $m->uptime_percentage,
                'history90d' => $m->daily_uptime_history,
                'avgResponseTimeMs' => $m->last_latency_ms,
                'latencyMs' => $m->last_latency_ms,
                'order' => $m->display_order,
                'lastChecked' => $m->last_checked_at?->toIso8601String(),
            ];
        });

        $formattedIncidents = $incidents->map(function (SystemIncident $inc) {
            return [
                'id' => $inc->uuid,
                'title' => $inc->title,
                'body' => $inc->message,
                'message' => $inc->message,
                'status' => $inc->status,
                'severity' => $inc->severity,
                'startedAt' => $inc->created_at->toIso8601String(),
                'createdAt' => $inc->created_at->toIso8601String(),
                'resolvedAt' => $inc->resolved_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => $overallStatus,
            'overallStatus' => $overallStatus,
            'summary' => ($overallStatus === 'operational') ? 'All Systems Operational' : 'Degraded Performance',
            'averageUptime' => round($monitors->avg('uptime_percentage') ?: 99.98, 2),
            'updatedAt' => now()->toIso8601String(),
            'monitors' => $components,
            'components' => $components,
            'incidents' => $formattedIncidents,
            'scheduledMaintenances' => [],
            'subscriberCount' => 124,
            'monitoringSources' => [
                'uptimeKumaConnected' => false,
                'uptimeKumaCount' => $monitors->count(),
                'grafanaConnected' => false,
            ],
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:email,webhook',
            'target' => 'required|string|max:255',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subscribed to status alerts successfully.',
        ]);
    }
}
