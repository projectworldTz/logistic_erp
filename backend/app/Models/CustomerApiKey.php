<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Deliberately does NOT use BelongsToTenant — the plaintext key is the only
 * credential AuthenticateClientApiKey has to resolve a tenant from, so this
 * table must be queryable before TenantContext is set.
 */
class CustomerApiKey extends Model
{
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'created_by',
        'name',
        'key_prefix',
        'key_hash',
        'last_used_at',
        'revoked_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Returns [model, plaintext key]. The plaintext is only ever available
     * here, at creation time — only its SHA-256 hash is persisted.
     */
    public static function generate(int $tenantId, int $customerId, string $name, ?int $createdBy): array
    {
        $plaintext = 'cak_'.Str::random(40);

        $model = static::create([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'created_by' => $createdBy,
            'name' => $name,
            'key_prefix' => substr($plaintext, 0, 12),
            'key_hash' => hash('sha256', $plaintext),
        ]);

        return [$model, $plaintext];
    }
}
