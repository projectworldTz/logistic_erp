<?php

namespace App\Models;

use App\Enums\SubscriptionInvoiceStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A SaaS platform bill charged to a TENANT for its own subscription — distinct
 * from the tenant's own Invoice model, which bills THEIR customers within the
 * ERP. Payment collection is not wired to a real gateway; invoices sit as
 * pending/overdue until the platform adds one (mirrors the Beem SMS / Anthropic
 * API pattern of shipping the data model + workflow ahead of live credentials).
 */
class SubscriptionInvoice extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'plan_id',
        'plan_name',
        'amount',
        'currency',
        'period_start',
        'period_end',
        'due_date',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'status' => SubscriptionInvoiceStatus::class,
        'period_start' => 'date',
        'period_end' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
