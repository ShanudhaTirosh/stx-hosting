<?php

namespace App\Policies\Commerce;

use App\Models\Commerce\Invoice;
use App\Models\User;
use App\Services\RbacService;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if (RbacService::isStaff($user)) {
            return true;
        }

        return $user->canPerformInTeam($invoice->team_id, 'billing.view');
    }

    public function uploadProof(User $user, Invoice $invoice): bool
    {
        if (! in_array($invoice->status, ['unpaid', 'payment_pending'])) {
            return false;
        }

        if (RbacService::isStaff($user)) {
            return true;
        }

        return $user->canPerformInTeam($invoice->team_id, 'billing.manage');
    }

    public function approve(User $user, Invoice $invoice): bool
    {
        return RbacService::isStaff($user);
    }

    public function reject(User $user, Invoice $invoice): bool
    {
        return RbacService::isStaff($user);
    }
}

