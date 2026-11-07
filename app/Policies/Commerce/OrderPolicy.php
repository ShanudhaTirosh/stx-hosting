<?php

namespace App\Policies\Commerce;

use App\Models\Commerce\Order;
use App\Models\User;
use App\Services\RbacService;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        if (RbacService::isStaff($user)) {
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
        if (RbacService::isStaff($user)) {
            return true;
        }

        return $user->isAdminOfTeam($order->team_id);
    }

    public function cancel(User $user, Order $order): bool
    {
        if (!in_array($order->status, ['pending', 'payment_pending', 'payment_submitted'])) {
            return false;
        }

        if (RbacService::isStaff($user)) {
            return true;
        }

        return $user->teams->contains('id', $order->team_id);
    }

    public function delete(User $user, Order $order): bool
    {
        return RbacService::isStaff($user);
    }
}

