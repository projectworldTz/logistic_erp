<?php

namespace Database\Seeders;

use App\Services\Rbac\RolePermissionSeederService;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(RolePermissionSeederService::class);

        $service->seedPermissions();
        $service->seedGlobalRoles();
    }
}
