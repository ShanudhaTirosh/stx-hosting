<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * HostingInvoice — Financial record attached to a team's purchase.
 */
class HostingInvoice extends Model
{
    use HasFactory;

    protected $table = 'hosting_invoices';

    protected $fillable = [
        'team_id',
        'hosting_plan_id',
        'amount_cents',
        'currency',
        'status',
        'due_at',
        'paid_at',
        'metadata',
    ];

    protected $casts = [
        'team_id'        => 'integer',
        'hosting_plan_id'=> 'integer',
        'amount_cents'   => 'integer',
        'due_at'         => 'datetime',
        'paid_at'        => 'datetime',
        'metadata'       => 'array',
    ];

    /**
     * Valid invoice statuses.
     */
    public const STATUSES = ['draft', 'unpaid', 'payment_pending', 'paid', 'overdue', 'cancelled', 'refunded'];
}

