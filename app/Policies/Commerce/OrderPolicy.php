<?php

namespace App\Policies\Commerce;

use App\Models\Commerce\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->teams->contains('id', $order->team_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Order $order): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isAdminOfTeam($order->team_id);
    }

    public function cancel(User $user, Order $order): bool
    {
        if (!in_array($order->status, ['payment_pending', 'payment_submitted'])) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->teams->contains('id', $order->team_id);
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }
}
