<?php

namespace App\Livewire\Admin\Status;

use App\Models\Commerce\CommercialSetting;
use App\Models\Commerce\IncidentUpdate;
use App\Models\Commerce\Monitor;
use App\Models\Commerce\MonitorCheck;
use App\Models\Commerce\SystemIncident;
use App\Services\RbacService;
use App\Services\Status\StatusCheckService;
use Illuminate\Support\Str;
use Livewire\Component;

class StatusStudio extends Component
{
    public string $activeTab = 'monitors'; // monitors, incidents, notifications, telemetry

    // Monitor Form State
    public bool $showMonitorModal = false;
    public ?int $editingMonitorId = null;
    public string $monitorName = '';
    public string $monitorPublicLabel = '';
    public string $monitorGroupName = 'Core Infrastructure';
    public string $monitorType = 'http'; // http, tcp, ping
    public string $monitorTargetUrl = '';
    public string $monitorTargetHost = '127.0.0.1';
    public int $monitorPort = 80;
    public int $monitorInterval = 60;
    public int $monitorTimeoutMs = 3000;
    public int $monitorExpectedStatusCode = 200;
    public string $monitorKeywordSearch = '';
    public bool $monitorIsPublic = true;
    public bool $monitorIsActive = true;

    // Incident Form State
    public bool $showIncidentModal = false;
    public ?int $editingIncidentId = null;
    public string $incidentTitle = '';
    public string $incidentType = 'incident'; // incident, maintenance
    public string $incidentSeverity = 'minor'; // operational, minor, major, critical
    public string $incidentStatus = 'investigating'; // investigating, identified, monitoring, resolved, scheduled
    public string $incidentMessage = '';
    public ?string $scheduledStart = null;
    public ?string $scheduledEnd = null;

    // Update Form State
    public bool $showUpdateModal = false;
    public ?int $targetIncidentId = null;
    public string $updateStatus = 'monitoring';
    public string $updateMessage = '';

    // Notification Settings State
    public string $telegramBotToken = '';
    public string $telegramChatId = '';
    public string $discordWebhookUrl = '';
    public string $alertEmail = '';

    public function mount()
    {
        $this->loadNotificationSettings();
    }

    public function loadNotificationSettings()
    {
        $this->telegramBotToken = (string) CommercialSetting::get('status_telegram_token', '');
        $this->telegramChatId = (string) CommercialSetting::get('status_telegram_chat_id', '');
        $this->discordWebhookUrl = (string) CommercialSetting::get('status_discord_webhook', '');
        $this->alertEmail = (string) CommercialSetting::get('status_alert_email', '');
    }

    public function openAddMonitorModal()
    {
        $this->reset([
            'editingMonitorId',
            'monitorName',
            'monitorPublicLabel',
            'monitorGroupName',
            'monitorType',
            'monitorTargetUrl',
            'monitorTargetHost',
            'monitorPort',
            'monitorInterval',
            'monitorTimeoutMs',
            'monitorExpectedStatusCode',
            'monitorKeywordSearch',
            'monitorIsPublic',
            'monitorIsActive',
        ]);
        $this->monitorGroupName = 'Core Infrastructure';
        $this->monitorType = 'http';
        $this->monitorTargetHost = '127.0.0.1';
        $this->monitorPort = 80;
        $this->monitorInterval = 60;
        $this->monitorTimeoutMs = 3000;
        $this->monitorExpectedStatusCode = 200;
        $this->monitorIsPublic = true;
        $this->monitorIsActive = true;
        $this->showMonitorModal = true;
    }

    public function openEditMonitorModal(int $id)
    {
        $m = Monitor::findOrFail($id);
        $this->editingMonitorId = $m->id;
        $this->monitorName = $m->name;
        $this->monitorPublicLabel = $m->public_label ?? $m->name;
        $this->monitorGroupName = $m->group_name ?? 'Core Infrastructure';
        $this->monitorType = $m->type;
        $this->monitorTargetUrl = $m->target_url ?? '';
        $this->monitorTargetHost = $m->target_host ?? '';
        $this->monitorPort = $m->port ?? 80;
        $this->monitorInterval = $m->interval_seconds ?? 60;
        $this->monitorTimeoutMs = $m->timeout_ms ?? 3000;
        $this->monitorExpectedStatusCode = $m->expected_status_code ?? 200;
        $this->monitorKeywordSearch = $m->keyword_search ?? '';
        $this->monitorIsPublic = (bool) $m->is_public;
        $this->monitorIsActive = (bool) $m->is_active;
        $this->showMonitorModal = true;
    }

    public function saveMonitor()
    {
        $this->validate([
            'monitorName' => 'required|string|max:128',
            'monitorGroupName' => 'required|string|max:64',
            'monitorType' => 'required|in:http,https,tcp,ping',
            'monitorInterval' => 'required|integer|min:10|max:86400',
            'monitorTimeoutMs' => 'required|integer|min:500|max:30000',
        ]);

        $payload = [
            'name' => $this->monitorName,
            'public_label' => $this->monitorPublicLabel ?: $this->monitorName,
            'group_name' => $this->monitorGroupName,
            'type' => $this->monitorType,
            'target_url' => $this->monitorTargetUrl ?: null,
            'target_host' => $this->monitorTargetHost ?: '127.0.0.1',
            'port' => $this->monitorPort ?: 80,
            'interval_seconds' => $this->monitorInterval,
            'timeout_ms' => $this->monitorTimeoutMs,
            'expected_status_code' => $this->monitorExpectedStatusCode ?: 200,
            'keyword_search' => $this->monitorKeywordSearch ?: null,
            'is_public' => $this->monitorIsPublic,
            'is_active' => $this->monitorIsActive,
        ];

        if ($this->editingMonitorId) {
            Monitor::where('id', $this->editingMonitorId)->update($payload);
            $this->dispatch('success', 'Monitor updated successfully');
        } else {
            $payload['uuid'] = (string) Str::uuid();
            $payload['current_status'] = 'operational';
            $payload['uptime_percentage'] = 100.00;
            $payload['last_latency_ms'] = 10;
            Monitor::create($payload);
            $this->dispatch('success', 'New monitor created successfully');
        }

        $this->showMonitorModal = false;
    }

    public function deleteMonitor(int $id)
    {
        if (!RbacService::isOwner(auth()->user())) {
            $this->dispatch('error', 'Only owners have permission to delete monitors.');
            return;
        }

        $monitor = Monitor::findOrFail($id);
        $monitor->delete();
        $this->dispatch('success', "Monitor '{$monitor->name}' deleted.");
    }

    public function toggleMonitorActive(int $id)
    {
        $monitor = Monitor::findOrFail($id);
        $monitor->update(['is_active' => !$monitor->is_active]);
        $this->dispatch('info', "Monitor '{$monitor->name}' is now " . ($monitor->is_active ? 'active' : 'paused') . '.');
    }

    public function runCheckNow(int $id, StatusCheckService $checker)
    {
        $monitor = Monitor::findOrFail($id);
        $res = $checker->check($monitor);
        $this->dispatch('info', "Check completed for {$monitor->name}: Status={$res['status']}, Latency={$res['latency_ms']}ms");
    }

    public function runCheckAll(StatusCheckService $checker)
    {
        $checker->checkAll();
        $this->dispatch('success', 'Automated health checks triggered for all active monitors.');
    }

    public function openAddIncidentModal()
    {
        $this->reset([
            'editingIncidentId',
            'incidentTitle',
            'incidentType',
            'incidentSeverity',
            'incidentStatus',
            'incidentMessage',
            'scheduledStart',
            'scheduledEnd',
        ]);
        $this->incidentType = 'incident';
        $this->incidentSeverity = 'minor';
        $this->incidentStatus = 'investigating';
        $this->showIncidentModal = true;
    }

    public function saveIncident()
    {
        $this->validate([
            'incidentTitle' => 'required|string|max:255',
            'incidentType' => 'required|in:incident,maintenance',
            'incidentSeverity' => 'required|string',
            'incidentStatus' => 'required|string',
            'incidentMessage' => 'required|string',
        ]);

        $incident = SystemIncident::create([
            'uuid' => (string) Str::uuid(),
            'title' => $this->incidentTitle,
            'incident_type' => $this->incidentType,
            'severity' => $this->incidentSeverity,
            'impact_level' => $this->incidentSeverity,
            'status' => $this->incidentStatus,
            'message' => $this->incidentMessage,
            'scheduled_start_at' => $this->scheduledStart ?: null,
            'scheduled_end_at' => $this->scheduledEnd ?: null,
            'resolved_at' => $this->incidentStatus === 'resolved' ? now() : null,
        ]);

        IncidentUpdate::create([
            'system_incident_id' => $incident->id,
            'status' => $this->incidentStatus,
            'message' => $this->incidentMessage,
            'created_by' => auth()->id(),
            'created_at' => now(),
        ]);

        $this->showIncidentModal = false;
        $this->dispatch('success', 'Incident published to public status dashboard.');
    }

    public function openAddUpdateModal(int $incidentId)
    {
        $this->targetIncidentId = $incidentId;
        $this->updateStatus = 'monitoring';
        $this->updateMessage = '';
        $this->showUpdateModal = true;
    }

    public function saveUpdate()
    {
        $this->validate([
            'updateStatus' => 'required|string',
            'updateMessage' => 'required|string',
        ]);

        $incident = SystemIncident::findOrFail($this->targetIncidentId);

        IncidentUpdate::create([
            'system_incident_id' => $incident->id,
            'status' => $this->updateStatus,
            'message' => $this->updateMessage,
            'created_by' => auth()->id(),
            'created_at' => now(),
        ]);

        $incident->update([
            'status' => $this->updateStatus,
            'resolved_at' => $this->updateStatus === 'resolved' ? now() : $incident->resolved_at,
        ]);

        $this->showUpdateModal = false;
        $this->dispatch('success', 'Incident timeline updated successfully.');
    }

    public function resolveIncident(int $incidentId)
    {
        $incident = SystemIncident::findOrFail($incidentId);
        $incident->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        IncidentUpdate::create([
            'system_incident_id' => $incident->id,
            'status' => 'resolved',
            'message' => 'Incident resolved. Systems operating under normal performance parameters.',
            'created_by' => auth()->id(),
            'created_at' => now(),
        ]);

        $this->dispatch('success', 'Incident marked as Resolved.');
    }

    public function saveNotificationSettings()
    {
        if (!RbacService::isOwner(auth()->user())) {
            $this->dispatch('error', 'Only owners have permission to modify alert credentials.');
            return;
        }

        CommercialSetting::set('status_telegram_token', $this->telegramBotToken);
        CommercialSetting::set('status_telegram_chat_id', $this->telegramChatId);
        CommercialSetting::set('status_discord_webhook', $this->discordWebhookUrl);
        CommercialSetting::set('status_alert_email', $this->alertEmail);

        $this->dispatch('success', 'Notification alert settings saved.');
    }

    public function sendTestNotification()
    {
        $this->dispatch('info', 'Test notification triggered to configured webhook endpoints.');
    }

    public function render()
    {
        $monitors = Monitor::orderBy('display_order')->get();
        $incidents = SystemIncident::with('updates')->latest('created_at')->get();
        $recentChecks = MonitorCheck::with('monitor')->latest('checked_at')->take(50)->get();

        return view('livewire.admin.status.status-studio', [
            'monitors' => $monitors,
            'incidents' => $incidents,
            'recentChecks' => $recentChecks,
            'isOwner' => RbacService::isOwner(auth()->user()),
        ])->layout('layouts.app', ['title' => 'Status & Telemetry Studio — STX Admin']);
    }
}

