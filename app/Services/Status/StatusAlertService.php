<?php

namespace App\Services\Status;

use App\Models\Commerce\IncidentUpdate;
use App\Models\Commerce\StatusSubscription;
use App\Models\Commerce\SystemIncident;
use App\Notifications\Status\StatusIncidentNotification;
use Illuminate\Support\Facades\Log;

class StatusAlertService
{
    /**
     * Dispatch notification when an incident is opened.
     */
    public function dispatchIncidentOpened(SystemIncident $incident): void
    {
        $this->notifySubscribers($incident, 'new', $incident->message);
    }

    /**
     * Dispatch notification when progress is updated.
     */
    public function dispatchIncidentUpdated(SystemIncident $incident, IncidentUpdate $update): void
    {
        $this->notifySubscribers($incident, 'update', $update->message);
    }

    /**
     * Dispatch notification when an incident is resolved.
     */
    public function dispatchIncidentResolved(SystemIncident $incident, ?string $resolutionMessage = null): void
    {
        $message = $resolutionMessage ?: 'This incident has been resolved and all affected systems are operating normally.';
        $this->notifySubscribers($incident, 'resolved', $message);
    }

    private function notifySubscribers(SystemIncident $incident, string $updateType, ?string $message): void
    {
        $subscribers = StatusSubscription::verifiedAndActive()->get();

        foreach ($subscribers as $subscription) {
            try {
                $subscription->notify(new StatusIncidentNotification($incident, $updateType, $message));
            } catch (\Throwable $e) {
                Log::warning("Could not dispatch status alert to subscriber #{$subscription->id}: {$e->getMessage()}");
            }
        }
    }
}

