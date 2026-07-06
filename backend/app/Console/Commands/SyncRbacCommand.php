<?php

namespace App\Console\Commands;

use App\Services\Rbac\RolePermissionSeederService;
use Illuminate\Console\Command;

class SyncRbacCommand extends Command
{
    protected $signature = 'rbac:sync';

    protected $description = "Sync the permission catalog and re-sync every existing role's permissions from config/rbac.php (idempotent; no migrate:fresh required)";

    public function handle(RolePermissionSeederService $service): int
    {
        $service->seedPermissions();
        $service->resyncAllExistingRoles();

        $this->info('RBAC permission catalog and role assignments synced.');

        return self::SUCCESS;
    }
}
