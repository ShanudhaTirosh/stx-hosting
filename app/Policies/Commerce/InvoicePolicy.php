<?php

namespace App\Policies\Commerce;

use App\Models\Commerce\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->teams->contains('id', $invoice->team_id);
    }

    public function uploadProof(User $user, Invoice $invoice): bool
    {
        if (!in_array($invoice->status, ['unpaid', 'payment_pending'])) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->teams->contains('id', $invoice->team_id);
    }

    public function approve(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin();
    }

    public function reject(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin();
    }
}

