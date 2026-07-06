<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class BillingProfile extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'billing_name',
        'billing_email',
        'billing_phone',
        'billing_address',
        'tax_id',
        'payment_method_type',
        'payment_reference',
    ];
}
