<?php

namespace App\Livewire\Admin\Notifications;

use App\Jobs\SendMessageToDiscordJob;
use App\Models\Commerce\CommerceAuditLog;
use App\Models\NotificationRoute;
use App\Notifications\Dto\DiscordMessage;
use App\Services\Notifications\StaffEvents;
use App\Services\RbacService;
use Illuminate\Validation\Rule;
use Livewire\Component;

/** Owner decides which Discord channel hears about which event. */
class DiscordRoutes extends Component
{
    public string $event = StaffEvents::ORDER_CREATED;

    public string $label = '';

    public string $webhookUrl = '';

    public function boot(): void
    {
        abort_unless(RbacService::can(auth()->user(), 'notifications.manage'), 403, 'Only the owner can manage alert channels.');
    }

    public function add(): void
    {
        $this->validate([
            'event' => ['required', Rule::in(array_keys(StaffEvents::labels()))],
            'label' => 'nullable|string|max:120',
            'webhookUrl' => [
                'required', 'url', 'max:500',
                // Only real Discord webhooks, so this cannot be used to make the server call arbitrary addresses
                // (the send job also applies the SSRF checks every time).
                'regex:#^https://(discord|discordapp)\.com/api/webhooks/\d+/[\w-]+$#',
            ],
        ], ['webhookUrl.regex' => 'That does not look like a Discord webhook URL (https://discord.com/api/webhooks/...).']);

        $route = NotificationRoute::create([
            'event' => $this->event,
            'label' => trim($this->label) ?: null,
            'webhook_url' => $this->webhookUrl,
            'is_enabled' => true,
            'created_by' => auth()->id(),
        ]);

        CommerceAuditLog::record('notification_route.created', NotificationRoute::class, $route->id, ['event' => $this->event]);

        $this->reset(['label', 'webhookUrl']);
        $this->dispatch('success', 'Channel added. Use Test to check it.');
    }

    public function toggle(int $id): void
    {
        $route = NotificationRoute::findOrFail($id);
        $route->update(['is_enabled' => ! $route->is_enabled]);
    }

    public function test(int $id): void
    {
        $route = NotificationRoute::findOrFail($id);

        SendMessageToDiscordJob::dispatch(
            new DiscordMessage('Test alert', 'If you can read this, '.(StaffEvents::labels()[$route->event] ?? $route->event).' alerts will arrive here.', DiscordMessage::successColor()),
            $route->webhook_url
        );

        $this->dispatch('success', 'Test message sent.');
    }

    public function delete(int $id): void
    {
        $route = NotificationRoute::findOrFail($id);
        CommerceAuditLog::record('notification_route.deleted', NotificationRoute::class, $route->id, ['event' => $route->event]);
        $route->delete();
    }

    public function render()
    {
        return view('livewire.admin.notifications.discord-routes', [
            'events' => StaffEvents::labels(),
            'routes' => NotificationRoute::orderBy('event')->orderBy('id')->get()->groupBy('event'),
        ]);
    }
}
