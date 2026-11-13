<?php

namespace App\Livewire\Status;

use App\Models\Commerce\Monitor;
use App\Models\Commerce\StatusSubscription;
use App\Models\Commerce\SystemIncident;
use App\Notifications\Status\VerifyStatusSubscriptionNotification;
use Illuminate\Support\Str;
use Livewire\Component;

class PublicStatus extends Component
{
    public string $historyWindow = '60m'; // 60m, 24h, 30d, 90d
    public string $searchQuery = '';

    public bool $showSubscribeModal = false;
    public string $subscribeEmail = '';
    public ?string $subscribeMessage = null;

    public function setHistoryWindow(string $window): void
    {
        $this->historyWindow = in_array($window, ['60m', '24h', '30d', '90d']) ? $window : '60m';
    }

    public function openSubscribe(): void
    {
        $this->showSubscribeModal = true;
        $this->subscribeMessage = null;
    }

    public function closeSubscribe(): void
    {
        $this->showSubscribeModal = false;
        $this->subscribeEmail = '';
        $this->subscribeMessage = null;
    }

    public function subscribe(): void
    {
        $this->validate([
            'subscribeEmail' => 'required|email|max:255',
        ]);

        $email = strtolower(trim($this->subscribeEmail));
        $subscription = StatusSubscription::where('email', $email)->first();

        if ($subscription && $subscription->isVerified()) {
            $this->subscribeMessage = 'You are already subscribed to STX Status alerts!';
            return;
        }

        if (! $subscription) {
            $subscription = StatusSubscription::create([
                'email' => $email,
                'verification_token' => Str::random(40),
                'unsubscribe_token' => Str::random(40),
                'is_active' => true,
            ]);
        } else {
            $subscription->update([
                'verification_token' => Str::random(40),
                'is_active' => true,
            ]);
        }

        try {
            $subscription->notify(new VerifyStatusSubscriptionNotification($subscription));
            $this->subscribeMessage = 'Confirmation email sent! Please check your inbox and click the link to activate alerts.';
            $this->subscribeEmail = '';
        } catch (\Throwable $e) {
            $this->addError('subscribeEmail', 'Could not send verification email: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $query = Monitor::withCount('checks')
            ->where('is_public', true)
            ->where('is_active', true)
            ->orderBy('display_order');

        if (!empty($this->searchQuery)) {
            $term = '%' . strtolower(trim($this->searchQuery)) . '%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(name) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(COALESCE(target_host, \'\')) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(COALESCE(target_url, \'\')) LIKE ?', [$term]);
            });
        }

        $monitors = $query->get();

        $activeIncidents = SystemIncident::with('updates')
            ->where('status', '!=', 'resolved')
            ->latest('created_at')
            ->get();

        $hasOutage = $monitors->contains('current_status', 'down');
        $hasDegraded = $monitors->contains('current_status', 'degraded');
        $hasMaintenance = $activeIncidents->contains('incident_type', 'maintenance');

        $overallStatusText = 'All systems normal';
        $overallStatusColor = 'emerald';
        if ($hasOutage) {
            $overallStatusText = 'Major System Outage';
            $overallStatusColor = 'rose';
        } elseif ($hasDegraded) {
            $overallStatusText = 'Partial Degradation';
            $overallStatusColor = 'amber';
        } elseif ($hasMaintenance) {
            $overallStatusText = 'Scheduled Maintenance';
            $overallStatusColor = 'purple';
        }

        $windowLabel = match ($this->historyWindow) {
            '60m' => '60 MINUTES AGO',
            '24h' => '24 HOURS AGO',
            '30d' => '30 DAYS AGO',
            '90d' => '90 DAYS AGO',
            default => '60 MINUTES AGO',
        };

        return view('livewire.status.public-status', [
            'monitors' => $monitors,
            'activeIncidents' => $activeIncidents,
            'overallStatusText' => $overallStatusText,
            'overallStatusColor' => $overallStatusColor,
            'windowLabel' => $windowLabel,
        ])->layout('layouts.status', [
            'title' => 'STX Status — Cloud & Infrastructure Health',
        ]);
    }
}
