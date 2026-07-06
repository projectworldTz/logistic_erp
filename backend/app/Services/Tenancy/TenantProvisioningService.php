<?php

namespace App\Services\Tenancy;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Rbac\RolePermissionSeederService;
use App\Services\Uploads\LogoUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class TenantProvisioningService
{
    public function __construct(
        private readonly RolePermissionSeederService $rbacSeeder,
        private readonly AuditLogger $auditLogger,
        private readonly LogoUploadService $logoUploader,
    ) {}

    /**
     * Atomically create a tenant, its owner, and every default record
     * (branch, RBAC roles, subscription, billing profile, dashboard
     * settings). Returns the new tenant, company, and owner user.
     */
    public function provision(array $data, ?UploadedFile $logo = null): array
    {
        return DB::transaction(function () use ($data, $logo) {
            $slug = $this->uniqueSlug($data['company']['name']);

            $tenant = Tenant::query()->create([
                'name' => $data['company']['name'],
                'slug' => $slug,
                'status' => TenantStatus::Trial,
                'timezone' => $data['company']['timezone'],
                'currency' => $data['company']['currency'],
                'trial_ends_at' => now()->addDays(14),
            ]);

            $logoPath = $logo ? $this->logoUploader->store($logo, $slug) : null;

            $company = Company::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $data['company']['name'],
                'registration_number' => $data['company']['registration_number'] ?? null,
                'tax_number' => $data['company']['tax_number'] ?? null,
                'country' => $data['company']['country'],
                'city' => $data['company']['city'],
                'address' => $data['company']['address'],
                'currency' => $data['company']['currency'],
                'timezone' => $data['company']['timezone'],
                'industry' => $data['company']['industry'],
                'logo_path' => $logoPath,
            ]);

            $branch = Branch::query()->create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'name' => 'Main Branch',
                'code' => 'MAIN',
                'is_default' => true,
                'address' => $company->address,
                'city' => $company->city,
                'country' => $company->country,
                'timezone' => $company->timezone,
            ]);

            $this->rbacSeeder->seedForTenant($tenant->id);

            $owner = User::query()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'name' => $data['owner']['name'],
                'email' => $data['owner']['email'],
                'phone' => $data['owner']['phone'] ?? null,
                'password' => $data['owner']['password'],
                'status' => UserStatus::Active,
                'is_super_admin' => false,
                'email_verified_at' => now(),
            ]);

            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
            $owner->assignRole('Company Owner');

            $plan = Plan::query()->where('code', $data['plan_code'])->firstOrFail();

            $subscription = $tenant->subscription()->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Trialing,
                'billing_cycle' => 'monthly',
                'starts_at' => now(),
                'trial_ends_at' => $tenant->trial_ends_at,
            ]);

            $tenant->billingProfile()->create([
                'tenant_id' => $tenant->id,
                'billing_name' => $company->name,
                'billing_email' => $owner->email,
                'billing_phone' => $owner->phone,
                'tax_id' => $company->tax_number,
            ]);

            $tenant->dashboardSetting()->create([
                'tenant_id' => $tenant->id,
                'widgets' => $this->defaultWidgets(),
            ]);

            $this->auditLogger->log(
                action: 'tenant.provisioned',
                auditable: $tenant,
                newValues: ['tenant' => $tenant->name, 'plan' => $plan->code, 'owner' => $owner->email],
                tenantId: $tenant->id,
                userId: $owner->id,
            );

            return compact('tenant', 'company', 'owner', 'branch', 'subscription');
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function defaultWidgets(): array
    {
        return [
            'daily_shipments' => 0,
            'pending_customs' => 0,
            'active_containers' => 0,
            'revenue' => 0,
            'expenses' => 0,
            'outstanding_invoices' => 0,
            'fleet_status' => ['active' => 0, 'maintenance' => 0],
            'warehouse_status' => ['utilization_percent' => 0],
        ];
    }
}
