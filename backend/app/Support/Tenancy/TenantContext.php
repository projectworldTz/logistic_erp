<?php

namespace App\Support\Tenancy;

class TenantContext
{
    private ?int $tenantId = null;

    public function set(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function hasTenant(): bool
    {
        return $this->tenantId !== null;
    }

    public function clear(): void
    {
        $this->tenantId = null;
    }
}
