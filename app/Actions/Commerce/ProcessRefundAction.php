<?php

namespace App\Actions\Commerce;

use App\Models\Commerce\Invoice;
use App\Models\User;
use App\Services\Commerce\RefundService;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessRefundAction
{
    use AsAction;

    public function handle(
        Invoice $invoice,
        int $amountCents,
        string $reason,
        User $processor,
        string $refundMethod = 'manual',
        bool $revokeEntitlements = true
    ): Invoice {
        return app(RefundService::class)->refund(
            $invoice,
            $amountCents,
            $reason,
            $processor,
            $refundMethod,
            $revokeEntitlements
        );
    }
}

