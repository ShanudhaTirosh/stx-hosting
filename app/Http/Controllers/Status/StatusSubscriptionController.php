<?php

namespace App\Http\Controllers\Status;

use App\Http\Controllers\Controller;
use App\Models\Commerce\StatusSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StatusSubscriptionController extends Controller
{
    public function verify(string $token): RedirectResponse
    {
        $subscription = StatusSubscription::where('verification_token', $token)->first();

        if (! $subscription) {
            return redirect()->route('status.public')
                ->with('error', 'Invalid or expired verification link.');
        }

        $subscription->update([
            'verified_at' => now(),
            'verification_token' => null,
            'is_active' => true,
        ]);

        return redirect()->route('status.public')
            ->with('success', 'Your subscription to STX Status alerts is now active! You will receive email notifications when incidents or maintenance occur.');
    }

    public function unsubscribe(string $token): RedirectResponse
    {
        $subscription = StatusSubscription::where('unsubscribe_token', $token)->first();

        if (! $subscription) {
            return redirect()->route('status.public')
                ->with('error', 'Subscription not found.');
        }

        $subscription->update([
            'is_active' => false,
        ]);

        return redirect()->route('status.public')
            ->with('success', 'You have been unsubscribed from STX Status alerts.');
    }
}

