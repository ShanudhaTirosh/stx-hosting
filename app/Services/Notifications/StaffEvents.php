<?php

namespace App\Services\Notifications;

use App\Jobs\SendMessageToDiscordJob;
use App\Models\NotificationRoute;
use App\Notifications\Dto\DiscordMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Staff alerts. The business code says "this happened"; the owner decides, per event, which Discord
 * channels hear about it. Alerts never break the thing that triggered them, repeats of the same
 * thing are dropped, and a burst of one kind of event is capped.
 */
class StaffEvents
{
    public const ORDER_CREATED = 'order.created';

    public const PROOF_UPLOADED = 'proof.uploaded';

    public const PROOF_REVIEWED = 'proof.reviewed';

    public const DELIVERY_DUE = 'delivery.due';

    public const TICKET_CREATED = 'ticket.created';

    public const CHAT_MESSAGE = 'chat.message';

    public const NODE_ISSUE = 'node.issue';

    public const BACKUP_FAILED = 'backup.failed';

    /** @return array<string, string> event => what the owner sees */
    public static function labels(): array
    {
        return [
            self::ORDER_CREATED => 'New order',
            self::PROOF_UPLOADED => 'New payment proof',
            self::PROOF_REVIEWED => 'Payment proof approved or rejected',
            self::DELIVERY_DUE => 'VPS delivery reminder (24, 48, 72 hours)',
            self::TICKET_CREATED => 'New support conversation',
            self::CHAT_MESSAGE => 'Customer message in a conversation',
            self::NODE_ISSUE => 'Node or service issue',
            self::BACKUP_FAILED => 'Backup failed',
        ];
    }

    /**
     * @param  'info'|'success'|'warning'|'error'  $level
     * @param  array<string, string>  $fields
     * @param  string|null  $dedupeKey  the same event with the same key is sent once per hour
     */
    public static function notify(string $event, string $title, string $description = '', array $fields = [], string $level = 'info', ?string $dedupeKey = null): void
    {
        try {
            $routes = NotificationRoute::where('event', $event)->where('is_enabled', true)->get();

            if ($routes->isEmpty()) {
                return;
            }

            if ($dedupeKey !== null && ! Cache::add("staff-event:{$event}:".sha1($dedupeKey), 1, 3600)) {
                return;
            }

            if (! RateLimiter::attempt("staff-event:{$event}", 30, fn () => true, 60)) {
                return;
            }

            $message = new DiscordMessage(
                self::clean($title, 200),
                self::clean($description, 1500),
                match ($level) {
                    'success' => DiscordMessage::successColor(),
                    'warning' => DiscordMessage::warningColor(),
                    'error' => DiscordMessage::errorColor(),
                    default => DiscordMessage::infoColor(),
                },
            );

            foreach (array_slice($fields, 0, 8, true) as $name => $value) {
                $message->addField(self::clean((string) $name, 80), self::clean((string) $value, 300) ?: '-', true);
            }

            foreach ($routes as $route) {
                SendMessageToDiscordJob::dispatch($message, $route->webhook_url);
            }
        } catch (\Throwable $e) {
            logger()->warning("Staff alert '{$event}' failed: {$e->getMessage()}");
        }
    }

    /** Customer text goes into a staff channel: no pings, no mass mentions, bounded length. */
    public static function clean(string $text, int $max): string
    {
        $text = str_replace(['@everyone', '@here'], ['@\u{200B}everyone', '@\u{200B}here'], strip_tags($text));
        $text = preg_replace('/<@[!&]?\d+>/', '', $text) ?? '';

        return mb_substr(trim($text), 0, $max);
    }
}
