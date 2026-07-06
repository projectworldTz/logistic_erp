<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Rbac\PermissionRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('rbac.super_admin.email') ?? env('SUPER_ADMIN_EMAIL', 'admin@example.com');
        $password = config('rbac.super_admin.password') ?? env('SUPER_ADMIN_PASSWORD', 'password');
        $name = config('rbac.super_admin.name') ?? env('SUPER_ADMIN_NAME', 'Platform Super Admin');

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'is_super_admin' => true,
                'status' => 'active',
                'tenant_id' => null,
                'email_verified_at' => now(),
            ],
        );

        app(PermissionRegistrar::class)->setPermissionsTeamId(PermissionRegistry::PLATFORM_TEAM_ID);
        $user->syncRoles(['Super Admin']);
    }
}
