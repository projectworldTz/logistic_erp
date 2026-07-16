<?php

namespace App\Services\Backup;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Exports/restores a single tenant's data as a portable JSON snapshot, for
 * disaster-recovery point-in-time restore of THAT SAME tenant — not for
 * cloning data into a different tenant. Restoring into another tenant would
 * collide with that tenant's own row IDs (this app's tables use one global
 * auto-increment space shared across tenants), so restore() refuses to run
 * unless the backup's own tenant_id matches the tenant being restored.
 *
 * File attachments (documents, logos, signatures, photos) are NOT included —
 * only the database rows that reference their storage paths. This keeps the
 * backup a plain JSON file instead of a binary archive.
 *
 * Tables are deleted/inserted in FK-dependency order (real foreign keys,
 * introspected via Schema::getForeignKeys) rather than by toggling foreign
 * key checks off — that trick is MySQL-session-scoped and SQLite refuses to
 * honor it inside an open transaction, which is exactly the transaction
 * RefreshDatabase wraps every test in.
 */
class TenantBackupService
{
    /**
     * Child tables that don't carry their own tenant_id column — they're
     * scoped indirectly through a tenant-scoped parent row instead.
     */
    private const CHILD_TABLES = [
        'approval_workflow_steps' => ['parent_table' => 'approval_workflows', 'parent_fk' => 'approval_workflow_id'],
        'approval_decisions' => ['parent_table' => 'approval_requests', 'parent_fk' => 'approval_request_id'],
        'demurrage_rate_tiers' => ['parent_table' => 'demurrage_rate_cards', 'parent_fk' => 'demurrage_rate_card_id'],
        'detention_rate_tiers' => ['parent_table' => 'detention_rate_cards', 'parent_fk' => 'detention_rate_card_id'],
        'quotation_items' => ['parent_table' => 'quotations', 'parent_fk' => 'quotation_id'],
    ];

    public function export(int $tenantId): array
    {
        $tables = [];

        foreach ($this->tenantScopedTables() as $table) {
            $tables[$table] = DB::table($table)->where('tenant_id', $tenantId)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }

        foreach (self::CHILD_TABLES as $table => $meta) {
            $parentIds = DB::table($meta['parent_table'])->where('tenant_id', $tenantId)->pluck('id');
            $tables[$table] = DB::table($table)->whereIn($meta['parent_fk'], $parentIds)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }

        return [
            'tenant_id' => $tenantId,
            'generated_at' => now()->toIso8601String(),
            'tables' => $tables,
        ];
    }

    public function restore(int $tenantId, array $backup): void
    {
        if (($backup['tenant_id'] ?? null) !== $tenantId) {
            throw new RuntimeException('This backup belongs to a different company and cannot be restored here.');
        }

        if (! isset($backup['tables']) || ! is_array($backup['tables'])) {
            throw new RuntimeException('This file is not a valid backup.');
        }

        $childParentIds = [];
        foreach (self::CHILD_TABLES as $meta) {
            $childParentIds[$meta['parent_table']] = DB::table($meta['parent_table'])->where('tenant_id', $tenantId)->pluck('id');
        }

        $order = $this->insertionOrder();

        DB::transaction(function () use ($tenantId, $backup, $childParentIds, $order) {
            foreach (array_reverse($order) as $table) {
                if (isset(self::CHILD_TABLES[$table])) {
                    $meta = self::CHILD_TABLES[$table];
                    DB::table($table)->whereIn($meta['parent_fk'], $childParentIds[$meta['parent_table']])->delete();
                } else {
                    DB::table($table)->where('tenant_id', $tenantId)->delete();
                }
            }

            foreach ($order as $table) {
                $this->insertRows($table, $backup['tables'][$table] ?? []);
            }
        });
    }

    private function insertRows(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            if ($chunk !== []) {
                DB::table($table)->insert($chunk);
            }
        }
    }

    /**
     * Parents-first order across every table this service touches, derived
     * from real foreign key constraints so it can't drift from the schema.
     *
     * @return list<string>
     */
    private function insertionOrder(): array
    {
        $tables = array_merge($this->tenantScopedTables(), array_keys(self::CHILD_TABLES));
        $tableSet = array_flip($tables);

        $dependsOn = [];
        foreach ($tables as $table) {
            $dependsOn[$table] = [];

            foreach (Schema::getForeignKeys($table) as $fk) {
                $foreignTable = $fk['foreign_table'];

                if ($foreignTable !== $table && isset($tableSet[$foreignTable])) {
                    $dependsOn[$table][] = $foreignTable;
                }
            }
        }

        $sorted = [];
        $visited = [];

        $visit = function (string $table) use (&$visit, &$sorted, &$visited, $dependsOn) {
            if (isset($visited[$table])) {
                return;
            }
            $visited[$table] = true;

            foreach ($dependsOn[$table] as $dependency) {
                $visit($dependency);
            }

            $sorted[] = $table;
        };

        foreach ($tables as $table) {
            $visit($table);
        }

        return $sorted;
    }

    /**
     * @return list<string> table names for every model using BelongsToTenant
     */
    public function tenantScopedTables(): array
    {
        static $tables = null;

        if ($tables !== null) {
            return $tables;
        }

        $tables = [];

        foreach (glob(app_path('Models/*.php')) as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');

            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            if (in_array(BelongsToTenant::class, class_uses_recursive($class), true)) {
                $tables[] = (new $class)->getTable();
            }
        }

        sort($tables);

        return $tables;
    }
}
