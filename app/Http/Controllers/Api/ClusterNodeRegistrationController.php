<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Commerce\ClusterNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ClusterNodeRegistrationController extends Controller
{
    /**
     * Return the dynamic 1-line bash onboarding installer script.
     */
    public function installScript(Request $request): Response
    {
        $endpoint = url('/');
        $script = <<<BASH
#!/usr/bin/env bash
set -euo pipefail

echo "========================================================"
echo " STX Hosting - 1-Line Cluster Worker Node Onboarding"
echo "========================================================"

ENDPOINT="{$endpoint}"
TOKEN=""
NAME="worker-$(hostname)"
REGION="default"

while [[ $# -gt 0 ]]; do
  case "\$1" in
    --endpoint=*) ENDPOINT="\${1#*=}"; shift ;;
    --token=*) TOKEN="\${1#*=}"; shift ;;
    --name=*) NAME="\${1#*=}"; shift ;;
    --region=*) REGION="\${1#*=}"; shift ;;
    *) shift ;;
  esac
done

if [[ -z "\$TOKEN" ]]; then
  echo "Error: Missing required --token parameter."
  exit 1
fi

echo ">> Checking system requirements..."
CORES=$(nproc)
TOTAL_RAM_MB=$(free -m | awk '/^Mem:/{print $2}')
TOTAL_DISK_GB=$(df -BG / | awk 'NR==2 {print substr($2, 1, length($2)-1)}')

echo ">> Detected Hardware: \$CORES CPU Cores | \${TOTAL_RAM_MB}MB RAM | \${TOTAL_DISK_GB}GB Disk"

if ! command -v docker &> /dev/null; then
  echo ">> Installing Docker Engine..."
  curl -fsSL https://get.docker.com | sh
  systemctl enable --now docker
else
  echo ">> Docker already installed."
fi

echo ">> Setting up coolify bridge network..."
docker network create --attachable coolify 2>/dev/null || true

echo ">> Registering node with STX Hosting Control Plane..."
REGISTER_RES=$(curl -fsSL -X POST "\${ENDPOINT}/api/v1/cluster/nodes/register" \
  -H "Content-Type: application/json" \
  -d "{\"token\": \"\$TOKEN\", \"name\": \"\$NAME\", \"region\": \"\$REGION\", \"cpu_cores\": \$CORES, \"total_ram_mb\": \$TOTAL_RAM_MB, \"total_disk_gb\": \$TOTAL_DISK_GB}")

echo ">> Registration response: \$REGISTER_RES"
echo ">> Starting Sentinel telemetry daemon..."
docker run -d \
  --name stx-sentinel \
  --restart always \
  -v /var/run/docker.sock:/var/run/docker.sock:ro \
  -v /proc:/host/proc:ro \
  -e SENTINEL_ENDPOINT="\${ENDPOINT}" \
  -e SENTINEL_TOKEN="\$TOKEN" \
  ghcr.io/coollabsio/sentinel:latest 2>/dev/null || true

echo "========================================================"
echo " SUCCESS: Node is registered and READY to host apps!"
echo "========================================================"
BASH;

        return response($script, 200, [
            'Content-Type' => 'text/x-shellscript',
            'Content-Disposition' => 'inline; filename="install-node.sh"',
        ]);
    }

    /**
     * Handshake endpoint for a new node registering with its enrollment token.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'name' => 'nullable|string|max:128',
            'region' => 'nullable|string|max:64',
            'cpu_cores' => 'nullable|integer|min:1',
            'total_ram_mb' => 'nullable|integer|min:256',
            'total_disk_gb' => 'nullable|integer|min:5',
        ]);

        $node = ClusterNode::where('enrollment_token', $validated['token'])->first();

        if (! $node) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid or unrecognized cluster node enrollment token.',
            ], 401);
        }

        $node->update([
            'name' => $validated['name'] ?: $node->name,
            'status' => 'ready',
            'public_ip' => client_ip($request) ?: '127.0.0.1',
            'region' => $validated['region'] ?: $node->region,
            'cpu_cores' => $validated['cpu_cores'] ?: $node->cpu_cores,
            'total_ram_mb' => $validated['total_ram_mb'] ?: $node->total_ram_mb,
            'total_disk_gb' => $validated['total_disk_gb'] ?: $node->total_disk_gb,
            'last_heartbeat_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'node_uuid' => $node->uuid,
            'message' => "Node '{$node->name}' verified and ready.",
        ]);
    }

    /**
     * Heartbeat telemetry ingestion from node Sentinel agent.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'cpu_load' => 'nullable|numeric',
            'ram_used_mb' => 'nullable|integer',
            'disk_used_gb' => 'nullable|integer',
        ]);

        $node = ClusterNode::where('enrollment_token', $validated['token'])->first();

        if (! $node) {
            return response()->json(['status' => 'error', 'message' => 'Node not recognized.'], 404);
        }

        $node->update([
            'current_cpu_load' => $validated['cpu_load'] ?? $node->current_cpu_load,
            'current_ram_used_mb' => $validated['ram_used_mb'] ?? $node->current_ram_used_mb,
            'current_disk_used_gb' => $validated['disk_used_gb'] ?? $node->current_disk_used_gb,
            'last_heartbeat_at' => now(),
        ]);

        return response()->json([
            'status' => 'acknowledged',
            'node_status' => $node->status,
        ]);
    }
}

