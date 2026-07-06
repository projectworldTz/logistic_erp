<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'company_name',
        'industry',
        'email',
        'phone',
        'address',
        'city',
        'country',
        'currency',
        'status',
        'assigned_to',
    ];

    protected $casts = [
        'status' => CustomerStatus::class,
    ];

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CustomerMessage::class);
    }
}
